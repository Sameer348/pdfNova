<?php
/**
 * POST /api/convert.php — Office ⇄ PDF conversions through the configured
 * external conversion service (services/converters/).
 *
 * JSON body: { "tool": "pdf-to-word", "files": ["<id>"], "options": {} }
 *
 * When no service is configured, api_tool() answers 503 "not available" —
 * this endpoint never fakes a conversion.
 */

declare(strict_types=1);

use PDFNova\Converters\ConversionException;
use PDFNova\Converters\ConverterFactory;
use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;

require __DIR__ . '/_bootstrap.php';

api_require_method('POST');
api_rate_limit('process', 30, 600);

$body = api_json_body();
$tool = api_tool((string) ($body['tool'] ?? ''));
if ($tool['engine'] !== 'converter') {
    api_error('This tool does not use the conversion service.', 400, 'invalid_tool');
}
$def    = ConverterFactory::definition($tool['slug']);
$inputs = job_inputs($body['files'] ?? null, $tool, 1);
$file   = $inputs[0];
$converter = ConverterFactory::make((string) config('converter.provider', 'none'), trim((string) config('converter.api_key', '')));

job_enforce_quota();
$job = job_start($tool, $inputs, ['to' => $def['to']], $converter->name());

job_run($job, static function (string $outPath) use ($file, $def, $converter): array {
    try {
        $converter->convert(job_input_path($file), (string) $file['extension'], $def['to'], $outPath);
    } catch (ConversionException $e) {
        throw new PdfProcessingException($e->getMessage(), $e->errorCode, $e);
    }
    return [
        'name'  => PdfTools::baseName($file['original_name']) . '.' . $def['to'],
        'ext'   => $def['to'],
        'extra' => ['title' => 'Your ' . $def['label'] . ' is ready'],
    ];
});
