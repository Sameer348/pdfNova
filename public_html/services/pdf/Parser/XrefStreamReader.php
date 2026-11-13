<?php
/**
 * PDFNova — reader for PDF 1.5+ cross-reference streams (PDF spec §7.5.8).
 *
 * A cross-reference stream is a binary table: each entry has up to three
 * fields whose byte widths are given by /W [w1 w2 w3]:
 *   type 0 → free object
 *   type 1 → field2 = byte offset in the file, field3 = generation
 *   type 2 → object lives inside an object stream:
 *            field2 = object-stream number, field3 = index inside it
 * /Index [first count first count ...] lists which object numbers are covered
 * (default [0 Size]).
 *
 * Original code for PDFNova. Implements FPDI's ReaderInterface (MIT licence).
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

final class XrefStreamReader implements ReaderInterface
{
    /** @var array<int, array{0:int,1:int,2:int}> objectNumber → [type, field2, field3] */
    private array $entries = [];
    private PdfDictionary $trailer;

    public function __construct(PdfStream $stream)
    {
        // The xref stream dictionary doubles as the trailer (Root, Info, ID, Size, Prev, Encrypt).
        $this->trailer = $stream->value;
        $this->parse(StreamDecoder::decode($stream));
    }

    private function parse(string $data): void
    {
        $w = PdfDictionary::get($this->trailer, 'W');
        if (!$w instanceof PdfArray || \count($w->value) < 3) {
            throw new CrossReferenceException('Cross-reference stream has no valid /W.', CrossReferenceException::INVALID_DATA);
        }
        $widths = array_map(static fn($n): int => $n instanceof PdfNumeric ? (int) $n->value : -1, $w->value);
        [$w1, $w2, $w3] = $widths;
        if ($w1 < 0 || $w2 < 0 || $w3 < 0 || $w1 > 4 || $w2 > 8 || $w3 > 8) {
            throw new CrossReferenceException('Invalid /W widths in cross-reference stream.', CrossReferenceException::INVALID_DATA);
        }
        $entryLength = $w1 + $w2 + $w3;
        if ($entryLength === 0) {
            return;
        }

        $size = PdfDictionary::get($this->trailer, 'Size');
        $index = PdfDictionary::get($this->trailer, 'Index');
        $sections = [];
        if ($index instanceof PdfArray && \count($index->value) >= 2) {
            for ($i = 0; $i + 1 < \count($index->value); $i += 2) {
                $sections[] = [(int) $index->value[$i]->value, (int) $index->value[$i + 1]->value];
            }
        } else {
            $sections[] = [0, $size instanceof PdfNumeric ? (int) $size->value : 0];
        }

        $pos = 0;
        $length = \strlen($data);
        foreach ($sections as [$first, $count]) {
            for ($n = 0; $n < $count; $n++) {
                if ($pos + $entryLength > $length) {
                    return; // truncated table: keep what we have, like viewers do
                }
                // Field 1 defaults to type 1 when its width is 0.
                $type = $w1 === 0 ? 1 : self::int($data, $pos, $w1);
                $f2 = self::int($data, $pos + $w1, $w2);
                $f3 = self::int($data, $pos + $w1 + $w2, $w3);
                $pos += $entryLength;

                $objectNumber = $first + $n;
                // First occurrence wins inside one table.
                if (!isset($this->entries[$objectNumber])) {
                    $this->entries[$objectNumber] = [$type, $f2, $f3];
                }
            }
        }
    }

    /** Big-endian unsigned integer of $width bytes. */
    private static function int(string $data, int $offset, int $width): int
    {
        $value = 0;
        for ($i = 0; $i < $width; $i++) {
            $value = ($value << 8) | \ord($data[$offset + $i]);
        }
        return $value;
    }

    /** @return array{0:int,1:int,2:int}|null [type, field2, field3] or null if not in this table */
    public function getEntry(int $objectNumber): ?array
    {
        return $this->entries[$objectNumber] ?? null;
    }

    public function getOffsetFor($objectNumber)
    {
        $entry = $this->entries[(int) $objectNumber] ?? null;
        return ($entry !== null && $entry[0] === 1) ? $entry[1] : false;
    }

    public function getTrailer()
    {
        return $this->trailer;
    }
}
