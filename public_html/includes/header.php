<?php
/**
 * Global layout — header.
 * Expects page metadata set via set_page() (see index.php for defaults).
 */

declare(strict_types=1);

$siteName    = (string) config('app.name', 'PDFNova');
$title       = (string) page('title', $siteName);
$description = (string) page('description', '');
$canonical   = absolute_url((string) page('canonical', current_path()));
$robots      = (string) page('robots', 'index,follow');
$ogImage     = absolute_url('assets/images/og-image.png');
$user        = current_user();
$grouped     = tools_by_category();
$categories  = tool_categories();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <meta name="theme-color" content="#d21f45">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script type="module" src="<?= e(module_url('js/app.js')) ?>"></script>
    <?php foreach ((array) page('scripts', []) as $script): ?>
    <script type="module" src="<?= e(module_url($script)) ?>"></script>
    <?php endforeach; ?>
    <?php if (page('schema')): ?>
    <script type="application/ld+json"><?= json_encode(page('schema'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endif; ?>
</head>
<body class="<?= e((string) page('body_class', '')) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" data-site-header>
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteName) ?> home"><?= logo() ?></a>

        <nav class="main-nav" aria-label="Main">
            <ul class="nav-list">
                <!-- Tools: all categories -->
                <li class="nav-item" data-dropdown>
                    <button class="nav-link" type="button" aria-expanded="false" aria-controls="panel-tools">
                        Tools <?= icon('chevron-down', 'icon icon-sm nav-caret') ?>
                    </button>
                    <div class="nav-panel nav-panel-mega" id="panel-tools">
                        <div class="mega-grid">
                            <?php foreach ($grouped as $cat => $catTools): ?>
                            <div class="mega-col">
                                <p class="mega-title"><?= e($categories[$cat]['label']) ?></p>
                                <ul>
                                    <?php foreach ($catTools as $slug => $t): ?>
                                    <li><a class="menu-link" href="<?= e(url($slug)) ?>">
                                        <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                                        <?= e($t['name']) ?>
                                    </a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </li>

                <li class="nav-item"><a class="nav-link<?= is_current('compress-pdf') ? ' is-active' : '' ?>" href="<?= e(url('compress-pdf')) ?>">Compress</a></li>

                <!-- Convert: to / from -->
                <li class="nav-item" data-dropdown>
                    <button class="nav-link" type="button" aria-expanded="false" aria-controls="panel-convert">
                        Convert <?= icon('chevron-down', 'icon icon-sm nav-caret') ?>
                    </button>
                    <div class="nav-panel nav-panel-2col" id="panel-convert">
                        <?php foreach (['convert-to', 'convert-from'] as $cat): ?>
                        <div class="mega-col">
                            <p class="mega-title"><?= e($categories[$cat]['label']) ?></p>
                            <ul>
                                <?php foreach ($grouped[$cat] as $slug => $t): ?>
                                <li><a class="menu-link" href="<?= e(url($slug)) ?>">
                                    <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                                    <?= e($t['name']) ?>
                                </a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </li>

                <!-- Organize -->
                <li class="nav-item" data-dropdown>
                    <button class="nav-link" type="button" aria-expanded="false" aria-controls="panel-organize">
                        Organize <?= icon('chevron-down', 'icon icon-sm nav-caret') ?>
                    </button>
                    <div class="nav-panel" id="panel-organize">
                        <ul>
                            <?php foreach ($grouped['organize'] as $slug => $t): ?>
                            <li><a class="menu-link menu-link-desc" href="<?= e(url($slug)) ?>">
                                <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                                <span><strong><?= e($t['name']) ?></strong><small><?= e($t['short']) ?></small></span>
                            </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>

                <li class="nav-item"><a class="nav-link<?= is_current('pricing') ? ' is-active' : '' ?>" href="<?= e(url('pricing')) ?>">Pricing</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <?php if ($user): ?>
                <?php if ($user['role'] === 'admin'): ?>
                <a class="btn btn-ghost btn-sm hide-mobile" href="<?= e(url('admin/')) ?>">Admin</a>
                <?php endif; ?>
                <a class="btn btn-primary btn-sm hide-xs" href="<?= e(url(auth_home())) ?>"><?= icon('dashboard', 'icon icon-sm') ?> <?= e(explode(' ', $user['name'])[0]) ?></a>
                <form class="hide-mobile" method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('logout', 'icon icon-sm') ?> Log out</button>
                </form>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm hide-mobile" href="<?= e(url('login')) ?>">Login</a>
                <a class="btn btn-primary btn-sm hide-xs" href="<?= e(url('register')) ?>">Get Started</a>
            <?php endif; ?>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu" data-menu-toggle>
                <?= icon('menu', 'icon icon-menu-open') ?><?= icon('close', 'icon icon-menu-close') ?>
            </button>
        </div>
    </div>

    <!-- Mobile navigation (< 1024px) -->
    <div class="mobile-nav" id="mobile-nav" data-mobile-nav>
        <div class="container mobile-nav-inner">
            <?php foreach ($grouped as $cat => $catTools): ?>
            <details class="mobile-group">
                <summary><?= e($categories[$cat]['label']) ?> <?= icon('chevron-down', 'icon icon-sm') ?></summary>
                <ul>
                    <?php foreach ($catTools as $slug => $t): ?>
                    <li><a class="menu-link" href="<?= e(url($slug)) ?>">
                        <span class="tool-icon tool-icon-sm <?= e(tool_tone($t)) ?>"><?= icon($t['icon']) ?></span>
                        <?= e($t['name']) ?>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>
            <a class="mobile-link" href="<?= e(url('pricing')) ?>">Pricing</a>
            <div class="mobile-actions">
                <?php if ($user): ?>
                    <a class="btn btn-primary btn-block" href="<?= e(url(auth_home())) ?>">Dashboard</a>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a class="btn btn-secondary btn-block" href="<?= e(url('admin/')) ?>">Admin</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-block" type="submit">Log out</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-secondary btn-block" href="<?= e(url('login')) ?>">Login</a>
                    <a class="btn btn-primary btn-block" href="<?= e(url('register')) ?>">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main id="main" class="site-main" tabindex="-1">
<?php $flashes = take_flashes(); if ($flashes): ?>
<div class="container flash-stack">
    <?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status">
        <?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert' : 'info')) ?>
        <p><?= e($flash['message']) ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
