<?php
/**
 * PDFNova's PDF writer/importer: FPDI + TCPDF, with our extended parser.
 *
 * Use this class everywhere instead of \setasign\Fpdi\Tcpdf\Fpdi so that
 * modern PDFs (PDF 1.5+ with compressed cross-references) can be imported.
 *
 *   $pdf = new \PDFNova\Pdf\NovaFpdi();
 *   $pages = $pdf->setSourceFile($path);
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

use PDFNova\Pdf\Parser\ExtendedPdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

class NovaFpdi extends Fpdi
{
    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        // Imported pages carry their own content — no TCPDF header/footer lines.
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
    }

    /**
     * Parser params: ['password' => '...'] opens an encrypted PDF (see
     * setSourceFileWithParserParams). Without it, encrypted files are rejected.
     */
    protected function getPdfParserInstance(StreamReader $streamReader, array $parserParams = [])
    {
        $password = isset($parserParams['password']) ? (string) $parserParams['password'] : null;
        return new ExtendedPdfParser($streamReader, $password);
    }
}
