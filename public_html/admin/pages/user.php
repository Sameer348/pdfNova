<?php
/**
 * Admin — one user: details and actions (role, status, plan, sessions, delete).
 *
 * Safety rules: an admin cannot demote, suspend or delete themselves, and the
 * last active admin can never be removed (no lock-out).
 */

declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if ($u === null) {
    render_error_page(404, 'User not found', 'This user does not exist (or was deleted).');
    exit;
}
$self = (int) $u['id'] === (int) $admin['id'];
$plans = db_all('SELECT id, slug, name FROM plans WHERE is_active = 1 ORDER BY sort_order');
$back = admin_url('users/view', ['id' => $id]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $protectsLastAdmin = $u['role'] === 'admin' && $u['status'] === 'active' && admin_count() <= 1;

    switch ($action) {
        case 'role':
            $newRole = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
            if ($self || ($newRole === 'user' && $protectsLastAdmin)) {
                flash('error', $self ? 'You cannot change your own role.' : 'The last admin cannot be demoted.');
                break;
            }
            db_exec('UPDATE users SET role = ? WHERE id = ?', [$newRole, $id]);
            revoke_user_sessions($id); // privilege change → fresh sign-in
            admin_audit('user.role', 'user', $id, ['role' => $newRole]);
            flash('success', 'Role updated. The user has been signed out so the change takes effect.');
            break;

        case 'status':
            $newStatus = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
            if ($self || ($newStatus === 'suspended' && $protectsLastAdmin)) {
                flash('error', $self ? 'You cannot suspend yourself.' : 'The last admin cannot be suspended.');
                break;
            }
            db_exec('UPDATE users SET status = ? WHERE id = ?', [$newStatus, $id]);
            if ($newStatus === 'suspended') {
                revoke_user_sessions($id);
            }
            admin_audit('user.status', 'user', $id, ['status' => $newStatus]);
            flash('success', $newStatus === 'suspended' ? 'User suspended and signed out everywhere.' : 'User reactivated.');
            break;

        case 'plan':
            $planId = (int) ($_POST['plan_id'] ?? 0);
            $months = max(0, min(60, (int) ($_POST['months'] ?? 0)));
            $plan = db_one('SELECT * FROM plans WHERE id = ? AND is_active = 1', [$planId]);
            if ($plan === null) {
                flash('error', 'Choose a plan.');
                break;
            }
            db_transaction(static function () use ($id, $plan, $months): void {
                db_exec("UPDATE subscriptions SET status = 'canceled', canceled_at = UTC_TIMESTAMP() WHERE user_id = ? AND status IN ('active','trialing')", [$id]);
                if ($plan['slug'] !== 'free') {
                    db_exec(
                        "INSERT INTO subscriptions (user_id, plan_id, status, billing_cycle, gateway, amount_cents, currency, current_period_start, current_period_end)
                         VALUES (?, ?, 'active', ?, 'manual', 0, ?, UTC_TIMESTAMP(), " . ($months > 0 ? 'UTC_TIMESTAMP() + INTERVAL ' . $months . ' MONTH' : 'NULL') . ')',
                        [$id, $plan['id'], $months > 0 ? 'monthly' : 'none', $plan['currency']]
                    );
                }
            });
            admin_audit('user.plan', 'user', $id, ['plan' => $plan['slug'], 'months' => $months]);
            flash('success', 'Plan set to ' . $plan['name'] . ($plan['slug'] !== 'free' ? ($months ? " for $months months." : ' with no end date.') : '.'));
            break;

        case 'sessions':
            $n = revoke_user_sessions($id);
            admin_audit('user.sessions_revoked', 'user', $id, ['count' => $n]);
            flash('success', "Signed out of $n " . ($n === 1 ? 'session.' : 'sessions.'));
            break;

        case 'delete':
            if ($self || $protectsLastAdmin) {
                flash('error', $self ? 'You cannot delete your own account here.' : 'The last admin cannot be deleted.');
                break;
            }
            if (mb_strtolower(trim((string) ($_POST['confirm_email'] ?? ''))) !== $u['email']) {
                flash('error', 'Type the user\'s email address exactly to confirm deletion.');
                break;
            }
            delete_account($id);
            admin_audit('user.deleted', 'user', $id, ['email_domain' => substr(strrchr($u['email'], '@') ?: '', 1)]);
            flash('success', 'User deleted, including their files.');
            redirect(admin_url('users'));
    }
    redirect($back);
}

$plan = plan_for_user($id);
$sessions = account_sessions($id);
$jobs = account_jobs($id, 3650, 10);
$stats = account_stats($id);
$audit = db_all("SELECT a.action, a.details_json, a.created_at, x.email AS admin_email FROM admin_audit_log a LEFT JOIN users x ON x.id = a.admin_id
                  WHERE a.target_type = 'user' AND a.target_id = ? ORDER BY a.id DESC LIMIT 10", [(string) $id]);

set_page(['title' => $u['name']]);
?>
<div class="account-head">
    <div><p class="small"><a href="<?= e(admin_url('users')) ?>">← Users</a></p><h1><?= e($u['name']) ?></h1><p class="muted"><?= e($u['email']) ?></p></div>
    <div class="row"><?= $u['role'] === 'admin' ? '<span class="badge badge-info">Admin</span>' : '' ?><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e(ucfirst($u['status'])) ?></span></div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <div class="panel-head"><h2>Details</h2></div>
        <dl class="kv">
            <dt>User ID</dt><dd><?= (int) $u['id'] ?></dd>
            <dt>Plan</dt><dd><?= e($plan['name']) ?></dd>
            <dt>Joined</dt><dd><?= e(format_date($u['created_at'])) ?></dd>
            <dt>Last login</dt><dd><?= e(format_date($u['last_login_at'])) ?> (<?= e(masked_ip($u['last_login_ip'])) ?>)</dd>
            <dt>Failed logins</dt><dd><?= (int) $u['failed_login_count'] ?></dd>
            <dt>Jobs completed</dt><dd><?= number_format($stats['total']) ?> (<?= $stats['month'] ?> this month)</dd>
            <dt>Active sessions</dt><dd><?= count($sessions) ?></dd>
        </dl>
    </div>

    <div class="panel stack">
        <div class="panel-head"><h2>Actions</h2></div>
        <?php if ($self): ?><p class="hint">This is your own account — role, status and deletion are locked to prevent lock-outs.</p><?php endif; ?>

        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="plan">
            <?= admin_select('plan_id', array_column($plans, 'name', 'id'), (string) $plan['id'], 'Plan') ?>
            <?= admin_select('months', ['0' => 'No end date', '1' => '1 month', '3' => '3 months', '12' => '12 months'], '0', 'Duration') ?>
            <button class="btn btn-secondary btn-sm" type="submit">Set plan</button>
        </form>

        <?php if (!$self): ?>
        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role">
            <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?= $u['role'] === 'admin' ? 'Remove admin role' : 'Make admin' ?></button>
        </form>
        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
            <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
            <button class="btn <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?> btn-sm" type="submit"><?= $u['status'] === 'active' ? 'Suspend user' : 'Reactivate user' ?></button>
        </form>
        <?php endif; ?>

        <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sessions">
            <button class="btn btn-secondary btn-sm" type="submit">Sign out all sessions</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h2>Recent jobs</h2></div>
    <?php partial('components/jobs-table', ['jobs' => $jobs, 'showDownloads' => false]); ?>
    <p class="hint mt-4">Download links are shown only to the file's owner; admins see metadata.</p>
</div>

<div class="panel">
    <div class="panel-head"><h2>Admin history for this user</h2></div>
    <?php if (!$audit): ?><p class="muted small">No admin actions yet.</p><?php else: ?>
    <ul class="session-list"><?php foreach ($audit as $a): ?>
        <li><div><p><strong><?= e($a['action']) ?></strong> <span class="log-line"><?= e((string) $a['details_json']) ?></span></p><p class="hint"><?= e($a['admin_email'] ?? 'deleted admin') ?> · <?= e(format_date($a['created_at'])) ?></p></div></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
</div>

<?php if (!$self): ?>
<form class="panel panel-danger form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <h2 class="h4">Delete user</h2>
    <p class="small muted">Permanently deletes the account, its history and any stored files. Type the email address to confirm.</p>
    <div class="inline-form"><input class="input" name="confirm_email" type="text" autocomplete="off" placeholder="<?= e($u['email']) ?>" aria-label="Confirm email">
        <button class="btn btn-danger" type="submit">Delete user</button></div>
</form>
<?php endif; ?>
