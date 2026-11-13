<?php
/**
 * /login — email + password sign-in.
 */

declare(strict_types=1);

if (is_logged_in()) {
    redirect(auth_home());
}

$error = null;
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $email = (string) ($_POST['email'] ?? '');
    $result = attempt_login($email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        flash('success', 'Welcome back!');
        redirect(intended_path(auth_home()));
    }
    $error = $result['error'];
}

set_page([
    'title'       => 'Log in | PDFNova',
    'description' => 'Log in to your PDFNova account.',
    'robots'      => 'noindex,follow',
    'body_class'  => 'page-auth',
]);
?>
<section class="auth-page">
    <div class="auth-card">
        <h1 class="h2">Welcome back</h1>
        <p class="muted">Log in to see your recent files and use your plan's higher limits.</p>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= icon('alert') ?><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <form class="form" method="post" action="<?= e(url('login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label class="label" for="login-email">Email</label>
                <input class="input" id="login-email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>"<?= $error ? ' aria-invalid="true"' : '' ?>>
            </div>
            <div class="field">
                <div class="label-row">
                    <label class="label" for="login-password">Password</label>
                    <a class="small" href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
                </div>
                <?= password_input('login-password', 'password', 'current-password') ?>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Log in</button>
        </form>
        <p class="auth-switch">New to PDFNova? <a href="<?= e(url('register')) ?>">Create a free account</a></p>
    </div>
</section>
