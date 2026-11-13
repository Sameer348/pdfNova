<?php
/**
 * Used when no conversion service is configured. It never pretends to
 * convert: it always reports that the feature is not available.
 */

declare(strict_types=1);

namespace PDFNova\Converters;

final class NullConverter implements ConverterInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function convert(string $inPath, string $from, string $to, string $outPath): void
    {
        throw new ConversionException('This conversion is not available yet: no conversion service is configured.', 'tool_unavailable');
    }
}
