<?php
/**
 * PDFNova admin — front controller for every /admin/... URL.
 *
 * Access: the visitor must be logged in AND have role = 'admin'
 * (require_role). Guests are sent to /login (and back here afterwards);
 * logged-in non-admins get 403. All changes are POST + CSRF and are
 * written to admin_audit_log.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/admin.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$admin = require_role('admin');

$section = trim(substr(current_path(), strlen('/admin')), '/');
$routes = [
    ''              => 'dashboard',
    'users'         => 'users',
    'users/view'    => 'user',
    'files'         => 'files',
    'jobs'          => 'jobs',
    'messages'      => 'messages',
    'subscriptions' => 'subscriptions',
    'settings'      => 'settings',
];
if (!isset($routes[$section])) {
    render_error_page(404, 'Page not found', 'This admin page does not exist.');
    exit;
}

$GLOBALS['admin_active'] = explode('/', $section)[0] ?: 'dashboard';
set_page(['title' => 'Admin', 'robots' => 'noindex,nofollow']);

// Render the page first (it may redirect after a POST), then wrap it in the layout.
ob_start();
(static function (string $__file, array $admin): void {
    require $__file;
})(__DIR__ . '/pages/' . $routes[$section] . '.php', $admin);
$content = (string) ob_get_clean();

require __DIR__ . '/partials/header.php';
echo $content;
require __DIR__ . '/partials/footer.php';
