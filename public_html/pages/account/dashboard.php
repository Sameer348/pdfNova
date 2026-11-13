<?php
/**
 * /dashboard — overview of the signed-in user's usage and recent files.
 */

declare(strict_types=1);

$user   = require_login();
$plan   = current_plan();
$limits = visitor_limits();
$stats  = account_stats((int) $user['id']);
$jobs   = account_jobs((int) $user['id'], (int) $plan['history_days'], 8);

set_page(['title' => 'Dashboard | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'dashboard', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head">
                <div>
                    <h1>Hi, <?= e(explode(' ', $user['name'])[0]) ?></h1>
                    <p class="muted">Here's what you've been working on.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(url('/')) ?>#tools"><?= icon('plus', 'icon icon-sm') ?> New task</a>
            </div>

            <div class="stat-grid">
                <div class="stat">
                    <p class="stat-label"><?= icon('check-circle', 'icon icon-sm') ?> Files processed</p>
                    <p class="stat-value"><?= number_format($stats['total']) ?></p>
                    <p class="stat-sub">All time</p>
                </div>
                <div class="stat">
                    <p class="stat-label"><?= icon('clock', 'icon icon-sm') ?> This month</p>
                    <p class="stat-value"><?= number_format($stats['month']) ?></p>
                    <p class="stat-sub"><?= e(gmdate('F Y')) ?></p>
                </div>
                <div class="stat">
                    <p class="stat-label"><?= icon('credit-card', 'icon icon-sm') ?> Current plan</p>
                    <p class="stat-value"><?= e($plan['name']) ?></p>
                    <?php if ($limits['daily_jobs'] !== null): ?>
                    <progress class="usage" max="<?= (int) $limits['daily_jobs'] ?>" value="<?= min($stats['last24h'], (int) $limits['daily_jobs']) ?>" aria-label="Tasks used in the last 24 hours"></progress>
                    <p class="stat-sub"><?= $stats['last24h'] ?> of <?= (int) $limits['daily_jobs'] ?> tasks used (24 h)</p>
                    <?php else: ?>
                    <p class="stat-sub">Unlimited tasks</p>
                    <?php endif; ?>
                </div>
                <div class="stat">
                    <p class="stat-label"><?= icon('folder', 'icon icon-sm') ?> Storage used</p>
                    <p class="stat-value"><?= e(format_bytes($stats['storage_bytes'])) ?></p>
                    <p class="stat-sub"><?= $stats['storage_files'] ?> temporary <?= $stats['storage_files'] === 1 ? 'file' : 'files' ?> · auto-deleted</p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Recent files</h2>
                    <a class="small" href="<?= e(url('dashboard/files')) ?>">View all</a>
                </div>
                <?php partial('components/jobs-table', ['jobs' => $jobs]); ?>
                <p class="hint mt-4"><?= icon('shield', 'icon icon-xs') ?> For your privacy, result files are deleted <?= (int) ceil((int) config('retention.result_minutes', 120) / 60) ?> hours after processing. The list itself is kept for <?= (int) $plan['history_days'] ?> days.</p>
            </div>
        </div>
    </div>
</section>
