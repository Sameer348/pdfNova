<?php
/**
 * Admin layout — top bar + sidebar. Vars: $admin (current admin user)
 */

declare(strict_types=1);

$nav = [
    'dashboard'     => ['Dashboard', '', 'dashboard'],
    'users'         => ['Users', 'users', 'user'],
    'files'         => ['Files', 'files', 'folder'],
    'jobs'          => ['Processing jobs', 'jobs', 'activity'],
    'messages'      => ['Contact messages', 'messages', 'mail'],
    'subscriptions' => ['Subscriptions', 'subscriptions', 'credit-card'],
    'settings'      => ['Settings', 'settings', 'settings'],
];
$active = $GLOBALS['admin_active'] ?? 'dashboard';
$unread = (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e((string) page('title', 'Admin')) ?> · PDFNova Admin</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script type="module" src="<?= e(module_url('js/app.js')) ?>"></script>
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">Skip to content</a>
<header class="admin-top">
    <a class="brand" href="<?= e(admin_url()) ?>"><?= logo() ?> <span class="admin-tag">Admin</span></a>
    <div class="admin-top-actions">
        <a class="btn btn-ghost btn-sm hide-xs" href="<?= e(url('/')) ?>">View site</a>
        <span class="small muted hide-xs"><?= e($admin['email']) ?></span>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Log out</button></form>
    </div>
</header>
<div class="admin-shell">
    <nav class="admin-nav" aria-label="Admin">
        <ul>
            <?php foreach ($nav as $key => [$label, $path, $iconName]): ?>
            <li><a class="account-link<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(admin_url($path)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName, 'icon icon-sm') ?> <?= e($label) ?>
                <?php if ($key === 'messages' && $unread): ?><span class="badge badge-red"><?= $unread ?></span><?php endif; ?>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <main class="admin-main" id="admin-main" tabindex="-1">
        <?php foreach (take_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert' : 'info')) ?><p><?= e($flash['message']) ?></p></div>
        <?php endforeach; ?>
