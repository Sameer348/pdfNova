<?php
/**
 * PDFNova cleanup job — deletes expired temporary files and records.
 *
 * Hostinger: hPanel → Advanced → Cron Jobs → "Custom", every 15 minutes:
 *     /usr/bin/php /home/<USER>/domains/<DOMAIN>/public_html/cron/cleanup.php
 * (hPanel shows the exact PHP path and your home folder.)
 *
 * CLI only: this folder is blocked over HTTP, and the script refuses to run
 * from a web request as a second safeguard.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$lockFile = storage_path('temp') . '/.cleanup.lock';
$handle = fopen($lockFile, 'c');
if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Cleanup already running, skipping.\n");
    exit(0);
}

$started = microtime(true);
$total = ['files' => 0, 'orphans' => 0, 'jobs' => 0, 'rate_limits' => 0, 'resets' => 0, 'records' => 0];
// Process in batches until nothing is left (bounded to avoid endless loops).
for ($i = 0; $i < 20; $i++) {
    $stats = run_cleanup(500);
    foreach ($stats as $k => $v) {
        $total[$k] += $v;
    }
    if ($stats['files'] < 500) {
        break;
    }
}

require_once INCLUDES_PATH . '/system-check.php';
system_mark_cleanup_run(); // lets the admin System check confirm the cron job runs

flock($handle, LOCK_UN);
fclose($handle);

$summary = sprintf(
    "[%s] cleanup: %d files, %d orphans, %d stuck jobs, %d rate-limit rows, %d resets, %d old records (%.2fs)\n",
    gmdate('c'), $total['files'], $total['orphans'], $total['jobs'], $total['rate_limits'], $total['resets'], $total['records'],
    microtime(true) - $started
);
fwrite(STDOUT, $summary);
app_log('info', trim($summary));
