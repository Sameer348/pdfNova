<?php
/**
 * POST /api/jpg-to-pdf.php — combine JPG/PNG images into one PDF.
 *
 * JSON body: { "files": ["<id>", ...], "options": {
 *   "page_size": "a4"|"letter"|"fit", "orientation": "auto"|"portrait"|"landscape",
 *   "margin": 0|10|20, "fit": "contain"|"cover"|"original"
 * } }
 */

declare(strict_types=1);

use PDFNova\Pdf\ImagesToPdf;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('jpg-to-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$o = is_array($body['options'] ?? null) ? $body['options'] : [];

$pick = static fn(string $key, array $allowed, string $default): string
    => in_array((string) ($o[$key] ?? $default), $allowed, true) ? (string) ($o[$key] ?? $default) : $default;
$opt = [
    'page_size'   => $pick('page_size', ['a4', 'letter', 'fit'], 'a4'),
    'orientation' => $pick('orientation', ['auto', 'portrait', 'landscape'], 'auto'),
    'margin'      => (int) $pick('margin', ['0', '10', '20'], '10'),
    'fit'         => $pick('fit', ['contain', 'cover', 'original'], 'contain'),
];

job_enforce_quota();
$job = job_start($tool, $inputs, $opt, 'tcpdf');

job_run($job, static function (string $outPath) use ($inputs, $opt): array {
    $tmpDir = storage_dir('temp') . '/' . random_token(16);
    if (!mkdir($tmpDir, 0750)) {
        throw new PdfProcessingException('Temporary storage is unavailable. Please try again.', 'storage_error');
    }
    try {
        $images = array_map(static fn(array $f): array => [
            'path' => job_input_path($f),
            'ext'  => $f['extension'] === 'png' ? 'png' : 'jpg',
            'name' => $f['original_name'],
        ], $inputs);
        $pages = ImagesToPdf::convert($images, $opt, $outPath, $tmpDir);
    } finally {
        remove_dir_recursive($tmpDir);
    }
    $name = count($inputs) === 1 ? PdfTools::baseName($inputs[0]['original_name'], 'images') . '.pdf' : 'images.pdf';
    return [
        'name'  => $name,
        'ext'   => 'pdf',
        'pages' => $pages,
        'extra' => [
            'title' => count($inputs) === 1 ? 'Your image is now a PDF' : 'Your images are now a PDF',
            'stats' => [
                ['label' => 'Images', 'value' => (string) count($inputs)],
                ['label' => 'Pages', 'value' => (string) $pages],
            ],
        ],
    ];
});
