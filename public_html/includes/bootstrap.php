<?php
/**
 * PDFNova bootstrap — loaded first by every entry point
 * (index.php, api/*.php, admin/*.php, cron/*.php).
 *
 * Responsibilities: constants, config, error handling, autoloading,
 * secure session start (web only) and security headers.
 */

declare(strict_types=1);

if (defined('PDFNOVA')) {
    return; // already bootstrapped
}
define('PDFNOVA', true);
define('BASE_PATH', dirname(__DIR__));          // .../public_html
define('INCLUDES_PATH', __DIR__);
define('IS_CLI', PHP_SAPI === 'cli');

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
$configFile = INCLUDES_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PDFNova is not configured yet.\n"
       . "Copy includes/config.example.php to includes/config.php and fill in your settings.\n";
    exit;
}
$GLOBALS['__pdfnova_config'] = require $configFile;

require INCLUDES_PATH . '/functions.php';
require INCLUDES_PATH . '/security.php';
require INCLUDES_PATH . '/database.php';
require INCLUDES_PATH . '/session.php';
require INCLUDES_PATH . '/csrf.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/icons.php';
require INCLUDES_PATH . '/tools.php';
require INCLUDES_PATH . '/limits.php';
require INCLUDES_PATH . '/validation.php';
require INCLUDES_PATH . '/files.php';
require INCLUDES_PATH . '/cleanup.php';
require INCLUDES_PATH . '/mailer.php';
require INCLUDES_PATH . '/account.php';
require INCLUDES_PATH . '/legal.php';

// ---------------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------------
date_default_timezone_set('UTC');   // DB and app both work in UTC
mb_internal_encoding('UTF-8');

$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = storage_path('logs');
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

// Refuse to run in production with the placeholder key (would make download
// links forgeable).
$appKey = (string) config('app.key', '');
if (!preg_match('/^[a-f0-9]{64}$/i', $appKey)) {
    if (config('app.env') === 'production') {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configuration error: app.key must be 64 hex characters (see config.example.php).\n";
        exit;
    }
}

// Composer autoloader (FPDI, TCPDF, PDFNova\ service classes)
$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// ---------------------------------------------------------------------------
// Error handling: log everything, show a generic message to visitors.
// ---------------------------------------------------------------------------
set_exception_handler(static function (Throwable $e): void {
    app_log('error', $e->getMessage(), [
        'type' => get_class($e),
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);

    if (IS_CLI) {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_api_request()) {
        json_response([
            'success' => false,
            'message' => config('app.debug') ? $e->getMessage() : 'Server error. Please try again.',
        ], 500);
    }
    $message = config('app.debug') ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() : '';
    render_error_page(500, 'Something went wrong', 'An unexpected error occurred. Please try again in a moment.', $message);
    exit;
});

// ---------------------------------------------------------------------------
// Web requests: session + headers
// ---------------------------------------------------------------------------
if (!IS_CLI) {
    start_secure_session();
    send_security_headers();
}
