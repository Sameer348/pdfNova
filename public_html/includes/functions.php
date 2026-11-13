<?php
/**
 * General-purpose helpers used across pages, API endpoints and admin.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration & paths
// ---------------------------------------------------------------------------

/**
 * Read a config value using dot notation: config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__pdfnova_config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function storage_path(string $sub = ''): string
{
    $base = rtrim((string) config('paths.storage', BASE_PATH . '/storage'), '/');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

/**
 * Append a line to storage/logs/app.log (JSON lines). Never logs file contents
 * or passwords — callers must pass only safe context.
 */
function app_log(string $level, string $message, array $context = []): void
{
    $dir = storage_path('logs');
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("[pdfnova][$level] $message");
        return;
    }
    $line = json_encode([
        'time'    => gmdate('c'),
        'level'   => $level,
        'message' => $message,
        'context' => $context,
        'uri'     => IS_CLI ? 'cli' : ($_SERVER['REQUEST_URI'] ?? ''),
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    @file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------------

/** Base path when the site lives in a sub-folder (normally ''). */
function base_path_url(): string
{
    $path = (string) parse_url((string) config('app.url', ''), PHP_URL_PATH);
    return rtrim($path, '/');
}

/** Site-relative URL: url('/merge-pdf') → '/merge-pdf' (or '/sub/merge-pdf'). */
function url(string $path = '/'): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

/** Absolute URL for canonical / Open Graph tags. */
function absolute_url(string $path = '/'): string
{
    $origin = rtrim((string) config('app.url', ''), '/');
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $origin);
    return $origin . url($path);
}

/** Asset URL with cache-busting version based on file modification time. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . $path) . '?v=' . $v;
}

/**
 * URL for a JavaScript ES module — deliberately WITHOUT ?v= cache busting.
 * Modules import each other by plain relative URLs ("./app.js"); a versioned
 * URL in the <script> tag would load a second, separate copy of the module.
 * Freshness comes instead from "Cache-Control: no-cache" (see .htaccess),
 * which makes browsers revalidate with a cheap 304 request.
 */
function module_url(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Current request path without the base folder and query string, e.g. '/merge-pdf'. */
function current_path(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = base_path_url();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    // Hostinger terminates TLS in front of the web server on some setups.
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_api_request(): bool
{
    return !IS_CLI && str_starts_with(current_path(), '/api/');
}

function redirect(string $path, int $status = 302): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

// ---------------------------------------------------------------------------
// Responses
// ---------------------------------------------------------------------------

/**
 * Send a JSON response and stop. All API endpoints use this so the response
 * shape is always { success: bool, message: string, ... }.
 */
function json_response(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Minimal standalone error page (used for fatal errors before/without layout).
 */
function render_error_page(int $status, string $title, string $text, string $debug = ''): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $css = asset('css/app.css');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . e($title) . ' · PDFNova</title>'
        . '<link rel="stylesheet" href="' . e($css) . '"></head><body>'
        . '<main class="error-page"><div class="container narrow">'
        . '<p class="error-code">' . $status . '</p>'
        . '<h1>' . e($title) . '</h1><p class="lead">' . e($text) . '</p>'
        . ($debug !== '' ? '<pre class="debug-box">' . e($debug) . '</pre>' : '')
        . '<p><a class="btn btn-primary" href="' . e(url('/')) . '">Back to home</a></p>'
        . '</div></main></body></html>';
}

// ---------------------------------------------------------------------------
// Page metadata (title, description, canonical...). Pages call set_page()
// before the layout header is rendered.
// ---------------------------------------------------------------------------

function set_page(array $values): void
{
    $GLOBALS['__pdfnova_page'] = array_merge($GLOBALS['__pdfnova_page'] ?? [], $values);
}

function page(string $key, mixed $default = null): mixed
{
    return $GLOBALS['__pdfnova_page'][$key] ?? $default;
}

// ---------------------------------------------------------------------------
// Templates
// ---------------------------------------------------------------------------

/**
 * Render a template file with isolated variables.
 * partial('tool-options/split', ['tool' => $tool]) → templates/tool-options/split.php
 */
function partial(string $name, array $vars = []): void
{
    if (!preg_match('#^[a-z0-9/_-]+$#', $name)) {
        throw new InvalidArgumentException('Invalid template name');
    }
    $file = BASE_PATH . '/templates/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Template not found: $name");
    }
    (static function (string $__file, array $__vars): void {
        extract($__vars, EXTR_SKIP);
        require $__file;
    })($file, $vars);
}

// ---------------------------------------------------------------------------
// Flash messages (one-time messages across a redirect)
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type:string, message:string}> */
function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function format_bytes(int|float $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max(0, (float) $bytes);
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) (int) $bytes : number_format($bytes, $precision)) . ' ' . $units[$i];
}

function format_date(?string $utcDatetime, string $format = 'M j, Y H:i'): string
{
    if ($utcDatetime === null || $utcDatetime === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC')))->format($format) . ' UTC';
    } catch (Exception) {
        return '—';
    }
}

/** True when the given nav path matches the current page (for active links). */
function is_current(string $path): bool
{
    return current_path() === '/' . trim($path, '/') || (trim($path, '/') === '' && current_path() === '/');
}
