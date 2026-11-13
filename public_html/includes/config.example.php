<?php
/**
 * PDFNova configuration TEMPLATE.
 *
 * 1. Copy this file to includes/config.php (same folder).
 * 2. Fill in the values below.
 * 3. NEVER commit config.php to Git — it is listed in .gitignore.
 *
 * Generate the app key on your computer with:
 *     php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 * (or use any password generator to create 64 random hex characters).
 */

return [

    'app' => [
        'name'  => 'PDFNova',
        'env'   => 'production',            // 'production' or 'local'
        'debug' => false,                   // NEVER true in production (shows error details)
        'url'   => 'https://yourdomain.com', // Full site URL, no trailing slash
        // Secret used to sign download links and tokens. 64 hex characters.
        'key'   => 'CHANGE_ME_64_HEX_CHARACTERS',
    ],

    // Hostinger: hPanel → Databases → MySQL Databases
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'u000000000_pdfnova',
        'user'    => 'u000000000_pdfnova',
        'pass'    => 'CHANGE_ME',
        'socket'  => '',                    // leave empty on Hostinger
        'charset' => 'utf8mb4',
    ],

    // Absolute paths. Defaults work when everything is inside public_html.
    // For extra security you may move storage/ above public_html and set it here,
    // e.g. '/home/u000000000/domains/yourdomain.com/pdfnova_storage'
    'paths' => [
        'storage' => dirname(__DIR__) . '/storage',
    ],

    'session' => [
        'name'             => 'pdfnova_sid',
        'idle_timeout'     => 7200,   // seconds of inactivity before logout (2 h)
        'absolute_timeout' => 86400,  // max session lifetime (24 h)
    ],

    // Temporary-file retention (minutes). The cleanup cron enforces these.
    'retention' => [
        'upload_minutes' => 60,
        'result_minutes' => 120,
        'temp_minutes'   => 30,
        // Records (not files): how long history and logs are kept, in days.
        'history_days'       => 90,   // account job history (plans show up to this)
        'guest_history_days' => 30,   // guests' job records (used for limits/statistics)
        'log_days'           => 365,  // activity log, admin audit log, archived messages
    ],

    // Absolute server-wide cap, regardless of plan (MB). Must also be allowed by
    // PHP's upload_max_filesize / post_max_size (hPanel → Advanced → PHP Configuration).
    'uploads' => [
        'hard_max_mb' => 200,
    ],

    'mail' => [
        'from_email' => 'no-reply@yourdomain.com',
        'from_name'  => 'PDFNova',
    ],

    // Optional server binaries. Usually NOT available on shared hosting — leave
    // empty. The app detects them at runtime and falls back gracefully.
    'engines' => [
        'ghostscript' => '',   // e.g. '/usr/bin/gs'
        'qpdf'        => '',   // e.g. '/usr/bin/qpdf'
    ],

    // External conversion API (PDF↔Office). 'none' = tools show "not available".
    'converter' => [
        'provider' => 'none',  // 'none' | 'cloudconvert'  (only CloudConvert has an adapter)
        'api_key'  => '',
    ],

    // External OCR API. 'none' = OCR tool shows "not available".
    'ocr' => [
        'provider' => 'none',  // 'none' | 'ocrspace'
        'api_key'  => '',
    ],

    // Payments. 'none' = pricing page shows plans; upgrades handled manually.
    'payment' => [
        'provider'       => 'none', // 'none' | 'stripe'
        'stripe_secret'  => '',
        'stripe_webhook' => '',
    ],

    'security' => [
        // Only set true if your site is behind a proxy/CDN you control that sets
        // X-Forwarded-For (e.g. Cloudflare). Otherwise IPs could be spoofed.
        'trust_proxy_headers' => false,
        'hsts'                => true, // sent only over HTTPS
    ],

    // Shown on the Privacy Policy and Terms. Fill these in before going live.
    'legal' => [
        'company' => '',   // e.g. 'PDFNova Ltd.'
        'address' => '',   // postal address
        'country' => '',   // governing law, e.g. 'Pakistan'
    ],

    'contact' => [
        'email' => 'support@yourdomain.com',
    ],
];
