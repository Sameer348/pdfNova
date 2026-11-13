<?php
/**
 * Temporary file storage.
 *
 *  - Bytes live in storage/{uploads,processed,temp}/<32 random hex chars>
 *    (no extension, never the original name).
 *  - Metadata lives in the `files` table.
 *  - Every lookup checks ownership: the logged-in user OR the guest owner key
 *    of this browser session.
 */

declare(strict_types=1);

const STORAGE_AREAS = ['uploads', 'processed', 'temp'];

/** Absolute path of a stored file. stored_name is validated to prevent path tricks. */
function stored_file_path(string $area, string $storedName): string
{
    if (!in_array($area, STORAGE_AREAS, true) || !preg_match('/^[a-f0-9]{32}$/', $storedName)) {
        throw new InvalidArgumentException('Invalid stored file reference');
    }
    return storage_path($area) . '/' . $storedName;
}

/** Ensure a storage folder exists and is writable; returns its path. */
function storage_dir(string $area): string
{
    $dir = storage_path($area);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Storage folder missing: $area");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("Storage folder not writable: $area");
    }
    return $dir;
}

/** Owner columns for new rows: [user_id|null, session_hash]. */
function current_owner(): array
{
    $user = current_user();
    return [$user ? (int) $user['id'] : null, guest_owner_hash()];
}

/**
 * Move an uploaded file into storage and record it.
 * @param array $meta Output of validate_upload()
 */
function store_upload(string $tmpPath, array $meta): array
{
    storage_dir('uploads');
    $storedName = random_token(16);
    $target = stored_file_path('uploads', $storedName);
    if (!move_uploaded_file($tmpPath, $target)) {
        throw new RuntimeException('Could not move uploaded file');
    }
    @chmod($target, 0640);

    return record_file($storedName, 'upload', 'uploads', $meta, (int) config('retention.upload_minutes', 60));
}

/**
 * Register a file the server produced (a tool result) that already exists in
 * storage/processed/<storedName>.
 */
function store_result(string $storedName, string $displayName, string $ext, ?int $pages = null): array
{
    $path = stored_file_path('processed', $storedName);
    @chmod($path, 0640);
    return record_file($storedName, 'result', 'processed', [
        'name'  => clean_display_name($displayName, $ext),
        'ext'   => $ext,
        'mime'  => canonical_mime($ext),
        'size'  => (int) filesize($path),
        'pages' => $pages,
    ], (int) config('retention.result_minutes', 120));
}

function record_file(string $storedName, string $kind, string $area, array $meta, int $ttlMinutes): array
{
    [$userId, $sessionHash] = current_owner();
    $publicId = random_token(16);
    $id = db_insert(
        'INSERT INTO files (public_id, user_id, session_hash, kind, storage_area, stored_name, original_name,
                            mime_type, extension, size_bytes, sha256, page_count, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
        [
            $publicId, $userId, $sessionHash, $kind, $area, $storedName, $meta['name'],
            $meta['mime'], $meta['ext'], $meta['size'],
            hash_file('sha256', stored_file_path($area, $storedName)),
            $meta['pages'] ?? null, $ttlMinutes,
        ]
    );
    return db_one('SELECT * FROM files WHERE id = ?', [$id]);
}

/** Find an active, unexpired file owned by the current visitor, or null. */
function find_owned_file(string $publicId, ?string $kind = null): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
        return null;
    }
    [$userId, $sessionHash] = current_owner();
    $row = db_one(
        "SELECT * FROM files
          WHERE public_id = ? AND status = 'active' AND expires_at > UTC_TIMESTAMP()
            AND ((user_id IS NOT NULL AND user_id = ?) OR session_hash = ?)
          LIMIT 1",
        [$publicId, $userId ?? 0, $sessionHash]
    );
    if ($row === null || ($kind !== null && $row['kind'] !== $kind)) {
        return null;
    }
    if (!is_file(stored_file_path($row['storage_area'], $row['stored_name']))) {
        mark_file_deleted((int) $row['id']);
        return null;
    }
    return $row;
}

/** Delete a file's bytes and mark the row deleted. Safe to call twice. */
function delete_stored_file(array $file): void
{
    $path = stored_file_path($file['storage_area'], $file['stored_name']);
    if (is_file($path)) {
        @unlink($path);
    }
    mark_file_deleted((int) $file['id']);
}

function mark_file_deleted(int $id): void
{
    db_exec("UPDATE files SET status = 'deleted', deleted_at = UTC_TIMESTAMP() WHERE id = ? AND status <> 'deleted'", [$id]);
}

/** Public JSON shape for a file (never exposes paths or stored names). */
function file_public(array $file): array
{
    return [
        'id'         => $file['public_id'],
        'name'       => $file['original_name'],
        'size'       => (int) $file['size_bytes'],
        'size_label' => format_bytes((int) $file['size_bytes']),
        'type'       => strtoupper($file['extension']),
        'pages'      => $file['page_count'] !== null ? (int) $file['page_count'] : null,
    ];
}

/** Count of active uploads owned by the current visitor (abuse limit). */
function active_upload_count(): int
{
    [$userId, $sessionHash] = current_owner();
    return (int) db_value(
        "SELECT COUNT(*) FROM files
          WHERE kind = 'upload' AND status = 'active' AND expires_at > UTC_TIMESTAMP()
            AND ((user_id IS NOT NULL AND user_id = ?) OR session_hash = ?)",
        [$userId ?? 0, $sessionHash]
    );
}

function log_usage(string $action, ?string $tool = null, ?int $jobId = null, int $bytes = 0): void
{
    [$userId, $sessionHash] = current_owner();
    db_exec(
        'INSERT INTO usage_logs (user_id, session_hash, tool, action, job_id, bytes, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $sessionHash, $tool, $action, $jobId, $bytes, client_ip_bin()]
    );
}

/**
 * Signed, expiring download link for a result file. The link only works for
 * the same visitor (download.php re-checks ownership), so a leaked link is
 * useless to anyone else.
 */
function download_url(array $file): string
{
    $ttl = max(60, strtotime($file['expires_at'] . ' UTC') - time());
    return url('api/download.php') . '?t=' . rawurlencode(sign_payload(['f' => $file['public_id']], $ttl));
}
