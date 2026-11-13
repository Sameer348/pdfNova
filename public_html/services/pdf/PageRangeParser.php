<?php
/**
 * Parses page selections like "1-3, 5-7, 10, 12-".
 *
 *   3      → page 3
 *   1-5    → pages 1 to 5
 *   8-     → page 8 to the last page
 * Separators: comma or semicolon. Spaces are ignored. En/em dashes work too.
 *
 * Keep in sync with assets/js/tools/split-pdf.js (same rules and messages).
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

final class PageRangeParser
{
    public const MAX_RANGES = 200;

    /**
     * @return array<int, array{0:int, 1:int}> list of [start, end] (inclusive)
     * @throws PdfProcessingException with a user-friendly message
     */
    public static function parse(string $input, int $pageCount): array
    {
        $input = trim(str_replace(["\u{2013}", "\u{2014}"], '-', $input));
        if ($input === '') {
            throw self::error('Enter at least one page or range, for example 1-3.');
        }

        $ranges = [];
        foreach (preg_split('/[,;]+/', $input) ?: [] as $part) {
            $part = preg_replace('/\s+/', '', $part) ?? '';
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d{1,6})$/', $part, $m)) {
                $start = $end = (int) $m[1];
            } elseif (preg_match('/^(\d{1,6})-(\d{1,6})$/', $part, $m)) {
                [$start, $end] = [(int) $m[1], (int) $m[2]];
            } elseif (preg_match('/^(\d{1,6})-$/', $part, $m)) {
                [$start, $end] = [(int) $m[1], $pageCount];
            } else {
                throw self::error("\"$part\" isn't a valid page or range. Use formats like 3, 1-5 or 8-.");
            }

            if ($start < 1) {
                throw self::error('Page numbers start at 1.');
            }
            if ($start > $end) {
                throw self::error("The range $start-$end is backwards. Write it as $end-$start.");
            }
            if ($end > $pageCount) {
                $missing = $start > $pageCount ? $start : $end;
                throw self::error("Page $missing doesn't exist — this PDF has $pageCount " . ($pageCount === 1 ? 'page' : 'pages') . '.');
            }
            $ranges[] = [$start, $end];
            if (\count($ranges) > self::MAX_RANGES) {
                throw self::error('Please use at most ' . self::MAX_RANGES . ' ranges at once.');
            }
        }

        if ($ranges === []) {
            throw self::error('Enter at least one page or range, for example 1-3.');
        }
        return $ranges;
    }

    /** Ranges for "split every N pages". */
    public static function everyN(int $n, int $pageCount): array
    {
        if ($n < 1) {
            throw self::error('Enter how many pages each file should have (1 or more).');
        }
        if ($n >= $pageCount) {
            throw self::error("This PDF has $pageCount " . ($pageCount === 1 ? 'page' : 'pages') . ", so splitting every $n pages would not create more than one file.");
        }
        $ranges = [];
        for ($start = 1; $start <= $pageCount; $start += $n) {
            $ranges[] = [$start, min($pageCount, $start + $n - 1)];
        }
        return $ranges;
    }

    /** Expand ranges to a page list in the given order, without duplicates. */
    public static function pages(array $ranges): array
    {
        $pages = [];
        foreach ($ranges as [$start, $end]) {
            for ($p = $start; $p <= $end; $p++) {
                $pages[$p] = $p;
            }
        }
        return array_values($pages);
    }

    /** "1-3" or "10". */
    public static function label(array $range): string
    {
        return $range[0] === $range[1] ? (string) $range[0] : $range[0] . '-' . $range[1];
    }

    private static function error(string $message): PdfProcessingException
    {
        return new PdfProcessingException($message, 'invalid_range');
    }
}
