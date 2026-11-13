<?php
/**
 * /reset-password — choose a new password with an emailed token.
 *
 * The token arrives as ?token=… and is immediately moved into the session and
 * the URL cleaned, so it doesn't stay in browser history or get shared.
 */

declare(strict_types=1);

if (isset($_GET['token'])) {
    $_SESSION['_reset_token'] = (string) $_GET['token'];
    redirect('/reset-password');
}

$token = (string) ($_SESSION['_reset_token'] ?? '');
$valid = find_reset($token) !== null;
$error = null;

if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    if (!rate_limit('reset-submit', client_ip(), 10, 600)) {
        $error = 'Too many attempts. Please wait a few minutes.';
    } else {
        $result = reset_password($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
        if ($result['ok']) {
            unset($_SESSION['_reset_token']);
            if (is_logged_in()) {
                log_out_user();
            }
            flash('success', 'Your password has been changed. You\'ve been signed out everywhere — please log in with your new password.');
            redirect('/login');
        }
        $error = $result['error'];
    }
}

set_page([
    'title'       => 'Choose a new password | PDFNova',
    'description' => 'Choose a new password for your PDFNova account.',
    'robots'      => 'noindex,nofollow',
    'body_class'  => 'page-auth',
]);
?>
<section class="auth-page">
    <div class="auth-card">
        <?php if (!$valid): ?>
            <div class="auth-icon auth-icon-warn"><?= icon('alert', 'icon icon-xl') ?></div>
            <h1 class="h2">This link has expired</h1>
            <p class="muted">Password reset links work once and expire after <?= (int) RESET_TTL_MINUTES ?> minutes.</p>
            <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('forgot-password')) ?>">Request a new link</a>
        <?php else: ?>
            <h1 class="h2">Choose a new password</h1>
            <p class="muted">You'll be signed out of all devices after the change.</p>
            <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= icon('alert') ?><p><?= e($error) ?></p></div>
            <?php endif; ?>
            <form class="form" method="post" action="<?= e(url('reset-password')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="label" for="new-password">New password</label>
                    <?= password_input('new-password', 'password', 'new-password') ?>
                    <p class="hint">At least <?= (int) PASSWORD_MIN ?> characters.</p>
                </div>
                <div class="field">
                    <label class="label" for="new-password-confirm">Confirm new password</label>
                    <?= password_input('new-password-confirm', 'password_confirm', 'new-password') ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Save new password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
