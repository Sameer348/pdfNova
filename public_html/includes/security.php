<?php
/**
 * Security helpers: output escaping, HTTP security headers, client IP,
 * random tokens, HMAC-signed payloads and MySQL-backed rate limiting.
 */

declare(strict_types=1);

/**
 * Escape a value for safe HTML output (text and attribute context).
 * Use for EVERY piece of dynamic data printed into HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Security headers sent with every web response.
 * Static headers are also set in .htaccess; CSP lives here so it can evolve
 * with the code. No inline scripts or style attributes are used anywhere,
 * which keeps the CSP strict.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data: blob:",
        "font-src 'self'",
        "connect-src 'self'",
        "worker-src 'self' blob:",   // pdf.js renders in a web worker
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https() && config('security.hsts', true)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

/**
 * Client IP. Proxy headers are ignored unless explicitly trusted in config,
 * because they are trivially spoofable.
 */
function client_ip(): string
{
    if (config('security.trust_proxy_headers', false)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', (string) $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** IP as packed binary for VARBINARY(16) columns. */
function client_ip_bin(): ?string
{
    $packed = @inet_pton(client_ip());
    return $packed === false ? null : $packed;
}

/** Cryptographically secure random hex token (default 32 hex chars). */
function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function app_key(): string
{
    $key = (string) config('app.key', '');
    if ($key === '') {
        throw new RuntimeException('app.key is not configured');
    }
    return $key;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string|false
{
    return base64_decode(strtr($data, '-_', '+/'), true);
}

/**
 * Create a tamper-proof, expiring token: base64url(json) . '.' . hmac.
 * Used for download links. The payload is readable but cannot be modified.
 */
function sign_payload(array $payload, int $ttlSeconds): string
{
    $payload['exp'] = time() + $ttlSeconds;
    $body = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig  = base64url_encode(hash_hmac('sha256', $body, app_key(), true));
    return $body . '.' . $sig;
}

/** Verify a token from sign_payload(). Returns the payload or null. */
function verify_payload(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $body, app_key(), true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $json = base64url_decode($body);
    $payload = $json === false ? null : json_decode($json, true);
    if (!is_array($payload) || !isset($payload['exp']) || (int) $payload['exp'] < time()) {
        return null;
    }
    return $payload;
}

/**
 * Fixed-window rate limiter stored in MySQL (no Redis on shared hosting).
 *
 * Returns true if the action is allowed, false if the limit is exceeded.
 * Each call counts as one hit.
 *
 * Note: MySQL/MariaDB evaluate ON DUPLICATE KEY UPDATE assignments left to
 * right, so `hits` and `window_start` see the OLD expires_at before it is reset.
 */
function rate_limit(string $action, string $identifier, int $maxHits, int $windowSeconds): bool
{
    $key = hash('sha256', $action . '|' . $identifier);
    db_exec(
        'INSERT INTO rate_limits (rate_key, action, hits, window_start, expires_at)
         VALUES (?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? SECOND)
         ON DUPLICATE KEY UPDATE
            hits         = IF(expires_at <= UTC_TIMESTAMP(), 1, hits + 1),
            window_start = IF(expires_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP(), window_start),
            expires_at   = IF(expires_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? SECOND, expires_at)',
        [$key, $action, $windowSeconds, $windowSeconds]
    );
    $hits = (int) db_value('SELECT hits FROM rate_limits WHERE rate_key = ?', [$key]);
    return $hits <= $maxHits;
}
