<?php
/**
 * POST /api/rotate.php — rotate all or selected pages of one PDF.
 *
 * JSON body: { "files": ["<id>"], "options": {
 *     "angle": 90 | 180 | 270        (clockwise; 270 = 90° counter-clockwise)
 *     "apply": "all" | "selected",
 *     "pages": "1-3, 6"              (when apply = selected)
 * } }
 *
 * Rotation is lossless: only each page's /Rotate value changes.
 */

declare(strict_types=1);

use PDFNova\Pdf\PageRangeParser;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('rotate-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$pageCount = (int) ($file['page_count'] ?? 0);
if ($pageCount < 1) {
    api_error('We could not read the number of pages in this PDF. Please upload it again.', 422, 'corrupt_pdf');
}

$options = is_array($body['options'] ?? null) ? $body['options'] : [];
$angle = (int) ($options['angle'] ?? 0);
if (!in_array($angle, [90, 180, 270], true)) {
    api_error('Choose a rotation: 90° clockwise, 90° counter-clockwise or 180°.', 422, 'invalid_option');
}
$apply = (string) ($options['apply'] ?? 'all');

try {
    if ($apply === 'all') {
        $targets = range(1, $pageCount);
    } elseif ($apply === 'selected') {
        $targets = PageRangeParser::pages(PageRangeParser::parse((string) ($options['pages'] ?? ''), $pageCount));
    } else {
        throw new PdfProcessingException('Choose which pages to rotate.', 'invalid_option');
    }
} catch (PdfProcessingException $e) {
    api_error($e->getMessage(), 422, $e->errorCode);
}

$targetSet = array_fill_keys($targets, true);
$plan = [];
for ($p = 1; $p <= $pageCount; $p++) {
    $plan[] = ['page' => $p, 'rotate' => isset($targetSet[$p]) ? $angle : 0];
}

job_enforce_quota();
$job = job_start($tool, $inputs, ['angle' => $angle, 'apply' => $apply, 'pages' => count($targets)]);

job_run($job, static function (string $outPath) use ($file, $plan, $targets, $angle, $pageCount): array {
    PdfTools::rebuild(job_input_path($file), $file['original_name'], $plan, $outPath);
    $label = [90 => '90° clockwise', 180 => '180°', 270 => '90° counter-clockwise'][$angle];
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '_rotated.pdf',
        'ext'   => 'pdf',
        'pages' => $pageCount,
        'extra' => [
            'title' => count($targets) === $pageCount ? 'All pages rotated' : count($targets) . ' of ' . $pageCount . ' pages rotated',
            'stats' => [
                ['label' => 'Pages rotated', 'value' => count($targets) . ' / ' . $pageCount],
                ['label' => 'Rotation', 'value' => $label],
            ],
        ],
    ];
});
