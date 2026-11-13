<?php
/**
 * Admin-only helpers (loaded by admin/index.php after require_role('admin')).
 */

declare(strict_types=1);

/** Record an admin action in admin_audit_log (never include secrets in $details). */
function admin_audit(string $action, ?string $targetType = null, int|string|null $targetId = null, array $details = []): void
{
    db_exec(
        'INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        [current_user()['id'] ?? null, $action, $targetType, $targetId !== null ? (string) $targetId : null,
         $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, client_ip_bin()]
    );
}

/** Admin URL: admin_url('users') → /admin/users */
function admin_url(string $path = '', array $query = []): string
{
    $q = array_filter($query, static fn($v) => $v !== null && $v !== '');
    return url('admin/' . ltrim($path, '/')) . ($q ? '?' . http_build_query($q) : '');
}

/** Escape a user-supplied search term for LIKE (the term itself is still a bound parameter). */
function like_term(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/** Current page number from ?page= (1-based). */
function admin_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

/** Pager that keeps the current filters. */
function admin_pager(int $page, int $total, int $perPage, string $path, array $query = []): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pager" aria-label="Pagination">';
    if ($page > 1) {
        $html .= '<a class="btn btn-secondary btn-sm" href="' . e(admin_url($path, $query + ['page' => $page - 1])) . '">Previous</a>';
    }
    $html .= '<span class="small muted">Page ' . $page . ' of ' . $pages . ' · ' . number_format($total) . ' total</span>';
    if ($page < $pages) {
        $html .= '<a class="btn btn-secondary btn-sm" href="' . e(admin_url($path, $query + ['page' => $page + 1])) . '">Next</a>';
    }
    return $html . '</nav>';
}

/** Total bytes and file count on disk in a storage area. */
function storage_usage(string $area): array
{
    $bytes = 0;
    $count = 0;
    $dir = storage_path($area);
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && !str_starts_with($f->getFilename(), '.')) {
                $bytes += $f->getSize();
                $count++;
            }
        }
    }
    return ['bytes' => $bytes, 'files' => $count];
}

/** Last N "error"/"critical" entries from storage/logs/app.log (reads only the file's tail). */
function recent_log_errors(int $limit = 10): array
{
    $file = storage_path('logs') . '/app.log';
    if (!is_file($file)) {
        return [];
    }
    $size = filesize($file);
    $fh = fopen($file, 'rb');
    fseek($fh, max(0, $size - 200_000));
    $chunk = (string) fread($fh, 200_000);
    fclose($fh);
    $out = [];
    foreach (array_reverse(explode("\n", trim($chunk))) as $line) {
        $row = json_decode($line, true);
        if (is_array($row) && in_array($row['level'] ?? '', ['error', 'critical'], true)) {
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    return $out;
}

/** Number of active admins (used to prevent locking everyone out). */
function admin_count(): int
{
    return (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
}

/** Plan currently in effect for any user id (mirrors current_plan()). */
function plan_for_user(int $userId): array
{
    return db_one(
        "SELECT p.* FROM subscriptions s JOIN plans p ON p.id = s.plan_id
          WHERE s.user_id = ? AND s.status IN ('active','trialing')
            AND (s.current_period_end IS NULL OR s.current_period_end > UTC_TIMESTAMP())
          ORDER BY p.sort_order DESC LIMIT 1",
        [$userId]
    ) ?? db_one("SELECT * FROM plans WHERE slug = 'free' LIMIT 1");
}

/** Small "label: value" select helper for filters. */
function admin_select(string $name, array $options, string $current, string $label): string
{
    $html = '<label class="visually-hidden" for="f-' . e($name) . '">' . e($label) . '</label>'
        . '<select class="select select-sm" id="f-' . e($name) . '" name="' . e($name) . '">';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . e((string) $value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    return $html . '</select>';
}
