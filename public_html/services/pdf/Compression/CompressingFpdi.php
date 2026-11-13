<?php
/**
 * Pure-PHP PDF compression (works on shared hosting: needs only GD + zlib).
 *
 * While FPDI copies a document object by object, this class intercepts
 * every stream it writes:
 *
 *  - Images (JPEG/DCTDecode, or raw 8-bit RGB/Gray FlateDecode) are
 *    downscaled to a maximum size and re-encoded as JPEG with GD — but ONLY
 *    when the result is at least 10% smaller than the original image.
 *  - Uncompressed non-image streams are Flate-compressed.
 *
 * Deliberately skipped (left exactly as they are): CMYK and indexed images,
 * 1-bit/16-bit images, image masks, images with /Decode arrays, and any image
 * used as a soft mask or mask of another image (re-encoding those would
 * damage transparency).
 *
 * Original code for PDFNova.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Compression;

use PDFNova\Pdf\NovaFpdi;
use PDFNova\Pdf\Parser\StreamDecoder;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfBoolean;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

class CompressingFpdi extends NovaFpdi
{
    /** Images larger than this (pixels) are left alone to protect server memory. */
    private const MAX_IMAGE_PIXELS = 40_000_000;

    private int $maxSide = 1600;
    private int $quality = 65;

    /** Source object numbers ("reader:object") that must not be re-encoded (soft masks, masks). */
    private array $protected = [];
    private ?int $currentSourceObject = null;

    /** Counters live outside this object: TCPDF clears its own properties after Output(). */
    private ?CompressionStats $stats = null;

    public function setImageOptions(int $maxSide, int $quality, CompressionStats $stats): void
    {
        $this->stats = $stats;
        $this->maxSide = max(200, $maxSide);
        $this->quality = max(20, min(95, $quality));
    }

    protected function writePdfType(PdfType $value)
    {
        if ($value instanceof PdfIndirectObject) {
            // Remember which source object is being written (needed to honour the mask list).
            $previous = $this->currentSourceObject;
            $this->currentSourceObject = $value->objectNumber;
            parent::writePdfType($value);
            $this->currentSourceObject = $previous;
            return;
        }
        if ($value instanceof PdfStream) {
            $value = $this->optimizeStream($value);
        }
        parent::writePdfType($value);
    }

    private function optimizeStream(PdfStream $stream): PdfStream
    {
        $dict = $stream->value;
        $subtype = $this->resolve(PdfDictionary::get($dict, 'Subtype'));

        if ($subtype instanceof PdfName && $subtype->value === 'Image') {
            $this->stats->images++;
            // Masks of this image are written later; protect them now.
            foreach (['SMask', 'Mask'] as $key) {
                $ref = $dict->value[$key] ?? null;
                if ($ref instanceof PdfIndirectObjectReference) {
                    $this->protected[$this->currentReaderId . ':' . $ref->value] = true;
                }
            }
            if ($this->currentSourceObject !== null && isset($this->protected[$this->currentReaderId . ':' . $this->currentSourceObject])) {
                return $stream;
            }
            try {
                return $this->recompressImage($stream) ?? $stream;
            } catch (\Throwable $e) {
                return $stream; // never fail the whole job because of one image
            }
        }

        // Plain uncompressed streams (content, fonts, metadata): deflate them.
        if ($stream->getFilters() === []) {
            $raw = $stream->getStream();
            if (\strlen($raw) > 256) {
                $packed = gzcompress($raw, 9);
                if ($packed !== false && \strlen($packed) < \strlen($raw)) {
                    $newDict = PdfDictionary::create($dict->value);
                    $newDict->value['Filter'] = PdfName::create('FlateDecode');
                    unset($newDict->value['DecodeParms']);
                    $newDict->value['Length'] = PdfNumeric::create(\strlen($packed));
                    $this->stats->streamsDeflated++;
                    return PdfStream::create($newDict, $packed);
                }
            }
        }
        return $stream;
    }

    private function recompressImage(PdfStream $stream): ?PdfStream
    {
        $dict = $stream->value;
        $mask = $this->resolve(PdfDictionary::get($dict, 'ImageMask'));
        if ($mask instanceof PdfBoolean && $mask->value) {
            return null;
        }
        if (isset($dict->value['Decode'])) {
            return null;
        }
        // A soft mask with /Matte must keep the same dimensions as its image (PDF spec 11.6.5.3).
        $smask = $this->resolve($dict->value['SMask'] ?? null);
        if ($smask instanceof PdfStream && isset($smask->value->value['Matte'])) {
            return null;
        }
        $w = $this->int(PdfDictionary::get($dict, 'Width'));
        $h = $this->int(PdfDictionary::get($dict, 'Height'));
        $bpc = $this->int(PdfDictionary::get($dict, 'BitsPerComponent'), 8);
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_IMAGE_PIXELS || $bpc !== 8) {
            return null;
        }
        $components = $this->components($this->resolve(PdfDictionary::get($dict, 'ColorSpace')));
        if ($components !== 1 && $components !== 3) {
            return null; // CMYK, Indexed, Lab, DeviceN, ... are left untouched
        }

        $filters = array_map(static fn($f) => $f instanceof PdfName ? $f->value : '', $stream->getFilters());
        $original = $stream->getStream();

        if ($filters === ['DCTDecode'] || $filters === ['DCT']) {
            if (isset($dict->value['DecodeParms'])) {
                return null;
            }
            $img = @imagecreatefromstring($original);
        } elseif ($filters === ['FlateDecode'] || $filters === ['Fl']) {
            $raw = StreamDecoder::decode($stream);
            if (\strlen($raw) < $w * $h * $components) {
                return null;
            }
            $img = @imagecreatefromstring(self::rawToPng($raw, $w, $h, $components));
        } else {
            return null;
        }
        if (!$img instanceof \GdImage) {
            return null;
        }

        $longest = max($w, $h);
        if ($longest > $this->maxSide) {
            $scale = $this->maxSide / $longest;
            $newW = max(1, (int) round($w * $scale));
            $newH = max(1, (int) round($h * $scale));
            // imagecopyresampled works on every GD build (imagescale's bicubic
            // mode returns false on some), with good-quality filtering.
            $scaled = imagecreatetruecolor($newW, $newH);
            if (!$scaled instanceof \GdImage || !imagecopyresampled($scaled, $img, 0, 0, 0, 0, $newW, $newH, $w, $h)) {
                return null;
            }
            $img = $scaled;
        }

        ob_start();
        imagejpeg($img, null, $this->quality);
        $jpeg = (string) ob_get_clean();
        $newW = imagesx($img);
        $newH = imagesy($img);

        // Only keep the new image if it is meaningfully smaller.
        if ($jpeg === '' || \strlen($jpeg) > \strlen($original) * 0.9) {
            return null;
        }

        $newDict = PdfDictionary::create($dict->value);
        $newDict->value['Filter'] = PdfName::create('DCTDecode');
        unset($newDict->value['DecodeParms']);
        $newDict->value['Width'] = PdfNumeric::create($newW);
        $newDict->value['Height'] = PdfNumeric::create($newH);
        $newDict->value['BitsPerComponent'] = PdfNumeric::create(8);
        if ($components === 1) {
            // GD always writes 3-component JPEGs, so the colour space must say RGB.
            $newDict->value['ColorSpace'] = PdfName::create('DeviceRGB');
        }
        $newDict->value['Length'] = PdfNumeric::create(\strlen($jpeg));

        $this->stats->recompressed++;
        $this->stats->imageBytesBefore += \strlen($original);
        $this->stats->imageBytesAfter += \strlen($jpeg);
        return PdfStream::create($newDict, $jpeg);
    }

    /** Wrap raw 8-bit Gray/RGB samples in a minimal PNG so GD can read them quickly. */
    private static function rawToPng(string $raw, int $w, int $h, int $components): string
    {
        $rowLength = $w * $components;
        $rows = str_split(substr($raw, 0, $rowLength * $h), $rowLength);
        $idat = gzcompress("\0" . implode("\0", $rows), 1);
        $chunk = static fn(string $type, string $data): string =>
            pack('N', \strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, $components === 3 ? 2 : 0, 0, 0, 0))
            . $chunk('IDAT', (string) $idat)
            . $chunk('IEND', '');
    }

    /** Number of colour components, or 0 when unsupported. */
    private function components(?PdfType $cs): int
    {
        if ($cs instanceof PdfName) {
            return match ($cs->value) {
                'DeviceRGB', 'CalRGB', 'RGB' => 3,
                'DeviceGray', 'CalGray', 'G' => 1,
                default => 0,
            };
        }
        if ($cs instanceof PdfArray && isset($cs->value[0])) {
            $family = $this->resolve($cs->value[0]);
            if (!$family instanceof PdfName) {
                return 0;
            }
            if ($family->value === 'ICCBased' && isset($cs->value[1])) {
                $icc = $this->resolve($cs->value[1]);
                if ($icc instanceof PdfStream) {
                    $n = $this->int(PdfDictionary::get($icc->value, 'N'));
                    return \in_array($n, [1, 3], true) ? $n : 0;
                }
            }
            if ($family->value === 'CalRGB') {
                return 3;
            }
            if ($family->value === 'CalGray') {
                return 1;
            }
        }
        return 0;
    }

    private function resolve(?PdfType $value): ?PdfType
    {
        if ($value === null) {
            return null;
        }
        $parser = $this->getPdfReader($this->currentReaderId)->getParser();
        return PdfType::resolve($value, $parser);
    }

    private function int(?PdfType $value, int $default = 0): int
    {
        $value = $this->resolve($value);
        return $value instanceof PdfNumeric ? (int) $value->value : $default;
    }
}
