<?php
/**
 * PDFNova — stream decoding for cross-reference and object streams.
 *
 * FPDI's free parser decodes Flate/LZW/ASCII filters but refuses PNG/TIFF
 * "predictors", which almost every PDF 1.5+ cross-reference stream uses.
 * This class adds a PNG predictor implementation (PDF spec §7.4.4.4) and
 * decodes the streams our cross-reference reader needs.
 *
 * Original code for PDFNova. Builds on FPDI (MIT licence).
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Parser;

use setasign\Fpdi\PdfParser\Filter\Ascii85;
use setasign\Fpdi\PdfParser\Filter\AsciiHex;
use setasign\Fpdi\PdfParser\Filter\Flate;
use setasign\Fpdi\PdfParser\Filter\Lzw;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

final class StreamDecoder
{
    /** Hard cap on decoded size to protect server memory (zip-bomb style streams). */
    public const MAX_DECODED_BYTES = 64 * 1024 * 1024;

    /**
     * Return the fully decoded bytes of a stream.
     * @throws CrossReferenceException for unsupported filters or corrupt data
     */
    public static function decode(PdfStream $stream): string
    {
        $data = $stream->getStream();
        $filters = $stream->getFilters();
        if ($filters === []) {
            return $data;
        }

        $params = PdfDictionary::get($stream->value, 'DecodeParms');
        $params = $params instanceof PdfArray ? $params->value : [$params];

        foreach ($filters as $i => $filter) {
            if (!$filter instanceof PdfName) {
                continue;
            }
            $param = ($params[$i] ?? null) instanceof PdfDictionary ? $params[$i] : null;

            switch ($filter->value) {
                case 'FlateDecode':
                case 'Fl':
                    $data = (new Flate())->decode($data);
                    $data = self::applyPredictor($data, $param);
                    break;
                case 'LZWDecode':
                case 'LZW':
                    $data = (new Lzw())->decode($data);
                    $data = self::applyPredictor($data, $param);
                    break;
                case 'ASCII85Decode':
                case 'A85':
                    $data = (new Ascii85())->decode($data);
                    break;
                case 'ASCIIHexDecode':
                case 'AHx':
                    $data = (new AsciiHex())->decode($data);
                    break;
                default:
                    throw new CrossReferenceException(
                        'Unsupported stream filter: ' . $filter->value,
                        CrossReferenceException::INVALID_DATA
                    );
            }

            if (\strlen($data) > self::MAX_DECODED_BYTES) {
                throw new CrossReferenceException('Decoded stream is too large.', CrossReferenceException::INVALID_DATA);
            }
        }
        return $data;
    }

    private static function applyPredictor(string $data, ?PdfDictionary $param): string
    {
        if ($param === null) {
            return $data;
        }
        $predictor = (int) self::num($param, 'Predictor', 1);
        if ($predictor === 1) {
            return $data;
        }
        $colors  = (int) self::num($param, 'Colors', 1);
        $bpc     = (int) self::num($param, 'BitsPerComponent', 8);
        $columns = (int) self::num($param, 'Columns', 1);

        if ($predictor >= 10 && $predictor <= 15) {
            return self::pngUnpredict($data, $columns, $colors, $bpc);
        }
        throw new CrossReferenceException(
            'Unsupported predictor ' . $predictor . ' (TIFF predictors are not supported).',
            CrossReferenceException::INVALID_DATA
        );
    }

    private static function num(PdfDictionary $dict, string $key, int $default): int
    {
        $value = PdfDictionary::get($dict, $key, PdfNumeric::create($default));
        return $value instanceof PdfNumeric ? (int) $value->value : $default;
    }

    /**
     * Reverse PNG row filters. Every row starts with one filter-type byte
     * (0 None, 1 Sub, 2 Up, 3 Average, 4 Paeth) followed by $rowLength bytes.
     */
    public static function pngUnpredict(string $data, int $columns, int $colors = 1, int $bitsPerComponent = 8): string
    {
        if ($columns < 1 || $colors < 1 || $bitsPerComponent < 1) {
            throw new CrossReferenceException('Invalid predictor parameters.', CrossReferenceException::INVALID_DATA);
        }
        $bpp = max(1, intdiv($colors * $bitsPerComponent, 8));            // bytes per pixel
        $rowLength = intdiv($colors * $bitsPerComponent * $columns + 7, 8);
        $length = \strlen($data);
        $previous = array_fill(0, $rowLength, 0);
        $out = [];

        for ($pos = 0; $pos < $length; $pos += $rowLength + 1) {
            $type = \ord($data[$pos]);
            $current = [];
            for ($i = 0; $i < $rowLength; $i++) {
                $x = isset($data[$pos + 1 + $i]) ? \ord($data[$pos + 1 + $i]) : 0;
                $a = $i >= $bpp ? $current[$i - $bpp] : 0;      // left
                $b = $previous[$i];                              // above
                $c = $i >= $bpp ? $previous[$i - $bpp] : 0;     // upper-left
                switch ($type) {
                    case 0: $value = $x; break;
                    case 1: $value = $x + $a; break;
                    case 2: $value = $x + $b; break;
                    case 3: $value = $x + intdiv($a + $b, 2); break;
                    case 4:
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $value = $x + (($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c));
                        break;
                    default:
                        throw new CrossReferenceException('Invalid PNG predictor row type.', CrossReferenceException::INVALID_DATA);
                }
                $current[$i] = $value & 0xFF;
            }
            $out[] = pack('C*', ...$current);
            $previous = $current;
        }
        return implode('', $out);
    }
}
