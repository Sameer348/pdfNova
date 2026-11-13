<?php
/**
 * POST /api/merge.php — merge uploaded PDFs in the given order.
 *
 * JSON body: { "files": ["<file id>", "<file id>", ...], "options": {} }
 * Success:   { success, message, download_url, filename, size, size_label, pages, job_id }
 */

declare(strict_types=1);

use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);   // 30 jobs per 10 minutes per IP

$tool   = api_tool('merge-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 2);
job_enforce_quota();

$job = job_start($tool, $inputs);

job_run($job, static function (string $outPath) use ($inputs): array {
    $paths  = array_map('job_input_path', $inputs);
    $labels = array_column($inputs, 'original_name');
    $info   = PdfTools::merge($paths, $labels, $outPath);

    return [
        'name'    => 'merged.pdf',
        'ext'     => 'pdf',
        'pages'   => $info['pages'],
        'message' => 'PDF processed successfully',
        'extra'   => [
            'title' => 'Your PDFs have been merged',
            'stats' => [
                ['label' => 'Files merged', 'value' => (string) count($inputs)],
                ['label' => 'Total pages', 'value' => (string) $info['pages']],
            ],
        ],
    ];
});
