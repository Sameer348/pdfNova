<?php
/**
 * Deployment self-check shown on the admin dashboard.
 *
 * Runs inside the WEB PHP process on purpose: on shared hosting the
 * command-line PHP often has different limits/extensions than the PHP that
 * serves the site, so only a web request reports what visitors really get.
 *
 * Each check returns ['label', 'status' => ok|warn|fail, 'detail'].
 * fail = something is broken or unsafe; warn = works, but should be fixed.
 * Checks never print secrets (no keys, passwords or full paths of config).
 */

declare(strict_types=1);

const CLEANUP_STAMP = '.cleanup-last-run';

/** Record a successful cleanup run (called by cron/cleanup.php). */
function system_mark_cleanup_run(): void
{
    @touch(storage_path('temp') . '/' . CLEANUP_STAMP);
}

/** @return list<array{label:string,status:string,detail:string}> */
function system_checks(): array
{
    $c = [];
    $add = static function (string $label, string $status, string $detail) use (&$c): void {
        $c[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
    };

    // --- PHP ----------------------------------------------------------------
    $add('PHP version', version_compare(PHP_VERSION, '8.1.0', '>=') ? (version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'warn') : 'fail',
        'Running ' . PHP_VERSION . '. Required 8.1+, recommended 8.2 or 8.3 (hPanel → Advanced → PHP Configuration).');

    $required = ['pdo_mysql' => 'database', 'mbstring' => 'text handling', 'fileinfo' => 'upload type detection',
                 'zlib' => 'reading and writing PDFs', 'json' => 'API responses'];
    $missing = array_keys(array_filter($required, static fn($why, $ext) => !extension_loaded($ext), ARRAY_FILTER_USE_BOTH));
    $add('Required PHP extensions', $missing ? 'fail' : 'ok',
        $missing ? 'Missing: ' . implode(', ', $missing) . '. Enable them in hPanel → PHP Configuration → PHP extensions.'
                 : implode(', ', array_keys($required)) . ' loaded.');

    $optional = ['gd' => 'JPG to PDF and image compression', 'openssl' => 'Protect PDF and Unlock PDF (AES)',
                 'zip' => 'ZIP downloads from Split PDF', 'curl' => 'external converter/OCR APIs'];
    $off = [];
    foreach ($optional as $ext => $why) {
        if (!extension_loaded($ext)) {
            $off[] = "$ext ($why)";
        }
    }
    $add('Optional PHP extensions', $off ? 'warn' : 'ok',
        $off ? 'Not loaded — these features are switched off: ' . implode('; ', $off) . '.' : implode(', ', array_keys($optional)) . ' loaded.');

    $add('PDF libraries (Composer vendor/)',
        class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class) && class_exists(\TCPDF::class) ? 'ok' : 'fail',
        class_exists(\TCPDF::class) ? 'FPDI and TCPDF found.' : 'vendor/ is missing or incomplete. Upload the vendor/ folder built with "composer install --no-dev".');

    // --- PHP limits ---------------------------------------------------------
    $planMaxMb = 0;
    try {
        $planMaxMb = (int) db_value('SELECT COALESCE(MAX(max_file_size_mb), 0) FROM plans WHERE is_active = 1');
    } catch (Throwable) {
    }
    $wantMb = min(max($planMaxMb, (int) setting('guest_max_file_size_mb', '10')), (int) config('uploads.hard_max_mb', 200));
    $upload = ini_bytes((string) ini_get('upload_max_filesize'));
    $post   = ini_bytes((string) ini_get('post_max_size'));
    $limitOk = $upload >= $wantMb * 1048576 && $post > $upload;
    $add('Upload size limit', $limitOk ? 'ok' : 'warn',
        sprintf('upload_max_filesize %s, post_max_size %s. Your largest plan allows %d MB per file, so set upload_max_filesize ≥ %dM and post_max_size a little higher. Until then uploads are capped at %s.',
            ini_get('upload_max_filesize'), ini_get('post_max_size'), $wantMb, $wantMb, format_bytes(php_upload_limit_bytes())));

    $mem = (string) ini_get('memory_limit');
    $memOk = $mem === '-1' || ini_bytes($mem) >= 256 * 1048576;
    $add('Memory limit', $memOk ? 'ok' : 'warn', "memory_limit $mem. 256M or more is recommended for large PDFs and image compression.");

    $exec = (int) ini_get('max_execution_time');
    $add('Execution time', $exec === 0 || $exec >= 60 ? 'ok' : 'warn',
        "max_execution_time {$exec}s. 60s or more is recommended (120s for big compress jobs).");

    // --- Configuration ------------------------------------------------------
    $env = (string) config('app.env', 'production');
    $add('Environment', $env === 'production' ? 'ok' : 'warn',
        $env === 'production' ? 'app.env is production.' : "app.env is \"$env\". Set it to production on the live site (search engines are blocked until you do).");

    $debug = (bool) config('app.debug', false);
    $add('Debug mode', $debug ? ($env === 'production' ? 'fail' : 'warn') : 'ok',
        $debug ? 'app.debug is ON — error details are shown to visitors. Set it to false.' : 'Off.');

    $key = (string) config('app.key', '');
    $add('App key', preg_match('/^[0-9a-f]{64}$/i', $key) && !str_contains($key, 'CHANGE') ? 'ok' : 'fail',
        preg_match('/^[0-9a-f]{64}$/i', $key) ? 'Set (64 hex characters).' : 'app.key is not a 64-character hex secret. Download links and tokens are not safe until you set one.');

    $appUrl = (string) config('app.url', '');
    $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
    $reqHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $urlOk = str_starts_with($appUrl, 'https://') && ($reqHost === '' || strtolower($appHost) === $reqHost);
    $add('Site URL', $urlOk ? 'ok' : 'warn',
        "app.url is $appUrl" . ($reqHost !== '' && strtolower($appHost) !== $reqHost ? ", but this page was opened on $reqHost" : '')
        . '. It must be the https:// address visitors use — it is used in emails, the sitemap and canonical links.');

    $add('HTTPS', is_https() ? 'ok' : 'warn', is_https() ? 'This page was served over HTTPS.' : 'This page was served over plain HTTP. Install SSL (hPanel → Security → SSL) and reload.');

    // --- Storage ------------------------------------------------------------
    $bad = [];
    foreach (['uploads', 'processed', 'temp', 'logs'] as $dir) {
        $p = storage_path($dir);
        if (!is_dir($p) || !is_writable($p)) {
            $bad[] = "storage/$dir";
        }
    }
    $add('Writable folders', $bad ? 'fail' : 'ok',
        $bad ? 'Not writable or missing: ' . implode(', ', $bad) . '. Create them and set permissions to 755.' : 'storage/uploads, processed, temp and logs are writable.');

    $storageReal = (string) realpath(storage_path());
    $insideWeb = $storageReal === '' || str_starts_with($storageReal, (string) realpath(BASE_PATH));
    $protected = is_file(storage_path() . '/.htaccess');
    $add('Storage protection', !$insideWeb || $protected ? 'ok' : 'fail',
        !$insideWeb ? 'Storage is outside public_html — not reachable from the web.'
            : ($protected ? 'storage/.htaccess present (blocks web access and script execution).'
                          : 'storage/.htaccess is missing — uploaded files could be reachable from the web. Upload it again.'));

    $free = @disk_free_space(storage_path());
    if ($free !== false) {
        $add('Free disk space', $free > 1024 ** 3 ? 'ok' : 'warn', format_bytes($free) . ' free for temporary files.');
    }

    // --- Cron ---------------------------------------------------------------
    $stamp = storage_path('temp') . '/' . CLEANUP_STAMP;
    $last = is_file($stamp) ? (int) filemtime($stamp) : 0;
    $age = $last ? time() - $last : null;
    $add('Cleanup cron job', $age !== null && $age < 3600 ? 'ok' : 'warn',
        $age === null ? 'Has never run. Add the cron job (every 15 minutes) — without it, expired files are not deleted.'
                      : 'Last run ' . ($age < 120 ? 'just now' : round($age / 60) . ' minutes ago') . ($age >= 3600 ? '. It should run every 15 minutes — check the cron job.' : '.'));

    // --- Database -----------------------------------------------------------
    try {
        $ver = (string) db_value('SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1');
        $add('Database schema', $ver !== '' ? 'ok' : 'fail', $ver !== '' ? "Installed: $ver." : 'schema_migrations is empty — import database/schema.sql.');
    } catch (Throwable) {
        $add('Database schema', 'fail', 'Tables not found — import database/schema.sql in phpMyAdmin.');
    }

    // --- Mail & content -----------------------------------------------------
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $from = (string) config('mail.from_email', '');
    $fromDomain = strtolower(substr(strrchr($from, '@') ?: '', 1));
    $siteDomain = strtolower(preg_replace('/^www\./', '', $appHost));
    $mailOk = function_exists('mail') && !in_array('mail', $disabled, true);
    $add('Email sending', !$mailOk ? 'fail' : ($fromDomain !== '' && str_ends_with($siteDomain, $fromDomain) ? 'ok' : 'warn'),
        !$mailOk ? 'PHP mail() is disabled — password-reset emails cannot be sent.'
                 : "Sender is $from. It must be a mailbox that exists on your own domain ($siteDomain), or emails are rejected or marked as spam.");

    $contact = (string) setting('contact_email', '');
    $add('Contact email', $contact !== '' && !str_ends_with($contact, '@example.com') ? 'ok' : 'warn',
        $contact === '' || str_ends_with($contact, '@example.com')
            ? 'Still the placeholder. Set the address that receives contact messages in Settings.' : "Messages go to $contact.");

    $legalMissing = array_filter(['company', 'address', 'country'], static fn($k) => trim((string) config("legal.$k", '')) === '');
    $add('Legal details', $legalMissing ? 'warn' : 'ok',
        $legalMissing ? 'Empty in config.php: legal.' . implode(', legal.', $legalMissing) . '. They appear in the Privacy Policy and Terms.' : 'Company, address and country set.');

    return $c;
}
