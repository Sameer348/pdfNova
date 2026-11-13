<?php
/**
 * POST /api/upload.php — upload ONE file for a tool.
 *
 * Request (multipart/form-data): file, tool   + header X-CSRF-Token
 * Success: { success, message, file: { id, name, size, size_label, type, pages } }
 * Errors:  { success: false, message, code }   (HTTP 4xx/5xx)
 *
 * The browser uploads files one at a time so each gets its own progress bar,
 * error and retry. The returned id is later passed to the tool endpoint.
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

// When a request exceeds post_max_size, PHP silently drops the whole body.
// Detect that before anything else so the user gets a clear message.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_FILES) && empty($_POST) && $contentLength > 0) {
    api_error(
        'This file is larger than the server allows (' . format_bytes(php_upload_limit_bytes()) . ').',
        413,
        'file_too_large'
    );
}

api_require_method('POST');
api_rate_limit('upload', 120, 600);           // 120 uploads per 10 minutes per IP

$tool = api_tool((string) ($_POST['tool'] ?? ''));
if (!$tool['upload']) {
    api_error('This tool works in your browser and does not need an upload.', 400, 'no_upload');
}

$limits = visitor_limits();

if ($limits['is_guest'] && setting('guest_uploads_enabled', '1') !== '1') {
    api_error('Please log in or create a free account to use this tool.', 401, 'login_required');
}

// Abuse guard: cap how many active uploads one visitor can hold.
if (active_upload_count() >= max(20, $limits['max_files'] * 2)) {
    api_error('You have too many files waiting. Remove some files or wait a few minutes.', 429, 'too_many_files');
}

if (!isset($_FILES['file']) || is_array($_FILES['file']['name'] ?? null)) {
    api_error('Please send exactly one file.', 400, 'no_file');
}

// Optional extra file role (e.g. the image for an image watermark).
$role = (string) ($_POST['role'] ?? '');
$validateAs = $tool;
$maxBytes = $limits['max_file_bytes'];
if ($role !== '') {
    if (!isset($tool['extra_uploads'][$role])) {
        api_error('This kind of file is not used by this tool.', 400, 'invalid_role');
    }
    $validateAs = ['slug' => $tool['slug'] . ':' . $role, 'name' => 'This option', 'accept' => $tool['extra_uploads'][$role]];
    $maxBytes = min($maxBytes, 5 * 1024 * 1024); // extra images are small
}

try {
    $meta = validate_upload($_FILES['file'], $validateAs, $maxBytes);
    $file = store_upload($_FILES['file']['tmp_name'], $meta);
} catch (UploadValidationException $e) {
    api_error($e->getMessage(), $e->status, $e->errorCode);
} catch (Throwable $e) {
    app_log('error', 'Upload storage failed', ['error' => $e->getMessage()]);
    api_error('The server could not save your file. Please try again.', 500, 'storage_error');
}

log_usage('upload', $tool['slug'], null, (int) $file['size_bytes']);

api_success(['file' => file_public($file)], 'File uploaded.');
