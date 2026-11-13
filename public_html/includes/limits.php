<?php
/**
 * Site settings (settings table) and per-visitor usage limits.
 *
 * Limits come from three places; the SMALLEST wins:
 *   1. The visitor's plan (guest settings, or the user's plan row)
 *   2. config('uploads.hard_max_mb') — server-wide cap
 *   3. PHP's own upload_max_filesize / post_max_size
 */

declare(strict_types=1);

/** Read a value from the settings table (cached per request). */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            app_log('warning', 'Settings unavailable, using defaults', ['error' => $e->getMessage()]);
        }
    }
    return $cache[$key] ?? $default;
}

/** Convert php.ini shorthand ("8M", "1G") to bytes. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    return (int) match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

/** Largest single upload PHP itself will accept. */
function php_upload_limit_bytes(): int
{
    return min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
}

/** The plan row that applies to the current visitor (null for guests). */
function current_plan(): ?array
{
    static $plan = false;
    if ($plan !== false) {
        return $plan;
    }
    $user = current_user();
    if ($user === null) {
        return $plan = null;
    }
    $plan = db_one(
        "SELECT p.* FROM subscriptions s JOIN plans p ON p.id = s.plan_id
          WHERE s.user_id = ? AND s.status IN ('active','trialing')
            AND (s.current_period_end IS NULL OR s.current_period_end > UTC_TIMESTAMP())
          ORDER BY p.sort_order DESC LIMIT 1",
        [$user['id']]
    ) ?? db_one("SELECT * FROM plans WHERE slug = 'free' LIMIT 1");
    return $plan;
}

/**
 * Effective limits for the current visitor.
 * @return array{plan:string, max_file_bytes:int, max_files:int, daily_jobs:?int, is_guest:bool}
 */
function visitor_limits(): array
{
    static $limits = null;
    if ($limits !== null) {
        return $limits;
    }
    $plan = current_plan();
    $hardCap = (int) config('uploads.hard_max_mb', 200) * 1024 * 1024;

    if ($plan === null) {
        $free = db_one("SELECT max_files_per_job FROM plans WHERE slug = 'free' LIMIT 1");
        $planBytes = (int) setting('guest_max_file_size_mb', '10') * 1024 * 1024;
        $maxFiles  = (int) ($free['max_files_per_job'] ?? 5);
        $daily     = (int) setting('guest_daily_jobs_limit', '10');
        $name      = 'guest';
    } else {
        $planBytes = (int) $plan['max_file_size_mb'] * 1024 * 1024;
        $maxFiles  = (int) $plan['max_files_per_job'];
        $daily     = $plan['daily_jobs_limit'] === null ? null : (int) $plan['daily_jobs_limit'];
        $name      = (string) $plan['slug'];
    }

    return $limits = [
        'plan'           => $name,
        'is_guest'       => $plan === null,
        'max_file_bytes' => max(1, min($planBytes, $hardCap, php_upload_limit_bytes())),
        'max_files'      => max(1, $maxFiles),
        'daily_jobs'     => $daily,
    ];
}

/** Processing jobs (not counting failed ones) the current visitor ran in the last 24 hours. */
function jobs_last_24h(): int
{
    $user = current_user();
    return (int) db_value(
        "SELECT COUNT(*) FROM processing_jobs
          WHERE created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY AND status <> 'failed'
            AND " . ($user ? 'user_id = ?' : 'user_id IS NULL AND session_hash = ?'),
        [$user ? $user['id'] : guest_owner_hash()]
    );
}
