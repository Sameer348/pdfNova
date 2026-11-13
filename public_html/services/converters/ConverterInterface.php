<?php
/**
 * Contract for document conversion engines (PDF ↔ Word/Excel/PowerPoint).
 *
 * Shared hosting cannot run LibreOffice or other conversion binaries, so real
 * conversions are delegated to an external service through an adapter that
 * implements this interface. Add a new provider by:
 *   1. creating a class implementing ConverterInterface in this folder,
 *   2. registering it in ConverterFactory::make(),
 *   3. setting converter.provider / converter.api_key in includes/config.php.
 */

declare(strict_types=1);

namespace PDFNova\Converters;

interface ConverterInterface
{
    /** Short provider name stored with each job (e.g. "cloudconvert"). */
    public function name(): string;

    /**
     * Convert $inPath (format $from) to $to, writing the result to $outPath.
     * @throws ConversionException with a user-safe message
     */
    public function convert(string $inPath, string $from, string $to, string $outPath): void;
}
