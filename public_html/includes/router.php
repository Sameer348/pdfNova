<?php
/**
 * Whitelist router. Only paths listed here can render a page; everything
 * else is a 404. Page files live in /pages (denied from direct HTTP access).
 *
 * A route whose page file does not exist yet (built in a later phase) also
 * returns 404 rather than a broken page.
 */

declare(strict_types=1);

function routes(): array
{
    $routes = [
        '/'                => ['page' => 'home'],
        '/pricing'         => ['page' => 'pricing'],
        '/contact'         => ['page' => 'contact'],
        '/security'        => ['page' => 'security'],

        '/login'           => ['page' => 'auth/login'],
        '/register'        => ['page' => 'auth/register'],
        '/forgot-password' => ['page' => 'auth/forgot-password'],
        '/reset-password'  => ['page' => 'auth/reset-password'],
        '/logout'          => ['page' => 'auth/logout'],

        '/dashboard'              => ['page' => 'account/dashboard'],
        '/dashboard/files'        => ['page' => 'account/files'],
        '/dashboard/activity'     => ['page' => 'account/activity'],
        '/dashboard/subscription' => ['page' => 'account/subscription'],
        '/dashboard/settings'     => ['page' => 'account/settings'],
        '/account'                => ['page' => 'account/account'],

        '/privacy-policy'  => ['page' => 'legal/privacy-policy'],
        '/terms'           => ['page' => 'legal/terms'],
        '/cookies'         => ['page' => 'legal/cookies'],
    ];

    // Every tool shares ONE page template.
    foreach (tools() as $slug => $tool) {
        $routes['/' . $slug] = ['page' => 'tool', 'tool' => $slug];
    }
    return $routes;
}

/**
 * Resolve a request path to a route, or null for 404.
 * @return array{page:string, file:string, tool?:string}|null
 */
function resolve_route(string $path): ?array
{
    $route = routes()[$path] ?? null;
    if ($route === null) {
        return null;
    }
    $file = BASE_PATH . '/pages/' . $route['page'] . '.php';
    if (!is_file($file)) {
        return null;
    }
    $route['file'] = $file;
    return $route;
}
