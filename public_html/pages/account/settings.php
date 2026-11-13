<?php
/**
 * /dashboard/settings — signed-in devices and "sign out everywhere else".
 */

declare(strict_types=1);

$user = require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'revoke-others') {
        $n = revoke_user_sessions((int) $user['id'], (int) $user['session_id']);
        flash('success', $n ? "Signed out of $n other " . ($n === 1 ? 'device.' : 'devices.') : 'No other devices were signed in.');
    } elseif ($action === 'revoke' && ($id = filter_var($_POST['session'] ?? null, FILTER_VALIDATE_INT))) {
        // Only the user's own sessions, never the current one (use Logout for that).
        db_exec('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND id <> ? AND revoked_at IS NULL',
            [$id, $user['id'], $user['session_id']]);
        flash('success', 'That device has been signed out.');
    }
    redirect('/dashboard/settings');
}

$sessions = account_sessions((int) $user['id']);

set_page(['title' => 'Settings | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'settings', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head"><div><h1>Settings</h1><p class="muted">Security and signed-in devices.</p></div></div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Signed-in devices</h2>
                    <?php if (count($sessions) > 1): ?>
                    <form method="post" action="<?= e(url('dashboard/settings')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="action" value="revoke-others">
                        <button class="btn btn-secondary btn-sm" type="submit">Sign out all other devices</button>
                    </form>
                    <?php endif; ?>
                </div>
                <ul class="session-list">
                    <?php foreach ($sessions as $s): $current = (int) $s['id'] === (int) $user['session_id']; ?>
                    <li>
                        <div>
                            <p><strong><?= e(describe_device($s['user_agent'])) ?></strong> <?= $current ? '<span class="badge badge-success">This device</span>' : '' ?></p>
                            <p class="hint">IP <?= e(masked_ip($s['ip_address'])) ?> · signed in <?= e(format_date($s['created_at'], 'M j, H:i')) ?> · last active <?= e(format_date($s['last_activity_at'], 'M j, H:i')) ?></p>
                        </div>
                        <?php if (!$current): ?>
                        <form method="post" action="<?= e(url('dashboard/settings')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="session" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
                        </form>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="panel">
                <h2 class="h4">Privacy</h2>
                <ul class="plan-features mt-4">
                    <li><?= icon('check', 'icon icon-sm') ?> Uploaded files are deleted within <?= (int) config('retention.upload_minutes', 60) ?> minutes; results within <?= (int) ceil((int) config('retention.result_minutes', 120) / 60) ?> hours.</li>
                    <li><?= icon('check', 'icon icon-sm') ?> We keep a short history (file names, tools, dates) so you can find your recent work.</li>
                    <li><?= icon('check', 'icon icon-sm') ?> You can delete your account and everything with it on the <a href="<?= e(url('account')) ?>">Account</a> page.</li>
                </ul>
            </div>
        </div>
    </div>
</section>
