<?php
/**
 * LOCAL DEVELOPMENT ONLY — router for PHP's built-in web server, which does
 * not read .htaccess. It mimics the production rewrite rules so the site can
 * be tested locally:
 *
 *     php -S localhost:8080 -t public_html build/dev-router.php
 *
 * Never upload this file to Hostinger.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/../public_html');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Same blocks as .htaccess
if (preg_match('#^/(includes|pages|templates|services|database|cron|vendor|storage)(/|$)#', $path)
    || preg_match('#(^|/)\.(?!well-known/)#', $path)
    || preg_match('#\.(sql|md|log|ini|lock|dist|bak|sh|env|example\.php)$#i', $path)
    || preg_match('#(^|/)composer\.(json|lock)$#i', $path)
    || preg_match('#^/api/_#', $path)
    || preg_match('#^/admin/(pages|partials)(/|$)#', $path)) {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
    // Real file: PHP files execute (api/, admin/), static files are served as-is.
    return false;
}

if (preg_match('#^/admin(/|$)#', $path)) {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    require $root . '/admin/index.php';
    return true;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
return true;
