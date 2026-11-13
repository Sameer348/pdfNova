<?php
/**
 * CloudConvert adapter (https://cloudconvert.com — API v2).
 *
 * Flow: create a job with three tasks (import/upload → convert → export/url),
 * upload the file to the signed form URL, poll the job until it finishes,
 * then download the exported file.
 *
 * Setup: create an API key at cloudconvert.com (scopes: task.read, task.write)
 * and set in includes/config.php:
 *     'converter' => ['provider' => 'cloudconvert', 'api_key' => '...'],
 *
 * ⚠ Written to CloudConvert's documented API but NOT tested against the live
 * service (that requires an API key). Test one conversion of each type after
 * adding your key; errors from the service are logged and shown politely.
 *
 * Privacy: the file is sent to CloudConvert for conversion — say so in your
 * privacy policy if you enable this (CloudConvert deletes files after 24 h).
 */

declare(strict_types=1);

namespace PDFNova\Converters;

final class CloudConvertConverter implements ConverterInterface
{
    private const API = 'https://api.cloudconvert.com/v2';
    private const TIMEOUT_SECONDS = 150;

    public function __construct(private readonly string $apiKey)
    {
    }

    public function name(): string
    {
        return 'cloudconvert';
    }

    public function convert(string $inPath, string $from, string $to, string $outPath): void
    {
        $auth = ['Authorization: Bearer ' . $this->apiKey];

        // 1. Create the job
        $job = Http::json('POST', self::API . '/jobs', $auth, [
            'tag'   => 'pdfnova',
            'tasks' => [
                'upload'  => ['operation' => 'import/upload'],
                'convert' => ['operation' => 'convert', 'input' => 'upload', 'input_format' => $from, 'output_format' => $to],
                'export'  => ['operation' => 'export/url', 'input' => 'convert'],
            ],
        ]);
        $this->assertOk($job, 'create job');
        $jobId = (string) ($job['data']['data']['id'] ?? '');
        $form = $this->task($job['data']['data']['tasks'] ?? [], 'upload')['result']['form'] ?? null;
        if ($jobId === '' || !\is_array($form) || empty($form['url'])) {
            throw $this->failure('Unexpected response from the conversion service.', 'create job: missing upload form');
        }

        // 2. Upload the file to the signed form
        $fields = (array) ($form['parameters'] ?? []);
        $fields['file'] = new \CURLFile($inPath, 'application/octet-stream', 'input.' . $from);
        $upload = Http::request('POST', (string) $form['url'], [], $fields, 180);
        if ($upload['status'] >= 400) {
            throw $this->failure('The file could not be sent to the conversion service.', 'upload HTTP ' . $upload['status']);
        }

        // 3. Wait for the job to finish
        $deadline = time() + self::TIMEOUT_SECONDS;
        do {
            sleep(2);
            $status = Http::json('GET', self::API . '/jobs/' . rawurlencode($jobId), $auth);
            $this->assertOk($status, 'poll job');
            $state = $status['data']['data']['status'] ?? '';
            if ($state === 'error') {
                $failed = array_values(array_filter($status['data']['data']['tasks'] ?? [], static fn($t) => ($t['status'] ?? '') === 'error'));
                throw $this->failure('The conversion service could not convert this file.', 'job error: ' . ($failed[0]['message'] ?? 'unknown'));
            }
        } while ($state !== 'finished' && time() < $deadline);

        if ($state !== 'finished') {
            throw new ConversionException('The conversion took too long. Please try again with a smaller file.', 'timeout');
        }

        // 4. Download the result
        $url = $this->task($status['data']['data']['tasks'] ?? [], 'export')['result']['files'][0]['url'] ?? '';
        if (!\is_string($url) || !str_starts_with($url, 'https://')) {
            throw $this->failure('The conversion service returned no file.', 'export: missing url');
        }
        Http::download($url, $outPath);
    }

    private function task(array $tasks, string $name): array
    {
        foreach ($tasks as $task) {
            if (($task['name'] ?? '') === $name) {
                return $task;
            }
        }
        return [];
    }

    private function assertOk(array $response, string $step): void
    {
        if ($response['status'] === 401 || $response['status'] === 403) {
            throw $this->failure('The conversion service rejected our credentials.', "$step: HTTP {$response['status']}");
        }
        if ($response['status'] === 402) {
            throw $this->failure('The conversion service quota is used up. Please try again later.', "$step: HTTP 402");
        }
        if ($response['status'] >= 400 || $response['status'] === 0) {
            throw $this->failure('The conversion service is unavailable. Please try again later.', "$step: HTTP {$response['status']} " . json_encode($response['data']['message'] ?? ''));
        }
    }

    private function failure(string $userMessage, string $detail): ConversionException
    {
        if (\function_exists('app_log')) {
            app_log('error', 'CloudConvert: ' . $detail);
        }
        return new ConversionException($userMessage, 'engine_failed');
    }
}
