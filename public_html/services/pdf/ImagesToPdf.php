<?php
/**
 * JPG/PNG → PDF (TCPDF). One image per page.
 *
 * Options:
 *   page_size   a4 | letter | fit        ("fit" = page matches the image)
 *   orientation auto | portrait | landscape
 *   margin      0 | 10 | 20              (mm)
 *   fit         contain | cover | original
 *       contain  — whole image visible, keeps proportions
 *       cover    — fills the area, keeps proportions, crops the overflow
 *       original — natural size at 96 dpi, shrunk only if it doesn't fit
 *
 * JPEG photos from phones often carry an EXIF orientation flag instead of
 * being stored upright; we read it ourselves (no exif extension needed) and
 * rotate the pixels so the page shows the photo the way the camera intended.
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

final class ImagesToPdf
{
    private const PX_TO_MM = 25.4 / 96;
    private const PAGE = ['a4' => [210.0, 297.0], 'letter' => [215.9, 279.4]];

    /**
     * @param array<int, array{path:string, ext:string, name:string}> $images in page order
     * @return int pages written
     */
    public static function convert(array $images, array $opt, string $outPath, string $tmpDir): int
    {
        if ($images === []) {
            throw new PdfProcessingException('Add at least one image.', 'no_files');
        }
        $pdf = PdfTools::newDocument();
        $margin = (float) $opt['margin'];

        foreach ($images as $i => $image) {
            [$path, $type, $w, $h] = self::prepare($image, $tmpDir, $i);
            $imgW = $w * self::PX_TO_MM;
            $imgH = $h * self::PX_TO_MM;

            // Page size and orientation
            if ($opt['page_size'] === 'fit') {
                [$pw, $ph] = [min(5000.0, max(20.0, $imgW + 2 * $margin)), min(5000.0, max(20.0, $imgH + 2 * $margin))];
            } else {
                [$pw, $ph] = self::PAGE[$opt['page_size']];
                $landscape = $opt['orientation'] === 'landscape' || ($opt['orientation'] === 'auto' && $w > $h);
                if ($landscape) {
                    [$pw, $ph] = [$ph, $pw];
                }
            }
            $pdf->AddPage($pw > $ph ? 'L' : 'P', [$pw, $ph]);

            // Placement inside the printable area
            $areaW = $pw - 2 * $margin;
            $areaH = $ph - 2 * $margin;
            $fit = $opt['page_size'] === 'fit' ? 'contain' : $opt['fit'];
            $scale = match ($fit) {
                'cover'    => max($areaW / $imgW, $areaH / $imgH),
                'original' => min(1.0, $areaW / $imgW, $areaH / $imgH),
                default    => min($areaW / $imgW, $areaH / $imgH),
            };
            $dw = $imgW * $scale;
            $dh = $imgH * $scale;
            $x = $margin + ($areaW - $dw) / 2;
            $y = $margin + ($areaH - $dh) / 2;

            try {
                if ($fit === 'cover') {
                    $pdf->StartTransform();
                    $pdf->Rect($margin, $margin, $areaW, $areaH, 'CNZ'); // clip to the area
                    $pdf->Image($path, $x, $y, $dw, $dh, $type);
                    $pdf->StopTransform();
                } else {
                    $pdf->Image($path, $x, $y, $dw, $dh, $type);
                }
            } catch (\Throwable $e) {
                throw new PdfProcessingException('"' . $image['name'] . '" could not be added. It may be damaged.', 'invalid_image', $e);
            }
        }

        PdfTools::save($pdf, $outPath);
        return \count($images);
    }

    /**
     * Returns [path, TCPDF type, width px, height px], fixing EXIF rotation
     * for JPEGs by writing an upright copy into $tmpDir when needed.
     */
    private static function prepare(array $image, string $tmpDir, int $index): array
    {
        $info = @getimagesize($image['path']);
        if ($info === false) {
            throw new PdfProcessingException('"' . $image['name'] . '" could not be read.', 'invalid_image');
        }
        [$w, $h] = $info;
        if ($image['ext'] === 'png') {
            return [$image['path'], 'PNG', $w, $h];
        }

        $orientation = self::jpegOrientation($image['path']);
        if (\in_array($orientation, [1, 0], true)) {
            return [$image['path'], 'JPG', $w, $h];
        }

        $img = @imagecreatefromjpeg($image['path']);
        if (!$img instanceof \GdImage) {
            return [$image['path'], 'JPG', $w, $h]; // can't fix it; use as stored
        }
        // EXIF orientations: 3 = 180°, 6 = 90° CW, 8 = 90° CCW; 2/4/5/7 also mirror.
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }
        // imagerotate(): positive angle = counter-clockwise.
        //   3 → 180°, 6 → 90° clockwise, 8 → 90° counter-clockwise,
        //   4 = mirror + 180°, 5 = mirror + 90° CCW, 7 = mirror + 90° CW
        $angle = match ($orientation) { 3, 4 => 180, 6, 7 => -90, 5, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated instanceof \GdImage) {
                $img = $rotated;
            }
        }
        $fixed = $tmpDir . '/upright-' . $index . '.jpg';
        imagejpeg($img, $fixed, 92);
        return [$fixed, 'JPG', imagesx($img), imagesy($img)];
    }

    /** Read the EXIF Orientation tag (1–8) from a JPEG; 0 when absent. */
    public static function jpegOrientation(string $path): int
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return 0;
        }
        try {
            if (fread($fh, 2) !== "\xFF\xD8") {
                return 0;
            }
            // Walk the JPEG segments until APP1 (Exif) or the image data starts.
            for ($guard = 0; $guard < 64; $guard++) {
                $marker = fread($fh, 4);
                if (\strlen($marker) < 4 || $marker[0] !== "\xFF") {
                    return 0;
                }
                $type = \ord($marker[1]);
                $len = unpack('n', substr($marker, 2, 2))[1];
                if ($type === 0xDA || $len < 2) {
                    return 0;
                }
                $data = fread($fh, $len - 2);
                if ($type === 0xE1 && str_starts_with($data, "Exif\0\0")) {
                    return self::orientationFromTiff(substr($data, 6));
                }
            }
            return 0;
        } finally {
            fclose($fh);
        }
    }

    private static function orientationFromTiff(string $tiff): int
    {
        if (\strlen($tiff) < 8) {
            return 0;
        }
        $le = substr($tiff, 0, 2) === 'II';
        $u16 = static fn(int $o): int => unpack($le ? 'v' : 'n', substr($tiff, $o, 2))[1];
        $u32 = static fn(int $o): int => unpack($le ? 'V' : 'N', substr($tiff, $o, 4))[1];
        $ifd = $u32(4);
        if ($ifd + 2 > \strlen($tiff)) {
            return 0;
        }
        $count = $u16($ifd);
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > \strlen($tiff)) {
                break;
            }
            if ($u16($entry) === 0x0112) { // Orientation
                $value = $u16($entry + 8);
                return $value >= 1 && $value <= 8 ? $value : 0;
            }
        }
        return 0;
    }
}
