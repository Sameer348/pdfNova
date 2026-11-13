<?php
/**
 * Authentication & authorization.
 *
 * Security measures:
 *  - Passwords: password_hash() (Argon2id when available, else bcrypt) and
 *    password_verify(); hashes are upgraded on login (password_needs_rehash).
 *  - Session fixation: the session id is regenerated on login and logout.
 *  - Every login creates a row in `sessions`; the browser session holds a
 *    random login token whose SHA-256 must match an unrevoked row on every
 *    request. Logout, password reset and suspension revoke rows instantly.
 *  - Brute force: per-IP and per-email rate limits. The per-email limit
 *    applies whether or not the account exists, so it reveals nothing.
 *  - No user enumeration on login or "forgot password"; unknown emails still
 *    run a password_verify() so response times match.
 *  - Reset tokens: 256-bit random, only their SHA-256 is stored, single use,
 *    valid for 60 minutes; using one revokes all sessions of that user.
 */

declare(strict_types=1);

const PASSWORD_MIN = 8;
const PASSWORD_MAX_BYTES = 72;      // bcrypt limit; enforced for every algorithm for consistency
const RESET_TTL_MINUTES = 60;

/* ===================================================================== */
/* Current user                                                          */
/* ===================================================================== */

/** The logged-in user (without password hash) or null for guests. */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = (int) ($_SESSION['user_id'] ?? 0);
    $token = (string) ($_SESSION['login_token'] ?? '');
    if ($id <= 0 || $token === '') {
        return null;
    }

    $row = db_one(
        'SELECT u.id, u.public_id, u.name, u.email, u.role, u.status, u.email_verified_at, u.created_at,
                s.id AS session_id, s.last_activity_at
           FROM users u
           JOIN sessions s ON s.user_id = u.id
          WHERE u.id = ? AND s.session_hash = ? AND s.revoked_at IS NULL
          LIMIT 1',
        [$id, hash('sha256', $token)]
    );
    if ($row === null || $row['status'] !== 'active') {
        // Logged out elsewhere, password reset, suspended or deleted.
        unset($_SESSION['user_id'], $_SESSION['login_token']);
        rotate_session();
        return null;
    }

    // Record activity at most every 5 minutes (keeps the device list accurate cheaply).
    if (strtotime($row['last_activity_at'] . ' UTC') < time() - 300) {
        db_exec('UPDATE sessions SET last_activity_at = UTC_TIMESTAMP() WHERE id = ?', [$row['session_id']]);
    }
    $user = $row;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

/** Guard for pages that need a logged-in user. */
function require_login(): array
{
    $user = current_user();
    if ($user !== null) {
        return $user;
    }
    if (is_api_request()) {
        json_response(['success' => false, 'message' => 'Please log in to continue.'], 401);
    }
    $_SESSION['_intended'] = current_path();
    flash('info', 'Please log in to continue.');
    redirect('/login');
}

/** Guard for role-restricted areas (e.g. admin). 403 for logged-in non-members. */
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] === $role) {
        return $user;
    }
    if (is_api_request()) {
        json_response(['success' => false, 'message' => 'You do not have permission to do that.'], 403);
    }
    render_error_page(403, 'Access denied', 'You do not have permission to view this page.');
    exit;
}

/** Where to send someone after login: the page they wanted, if it is internal. */
function intended_path(string $default = '/dashboard'): string
{
    $path = (string) ($_SESSION['_intended'] ?? '');
    unset($_SESSION['_intended']);
    // Only local paths like "/merge-pdf" — never "//evil.com" or "https://...".
    return preg_match('#^/(?![/\\\\])[A-Za-z0-9/_\-]*$#', $path) ? $path : $default;
}

/* ===================================================================== */
/* Passwords                                                             */
/* ===================================================================== */

function password_algorithm(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

/** Error message for an unacceptable new password, or null. */
function password_problem(string $password, string $confirm, string $email = ''): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN) {
        return 'Use at least ' . PASSWORD_MIN . ' characters.';
    }
    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return 'Use at most ' . PASSWORD_MAX_BYTES . ' characters.';
    }
    if ($email !== '' && strcasecmp($password, $email) === 0) {
        return 'Your password cannot be the same as your email address.';
    }
    if (!hash_equals($password, $confirm)) {
        return 'The passwords do not match.';
    }
    return null;
}

/* ===================================================================== */
/* Registration                                                          */
/* ===================================================================== */

/**
 * Create an account and log it in.
 * @return array{ok:bool, errors:array<string,string>}
 */
function register_user(string $name, string $email, string $password, string $confirm, bool $acceptedTerms): array
{
    $errors = [];
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $email = mb_strtolower(trim($email));

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Enter your name (2–100 characters).';
    } elseif (preg_match('/[<>\x00-\x1F]/u', $name)) {
        $errors['name'] = 'Your name contains characters that are not allowed.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (($problem = password_problem($password, $confirm, $email)) !== null) {
        $errors['password'] = $problem;
    }
    if (!$acceptedTerms) {
        $errors['terms'] = 'Please accept the Terms of Service and Privacy Policy.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    if (db_value('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        return ['ok' => false, 'errors' => ['email' => 'An account with this email already exists. Log in, or reset your password if you forgot it.']];
    }

    try {
        $id = db_insert(
            'INSERT INTO users (public_id, name, email, password_hash, role, status, password_changed_at)
             VALUES (?, ?, ?, ?, \'user\', \'active\', UTC_TIMESTAMP())',
            [random_token(16), $name, $email, password_hash($password, password_algorithm())]
        );
    } catch (PDOException $e) {
        // Two sign-ups with the same email at the same moment: the unique index wins.
        if ((string) $e->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['email' => 'An account with this email already exists.']];
        }
        throw $e;
    }

    log_in_user((int) $id);
    app_log('info', 'User registered', ['user_id' => $id]);
    return ['ok' => true, 'errors' => []];
}

/* ===================================================================== */
/* Login / logout                                                        */
/* ===================================================================== */

/**
 * @return array{ok:bool, error:?string}
 */
function attempt_login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $generic = 'Incorrect email or password.';

    // Brute-force protection (same answer for existing and unknown accounts).
    if (!rate_limit('login-ip', client_ip(), 20, 600) || !rate_limit('login-email', $email, 6, 900)) {
        return ['ok' => false, 'error' => 'Too many login attempts. Please wait 15 minutes and try again.'];
    }
    if ($email === '' || $password === '' || strlen($password) > 1024) {
        return ['ok' => false, 'error' => $generic];
    }

    $user = db_one('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($user === null) {
        // Do the same amount of hashing work as a real check, so response time
        // doesn't reveal whether the email has an account.
        password_hash($password, password_algorithm());
        return ['ok' => false, 'error' => $generic];
    }
    $valid = password_verify($password, $user['password_hash']);

    if ($user === null || !$valid) {
        if ($user !== null) {
            db_exec('UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?', [$user['id']]);
        }
        return ['ok' => false, 'error' => $generic];
    }
    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This account is not active. Please contact support.'];
    }

    if (password_needs_rehash($user['password_hash'], password_algorithm())) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, password_algorithm()), $user['id']]);
    }
    log_in_user((int) $user['id']);
    return ['ok' => true, 'error' => null];
}

/** Start an authenticated session for a user id (after their credentials were checked). */
function log_in_user(int $userId): void
{
    $guestOwner = guest_owner_hash();
    rotate_session();                               // defeat session fixation
    $token = random_token(32);
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_token'] = $token;

    db_insert(
        'INSERT INTO sessions (user_id, session_hash, ip_address, user_agent) VALUES (?, ?, ?, ?)',
        [$userId, hash('sha256', $token), client_ip_bin(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
    );
    db_exec(
        'UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ?, failed_login_count = 0 WHERE id = ?',
        [client_ip_bin(), $userId]
    );

    // Files and jobs made as a guest in this browser now belong to the account.
    db_exec('UPDATE files SET user_id = ? WHERE user_id IS NULL AND session_hash = ?', [$userId, $guestOwner]);
    db_exec('UPDATE processing_jobs SET user_id = ? WHERE user_id IS NULL AND session_hash = ?', [$userId, $guestOwner]);
    log_usage_for($userId, 'login');
}

function log_out_user(): void
{
    $token = (string) ($_SESSION['login_token'] ?? '');
    if ($token !== '') {
        db_exec('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE session_hash = ? AND revoked_at IS NULL', [hash('sha256', $token)]);
    }
    // Keep nothing from the logged-in session (including the guest owner key).
    $_SESSION = [];
    rotate_session();
}

/** Revoke every active session of a user (password reset, suspension, "log out everywhere"). */
function revoke_user_sessions(int $userId, ?int $exceptSessionId = null): int
{
    return db_exec(
        'UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL AND id <> ?',
        [$userId, $exceptSessionId ?? 0]
    );
}

function log_usage_for(int $userId, string $action): void
{
    db_exec('INSERT INTO usage_logs (user_id, action, ip_address) VALUES (?, ?, ?)', [$userId, $action, client_ip_bin()]);
}

/* ===================================================================== */
/* Password reset                                                        */
/* ===================================================================== */

/**
 * Start a password reset. Always "succeeds" from the visitor's point of view
 * (no hint whether the email has an account).
 */
function request_password_reset(string $email): void
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    if (!rate_limit('reset-email', $email, 3, 3600)) {
        return; // silently ignore floods for one address
    }
    $user = db_one("SELECT id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email]);
    if ($user === null) {
        password_hash('timing-equaliser', PASSWORD_BCRYPT); // similar work either way
        return;
    }

    $token = random_token(32);
    db_exec('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$user['id']]); // one live link at a time
    db_insert(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, request_ip)
         VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE, ?)',
        [$user['id'], hash('sha256', $token), RESET_TTL_MINUTES, client_ip_bin()]
    );

    $link = absolute_url('reset-password') . '?token=' . $token;
    send_password_reset_email($user['email'], $user['name'], $link);
}

/** The reset row for a token if it is valid, unused and unexpired. */
function find_reset(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return db_one(
        "SELECT r.id, r.user_id, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
          WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > UTC_TIMESTAMP() AND u.status = 'active'
          LIMIT 1",
        [hash('sha256', $token)]
    );
}

/**
 * Set a new password with a reset token.
 * @return array{ok:bool, error:?string}
 */
function reset_password(string $token, string $password, string $confirm): array
{
    $reset = find_reset($token);
    if ($reset === null) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired. Please request a new one.'];
    }
    if (($problem = password_problem($password, $confirm, $reset['email'])) !== null) {
        return ['ok' => false, 'error' => $problem];
    }

    db_transaction(static function () use ($reset, $password): void {
        db_exec('UPDATE users SET password_hash = ?, password_changed_at = UTC_TIMESTAMP(), failed_login_count = 0 WHERE id = ?',
            [password_hash($password, password_algorithm()), $reset['user_id']]);
        db_exec('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$reset['user_id']]);
    });
    revoke_user_sessions((int) $reset['user_id']);  // sign out every device
    app_log('info', 'Password reset completed', ['user_id' => $reset['user_id']]);
    return ['ok' => true, 'error' => null];
}

/* ===================================================================== */
/* Page helpers                                                          */
/* ===================================================================== */

/** Where signed-in users land: the dashboard once it exists (Phase 13), otherwise home. */
function auth_home(): string
{
    return is_file(BASE_PATH . '/pages/account/dashboard.php') ? '/dashboard' : '/';
}

/** Inline error for a form field (escaped). */
function field_error(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . icon('alert') . ' ' . e($errors[$key]) . '</p>';
}

/** aria attributes for a field that may have an error. */
function field_aria(array $errors, string $key): string
{
    return empty($errors[$key]) ? '' : ' aria-invalid="true" aria-describedby="err-' . e($key) . '"';
}

/** Password input with a show/hide button. */
function password_input(string $id, string $name, string $autocomplete, string $extra = ''): string
{
    return '<div class="input-group"><input class="input" id="' . e($id) . '" name="' . e($name) . '" type="password" autocomplete="'
        . e($autocomplete) . '" maxlength="128" required' . $extra . '>'
        . '<button class="icon-btn" type="button" data-password-toggle="' . e($id) . '" aria-label="Show password" aria-pressed="false">'
        . icon('eye', 'icon icon-eye') . icon('eye-off', 'icon icon-eye-off') . '</button></div>';
}
