<?php
/**
 * Admin — user list with search and filters.
 */

declare(strict_types=1);

set_page(['title' => 'Users']);

$q = trim((string) ($_GET['q'] ?? ''));
$role = in_array($_GET['role'] ?? '', ['user', 'admin'], true) ? $_GET['role'] : '';
$status = in_array($_GET['status'] ?? '', ['active', 'suspended', 'pending'], true) ? $_GET['status'] : '';
$perPage = 25;
$page = admin_page();

$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.email LIKE ? OR u.name LIKE ?)';
    $params[] = like_term($q);
    $params[] = like_term($q);
}
if ($role !== '') { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM users u WHERE $whereSql", $params);
$users = db_all(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM processing_jobs j WHERE j.user_id = u.id AND j.status = 'completed') AS jobs,
            (SELECT p.name FROM subscriptions s JOIN plans p ON p.id = s.plan_id
              WHERE s.user_id = u.id AND s.status IN ('active','trialing')
                AND (s.current_period_end IS NULL OR s.current_period_end > UTC_TIMESTAMP())
              ORDER BY p.sort_order DESC LIMIT 1) AS plan
       FROM users u WHERE $whereSql ORDER BY u.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$filters = ['q' => $q, 'role' => $role, 'status' => $status];
?>
<div class="account-head"><div><h1>Users</h1><p class="muted"><?= number_format($total) ?> matching</p></div></div>

<form class="panel filters" method="get" action="<?= e(admin_url('users')) ?>">
    <label class="visually-hidden" for="f-q">Search</label>
    <input class="input" id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or email">
    <?= admin_select('role', ['' => 'All roles', 'user' => 'Users', 'admin' => 'Admins'], $role, 'Role') ?>
    <?= admin_select('status', ['' => 'All statuses', 'active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'], $status, 'Status') ?>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    <?php if ($q || $role || $status): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('users')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$users): ?><p class="muted">No users found.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Plan</th><th>Jobs</th><th>Joined</th><th>Last login</th></tr></thead>
        <tbody><?php foreach ($users as $u): ?>
            <tr>
                <td><a class="cell-file" href="<?= e(admin_url('users/view', ['id' => $u['id']])) ?>"><?= e($u['name']) ?></a><div class="cell-sub"><?= e($u['email']) ?></div></td>
                <td data-label="Role"><?= $u['role'] === 'admin' ? '<span class="badge badge-info">Admin</span>' : 'User' ?></td>
                <td data-label="Status"><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                <td data-label="Plan"><?= e($u['plan'] ?? 'Free') ?></td>
                <td data-label="Jobs"><?= (int) $u['jobs'] ?></td>
                <td data-label="Joined"><?= e(format_date($u['created_at'], 'M j, Y')) ?></td>
                <td data-label="Last login"><?= e(format_date($u['last_login_at'], 'M j, H:i')) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= admin_pager($page, $total, $perPage, 'users', $filters) ?>
    <?php endif; ?>
</div>
