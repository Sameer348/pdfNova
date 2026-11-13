<?php
/**
 * PDFNova front controller.
 * .htaccess sends every request that is not a real file here.
 *
 * Flow: bootstrap → resolve route (whitelist) → run page file into a buffer
 * (the page calls set_page() for its title/meta) → header + page + footer.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/router.php';

$path = current_path();

// Canonical form: no trailing slash (except the homepage).
if ($path !== '/' && str_ends_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/')) {
    $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    redirect($path . ($query !== '' ? '?' . $query : ''), 301);
}

// robots.txt and sitemap.xml are generated so they always use the real domain and tool list.
if ($path === '/robots.txt' || $path === '/sitemap.xml') {
    require __DIR__ . '/includes/seo.php';
    $path === '/robots.txt' ? output_robots() : output_sitemap();
    exit;
}

$route = resolve_route($path);

// Maintenance mode: visitors get a 503; admins (and the login page) still work.
if (setting('maintenance_mode', '0') === '1' && !has_role('admin') && !in_array($path, ['/login', '/logout'], true)) {
    header('Retry-After: 1800');
    render_error_page(503, 'Back soon', 'PDFNova is undergoing maintenance. Please try again in a little while.');
    exit;
}

// Defaults every page can override with set_page().
set_page([
    'title'       => 'PDFNova – Powerful PDF tools, made simple',
    'description' => 'Merge, split, compress, convert and manage your PDF files quickly and securely with PDFNova.',
    'canonical'   => $path,
    'robots'      => 'index,follow',
    'body_class'  => '',
    'scripts'     => [],
]);

if ($route === null) {
    http_response_code(404);
    $route = ['page' => '404', 'file' => __DIR__ . '/pages/404.php'];
}

// Render the page body first so it can set metadata and send redirects/headers.
ob_start();
(static function (array $route): void {
    require $route['file'];
})($route);
$content = (string) ob_get_clean();

require __DIR__ . '/includes/header.php';
echo $content;
require __DIR__ . '/includes/footer.php';
