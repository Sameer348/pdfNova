<?php
/**
 * POST /api/split.php — split one PDF.
 *
 * JSON body: { "files": ["<id>"], "options": { "mode": ..., ... } }
 *
 * Modes:
 *   ranges  — "1-3, 5-7, 10": one PDF per range              (options.ranges)
 *   extract — "1, 3, 5-7":    one PDF with the selected pages (options.pages)
 *             or one PDF per selected page if options.separate = "1"
 *   every   — split every N pages                             (options.every)
 *
 * One output → a PDF download. Several outputs → a ZIP download.
 */

declare(strict_types=1);

use PDFNova\Pdf\PageRangeParser;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;
use PDFNova\Pdf\ZipBuilder;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('split-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$pageCount = (int) ($file['page_count'] ?? 0);
if ($pageCount < 1) {
    api_error('We could not read the number of pages in this PDF. Please upload it again.', 422, 'corrupt_pdf');
}

$options = is_array($body['options'] ?? null) ? $body['options'] : [];
$mode = (string) ($options['mode'] ?? 'ranges');
$base = PdfTools::baseName($file['original_name']);

// Validate the selection BEFORE starting a job, so a typo doesn't count as a task.
try {
    switch ($mode) {
        case 'ranges':
            $ranges = PageRangeParser::parse((string) ($options['ranges'] ?? ''), $pageCount);
            $parts = array_map(static fn(array $r): array => [
                'pages' => PageRangeParser::pages([$r]),
                'name'  => $base . ($r[0] === $r[1] ? '_page_' : '_pages_') . PageRangeParser::label($r) . '.pdf',
            ], $ranges);
            $clean = ['mode' => 'ranges', 'ranges' => array_map([PageRangeParser::class, 'label'], $ranges)];
            break;

        case 'extract':
            $pages = PageRangeParser::pages(PageRangeParser::parse((string) ($options['pages'] ?? ''), $pageCount));
            $separate = ($options['separate'] ?? '') === '1';
            $parts = $separate
                ? array_map(static fn(int $p): array => ['pages' => [$p], 'name' => $base . '_page_' . $p . '.pdf'], $pages)
                : [['pages' => $pages, 'name' => $base . '_extracted.pdf']];
            $clean = ['mode' => 'extract', 'pages' => count($pages), 'separate' => $separate];
            break;

        case 'every':
            $n = filter_var($options['every'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
            if ($n === false) {
                throw new PdfProcessingException('Enter how many pages each file should have (1 or more).', 'invalid_range');
            }
            $ranges = PageRangeParser::everyN($n, $pageCount);
            $parts = array_map(static fn(array $r, int $i): array => [
                'pages' => PageRangeParser::pages([$r]),
                'name'  => sprintf('%s_part_%0' . strlen((string) count($ranges)) . 'd.pdf', $base, $i + 1),
            ], $ranges, array_keys($ranges));
            $clean = ['mode' => 'every', 'every' => $n];
            break;

        default:
            api_error('Choose how you want to split the PDF.', 422, 'invalid_option');
    }
    if (count($parts) > PdfTools::MAX_OUTPUT_FILES) {
        throw new PdfProcessingException(
            'This would create ' . count($parts) . ' files. Please create at most ' . PdfTools::MAX_OUTPUT_FILES . ' at once.',
            'too_many_outputs'
        );
    }
} catch (PdfProcessingException $e) {
    api_error($e->getMessage(), 422, $e->errorCode);
}

job_enforce_quota();
$job = job_start($tool, $inputs, $clean);

job_run($job, static function (string $outPath) use ($file, $parts, $base): array {
    $src = job_input_path($file);
    $label = $file['original_name'];

    // A single output is delivered as a PDF.
    if (count($parts) === 1) {
        PdfTools::extract($src, $label, $parts[0]['pages'], $outPath);
        $pages = count($parts[0]['pages']);
        return [
            'name' => $parts[0]['name'], 'ext' => 'pdf', 'pages' => $pages,
            'extra' => [
                'title' => 'Your pages have been extracted',
                'stats' => [['label' => 'Files created', 'value' => '1'], ['label' => 'Pages', 'value' => (string) $pages]],
            ],
        ];
    }

    // Several outputs → build them in a private temp folder, then ZIP.
    $tmpDir = storage_dir('temp') . '/' . random_token(16);
    if (!mkdir($tmpDir, 0750)) {
        throw new PdfProcessingException('Temporary storage is unavailable. Please try again.', 'storage_error');
    }
    try {
        $outputs = PdfTools::splitParts($src, $label, $parts, $tmpDir);
        ZipBuilder::create($outputs, $outPath);
    } finally {
        remove_dir_recursive($tmpDir);
    }

    return [
        'name' => $base . '_split.zip', 'ext' => 'zip',
        'extra' => [
            'title' => 'Your PDF has been split',
            'note'  => 'Your ' . count($outputs) . ' PDF files are in one ZIP file. It is deleted from our server automatically.',
            'stats' => [
                ['label' => 'Files created', 'value' => (string) count($outputs)],
                ['label' => 'Pages', 'value' => (string) array_sum(array_column($outputs, 'pages'))],
            ],
        ],
    ];
});
