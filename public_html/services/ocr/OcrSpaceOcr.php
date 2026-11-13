<?php
/**
 * OCR.space adapter (https://ocr.space/ocrapi) — returns a searchable PDF.
 *
 * Setup: get an API key at ocr.space and set in includes/config.php:
 *     'ocr' => ['provider' => 'ocrspace', 'api_key' => '...'],
 * The free key has limits (about 1 MB per file and 3 PDF pages); paid "PRO"
 * keys use a different endpoint — change ENDPOINT if you upgrade.
 *
 * ⚠ Written to OCR.space's documented API but NOT tested against the live
 * service (that requires an API key). Test it after adding your key.
 *
 * Privacy: the file is sent to OCR.space — mention this in your privacy
 * policy if you enable OCR.
 */

declare(strict_types=1);

namespace PDFNova\Ocr;

use PDFNova\Converters\ConversionException;
use PDFNova\Converters\Http;

final class OcrSpaceOcr implements OcrInterface
{
    private const ENDPOINT = 'https://api.ocr.space/parse/image';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function name(): string
    {
        return 'ocrspace';
    }

    public function languages(): array
    {
        return [
            'eng' => 'English', 'ara' => 'Arabic', 'chs' => 'Chinese (Simplified)', 'cht' => 'Chinese (Traditional)',
            'dut' => 'Dutch', 'fre' => 'French', 'ger' => 'German', 'hin' => 'Hindi', 'ita' => 'Italian',
            'jpn' => 'Japanese', 'kor' => 'Korean', 'pol' => 'Polish', 'por' => 'Portuguese', 'rus' => 'Russian',
            'spa' => 'Spanish', 'swe' => 'Swedish', 'tur' => 'Turkish',
        ];
    }

    public function makeSearchable(string $inPath, string $language, string $outPath): void
    {
        if (!isset($this->languages()[$language])) {
            throw new ConversionException('Choose a supported language.', 'invalid_option');
        }
        $response = Http::request('POST', self::ENDPOINT, ['apikey: ' . $this->apiKey], [
            'file'                          => new \CURLFile($inPath, 'application/pdf', 'document.pdf'),
            'filetype'                      => 'PDF',
            'language'                      => $language,
            'isCreateSearchablePdf'         => 'true',
            'isSearchablePdfHideTextLayer'  => 'true',
            'scale'                         => 'true',
        ], 180);

        $data = json_decode($response['body'], true);
        if ($response['status'] === 403 || $response['status'] === 401) {
            $this->fail('The OCR service rejected our credentials.', 'HTTP ' . $response['status']);
        }
        if (!\is_array($data) || !empty($data['IsErroredOnProcessing'])) {
            $detail = \is_array($data) ? json_encode($data['ErrorMessage'] ?? $data['ErrorDetails'] ?? '') : substr($response['body'], 0, 200);
            $this->fail('The OCR service could not process this file. Large or long PDFs may exceed its limits.', 'error ' . $detail);
        }
        $url = $data['SearchablePDFURL'] ?? '';
        if (!\is_string($url) || !str_starts_with($url, 'https://')) {
            $this->fail('The OCR service did not return a searchable PDF.', 'missing SearchablePDFURL');
        }
        Http::download($url, $outPath);
    }

    private function fail(string $userMessage, string $detail): never
    {
        if (\function_exists('app_log')) {
            app_log('error', 'OCR.space: ' . $detail);
        }
        throw new ConversionException($userMessage, 'engine_failed');
    }
}
