<?php
/**
 * Contract for OCR engines that turn a scanned PDF into a searchable PDF.
 *
 * Shared hosting has no Tesseract or other OCR binaries, so OCR is delegated
 * to an external service through an adapter implementing this interface.
 * Add a provider: implement this interface, register it in OcrFactory::make(),
 * and set ocr.provider / ocr.api_key in includes/config.php.
 */

declare(strict_types=1);

namespace PDFNova\Ocr;

interface OcrInterface
{
    public function name(): string;

    /** Language codes this engine accepts, as code => label. */
    public function languages(): array;

    /**
     * Write a searchable PDF (original page images + invisible text layer).
     * @throws \PDFNova\Converters\ConversionException with a user-safe message
     */
    public function makeSearchable(string $inPath, string $language, string $outPath): void;
}
