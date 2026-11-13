<?php
/**
 * /dashboard/activity — sign-ins, uploads, tasks and downloads.
 */

declare(strict_types=1);

$user = require_login();
$perPage = 30;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = account_activity_count((int) $user['id']);
$rows = account_activity((int) $user['id'], $perPage, ($page - 1) * $perPage);
$labels = [
    'login'    => ['user', 'Signed in'],
    'upload'   => ['upload', 'Uploaded a file'],
    'process'  => ['check-circle', 'Completed a task'],
    'download' => ['download', 'Downloaded a result'],
];

set_page(['title' => 'Recent activity | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'activity', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head"><div><h1>Recent activity</h1><p class="muted">A record of what happened on your account.</p></div></div>
            <div class="panel">
                <?php if (!$rows): ?>
                <div class="empty-state"><?= icon('activity') ?><p>No activity yet.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="table table-stack">
                        <thead><tr><th>Activity</th><th>Tool</th><th>Size</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): [$iconName, $label] = $labels[$row['action']] ?? ['info', ucfirst($row['action'])]; ?>
                            <tr>
                                <td><span class="row"><?= icon($iconName, 'icon icon-sm') ?> <?= e($label) ?></span></td>
                                <td data-label="Tool"><?= $row['tool'] ? e(tool_name($row['tool'])) : '—' ?></td>
                                <td data-label="Size"><?= (int) $row['bytes'] > 0 ? e(format_bytes((int) $row['bytes'])) : '—' ?></td>
                                <td data-label="When"><?= e(format_date($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= pager($page, $total, $perPage, 'dashboard/activity') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
