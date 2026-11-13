<?php
/**
 * Draws text or image watermarks on top of imported PDF pages (FPDI + TCPDF).
 *
 * Geometry is in millimetres with the origin at the top-left of the visible
 * page. The watermark is rotated around its own centre; when a rotated
 * watermark would not fit the page it is scaled down automatically.
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

final class Watermarker
{
    public const POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'middle-left', 'center', 'middle-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];
    private const MARGIN = 10.0; // mm from the page edge for non-centred positions
    private const PT_TO_MM = 25.4 / 72;

    /**
     * @param array{type:string, text?:string, font_size?:int, color?:array{0:int,1:int,2:int},
     *              opacity:float, rotation:int, position:string,
     *              image_path?:string, image_type?:string, image_scale?:int} $opt
     * @param int[]|null $pages Pages to stamp (null = all)
     * @return array{pages:int, stamped:int}
     */
    public static function apply(string $path, string $label, array $opt, ?array $pages, string $outPath): array
    {
        $pdf = PdfTools::newDocument();
        $count = PdfTools::open($pdf, $path, $label);
        if ($count > PdfTools::MAX_PAGES) {
            throw new PdfProcessingException('This PDF has more than ' . PdfTools::MAX_PAGES . ' pages. Please split it first.', 'too_many_pages');
        }
        $targets = $pages === null ? null : array_fill_keys($pages, true);
        $stamped = 0;

        for ($p = 1; $p <= $count; $p++) {
            [$w, $h] = PdfTools::copyPage($pdf, $p, $label);
            if ($targets !== null && !isset($targets[$p])) {
                continue;
            }
            $opt['type'] === 'image' ? self::drawImage($pdf, $w, $h, $opt) : self::drawText($pdf, $w, $h, $opt);
            $stamped++;
        }
        PdfTools::save($pdf, $outPath);
        return ['pages' => $count, 'stamped' => $stamped];
    }

    private static function drawText(NovaFpdi $pdf, float $w, float $h, array $opt): void
    {
        $text = $opt['text'];
        [$font, $style] = self::fontFor($text);
        $size = (float) $opt['font_size'];

        $pdf->SetFont($font, $style, $size);
        $textW = $pdf->GetStringWidth($text, $font, $style, $size);
        $textH = $size * self::PT_TO_MM * 1.15;

        // Shrink to fit the page if the (rotated) text is too big.
        [$bw, $bh] = self::rotatedBox($textW, $textH, $opt['rotation']);
        $fit = min(1.0, ($w - 2 * self::MARGIN) / max($bw, 0.01), ($h - 2 * self::MARGIN) / max($bh, 0.01));
        if ($fit < 1.0) {
            $size = max(4.0, $size * $fit);
            $pdf->SetFont($font, $style, $size);
            $textW = $pdf->GetStringWidth($text, $font, $style, $size);
            $textH = $size * self::PT_TO_MM * 1.15;
            [$bw, $bh] = self::rotatedBox($textW, $textH, $opt['rotation']);
        }

        [$cx, $cy] = self::centre($w, $h, $bw, $bh, $opt['position']);
        [$r, $g, $b] = $opt['color'];

        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetAlpha($opt['opacity']);
        $pdf->StartTransform();
        $pdf->Rotate($opt['rotation'], $cx, $cy);           // TCPDF: positive = counter-clockwise
        $pdf->SetXY($cx - $textW / 2, $cy - $textH / 2);
        $pdf->Cell($textW, $textH, $text, 0, 0, 'C', false, '', 0, false, 'T', 'M');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
    }

    private static function drawImage(NovaFpdi $pdf, float $w, float $h, array $opt): void
    {
        $info = @getimagesize($opt['image_path']);
        if ($info === false || $info[0] < 1) {
            throw new PdfProcessingException('The watermark image could not be read.', 'invalid_image');
        }
        $imgW = $w * ($opt['image_scale'] / 100);
        $imgH = $imgW * $info[1] / $info[0];
        [$bw, $bh] = self::rotatedBox($imgW, $imgH, $opt['rotation']);
        $fit = min(1.0, ($w - 2 * self::MARGIN) / max($bw, 0.01), ($h - 2 * self::MARGIN) / max($bh, 0.01));
        $imgW *= $fit;
        $imgH *= $fit;
        $bw *= $fit;
        $bh *= $fit;

        [$cx, $cy] = self::centre($w, $h, $bw, $bh, $opt['position']);
        $pdf->SetAlpha($opt['opacity']);
        $pdf->StartTransform();
        $pdf->Rotate($opt['rotation'], $cx, $cy);
        $pdf->Image($opt['image_path'], $cx - $imgW / 2, $cy - $imgH / 2, $imgW, $imgH, $opt['image_type']);
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
    }

    /**
     * Pick a font that can draw every character.
     *  - Latin-1 text → core Helvetica Bold (not embedded, smallest file)
     *  - otherwise the first bundled Unicode font with full coverage:
     *    DejaVu Sans (regular — its bold face lacks proper Arabic shaping),
     *    then FreeSerif (adds Urdu letters such as ہ ے, Devanagari, ...).
     * @return array{0:string, 1:string} [font, style]
     */
    private static function fontFor(string $text): array
    {
        if (!preg_match('/[^\x{20}-\x{7E}\x{A0}-\x{FF}]/u', $text)) {
            return ['helvetica', 'B'];
        }
        $codepoints = array_unique(array_map('mb_ord', mb_str_split(preg_replace('/\s+/u', '', $text) ?? '')));
        $fontDir = \defined('K_PATH_FONTS') ? K_PATH_FONTS : dirname((new \ReflectionClass(\TCPDF::class))->getFileName()) . '/fonts/';
        foreach (['dejavusans', 'freeserif'] as $candidate) {
            $cw = [];
            include $fontDir . $candidate . '.php'; // defines $cw: codepoint => glyph width
            if (!array_diff($codepoints, array_keys($cw))) {
                return [$candidate, ''];
            }
        }
        return ['freeserif', '']; // widest coverage; truly missing glyphs show as boxes
    }

    /** Width/height of the axis-aligned box around a w×h rectangle rotated by $deg. */
    private static function rotatedBox(float $w, float $h, int $deg): array
    {
        $rad = deg2rad($deg);
        return [abs($w * cos($rad)) + abs($h * sin($rad)), abs($w * sin($rad)) + abs($h * cos($rad))];
    }

    /** Centre point of the watermark for a 3×3 position grid. */
    private static function centre(float $w, float $h, float $bw, float $bh, string $position): array
    {
        [$v, $hz] = match ($position) {
            'top-left' => ['top', 'left'], 'top-center' => ['top', 'center'], 'top-right' => ['top', 'right'],
            'middle-left' => ['middle', 'left'], 'middle-right' => ['middle', 'right'],
            'bottom-left' => ['bottom', 'left'], 'bottom-center' => ['bottom', 'center'], 'bottom-right' => ['bottom', 'right'],
            default => ['middle', 'center'],
        };
        $m = self::MARGIN;
        $cx = match ($hz) { 'left' => $m + $bw / 2, 'right' => $w - $m - $bw / 2, default => $w / 2 };
        $cy = match ($v) { 'top' => $m + $bh / 2, 'bottom' => $h - $m - $bh / 2, default => $h / 2 };
        return [$cx, $cy];
    }
}
