<?php
/**
 * Automatic deletion of temporary files and expired records.
 *
 * Runs from:
 *   - cron/cleanup.php (Hostinger Cron Job, every 15 minutes) — primary
 *   - a small random share of API requests — safety net if cron is not set up
 *
 * Retention (config 'retention'): uploads 60 min, results 120 min, temp 30 min.
 */

declare(strict_types=1);

/**
 * @param int $batch Max rows to process per run (keeps web-request runs quick)
 * @return array<string,int> counts of what was removed
 */
function run_cleanup(int $batch = 500): array
{
    $stats = ['files' => 0, 'orphans' => 0, 'jobs' => 0, 'rate_limits' => 0, 'resets' => 0, 'records' => 0];

    // 1. Expired file rows → delete bytes, mark deleted.
    $expired = db_all(
        "SELECT id, storage_area, stored_name FROM files
          WHERE status = 'active' AND expires_at <= UTC_TIMESTAMP()
          ORDER BY expires_at LIMIT " . (int) $batch
    );
    foreach ($expired as $file) {
        try {
            delete_stored_file($file);
            $stats['files']++;
        } catch (Throwable $e) {
            app_log('error', 'Cleanup could not delete file', ['id' => $file['id'], 'error' => $e->getMessage()]);
        }
    }

    // 2. Orphans on disk (crashed jobs, files with no DB row) older than their TTL.
    $ttl = [
        'uploads'   => (int) config('retention.upload_minutes', 60),
        'processed' => (int) config('retention.result_minutes', 120),
        'temp'      => (int) config('retention.temp_minutes', 30),
    ];
    foreach ($ttl as $area => $minutes) {
        $dir = storage_path($area);
        if (!is_dir($dir)) {
            continue;
        }
        $cutoff = time() - $minutes * 60;
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot() || str_starts_with($entry->getFilename(), '.')) {
                continue; // keep .htaccess / .gitkeep
            }
            if ($entry->getMTime() < $cutoff) {
                $path = $entry->getPathname();
                $entry->isDir() ? remove_dir_recursive($path) : @unlink($path);
                $stats['orphans']++;
            }
        }
    }

    // 3. Jobs stuck in processing for over an hour → failed.
    $stats['jobs'] = db_exec(
        "UPDATE processing_jobs SET status = 'failed', error_code = 'timeout',
                error_message = 'Processing did not finish in time.'
          WHERE status IN ('queued','processing') AND created_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR"
    );

    // 4. Expired rate-limit windows and password resets.
    $stats['rate_limits'] = db_exec('DELETE FROM rate_limits WHERE expires_at <= UTC_TIMESTAMP()');
    $stats['resets'] = db_exec(
        'DELETE FROM password_resets WHERE expires_at <= UTC_TIMESTAMP() - INTERVAL 1 DAY OR used_at IS NOT NULL'
    );

    // 5. Record retention (what the Privacy Policy promises). Bounded batches.
    $historyDays = max(1, (int) config('retention.history_days', 90));        // account job history
    $guestDays   = max(1, (int) config('retention.guest_history_days', 30));  // guests' job records
    $logDays     = max(1, (int) config('retention.log_days', 365));           // activity & audit logs
    $limit       = (int) $batch;
    $stats['records'] = 0;
    $stats['records'] += db_exec("DELETE FROM processing_jobs WHERE user_id IS NOT NULL AND created_at < UTC_TIMESTAMP() - INTERVAL $historyDays DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM processing_jobs WHERE user_id IS NULL AND created_at < UTC_TIMESTAMP() - INTERVAL $guestDays DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM files WHERE status = 'deleted' AND created_at < UTC_TIMESTAMP() - INTERVAL $historyDays DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM usage_logs WHERE created_at < UTC_TIMESTAMP() - INTERVAL $logDays DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM admin_audit_log WHERE created_at < UTC_TIMESTAMP() - INTERVAL $logDays DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM sessions WHERE (revoked_at IS NOT NULL AND revoked_at < UTC_TIMESTAMP() - INTERVAL 30 DAY)
                                     OR last_activity_at < UTC_TIMESTAMP() - INTERVAL 30 DAY LIMIT $limit");
    $stats['records'] += db_exec("DELETE FROM contact_messages WHERE status IN ('archived','spam') AND created_at < UTC_TIMESTAMP() - INTERVAL $logDays DAY LIMIT $limit");

    return $stats;
}

function remove_dir_recursive(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) {
        @unlink($dir);
        return;
    }
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/** Run a small cleanup on ~2% of API requests (fallback when cron is missing). */
function maybe_run_request_cleanup(): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }
    $lock = storage_path('temp') . '/.cleanup.lock';
    $handle = @fopen($lock, 'c');
    if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
        return; // another request is already cleaning
    }
    try {
        run_cleanup(50);
    } catch (Throwable $e) {
        app_log('error', 'Request cleanup failed', ['error' => $e->getMessage()]);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
