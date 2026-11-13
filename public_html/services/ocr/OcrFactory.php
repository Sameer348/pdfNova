<?php
/** Builds the OCR engine selected in config (ocr.provider). */

declare(strict_types=1);

namespace PDFNova\Ocr;

final class OcrFactory
{
    /** Shown when no provider is configured (the page is disabled anyway). */
    public const COMMON_LANGUAGES = ['eng' => 'English'];

    public static function make(string $provider, string $apiKey): OcrInterface
    {
        return match ($provider) {
            'ocrspace' => $apiKey !== '' ? new OcrSpaceOcr($apiKey) : new NullOcr(),
            default    => new NullOcr(),
        };
    }
}
