<?php
/**
 * /register — create a free account.
 */

declare(strict_types=1);

if (is_logged_in()) {
    redirect(auth_home());
}

$errors = [];
$old = ['name' => '', 'email' => ''];
if (setting('registration_enabled', '1') !== '1') {
    $errors['form'] = 'New registrations are paused at the moment. Please try again later.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $old = ['name' => (string) ($_POST['name'] ?? ''), 'email' => (string) ($_POST['email'] ?? '')];
    if (!rate_limit('register-ip', client_ip(), 5, 3600)) {
        $errors['form'] = 'Too many sign-ups from your network. Please try again in an hour.';
    } else {
        $result = register_user($old['name'], $old['email'], (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirm'] ?? ''), isset($_POST['terms']));
        if ($result['ok']) {
            flash('success', 'Your account is ready. Welcome to PDFNova!');
            redirect(intended_path(auth_home()));
        }
        $errors = $result['errors'];
    }
}

set_page([
    'title'       => 'Create a free account | PDFNova',
    'description' => 'Create a free PDFNova account for higher limits and a history of your files.',
    'robots'      => 'noindex,follow',
    'body_class'  => 'page-auth',
]);
?>
<section class="auth-page">
    <div class="auth-card">
        <h1 class="h2">Create your free account</h1>
        <p class="muted">Higher limits than guests and a history of your recent files. No card needed.</p>

        <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= icon('alert') ?><p><?= e($errors['form']) ?></p></div>
        <?php endif; ?>

        <form class="form" method="post" action="<?= e(url('register')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label class="label" for="reg-name">Name</label>
                <input class="input" id="reg-name" name="name" type="text" autocomplete="name" maxlength="100" required value="<?= e($old['name']) ?>"<?= field_aria($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label class="label" for="reg-email">Email</label>
                <input class="input" id="reg-email" name="email" type="email" autocomplete="email" maxlength="191" required value="<?= e($old['email']) ?>"<?= field_aria($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                <label class="label" for="reg-password">Password</label>
                <?= password_input('reg-password', 'password', 'new-password', field_aria($errors, 'password')) ?>
                <?= field_error($errors, 'password') ?: '<p class="hint">At least ' . PASSWORD_MIN . ' characters.</p>' ?>
            </div>
            <div class="field">
                <label class="label" for="reg-password-confirm">Confirm password</label>
                <?= password_input('reg-password-confirm', 'password_confirm', 'new-password') ?>
            </div>
            <div class="field<?= isset($errors['terms']) ? ' has-error' : '' ?>">
                <label class="check"><input type="checkbox" name="terms" value="1" required<?= field_aria($errors, 'terms') ?>>
                    <span>I agree to the <a href="<?= e(url('terms')) ?>">Terms of Service</a> and <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>.</span></label>
                <?= field_error($errors, 'terms') ?>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?= e(url('login')) ?>">Log in</a></p>
    </div>
</section>
