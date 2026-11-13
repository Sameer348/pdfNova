<?php
/**
 * POST /api/delete.php — delete one of your uploaded files right away
 * (e.g. when you remove it from the list). JSON body: { "file_id": "..." }
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('delete', 300, 600);

$body = api_json_body();
$file = find_owned_file((string) ($body['file_id'] ?? ''));

// Respond the same way whether the file never existed, expired or belongs to
// someone else — no information leak.
if ($file !== null) {
    delete_stored_file($file);
}

api_success([], 'File removed.');
