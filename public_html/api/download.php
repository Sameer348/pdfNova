<?php
/**
 * GET /api/download.php?t=<signed token> — download a processed file.
 *
 * Security:
 *  - The token is HMAC-signed and expires with the file (cannot be forged or edited).
 *  - Ownership is re-checked: only the visitor who created the file can download it.
 *  - The file is streamed from private storage by its random stored name; the
 *    display name is sent only in Content-Disposition (RFC 6266 / 5987 encoded).
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('GET');
api_rate_limit('download', 120, 600);

$payload = verify_payload((string) ($_GET['t'] ?? ''));
$file = $payload !== null ? find_owned_file((string) ($payload['f'] ?? ''), 'result') : null;

if ($file === null) {
    // Same answer for invalid, expired, deleted or someone else's file.
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    render_error_page(
        404,
        'Download link expired',
        'This download link is no longer valid. Files are deleted automatically for your privacy — please process your file again.'
    );
    exit;
}

$path = stored_file_path($file['storage_area'], $file['stored_name']);
$name = (string) $file['original_name'];
$ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'download';

log_usage('download', null, null, (int) $file['size_bytes']);

// Stream the file without loading it into memory.
while (ob_get_level() > 0) {
    ob_end_clean();
}
header_remove('Content-Security-Policy');
header('Content-Type: ' . canonical_mime((string) $file['extension']));
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Security-Policy: default-src \'none\'; sandbox');

$handle = fopen($path, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 1024 * 256);
        flush();
    }
    fclose($handle);
}
exit;
