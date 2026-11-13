<?php
/** A conversion/OCR failure whose message is safe to show to visitors. */

declare(strict_types=1);

namespace PDFNova\Converters;

final class ConversionException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'conversion_failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
