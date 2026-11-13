<?php
/**
 * POST /api/compress.php — reduce the size of one PDF.
 *
 * JSON body: { "files": ["<id>"], "options": { "level": "high" | "recommended" | "low" } }
 * Success adds: original_size, result_size, saved_percent, improved, engine
 *
 * Engine: Ghostscript when configured (config engines.ghostscript), otherwise
 * the pure-PHP engine. If no reduction is possible the original file is
 * returned unchanged and the response says so — we never claim savings we
 * didn't make.
 */

declare(strict_types=1);

use PDFNova\Pdf\Compression\PdfCompressor;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('compress-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];

$level = (string) ($body['options']['level'] ?? 'recommended');
if (!isset(PdfCompressor::LEVELS[$level])) {
    api_error('Choose a compression level.', 422, 'invalid_option');
}

job_enforce_quota();
$gs  = engine_binary_available('ghostscript') ? (string) config('engines.ghostscript') : null;
$job = job_start($tool, $inputs, ['level' => $level], $gs !== null ? 'ghostscript' : 'php-gd');

job_run($job, static function (string $outPath) use ($file, $level, $gs): array {
    $r = PdfCompressor::compress(job_input_path($file), $file['original_name'], $level, $outPath, $gs);
    $saved = $r['original'] > 0 ? round(100 * (1 - $r['result'] / $r['original']), 1) : 0.0;
    $base  = \PDFNova\Pdf\PdfTools::baseName($file['original_name']);

    $stats = [
        ['label' => 'Original size', 'value' => format_bytes($r['original'])],
        ['label' => 'Compressed size', 'value' => format_bytes($r['result'])],
        ['label' => 'Saved', 'value' => $r['improved'] ? $saved . '%' : '0%', 'highlight' => $r['improved']],
    ];

    return [
        'name'    => $base . ($r['improved'] ? '_compressed.pdf' : '.pdf'),
        'ext'     => 'pdf',
        'pages'   => $r['pages'] ?: ((int) $file['page_count'] ?: null),
        'message' => $r['improved'] ? 'PDF compressed successfully' : 'This PDF is already well optimized',
        'extra'   => [
            'title'         => $r['improved'] ? 'Your PDF is ' . $saved . '% smaller' : 'Your PDF is already well optimized',
            'note'          => $r['improved']
                ? 'Compressed with the "' . ucfirst($level) . '" setting. The file is deleted from our server automatically.'
                : 'We could not make this file smaller without losing quality, so you get your original file back unchanged.',
            'stats'         => $stats,
            'original_size' => $r['original'],
            'result_size'   => $r['result'],
            'saved_percent' => $r['improved'] ? $saved : 0,
            'improved'      => $r['improved'],
            'engine'        => $r['engine'],
        ],
    ];
});
