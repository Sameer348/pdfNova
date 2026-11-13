<?php
/**
 * Data for the signed-in user's dashboard pages. Every query is scoped to
 * the user id passed in (always the current user — never taken from input).
 */

declare(strict_types=1);

/** Headline numbers for the dashboard cards. */
function account_stats(int $userId): array
{
    $row = db_one(
        "SELECT
            SUM(status = 'completed') AS total,
            SUM(status = 'completed' AND created_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')) AS month,
            SUM(status <> 'failed' AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY) AS last24h
         FROM processing_jobs WHERE user_id = ?",
        [$userId]
    );
    $storage = db_one(
        "SELECT COALESCE(SUM(size_bytes), 0) AS bytes, COUNT(*) AS files
           FROM files WHERE user_id = ? AND status = 'active' AND expires_at > UTC_TIMESTAMP()",
        [$userId]
    );
    return [
        'total'         => (int) ($row['total'] ?? 0),
        'month'         => (int) ($row['month'] ?? 0),
        'last24h'       => (int) ($row['last24h'] ?? 0),
        'storage_bytes' => (int) $storage['bytes'],
        'storage_files' => (int) $storage['files'],
    ];
}

/**
 * Jobs with their result file and input names, newest first.
 * Only shows history within the plan's history window.
 */
function account_jobs(int $userId, int $historyDays, int $limit, int $offset = 0): array
{
    return db_all(
        "SELECT j.public_id, j.tool, j.status, j.error_message, j.created_at, j.input_count,
                r.public_id AS result_id, r.original_name AS result_name, r.size_bytes AS result_size,
                r.status AS result_status, r.expires_at AS result_expires,
                (SELECT GROUP_CONCAT(f.original_name ORDER BY jf.position SEPARATOR ', ')
                   FROM job_files jf JOIN files f ON f.id = jf.file_id WHERE jf.job_id = j.id) AS input_names
           FROM processing_jobs j
      LEFT JOIN files r ON r.id = j.result_file_id
          WHERE j.user_id = ? AND j.created_at > UTC_TIMESTAMP() - INTERVAL ? DAY
       ORDER BY j.created_at DESC, j.id DESC
          LIMIT " . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset),
        [$userId, $historyDays]
    );
}

function account_job_count(int $userId, int $historyDays): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM processing_jobs WHERE user_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? DAY',
        [$userId, $historyDays]
    );
}

/** A download link for a job row, or null when the result has expired/been deleted. */
function job_download_url(array $job): ?string
{
    if (empty($job['result_id']) || $job['result_status'] !== 'active' || strtotime($job['result_expires'] . ' UTC') <= time()) {
        return null;
    }
    $file = db_one("SELECT * FROM files WHERE public_id = ? AND status = 'active'", [$job['result_id']]);
    return $file ? download_url($file) : null;
}

function account_activity(int $userId, int $limit, int $offset = 0): array
{
    return db_all(
        'SELECT action, tool, bytes, created_at FROM usage_logs WHERE user_id = ?
          ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset),
        [$userId]
    );
}

function account_activity_count(int $userId): int
{
    return (int) db_value('SELECT COUNT(*) FROM usage_logs WHERE user_id = ?', [$userId]);
}

/** Active login sessions (devices). */
function account_sessions(int $userId): array
{
    return db_all(
        'SELECT id, ip_address, user_agent, created_at, last_activity_at FROM sessions
          WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_activity_at DESC',
        [$userId]
    );
}

/** "Chrome on macOS" from a user-agent string (best effort, display only). */
function describe_device(?string $ua): string
{
    $ua = (string) $ua;
    $browser = match (true) {
        str_contains($ua, 'Edg/')     => 'Edge',
        str_contains($ua, 'OPR/')     => 'Opera',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Chrome/')  => 'Chrome',
        str_contains($ua, 'Safari/')  => 'Safari',
        str_contains($ua, 'curl/')    => 'curl',
        default                       => 'Browser',
    };
    $os = match (true) {
        str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Android')   => 'Android',
        str_contains($ua, 'Mac OS X')  => 'macOS',
        str_contains($ua, 'Windows')   => 'Windows',
        str_contains($ua, 'Linux')     => 'Linux',
        default                        => 'unknown device',
    };
    return "$browser on $os";
}

/** IP for display with the last part hidden (privacy on shared screens). */
function masked_ip(?string $packed): string
{
    $ip = $packed ? @inet_ntop($packed) : false;
    if (!$ip) {
        return '—';
    }
    if (str_contains($ip, ':')) {
        return implode(':', array_slice(explode(':', $ip), 0, 3)) . ':…';
    }
    $parts = explode('.', $ip);
    return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.•••';
}

function tool_name(string $slug): string
{
    return tool($slug)['name'] ?? ucwords(str_replace('-', ' ', $slug));
}

function status_badge(string $status): string
{
    $map = ['completed' => ['success', 'Completed'], 'failed' => ['danger', 'Failed'], 'processing' => ['warning', 'Processing'],
            'queued' => ['warning', 'Queued'], 'expired' => ['', 'Expired']];
    [$tone, $label] = $map[$status] ?? ['', ucfirst($status)];
    return '<span class="badge' . ($tone ? ' badge-' . $tone : '') . '">' . e($label) . '</span>';
}

/** Simple pager links: ?page=N. */
function pager(int $page, int $total, int $perPage, string $path): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pager" aria-label="Pagination">';
    if ($page > 1) {
        $html .= '<a class="btn btn-secondary btn-sm" href="' . e(url($path) . '?page=' . ($page - 1)) . '">Previous</a>';
    }
    $html .= '<span class="small muted">Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        $html .= '<a class="btn btn-secondary btn-sm" href="' . e(url($path) . '?page=' . ($page + 1)) . '">Next</a>';
    }
    return $html . '</nav>';
}

/** Delete an account: remove its files immediately, then the user (cascades sessions etc.). */
function delete_account(int $userId): void
{
    foreach (db_all("SELECT id, storage_area, stored_name FROM files WHERE user_id = ? AND status = 'active'", [$userId]) as $file) {
        delete_stored_file($file);
    }
    db_exec('DELETE FROM users WHERE id = ?', [$userId]);
    app_log('info', 'Account deleted', ['user_id' => $userId]);
}
