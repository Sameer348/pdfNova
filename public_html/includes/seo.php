<?php
/**
 * robots.txt and sitemap.xml, generated from the tool registry and config.
 */

declare(strict_types=1);

function output_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    $production = config('app.env') === 'production';
    echo "User-agent: *\n";
    if (!$production) {
        echo "Disallow: /\n"; // never let a staging/local copy be indexed
        return;
    }
    foreach (['/api/', '/admin/', '/dashboard', '/account', '/reset-password', '/logout'] as $path) {
        echo 'Disallow: ' . url($path) . "\n";
    }
    echo "\nSitemap: " . absolute_url('sitemap.xml') . "\n";
}

function output_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $entries = [['/', 'pages/home.php', '1.0']];
    foreach (tools() as $slug => $tool) {
        $entries[] = ['/' . $slug, 'pages/tool.php', tool_available($tool) ? '0.9' : '0.5'];
    }
    foreach (['pricing' => '0.7', 'security' => '0.5', 'contact' => '0.4',
              'privacy-policy' => '0.3', 'terms' => '0.3', 'cookies' => '0.2'] as $page => $priority) {
        $file = is_file(BASE_PATH . "/pages/$page.php") ? "pages/$page.php" : "pages/legal/$page.php";
        $entries[] = ['/' . $page, $file, $priority];
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as [$path, $file, $priority]) {
        $mtime = @filemtime(BASE_PATH . '/' . $file) ?: time();
        echo '  <url><loc>' . htmlspecialchars(absolute_url($path), ENT_XML1) . '</loc><lastmod>' . gmdate('Y-m-d', $mtime)
            . '</lastmod><priority>' . $priority . "</priority></url>\n";
    }
    echo "</urlset>\n";
}
