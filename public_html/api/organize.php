<?php
/**
 * POST /api/organize.php — reorder, rotate, remove or extract pages of one PDF.
 *
 * JSON body: { "files": ["<id>"], "options": {
 *     "output": "all" | "selected",
 *     "plan":   [ { "page": 3, "rotate": 90 }, { "page": 1, "rotate": 0 }, ... ]
 * } }
 * The plan lists the pages to keep, in the new order (removed pages are left
 * out). For "selected", the browser sends only the selected pages.
 */

declare(strict_types=1);

use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('organize-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$pageCount = (int) ($file['page_count'] ?? 0);
if ($pageCount < 1) {
    api_error('We could not read the number of pages in this PDF. Please upload it again.', 422, 'corrupt_pdf');
}

$options = is_array($body['options'] ?? null) ? $body['options'] : [];
$output = ($options['output'] ?? 'all') === 'selected' ? 'selected' : 'all';
$rawPlan = $options['plan'] ?? null;

if (!is_array($rawPlan) || $rawPlan === []) {
    api_error($output === 'selected' ? 'Select at least one page to extract.' : 'Keep at least one page.', 422, 'no_pages');
}
if (count($rawPlan) > PdfTools::MAX_PAGES) {
    api_error('Too many pages in one request.', 422, 'too_many_pages');
}

// Strictly validate every entry; never trust the browser's numbers.
$plan = [];
$rotated = 0;
foreach ($rawPlan as $entry) {
    if (!is_array($entry)) {
        api_error('The page arrangement was not valid. Please reload the page and try again.', 422, 'invalid_plan');
    }
    $page = filter_var($entry['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $pageCount]]);
    $rotate = filter_var($entry['rotate'] ?? 0, FILTER_VALIDATE_INT);
    if ($page === false || !in_array($rotate, [0, 90, 180, 270], true)) {
        api_error('The page arrangement was not valid. Please reload the page and try again.', 422, 'invalid_plan');
    }
    $plan[] = ['page' => $page, 'rotate' => $rotate];
    $rotated += $rotate !== 0 ? 1 : 0;
}

$kept = count($plan);
$removed = $output === 'all' ? $pageCount - count(array_unique(array_column($plan, 'page'))) : 0;

job_enforce_quota();
$job = job_start($tool, $inputs, ['output' => $output, 'kept' => $kept, 'rotated' => $rotated, 'removed' => $removed]);

job_run($job, static function (string $outPath) use ($file, $plan, $output, $kept, $rotated, $removed): array {
    PdfTools::rebuild(job_input_path($file), $file['original_name'], $plan, $outPath);
    $base = PdfTools::baseName($file['original_name']);
    $stats = [['label' => 'Pages in result', 'value' => (string) $kept]];
    if ($output === 'all') {
        $stats[] = ['label' => 'Removed', 'value' => (string) $removed];
    }
    $stats[] = ['label' => 'Rotated', 'value' => (string) $rotated];

    return [
        'name'  => $base . ($output === 'selected' ? '_extracted.pdf' : '_organized.pdf'),
        'ext'   => 'pdf',
        'pages' => $kept,
        'extra' => [
            'title' => $output === 'selected' ? 'Your selected pages are ready' : 'Your organized PDF is ready',
            'stats' => $stats,
        ],
    ];
});
