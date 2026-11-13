<?php
/**
 * POST /api/ocr.php — make a scanned PDF searchable via the configured OCR
 * service (services/ocr/). JSON body: { "files": ["<id>"], "options": { "language": "eng" } }
 * Without a configured service the tool reports "not available" (503).
 */

declare(strict_types=1);

use PDFNova\Converters\ConversionException;
use PDFNova\Ocr\OcrFactory;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$tool   = api_tool('ocr-pdf');
$body   = api_json_body();
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$ocr    = OcrFactory::make((string) config('ocr.provider', 'none'), trim((string) config('ocr.api_key', '')));
$language = (string) ($body['options']['language'] ?? 'eng');
if (!isset($ocr->languages()[$language])) {
    api_error('Choose a supported language.', 422, 'invalid_option');
}

job_enforce_quota();
$job = job_start($tool, $inputs, ['language' => $language], $ocr->name());

job_run($job, static function (string $outPath) use ($file, $ocr, $language): array {
    try {
        $ocr->makeSearchable(job_input_path($file), $language, $outPath);
    } catch (ConversionException $e) {
        throw new PdfProcessingException($e->getMessage(), $e->errorCode, $e);
    }
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '_searchable.pdf',
        'ext'   => 'pdf',
        'extra' => ['title' => 'Your PDF is now searchable'],
    ];
});
