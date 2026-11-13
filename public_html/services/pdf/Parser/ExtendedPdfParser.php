<?php
/**
 * PDFNova — FPDI parser that uses ExtendedCrossReference, so PDF 1.5+
 * files (cross-reference streams and object streams) can be read.
 *
 * When constructed with a password, encrypted PDFs are opened too: the
 * Standard Security Handler is set up and every object is decrypted as it
 * is read. Without a password, encrypted files are rejected as before.
 *
 * Original code for PDFNova. Extends FPDI (MIT licence).
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;

class ExtendedPdfParser extends PdfParser
{
    private ?string $password;

    public function __construct(StreamReader $streamReader, ?string $password = null)
    {
        parent::__construct($streamReader);
        $this->password = $password;
    }

    public function getCrossReference()
    {
        if ($this->xref === null) {
            $xref = new ExtendedCrossReference($this, $this->resolveFileHeader(), $this->password !== null);
            $this->xref = $xref;
            if ($this->password !== null && $xref->encryptEntry() !== null) {
                $this->setUpDecryption($xref);
            }
        }
        return $this->xref;
    }

    private function setUpDecryption(ExtendedCrossReference $xref): void
    {
        $entry = $xref->encryptEntry();
        $excluded = null;
        if ($entry instanceof PdfIndirectObjectReference) {
            $excluded = (int) $entry->value;
            $entry = $xref->getRawIndirectObject($excluded)->value;
        }
        if (!$entry instanceof PdfDictionary) {
            throw new EncryptionException('This PDF\'s encryption data is damaged.', 'corrupt_pdf');
        }

        // First element of the trailer /ID array (needed by RC4/AES-128 key derivation).
        $ids = $xref->getTrailer()->value['ID'] ?? null;
        if ($ids instanceof PdfIndirectObjectReference) {
            $ids = $xref->getRawIndirectObject((int) $ids->value)->value;
        }
        $firstId = $ids instanceof PdfArray && isset($ids->value[0]) ? StandardSecurityHandler::bytes($ids->value[0]) : '';

        $resolve = function ($value) use ($xref) {
            return $value instanceof PdfIndirectObjectReference ? $xref->getRawIndirectObject((int) $value->value)->value : $value;
        };
        $xref->setSecurity(new StandardSecurityHandler($entry, $firstId, (string) $this->password, $resolve), $excluded);
    }
}
