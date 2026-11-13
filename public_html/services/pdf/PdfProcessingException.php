<?php
/**
 * A processing failure with a message that is SAFE to show to the visitor.
 * Internal details (paths, parser messages) go to the log, never to users.
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

final class PdfProcessingException extends \RuntimeException
{
    public function __construct(
        string $userMessage,
        public readonly string $errorCode = 'processing_failed',
        ?\Throwable $previous = null
    ) {
        parent::__construct($userMessage, 0, $previous);
    }
}
