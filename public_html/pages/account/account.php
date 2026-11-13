<?php
/**
 * /account — profile, password and account deletion.
 * Every change requires the current password (protects an unattended,
 * logged-in browser).
 */

declare(strict_types=1);

$user = require_login();
$errors = ['profile' => [], 'password' => [], 'delete' => []];
$values = ['name' => $user['name'], 'email' => $user['email']];

/** Check the current password of the signed-in user. */
$checkPassword = static function (string $password) use ($user): bool {
    if (!rate_limit('reauth', (string) $user['id'], 10, 900)) {
        return false;
    }
    $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    return $password !== '' && password_verify($password, $hash);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $values = ['name' => trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')) ?? ''),
                   'email' => mb_strtolower(trim((string) ($_POST['email'] ?? '')))];
        if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100 || preg_match('/[<>\x00-\x1F]/u', $values['name'])) {
            $errors['profile']['name'] = 'Enter your name (2–100 characters, no < or >).';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['profile']['email'] = 'Enter a valid email address.';
        }
        $emailChanged = $values['email'] !== $user['email'];
        if ($emailChanged && !$errors['profile']) {
            if (!$checkPassword((string) ($_POST['current_password'] ?? ''))) {
                $errors['profile']['current_password'] = 'Enter your current password to change your email.';
            } elseif (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$values['email'], $user['id']]) !== null) {
                $errors['profile']['email'] = 'Another account already uses this email.';
            }
        }
        if (!$errors['profile']) {
            db_exec('UPDATE users SET name = ?, email = ? WHERE id = ?', [$values['name'], $values['email'], $user['id']]);
            flash('success', 'Your profile has been updated.');
            redirect('/account');
        }
    }

    if ($action === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        if (!$checkPassword((string) ($_POST['current_password'] ?? ''))) {
            $errors['password']['current_password'] = 'Your current password is incorrect.';
        } elseif (($problem = password_problem($new, (string) ($_POST['new_password_confirm'] ?? ''), $user['email'])) !== null) {
            $errors['password']['new_password'] = $problem;
        } else {
            db_exec('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP() WHERE id = ?',
                [password_hash($new, password_algorithm()), $user['id']]);
            $others = revoke_user_sessions((int) $user['id'], (int) $user['session_id']);
            flash('success', 'Your password has been changed.' . ($others ? " You were signed out on $others other " . ($others === 1 ? 'device.' : 'devices.') : ''));
            redirect('/account');
        }
    }

    if ($action === 'delete') {
        if (($_POST['confirm_text'] ?? '') !== 'DELETE') {
            $errors['delete']['confirm_text'] = 'Type DELETE to confirm.';
        } elseif (!$checkPassword((string) ($_POST['current_password'] ?? ''))) {
            $errors['delete']['current_password'] = 'Your password is incorrect.';
        } else {
            delete_account((int) $user['id']);
            $_SESSION = [];
            rotate_session();
            flash('success', 'Your account and files have been deleted. Thank you for using PDFNova.');
            redirect('/');
        }
    }
}

set_page(['title' => 'Account | PDFNova', 'robots' => 'noindex,nofollow', 'body_class' => 'page-account']);
?>
<section class="account">
    <div class="container account-grid">
        <?php partial('components/account-nav', ['active' => 'account', 'user' => $user]); ?>
        <div class="account-main">
            <div class="account-head"><div><h1>Account</h1><p class="muted">Member since <?= e(format_date($user['created_at'], 'F Y')) ?></p></div></div>

            <form class="panel form" method="post" action="<?= e(url('account')) ?>" novalidate>
                <h2 class="h4">Profile</h2>
                <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                <div class="form-row form-row-2">
                    <div class="field<?= isset($errors['profile']['name']) ? ' has-error' : '' ?>">
                        <label class="label" for="acc-name">Name</label>
                        <input class="input" id="acc-name" name="name" type="text" maxlength="100" autocomplete="name" value="<?= e($values['name']) ?>"<?= field_aria($errors['profile'], 'name') ?>>
                        <?= field_error($errors['profile'], 'name') ?>
                    </div>
                    <div class="field<?= isset($errors['profile']['email']) ? ' has-error' : '' ?>">
                        <label class="label" for="acc-email">Email</label>
                        <input class="input" id="acc-email" name="email" type="email" maxlength="191" autocomplete="email" value="<?= e($values['email']) ?>"<?= field_aria($errors['profile'], 'email') ?>>
                        <?= field_error($errors['profile'], 'email') ?>
                    </div>
                </div>
                <div class="field<?= isset($errors['profile']['current_password']) ? ' has-error' : '' ?>">
                    <label class="label" for="acc-current">Current password <span class="hint">(only needed to change your email)</span></label>
                    <?= password_input('acc-current', 'current_password', 'current-password', field_aria($errors['profile'], 'current_password')) ?>
                    <?= field_error($errors['profile'], 'current_password') ?>
                </div>
                <div><button class="btn btn-primary" type="submit">Save profile</button></div>
            </form>

            <form class="panel form" method="post" action="<?= e(url('account')) ?>" novalidate>
                <h2 class="h4">Change password</h2>
                <?= csrf_field() ?><input type="hidden" name="action" value="password">
                <div class="field<?= isset($errors['password']['current_password']) ? ' has-error' : '' ?>">
                    <label class="label" for="pw-current">Current password</label>
                    <?= password_input('pw-current', 'current_password', 'current-password', field_aria($errors['password'], 'current_password')) ?>
                    <?= field_error($errors['password'], 'current_password') ?>
                </div>
                <div class="form-row form-row-2">
                    <div class="field<?= isset($errors['password']['new_password']) ? ' has-error' : '' ?>">
                        <label class="label" for="pw-new">New password</label>
                        <?= password_input('pw-new', 'new_password', 'new-password', field_aria($errors['password'], 'new_password')) ?>
                        <?= field_error($errors['password'], 'new_password') ?: '<p class="hint">At least ' . PASSWORD_MIN . ' characters.</p>' ?>
                    </div>
                    <div class="field">
                        <label class="label" for="pw-confirm">Confirm new password</label>
                        <?= password_input('pw-confirm', 'new_password_confirm', 'new-password') ?>
                    </div>
                </div>
                <p class="hint">Other devices will be signed out.</p>
                <div><button class="btn btn-primary" type="submit">Change password</button></div>
            </form>

            <form class="panel panel-danger form" method="post" action="<?= e(url('account')) ?>" novalidate>
                <h2 class="h4">Delete account</h2>
                <p class="small muted">This permanently deletes your account, history and any files still stored. It can't be undone.</p>
                <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <div class="form-row form-row-2">
                    <div class="field<?= isset($errors['delete']['confirm_text']) ? ' has-error' : '' ?>">
                        <label class="label" for="del-confirm">Type DELETE to confirm</label>
                        <input class="input" id="del-confirm" name="confirm_text" type="text" autocomplete="off"<?= field_aria($errors['delete'], 'confirm_text') ?>>
                        <?= field_error($errors['delete'], 'confirm_text') ?>
                    </div>
                    <div class="field<?= isset($errors['delete']['current_password']) ? ' has-error' : '' ?>">
                        <label class="label" for="del-password">Password</label>
                        <?= password_input('del-password', 'current_password', 'current-password', field_aria($errors['delete'], 'current_password')) ?>
                        <?= field_error($errors['delete'], 'current_password') ?>
                    </div>
                </div>
                <div><button class="btn btn-danger" type="submit">Delete my account</button></div>
            </form>
        </div>
    </div>
</section>
