<?php
/**
 * Raised while opening an encrypted PDF: wrong password, unsupported
 * security handler, or damaged encryption data. The message is user-safe.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

final class EncryptionException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }
}
