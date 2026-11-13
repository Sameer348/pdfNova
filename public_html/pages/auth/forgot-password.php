<?php
/**
 * /forgot-password — request a reset link. The response is identical whether
 * or not the email has an account (prevents account discovery).
 */

declare(strict_types=1);

$sent = false;
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $email = (string) ($_POST['email'] ?? '');
    if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!rate_limit('reset-ip', client_ip(), 5, 3600)) {
        $error = 'Too many requests from your network. Please try again in an hour.';
    } else {
        request_password_reset($email);
        $sent = true;
    }
}

set_page([
    'title'       => 'Reset your password | PDFNova',
    'description' => 'Get a link to reset your PDFNova password.',
    'robots'      => 'noindex,follow',
    'body_class'  => 'page-auth',
]);
?>
<section class="auth-page">
    <div class="auth-card">
        <?php if ($sent): ?>
            <div class="auth-icon"><?= icon('mail', 'icon icon-xl') ?></div>
            <h1 class="h2">Check your email</h1>
            <p class="muted">If an account exists for that address, we've sent a link to choose a new password. It works once and expires in <?= (int) RESET_TTL_MINUTES ?> minutes.</p>
            <p class="small muted">Nothing arrived after a few minutes? Check your spam folder, or try again.</p>
            <p class="auth-switch"><a href="<?= e(url('login')) ?>">Back to log in</a></p>
        <?php else: ?>
            <h1 class="h2">Forgot your password?</h1>
            <p class="muted">Enter your account email and we'll send you a link to choose a new one.</p>
            <form class="form" method="post" action="<?= e(url('forgot-password')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field<?= $error ? ' has-error' : '' ?>">
                    <label class="label" for="forgot-email">Email</label>
                    <input class="input" id="forgot-email" name="email" type="email" autocomplete="email" required<?= $error ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                    <?php if ($error): ?><p class="field-error" id="err-email"><?= icon('alert') ?> <?= e($error) ?></p><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
            </form>
            <p class="auth-switch">Remembered it? <a href="<?= e(url('login')) ?>">Log in</a></p>
        <?php endif; ?>
    </div>
</section>
