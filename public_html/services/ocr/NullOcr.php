<?php
/** Used when no OCR service is configured: always reports "not available". */

declare(strict_types=1);

namespace PDFNova\Ocr;

use PDFNova\Converters\ConversionException;

final class NullOcr implements OcrInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function languages(): array
    {
        return OcrFactory::COMMON_LANGUAGES;
    }

    public function makeSearchable(string $inPath, string $language, string $outPath): void
    {
        throw new ConversionException('OCR is not available yet: no OCR service is configured.', 'tool_unavailable');
    }
}
