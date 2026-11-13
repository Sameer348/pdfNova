<?php
/**
 * Secure PHP session handling.
 *
 *  - Cookie: HttpOnly, Secure (on HTTPS), SameSite=Lax, path limited to the site.
 *  - Strict mode: PHP rejects session ids it did not create (anti-fixation).
 *  - Idle timeout and absolute lifetime, both enforced server-side.
 *  - The id is regenerated on login/logout (see auth.php) and periodically.
 */

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    // (sid_length / sid_bits_per_character are deprecated in PHP 8.4+; the
    //  defaults give a 128-bit random id, which is sufficient.)

    session_name((string) config('session.name', 'pdfnova_sid'));
    session_set_cookie_params([
        'lifetime' => 0,                       // browser-session cookie
        'path'     => (base_path_url() ?: '') . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now       = time();
    $idle      = (int) config('session.idle_timeout', 7200);
    $absolute  = (int) config('session.absolute_timeout', 86400);
    $created   = (int) ($_SESSION['_created'] ?? 0);
    $lastSeen  = (int) ($_SESSION['_last_seen'] ?? 0);

    $expired = $created > 0 && (($now - $lastSeen) > $idle || ($now - $created) > $absolute);
    if ($expired) {
        // Wipe everything (including any login) and start a fresh session.
        $_SESSION = [];
        session_regenerate_id(true);
        $created = 0;
    }

    if ($created === 0) {
        $_SESSION['_created'] = $now;
        $_SESSION['_rotated'] = $now;
    }
    $_SESSION['_last_seen'] = $now;

    // Rotate the id every 15 minutes to limit the value of a stolen cookie.
    if ($now - (int) ($_SESSION['_rotated'] ?? $now) > 900) {
        session_regenerate_id(true);
        $_SESSION['_rotated'] = $now;
    }
}

/** Regenerate the session id (call on every privilege change: login, logout, role change). */
function rotate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['_rotated'] = time();
    }
}

/**
 * Stable, non-reversible owner key for guest files and jobs.
 * We store a per-session random value rather than the session id itself, so the
 * owner key survives id rotation and never exposes the real session id.
 */
function guest_owner_hash(): string
{
    if (empty($_SESSION['_owner'])) {
        $_SESSION['_owner'] = random_token(32);
    }
    return hash('sha256', (string) $_SESSION['_owner']);
}
