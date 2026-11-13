<?php
/**
 * Admin — processing jobs with status/tool filters.
 */

declare(strict_types=1);

set_page(['title' => 'Processing jobs']);

$status = in_array($_GET['status'] ?? '', ['completed', 'failed', 'processing', 'queued', 'expired'], true) ? $_GET['status'] : '';
$toolSlug = isset(tools()[$_GET['tool'] ?? '']) ? (string) $_GET['tool'] : '';
$perPage = 30;
$page = admin_page();
$where = ['1 = 1'];
$params = [];
if ($status) { $where[] = 'j.status = ?'; $params[] = $status; }
if ($toolSlug) { $where[] = 'j.tool = ?'; $params[] = $toolSlug; }
$whereSql = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM processing_jobs j WHERE $whereSql", $params);
$jobs = db_all(
    "SELECT j.tool, j.status, j.engine, j.input_count, j.input_bytes, j.output_bytes, j.duration_ms, j.error_code, j.error_message, j.created_at, u.email
       FROM processing_jobs j LEFT JOIN users u ON u.id = j.user_id WHERE $whereSql ORDER BY j.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$toolOptions = ['' => 'All tools'] + array_map(static fn(array $t): string => $t['name'], tools());
$filters = ['status' => $status, 'tool' => $toolSlug];
?>
<div class="account-head"><div><h1>Processing jobs</h1><p class="muted"><?= number_format($total) ?> matching</p></div></div>

<form class="panel filters" method="get" action="<?= e(admin_url('jobs')) ?>">
    <?= admin_select('status', ['' => 'All statuses', 'completed' => 'Completed', 'failed' => 'Failed', 'processing' => 'Processing', 'queued' => 'Queued'], $status, 'Status') ?>
    <?= admin_select('tool', $toolOptions, $toolSlug, 'Tool') ?>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<div class="panel">
    <?php if (!$jobs): ?><p class="muted">No jobs.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>Tool</th><th>Status</th><th>User</th><th>Input → output</th><th>Engine</th><th>Time</th><th>When</th></tr></thead>
        <tbody><?php foreach ($jobs as $j): ?>
            <tr>
                <td><?= e(tool_name($j['tool'])) ?></td>
                <td data-label="Status"><?= status_badge($j['status']) ?><?php if ($j['error_message']): ?><div class="cell-sub"><code><?= e((string) $j['error_code']) ?></code> <?= e($j['error_message']) ?></div><?php endif; ?></td>
                <td data-label="User"><?= e($j['email'] ?? 'Guest') ?></td>
                <td data-label="Size"><?= (int) $j['input_count'] ?> × <?= e(format_bytes((int) $j['input_bytes'])) ?><?= $j['output_bytes'] !== null ? ' → ' . e(format_bytes((int) $j['output_bytes'])) : '' ?></td>
                <td data-label="Engine"><?= e((string) $j['engine']) ?></td>
                <td data-label="Time"><?= $j['duration_ms'] !== null ? number_format((int) $j['duration_ms']) . ' ms' : '—' ?></td>
                <td data-label="When"><?= e(format_date($j['created_at'], 'M j, H:i')) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= admin_pager($page, $total, $perPage, 'jobs', $filters) ?>
    <?php endif; ?>
</div>
