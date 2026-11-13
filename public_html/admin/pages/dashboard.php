<?php
/**
 * Admin dashboard — totals, activity, storage, errors, revenue placeholder.
 */

declare(strict_types=1);

set_page(['title' => 'Dashboard']);

require_once INCLUDES_PATH . '/system-check.php';
$checks = system_checks();
$checkCounts = array_count_values(array_column($checks, 'status'));

$users = db_one(
    "SELECT COUNT(*) AS total,
            SUM(created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY) AS new7,
            SUM(status = 'suspended') AS suspended
       FROM users"
);
$active30 = (int) db_value(
    'SELECT COUNT(DISTINCT user_id) FROM sessions WHERE last_activity_at > UTC_TIMESTAMP() - INTERVAL 30 DAY'
);
$jobs = db_one(
    "SELECT SUM(status = 'completed') AS completed,
            SUM(status = 'completed' AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY) AS today,
            SUM(status = 'failed' AND created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY) AS failed7,
            SUM(created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY) AS all7
       FROM processing_jobs"
);
$storage = ['uploads' => storage_usage('uploads'), 'processed' => storage_usage('processed'), 'temp' => storage_usage('temp')];
$storageBytes = array_sum(array_column($storage, 'bytes'));
$storageFiles = array_sum(array_column($storage, 'files'));
$revenue = db_one("SELECT COALESCE(SUM(amount_cents), 0) AS cents, COUNT(*) AS n FROM payments WHERE status = 'succeeded'");
$activeSubs = (int) db_value("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')");
$byTool = db_all(
    "SELECT tool, COUNT(*) AS n FROM processing_jobs WHERE status = 'completed' AND created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY
      GROUP BY tool ORDER BY n DESC LIMIT 10"
);
$maxTool = max(1, (int) ($byTool[0]['n'] ?? 1));
$failedJobs = db_all(
    "SELECT j.tool, j.error_code, j.error_message, j.created_at, u.email FROM processing_jobs j
       LEFT JOIN users u ON u.id = j.user_id WHERE j.status = 'failed' ORDER BY j.id DESC LIMIT 8"
);
$logErrors = recent_log_errors(6);
$failRate = (int) $jobs['all7'] > 0 ? round(100 * (int) $jobs['failed7'] / (int) $jobs['all7'], 1) : 0;
?>
<div class="account-head"><div><h1>Dashboard</h1><p class="muted">Overview of PDFNova · times in UTC</p></div></div>

<div class="stat-grid stat-grid-6">
    <div class="stat"><p class="stat-label"><?= icon('user', 'icon icon-sm') ?> Total users</p><p class="stat-value"><?= number_format((int) $users['total']) ?></p><p class="stat-sub">+<?= (int) $users['new7'] ?> this week · <?= (int) $users['suspended'] ?> suspended</p></div>
    <div class="stat"><p class="stat-label"><?= icon('activity', 'icon icon-sm') ?> Active users</p><p class="stat-value"><?= number_format($active30) ?></p><p class="stat-sub">signed in during the last 30 days</p></div>
    <div class="stat"><p class="stat-label"><?= icon('check-circle', 'icon icon-sm') ?> Files processed</p><p class="stat-value"><?= number_format((int) $jobs['completed']) ?></p><p class="stat-sub"><?= (int) $jobs['today'] ?> in the last 24 hours</p></div>
    <div class="stat"><p class="stat-label"><?= icon('folder', 'icon icon-sm') ?> Storage</p><p class="stat-value"><?= e(format_bytes($storageBytes)) ?></p><p class="stat-sub"><?= $storageFiles ?> temporary files on disk</p></div>
    <div class="stat"><p class="stat-label"><?= icon('alert', 'icon icon-sm') ?> Errors</p><p class="stat-value"><?= (int) $jobs['failed7'] ?></p><p class="stat-sub">failed jobs in 7 days (<?= $failRate ?>%)</p></div>
    <div class="stat"><p class="stat-label"><?= icon('credit-card', 'icon icon-sm') ?> Revenue</p><p class="stat-value">$<?= number_format((int) $revenue['cents'] / 100, 2) ?></p><p class="stat-sub"><?= config('payment.provider', 'none') === 'none' ? 'No payment provider connected · ' : '' ?><?= $activeSubs ?> active subscriptions</p></div>
</div>

<div class="panel" id="system-check">
    <div class="panel-head"><h2>System check</h2>
        <span class="small"><?php if (!empty($checkCounts['fail'])): ?><span class="badge badge-danger"><?= (int) $checkCounts['fail'] ?> to fix</span> <?php endif; ?><?php if (!empty($checkCounts['warn'])): ?><span class="badge badge-warning"><?= (int) $checkCounts['warn'] ?> warnings</span> <?php endif; ?><?php if (empty($checkCounts['fail']) && empty($checkCounts['warn'])): ?><span class="badge badge-success">All good</span><?php endif; ?></span>
    </div>
    <p class="hint">Checked live by the web server on every visit. See HOSTINGER_DEPLOYMENT.md for how to fix each item.</p>
    <ul class="check-list">
        <?php foreach ($checks as $ch): ?>
        <li class="check-<?= e($ch['status']) ?>">
            <span class="badge <?= ['ok' => 'badge-success', 'warn' => 'badge-warning', 'fail' => 'badge-danger'][$ch['status']] ?>"><?= ['ok' => 'OK', 'warn' => 'Warning', 'fail' => 'Fix'][$ch['status']] ?></span>
            <div><strong><?= e($ch['label']) ?></strong><p class="small muted"><?= e($ch['detail']) ?></p></div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="grid grid-2">
    <div class="panel">
        <div class="panel-head"><h2>Most used tools (30 days)</h2></div>
        <?php if (!$byTool): ?><p class="muted small">No completed jobs yet.</p><?php else: ?>
        <ul class="bar-list">
            <?php foreach ($byTool as $row): ?>
            <li><span><?= e(tool_name($row['tool'])) ?></span><progress class="usage" max="<?= $maxTool ?>" value="<?= (int) $row['n'] ?>"></progress><span class="small"><?= (int) $row['n'] ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head"><h2>Storage by folder</h2></div>
        <dl class="kv">
            <?php foreach ($storage as $area => $u): ?>
            <dt><?= e(ucfirst($area)) ?></dt><dd><?= e(format_bytes($u['bytes'])) ?> · <?= $u['files'] ?> files</dd>
            <?php endforeach; ?>
        </dl>
        <p class="hint mt-4">Files are removed automatically by the cleanup job. If numbers keep growing, check that the cron job is configured.</p>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h2>Recent failed jobs</h2><a class="small" href="<?= e(admin_url('jobs', ['status' => 'failed'])) ?>">View all</a></div>
    <?php if (!$failedJobs): ?><p class="muted small">No failed jobs. 🎉</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>Tool</th><th>Error</th><th>User</th><th>When</th></tr></thead>
        <tbody><?php foreach ($failedJobs as $j): ?>
            <tr><td><?= e(tool_name($j['tool'])) ?></td><td data-label="Error"><code><?= e((string) $j['error_code']) ?></code> <?= e((string) $j['error_message']) ?></td><td data-label="User"><?= e($j['email'] ?? 'Guest') ?></td><td data-label="When"><?= e(format_date($j['created_at'], 'M j, H:i')) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-head"><h2>Recent server errors</h2><span class="hint">from storage/logs/app.log</span></div>
    <?php if (!$logErrors): ?><p class="muted small">No errors logged.</p><?php else: ?>
    <ul class="session-list">
        <?php foreach ($logErrors as $err): ?>
        <li><div><p class="log-line"><strong><?= e(strtoupper((string) $err['level'])) ?></strong> <?= e((string) $err['message']) ?></p>
            <p class="hint"><?= e(format_date(str_replace('T', ' ', substr((string) $err['time'], 0, 19)))) ?> · <?= e((string) ($err['uri'] ?? '')) ?></p></div></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
