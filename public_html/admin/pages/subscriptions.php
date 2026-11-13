<?php
/**
 * Admin — subscriptions. Plans are assigned per user on the user page;
 * here admins see all subscriptions and can cancel one.
 */

declare(strict_types=1);

set_page(['title' => 'Subscriptions']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'cancel') {
        $n = db_exec("UPDATE subscriptions SET status = 'canceled', canceled_at = UTC_TIMESTAMP() WHERE id = ? AND status IN ('active','trialing','past_due')", [$id]);
        if ($n) {
            admin_audit('subscription.canceled', 'subscription', $id);
            flash('success', 'Subscription canceled. The user is back on the Free plan.');
        }
    }
    redirect(admin_url('subscriptions', ['status' => $_GET['status'] ?? '']));
}

$status = in_array($_GET['status'] ?? '', ['active', 'canceled', 'expired', 'past_due', 'trialing'], true) ? $_GET['status'] : 'active';
$perPage = 30;
$page = admin_page();
$total = (int) db_value('SELECT COUNT(*) FROM subscriptions WHERE status = ?', [$status]);
$rows = db_all(
    "SELECT s.id, s.user_id, s.status, s.billing_cycle, s.gateway, s.current_period_end, s.created_at, s.canceled_at, p.name AS plan, u.email, u.name
       FROM subscriptions s JOIN plans p ON p.id = s.plan_id JOIN users u ON u.id = s.user_id
      WHERE s.status = ? ORDER BY s.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    [$status]
);
$byPlan = db_all("SELECT p.name, COUNT(s.id) AS n FROM plans p LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status IN ('active','trialing') WHERE p.slug <> 'free' GROUP BY p.id ORDER BY p.sort_order");
?>
<div class="account-head"><div><h1>Subscriptions</h1><p class="muted">Assign plans from a user's page. Online payments are <?= config('payment.provider', 'none') === 'none' ? 'not connected — all subscriptions are manual' : 'handled by ' . e((string) config('payment.provider')) ?>.</p></div></div>

<div class="stat-grid">
    <?php foreach ($byPlan as $p): ?>
    <div class="stat"><p class="stat-label"><?= e($p['name']) ?></p><p class="stat-value"><?= (int) $p['n'] ?></p><p class="stat-sub">active subscriptions</p></div>
    <?php endforeach; ?>
</div>

<form class="panel filters" method="get" action="<?= e(admin_url('subscriptions')) ?>">
    <?= admin_select('status', ['active' => 'Active', 'trialing' => 'Trialing', 'past_due' => 'Past due', 'canceled' => 'Canceled', 'expired' => 'Expired'], $status, 'Status') ?>
    <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<div class="panel">
    <?php if (!$rows): ?><p class="muted">No <?= e($status) ?> subscriptions.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>User</th><th>Plan</th><th>Gateway</th><th>Started</th><th>Ends</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td><a class="cell-file" href="<?= e(admin_url('users/view', ['id' => $r['user_id']])) ?>"><?= e($r['name']) ?></a><div class="cell-sub"><?= e($r['email']) ?></div></td>
                <td data-label="Plan"><?= e($r['plan']) ?></td>
                <td data-label="Gateway"><?= e($r['gateway']) ?></td>
                <td data-label="Started"><?= e(format_date($r['created_at'], 'M j, Y')) ?></td>
                <td data-label="Ends"><?= $r['current_period_end'] ? e(format_date($r['current_period_end'], 'M j, Y')) : 'No end date' ?></td>
                <td><?php if (in_array($r['status'], ['active', 'trialing', 'past_due'], true)): ?>
                    <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Cancel</button></form>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= admin_pager($page, $total, $perPage, 'subscriptions', ['status' => $status]) ?>
    <?php endif; ?>
</div>
