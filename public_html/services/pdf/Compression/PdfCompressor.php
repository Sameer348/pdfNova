<?php
/**
 * Compress PDF — chooses the best available engine and reports honest numbers.
 *
 *   Ghostscript (if configured)  → best results; falls back to PHP on failure
 *   Pure PHP (always available)  → image downscaling/re-encoding + stream deflate
 *
 * If the result is NOT smaller than the original, the original bytes are
 * returned unchanged and 'improved' is false — we never hand back a bigger file.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Compression;

use PDFNova\Pdf\PdfProcessingException;
use PDFNova\Pdf\PdfTools;

final class PdfCompressor
{
    /** Pure-PHP settings per level: longest image side (px) and JPEG quality. */
    public const LEVELS = [
        'high'        => ['max_side' => 1000, 'quality' => 45],
        'recommended' => ['max_side' => 1600, 'quality' => 65],
        'low'         => ['max_side' => 2400, 'quality' => 80],
    ];

    /**
     * @return array{engine:string, original:int, result:int, improved:bool, pages:int, images:int, images_recompressed:int}
     */
    public static function compress(string $in, string $label, string $level, string $out, ?string $gsPath = null): array
    {
        if (!isset(self::LEVELS[$level])) {
            throw new PdfProcessingException('Choose a compression level.', 'invalid_option');
        }
        $original = (int) filesize($in);
        $engine = 'php';
        $stats = new CompressionStats();
        $pages = 0;

        $done = false;
        if ($gsPath !== null) {
            try {
                GhostscriptCompressor::compress($gsPath, $in, $out, $level);
                $engine = 'ghostscript';
                $done = true;
            } catch (PdfProcessingException $e) {
                // fall back to the pure-PHP engine below
            }
        }

        if (!$done) {
            $pdf = new CompressingFpdi();
            $pdf->SetCreator('PDFNova');
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetMargins(0, 0, 0);
            $pdf->setImageOptions(self::LEVELS[$level]['max_side'], self::LEVELS[$level]['quality'], $stats);
            $pages = PdfTools::open($pdf, $in, $label);
            if ($pages > PdfTools::MAX_PAGES) {
                throw new PdfProcessingException('This PDF has more than ' . PdfTools::MAX_PAGES . ' pages. Please split it first.', 'too_many_pages');
            }
            for ($p = 1; $p <= $pages; $p++) {
                PdfTools::copyPage($pdf, $p, $label);
            }
            PdfTools::save($pdf, $out);   // images are re-encoded while the file is written
        }

        clearstatcache(true, $out);
        $result = (int) filesize($out);
        $improved = $result < $original;
        if (!$improved) {
            // Never return a bigger file: give back the original bytes.
            if (!copy($in, $out)) {
                throw new PdfProcessingException('The result could not be saved. Please try again.', 'save_failed');
            }
            $result = $original;
        }

        return [
            'engine'              => $engine,
            'original'            => $original,
            'result'              => $result,
            'improved'            => $improved,
            'pages'               => $pages,
            'images'              => $stats->images,
            'images_recompressed' => $stats->recompressed,
        ];
    }
}
