<?php
/**
 * POST /api/watermark.php — stamp a text or image watermark on a PDF.
 *
 * JSON body: { "files": ["<pdf id>"], "options": {
 *   "type": "text" | "image",
 *   "text": "CONFIDENTIAL", "font_size": 60, "color": "#d21f45",      (text)
 *   "image_id": "<id from upload.php with role=watermark-image>",       (image)
 *   "image_scale": 40,                                                 (image: % of page width)
 *   "opacity": 30, "rotation": 45, "position": "center",
 *   "apply": "all" | "selected", "pages": "1-3"
 * } }
 */

declare(strict_types=1);

use PDFNova\Pdf\PageRangeParser;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;
use PDFNova\Pdf\Watermarker;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('watermark-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$pageCount = (int) ($file['page_count'] ?? 0);
$o = is_array($body['options'] ?? null) ? $body['options'] : [];

/** Integer option clamped to a range (rejects non-numbers). */
$int = static function (string $key, int $min, int $max, int $default) use ($o): int {
    $v = filter_var($o[$key] ?? $default, FILTER_VALIDATE_INT);
    if ($v === false) {
        api_error('One of the watermark settings is not valid.', 422, 'invalid_option');
    }
    return max($min, min($max, $v));
};

$type = ($o['type'] ?? 'text') === 'image' ? 'image' : 'text';
$opt = [
    'type'     => $type,
    'opacity'  => $int('opacity', 5, 100, 30) / 100,
    'rotation' => $int('rotation', -180, 180, 45),
    'position' => in_array($o['position'] ?? '', Watermarker::POSITIONS, true) ? $o['position'] : 'center',
];

$extraInputs = [];
if ($type === 'text') {
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($o['text'] ?? '')) ?? '');
    if ($text === '' || mb_strlen($text) > 100) {
        api_error('Enter watermark text (up to 100 characters).', 422, 'invalid_option');
    }
    $color = (string) ($o['color'] ?? '#d21f45');
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $m)) {
        api_error('Choose a valid colour.', 422, 'invalid_option');
    }
    $opt += ['text' => $text, 'font_size' => $int('font_size', 8, 200, 60), 'color' => [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])]];
} else {
    $image = find_owned_file((string) ($o['image_id'] ?? ''), 'upload');
    if ($image === null || !in_array($image['extension'], ['png', 'jpg', 'jpeg'], true)) {
        api_error('Add a PNG or JPG image for the watermark (it may have expired — please add it again).', 422, 'no_image');
    }
    $opt += [
        'image_path'  => job_input_path($image),
        'image_type'  => $image['extension'] === 'png' ? 'PNG' : 'JPG',
        'image_scale' => $int('image_scale', 5, 100, 40),
    ];
    $extraInputs[] = $image;
}

try {
    $targets = ($o['apply'] ?? 'all') === 'selected'
        ? PageRangeParser::pages(PageRangeParser::parse((string) ($o['pages'] ?? ''), max(1, $pageCount)))
        : null;
} catch (PdfProcessingException $e) {
    api_error($e->getMessage(), 422, $e->errorCode);
}

job_enforce_quota();
// Log settings without the image path; the image file is linked to the job and deleted with it.
$logged = array_diff_key($opt, ['image_path' => 1]);
$job = job_start($tool, array_merge($inputs, $extraInputs), $logged);

job_run($job, static function (string $outPath) use ($file, $opt, $targets): array {
    $r = Watermarker::apply(job_input_path($file), $file['original_name'], $opt, $targets, $outPath);
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '_watermarked.pdf',
        'ext'   => 'pdf',
        'pages' => $r['pages'],
        'extra' => [
            'title' => 'Watermark added',
            'stats' => [
                ['label' => 'Pages watermarked', 'value' => $r['stamped'] . ' / ' . $r['pages']],
                ['label' => 'Type', 'value' => $opt['type'] === 'image' ? 'Image' : 'Text'],
            ],
        ],
    ];
});
