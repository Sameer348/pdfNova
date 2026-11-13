<?php
/**
 * POST /api/unlock.php — remove the password from a PDF you can open.
 *
 * JSON body: { "files": ["<id>"], "options": { "password": "..." } }
 * An empty password unlocks PDFs that open freely but have restrictions.
 *
 * Decryption is pure PHP (services/pdf/Parser/StandardSecurityHandler.php):
 * RC4 40/128, AES-128 and AES-256 (R2–R6). The password is used only in
 * memory and never stored or logged. Attempts are rate-limited per IP.
 */

declare(strict_types=1);

use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);
api_rate_limit('unlock-attempt', 15, 600); // slows down password guessing

$tool   = api_tool('unlock-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];

$password = (string) ($body['options']['password'] ?? '');
if (mb_strlen($password) > 128) {
    api_error('The password is too long.', 422, 'invalid_password');
}

job_enforce_quota();
$job = job_start($tool, $inputs, [], 'php-decrypt'); // password deliberately not logged

job_run($job, static function (string $outPath) use ($file, $password): array {
    $pages = PdfTools::unlock(job_input_path($file), $file['original_name'], $password, $outPath);
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '_unlocked.pdf',
        'ext'   => 'pdf',
        'pages' => $pages,
        'extra' => [
            'title' => 'Your PDF is unlocked',
            'note'  => 'The password and restrictions have been removed. The file is deleted from our server automatically.',
            'stats' => [['label' => 'Pages', 'value' => (string) $pages], ['label' => 'Password', 'value' => 'Removed']],
        ],
    ];
});
