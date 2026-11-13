<?php
/**
 * POST /api/protect.php — add a password to a PDF (AES-256).
 *
 * JSON body: { "files": ["<id>"], "options": {
 *   "password": "...", "confirm": "...",
 *   "allow_print": true, "allow_copy": true, "allow_modify": false
 * } }
 *
 * The password is used only in memory to encrypt the file. It is never
 * written to the database, logs or disk.
 */

declare(strict_types=1);

use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('protect-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$o = is_array($body['options'] ?? null) ? $body['options'] : [];

$password = (string) ($o['password'] ?? '');
$confirm  = (string) ($o['confirm'] ?? '');
$length   = mb_strlen($password);
if ($length < 6) {
    api_error('Use a password of at least 6 characters.', 422, 'weak_password');
}
if ($length > 128) {
    api_error('The password can be at most 128 characters.', 422, 'invalid_password');
}
if (!hash_equals($password, $confirm)) {
    api_error('The passwords do not match.', 422, 'password_mismatch');
}

$flag = static fn(string $key, bool $default): bool => filter_var($o[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
$allow = ['print' => $flag('allow_print', true), 'copy' => $flag('allow_copy', true), 'modify' => $flag('allow_modify', false)];

job_enforce_quota();
// Never log the password — only the permission choices.
$job = job_start($tool, $inputs, ['encryption' => 'AES-256', 'allow' => $allow]);

job_run($job, static function (string $outPath) use ($file, $password, $allow): array {
    $pages = PdfTools::protect(job_input_path($file), $file['original_name'], $password, $allow, $outPath);
    $yesNo = static fn(bool $v): string => $v ? 'Allowed' : 'Blocked';
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '_protected.pdf',
        'ext'   => 'pdf',
        'pages' => $pages,
        'extra' => [
            'title' => 'Your PDF is password-protected',
            'note'  => 'Keep your password safe — we don\'t store it and can\'t recover it. The file is deleted from our server automatically.',
            'stats' => [
                ['label' => 'Encryption', 'value' => 'AES-256'],
                ['label' => 'Printing', 'value' => $yesNo($allow['print'])],
                ['label' => 'Copying', 'value' => $yesNo($allow['copy'])],
            ],
        ],
    ];
});
