<?php
/**
 * PDFNova PDF operations (server-side, pure PHP: FPDI + TCPDF + our parser).
 *
 * Each method reads input files from paths, writes the result to $outPath
 * and returns information about the result. Methods throw
 * PdfProcessingException with a user-safe message on failure.
 *
 * Phase 6: merge(). Later phases add split, rotate, organize, watermark, ...
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;

final class PdfTools
{
    /** Upper bound on pages per job, to keep requests inside shared-hosting time limits. */
    public const MAX_PAGES = 1500;

    /**
     * Merge PDFs in the given order. Every page keeps its own size and
     * orientation (rotation is applied by FPDI when importing).
     *
     * @param string[] $paths   Input files, in output order
     * @param string[] $labels  Display names, used only in error messages
     * @return array{pages:int}
     */
    public static function merge(array $paths, array $labels, string $outPath): array
    {
        if (\count($paths) < 2) {
            throw new PdfProcessingException('Add at least two PDF files to merge.', 'not_enough_files');
        }

        $pdf = self::newDocument();
        $total = 0;

        foreach ($paths as $i => $path) {
            $label = $labels[$i] ?? ('file ' . ($i + 1));
            $count = self::open($pdf, $path, $label);
            if ($total + $count > self::MAX_PAGES) {
                throw new PdfProcessingException(
                    'These files have more than ' . self::MAX_PAGES . ' pages in total. Please merge fewer pages at once.',
                    'too_many_pages'
                );
            }
            for ($page = 1; $page <= $count; $page++) {
                self::copyPage($pdf, $page, $label);
            }
            $total += $count;
        }

        self::save($pdf, $outPath);
        return ['pages' => $total];
    }

    /** Most output files one split job may create. */
    public const MAX_OUTPUT_FILES = 300;

    /**
     * Write the given pages (in that order) of one PDF into a new PDF.
     * @param int[] $pages
     */
    public static function extract(string $path, string $label, array $pages, string $outPath): void
    {
        if ($pages === []) {
            throw new PdfProcessingException('Select at least one page.', 'invalid_range');
        }
        if (\count($pages) > self::MAX_PAGES) {
            throw new PdfProcessingException('Please select at most ' . self::MAX_PAGES . ' pages at once.', 'too_many_pages');
        }
        $pdf = self::newDocument();
        self::open($pdf, $path, $label);
        foreach ($pages as $page) {
            self::copyPage($pdf, $page, $label);
        }
        self::save($pdf, $outPath);
    }

    /**
     * Split one PDF into several files.
     * @param array<int, array{pages:int[], name:string}> $parts
     * @return array<int, array{path:string, name:string, pages:int}>
     */
    public static function splitParts(string $path, string $label, array $parts, string $dir): array
    {
        if (\count($parts) > self::MAX_OUTPUT_FILES) {
            throw new PdfProcessingException(
                'This would create ' . \count($parts) . ' files. Please create at most ' . self::MAX_OUTPUT_FILES . ' at once.',
                'too_many_outputs'
            );
        }
        $outputs = [];
        foreach (array_values($parts) as $i => $part) {
            $out = $dir . '/part-' . ($i + 1) . '.pdf';
            self::extract($path, $label, $part['pages'], $out);
            $outputs[] = ['path' => $out, 'name' => $part['name'], 'pages' => \count($part['pages'])];
        }
        return $outputs;
    }

    /**
     * Encrypt a PDF with AES-256 (TCPDF mode 3).
     *
     * The "open" password is what readers type. A random owner password is
     * generated so the chosen permissions can't simply be switched off; it is
     * never stored or shown. Note: permission flags are honoured by standard
     * PDF readers, but they are not a hard technical barrier once a document
     * is open.
     *
     * @param array{print:bool, copy:bool, modify:bool} $allow
     */
    public static function protect(string $path, string $label, string $password, array $allow, string $outPath): int
    {
        $blocked = [];
        if (!$allow['print']) {
            array_push($blocked, 'print', 'print-high');
        }
        if (!$allow['copy']) {
            array_push($blocked, 'copy', 'extract');
        }
        if (!$allow['modify']) {
            array_push($blocked, 'modify', 'annot-forms', 'fill-forms', 'assemble');
        }

        $pdf = self::newDocument();
        $pdf->setProtection($blocked, $password, bin2hex(random_bytes(16)), 3);
        $count = self::open($pdf, $path, $label);
        if ($count > self::MAX_PAGES) {
            throw new PdfProcessingException('This PDF has more than ' . self::MAX_PAGES . ' pages. Please split it first.', 'too_many_pages');
        }
        for ($p = 1; $p <= $count; $p++) {
            self::copyPage($pdf, $p, $label);
        }
        self::save($pdf, $outPath);
        return $count;
    }

    /**
     * Remove the password/encryption from a PDF (pure PHP — see
     * Parser\StandardSecurityHandler). Either the open password or the
     * owner password works; an empty password unlocks "restrictions-only" PDFs.
     */
    public static function unlock(string $path, string $label, string $password, string $outPath): int
    {
        $pdf = self::newDocument();
        try {
            $count = $pdf->setSourceFileWithParserParams($path, ['password' => $password]);
        } catch (Parser\EncryptionException $e) {
            throw new PdfProcessingException(
                $e->errorCode === 'wrong_password' ? 'The password is incorrect. Please check it and try again.' : $e->getMessage(),
                $e->errorCode,
                $e
            );
        } catch (\Throwable $e) {
            throw new PdfProcessingException("\"$label\" could not be read. It may be damaged.", 'corrupt_pdf', $e);
        }
        if ($count > self::MAX_PAGES) {
            throw new PdfProcessingException('This PDF has more than ' . self::MAX_PAGES . ' pages. Please split it first.', 'too_many_pages');
        }
        for ($p = 1; $p <= $count; $p++) {
            self::copyPage($pdf, $p, $label);
        }
        self::save($pdf, $outPath);
        return $count;
    }

    /** File-name-safe base name from a display name: "My Report (v2).pdf" → "My Report (v2)". */
    public static function baseName(string $displayName, string $fallback = 'document'): string
    {
        $base = pathinfo($displayName, PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '_', $base) ?? '';
        $base = trim(mb_substr($base, 0, 60), " ._");
        return $base === '' ? $fallback : $base;
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    public static function newDocument(): NovaFpdi
    {
        $pdf = new NovaFpdi();
        $pdf->SetCreator('PDFNova');
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->SetCompression(true);
        return $pdf;
    }

    /** Open a source file and return its page count. */
    public static function open(NovaFpdi $pdf, string $path, string $label): int
    {
        try {
            return $pdf->setSourceFile($path);
        } catch (CrossReferenceException $e) {
            if ($e->getCode() === CrossReferenceException::ENCRYPTED) {
                throw new PdfProcessingException("\"$label\" is password-protected. Unlock it first.", 'encrypted_pdf', $e);
            }
            throw new PdfProcessingException("\"$label\" could not be read. It may be damaged.", 'corrupt_pdf', $e);
        } catch (PdfParserException $e) {
            // Only "not implemented" codes mean a valid-but-unsupported PDF; the rest are damaged files.
            if (in_array($e->getCode(), [PdfParserException::NOT_IMPLEMENTED, PdfParserException::IMPLEMENTED_IN_FPDI_PDF_PARSER], true)) {
                throw new PdfProcessingException("\"$label\" uses PDF features we can't process yet.", 'unsupported_pdf', $e);
            }
            throw new PdfProcessingException("\"$label\" could not be read. It may be damaged.", 'corrupt_pdf', $e);
        } catch (\Throwable $e) {
            throw new PdfProcessingException("\"$label\" could not be read. It may be damaged.", 'corrupt_pdf', $e);
        }
    }

    /**
     * Copy one page of the current source file onto a new page of the same
     * visible size and orientation.
     *
     * $rotate (0, 90, 180, 270 — clockwise) is applied losslessly through the
     * page's /Rotate entry: the content itself is not redrawn, exactly like
     * rotating a page in a PDF viewer. (Any rotation the source page already
     * had is baked in by FPDI when the page is imported.)
     */
    public static function copyPage(NovaFpdi $pdf, int $pageNumber, string $label, int $rotate = 0): array
    {
        $rotate = (($rotate % 360) + 360) % 360;
        if ($rotate % 90 !== 0) {
            throw new PdfProcessingException('Pages can only be rotated in steps of 90°.', 'invalid_option');
        }
        try {
            $tpl = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($tpl);
            $w = (float) $size['width'];
            $h = (float) $size['height'];
            $format = [$w, $h];
            if ($rotate !== 0) {
                $format['Rotate'] = $rotate;
            }
            $pdf->AddPage($w > $h ? 'L' : 'P', $format);
            $pdf->useTemplate($tpl, 0, 0, $w, $h);
            return [$w, $h];
        } catch (\Throwable $e) {
            throw new PdfProcessingException("Page $pageNumber of \"$label\" could not be processed.", 'page_failed', $e);
        }
    }

    /**
     * Build a new PDF from a page plan: [['page' => 3, 'rotate' => 90], ...].
     * Order = output order; pages not listed are left out (deleted).
     * Used by Rotate PDF and Organize PDF.
     *
     * @param array<int, array{page:int, rotate:int}> $plan
     */
    public static function rebuild(string $path, string $label, array $plan, string $outPath): void
    {
        if ($plan === []) {
            throw new PdfProcessingException('Keep at least one page.', 'no_pages');
        }
        if (\count($plan) > self::MAX_PAGES) {
            throw new PdfProcessingException('Please keep at most ' . self::MAX_PAGES . ' pages at once.', 'too_many_pages');
        }
        $pdf = self::newDocument();
        $count = self::open($pdf, $path, $label);
        foreach ($plan as $item) {
            if ($item['page'] < 1 || $item['page'] > $count) {
                throw new PdfProcessingException("Page {$item['page']} doesn't exist in \"$label\".", 'invalid_range');
            }
            self::copyPage($pdf, $item['page'], $label, $item['rotate']);
        }
        self::save($pdf, $outPath);
    }

    /** Write the document to disk. */
    public static function save(NovaFpdi $pdf, string $outPath): void
    {
        try {
            $pdf->Output($outPath, 'F');
        } catch (\Throwable $e) {
            throw new PdfProcessingException('The result could not be saved. Please try again.', 'save_failed', $e);
        }
        if (!is_file($outPath) || filesize($outPath) === 0) {
            throw new PdfProcessingException('The result could not be saved. Please try again.', 'save_failed');
        }
    }
}
