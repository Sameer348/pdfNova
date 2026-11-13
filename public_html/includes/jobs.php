<?php
/**
 * Processing-job lifecycle shared by every tool endpoint.
 *
 *   $inputs = job_inputs($body['files'], $tool, minFiles: 2);   // ownership + type checks
 *   job_enforce_quota();
 *   $job = job_start($tool, $inputs, $options);
 *   ... run the tool, writing to job_result_path() ...
 *   $result = job_complete($job, $storedName, 'merged.pdf', 'pdf', $pages);
 *   // or job_fail($job, $code, $message)
 *
 * Input files are deleted as soon as a job completes successfully. On failure
 * they are kept (until they expire) so the visitor can press "Try again".
 */

declare(strict_types=1);

/**
 * Resolve and validate the file ids sent by the browser.
 * @param mixed $ids
 * @return array<int, array> file rows in the requested order
 */
function job_inputs(mixed $ids, array $tool, int $minFiles = 1, ?int $maxFiles = null): array
{
    if (!is_array($ids) || $ids === []) {
        api_error('Please add a file first.', 422, 'no_files');
    }
    $ids = array_values(array_filter($ids, 'is_string'));
    if (count($ids) !== count(array_unique($ids))) {
        api_error('The same file was sent twice.', 422, 'duplicate_files');
    }
    $limit = $maxFiles ?? ($tool['multiple'] ? visitor_limits()['max_files'] : 1);
    if (count($ids) > $limit) {
        api_error("You can process up to $limit files at once on your plan.", 422, 'too_many_files');
    }
    if (count($ids) < $minFiles) {
        api_error("Add at least $minFiles files.", 422, 'not_enough_files');
    }

    $files = [];
    foreach ($ids as $id) {
        $file = find_owned_file($id, 'upload');
        if ($file === null) {
            api_error('One of your files has expired or was removed. Please upload it again.', 410, 'file_expired');
        }
        $ext = $file['extension'] === 'jpeg' ? 'jpg' : $file['extension'];
        $accepted = array_map(static fn(string $e): string => $e === 'jpeg' ? 'jpg' : $e, $tool['accept']);
        if (!in_array($ext, $accepted, true)) {
            api_error('"' . $file['original_name'] . '" cannot be used with ' . $tool['name'] . '.', 422, 'invalid_type');
        }
        $files[] = $file;
    }
    return $files;
}

/** Stop with 429 when the visitor reached their daily processing limit. */
function job_enforce_quota(): void
{
    $limits = visitor_limits();
    if ($limits['daily_jobs'] !== null && jobs_last_24h() >= $limits['daily_jobs']) {
        $hint = $limits['is_guest']
            ? ' Create a free account for a higher limit.'
            : ' Upgrade your plan for more.';
        api_error("You've reached your limit of {$limits['daily_jobs']} tasks per 24 hours." . $hint, 429, 'quota_exceeded');
    }
}

/** Create the job row (status: processing) and link its input files. */
function job_start(array $tool, array $inputs, array $options = [], string $engine = 'fpdi'): array
{
    [$userId, $sessionHash] = current_owner();
    $publicId = random_token(16);
    $bytes = array_sum(array_map(static fn(array $f): int => (int) $f['size_bytes'], $inputs));

    $jobId = db_transaction(static function () use ($publicId, $userId, $sessionHash, $tool, $engine, $options, $inputs, $bytes): int {
        $id = db_insert(
            "INSERT INTO processing_jobs (public_id, user_id, session_hash, tool, status, engine, options_json,
                                          input_count, input_bytes, ip_address, started_at)
             VALUES (?, ?, ?, ?, 'processing', ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
            [$publicId, $userId, $sessionHash, $tool['slug'], $engine,
             $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
             count($inputs), $bytes, client_ip_bin()]
        );
        foreach (array_values($inputs) as $position => $file) {
            db_exec('INSERT INTO job_files (job_id, file_id, position) VALUES (?, ?, ?)', [$id, $file['id'], $position]);
        }
        return $id;
    });

    // Give long jobs room on shared hosting (if the host allows changing it).
    @set_time_limit(180);

    return ['id' => $jobId, 'public_id' => $publicId, 'tool' => $tool['slug'], 'started' => microtime(true), 'inputs' => $inputs];
}

/** Fresh random path in storage/processed for a job result. */
function job_result_path(): array
{
    storage_dir('processed');
    $storedName = random_token(16);
    return [$storedName, stored_file_path('processed', $storedName)];
}

/** Absolute path of an input file row. */
function job_input_path(array $file): string
{
    return stored_file_path($file['storage_area'], $file['stored_name']);
}

/**
 * Register the result, mark the job completed, delete the inputs and return
 * the JSON fields the browser needs (download_url, filename, size...).
 */
function job_complete(array $job, string $storedName, string $displayName, string $ext, ?int $pages = null): array
{
    $result = store_result($storedName, $displayName, $ext, $pages);
    db_exec(
        "UPDATE processing_jobs SET status = 'completed', result_file_id = ?, output_bytes = ?,
                duration_ms = ?, completed_at = UTC_TIMESTAMP() WHERE id = ?",
        [$result['id'], $result['size_bytes'], (int) round((microtime(true) - $job['started']) * 1000), $job['id']]
    );
    foreach ($job['inputs'] as $input) {
        delete_stored_file($input);
    }
    log_usage('process', $job['tool'], $job['id'], (int) $result['size_bytes']);

    return [
        'job_id'       => $job['public_id'],
        'download_url' => download_url($result),
        'filename'     => $result['original_name'],
        'size'         => (int) $result['size_bytes'],
        'size_label'   => format_bytes((int) $result['size_bytes']),
        'pages'        => $pages,
        'expires_in_minutes' => (int) config('retention.result_minutes', 120),
    ];
}

/** Mark a job failed with a user-safe message (and remove any partial output). */
function job_fail(array $job, string $code, string $message, ?string $partialPath = null): void
{
    if ($partialPath !== null && is_file($partialPath)) {
        @unlink($partialPath);
    }
    db_exec(
        "UPDATE processing_jobs SET status = 'failed', error_code = ?, error_message = ?, duration_ms = ?,
                completed_at = UTC_TIMESTAMP() WHERE id = ?",
        [substr($code, 0, 50), mb_substr($message, 0, 500), (int) round((microtime(true) - $job['started']) * 1000), $job['id']]
    );
}

/**
 * Run a tool safely: converts exceptions into a failed job + JSON error.
 * @param callable(string $outPath): array $work returns ['name'=>..., 'ext'=>..., 'pages'=>?int, 'extra'=>array]
 */
function job_run(array $job, callable $work): never
{
    [$storedName, $outPath] = job_result_path();
    try {
        $out = $work($outPath);
    } catch (\PDFNova\Pdf\PdfProcessingException $e) {
        app_log('notice', 'Job failed', ['job' => $job['public_id'], 'tool' => $job['tool'], 'code' => $e->errorCode,
            'cause' => $e->getPrevious()?->getMessage()]);
        job_fail($job, $e->errorCode, $e->getMessage(), $outPath);
        api_error($e->getMessage(), 422, $e->errorCode);
    } catch (Throwable $e) {
        app_log('error', 'Job crashed', ['job' => $job['public_id'], 'tool' => $job['tool'], 'error' => $e->getMessage(),
            'at' => $e->getFile() . ':' . $e->getLine()]);
        job_fail($job, 'internal_error', 'Unable to process PDF.', $outPath);
        api_error('Unable to process PDF. Please try again.', 500, 'internal_error');
    }

    $result = job_complete($job, $storedName, $out['name'], $out['ext'], $out['pages'] ?? null);
    api_success($result + ($out['extra'] ?? []), $out['message'] ?? 'PDF processed successfully');
}
