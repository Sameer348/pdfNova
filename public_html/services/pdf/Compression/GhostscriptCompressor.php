<?php
/**
 * Optional Ghostscript engine. Used ONLY when config('engines.ghostscript')
 * points to an executable and proc_open is allowed — typically a VPS, not
 * Hostinger shared hosting.
 *
 * Security: arguments are passed as an array (no shell), -dSAFER is always
 * on, input/output paths are our own random storage names, and the process
 * is killed after a timeout.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Compression;

use PDFNova\Pdf\PdfProcessingException;

final class GhostscriptCompressor
{
    private const SETTINGS = [
        'high'        => '/screen',   // ~72 dpi images
        'recommended' => '/ebook',    // ~150 dpi images
        'low'         => '/printer',  // ~300 dpi images
    ];

    public static function compress(string $gsPath, string $in, string $out, string $level, int $timeoutSeconds = 150): void
    {
        $cmd = [
            $gsPath,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS=' . (self::SETTINGS[$level] ?? '/ebook'),
            '-dDetectDuplicateImages=true',
            '-dCompressFonts=true',
            '-dSAFER',
            '-dNOPAUSE',
            '-dBATCH',
            '-dQUIET',
            '-sOutputFile=' . $out,
            $in,
        ];

        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!\is_resource($process)) {
            throw new PdfProcessingException('The compression engine could not start.', 'engine_failed');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = time() + $timeoutSeconds;
        $stderr = '';
        do {
            $stderr .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (time() > $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new PdfProcessingException('Compression took too long and was stopped. Try a smaller file.', 'timeout');
            }
            usleep(100_000);
        } while (true);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = $status['exitcode'];
        proc_close($process);

        if ($exit !== 0 || !is_file($out) || filesize($out) === 0) {
            throw new PdfProcessingException('The compression engine could not process this PDF.', 'engine_failed',
                new \RuntimeException('gs exit ' . $exit . ': ' . substr($stderr, 0, 500)));
        }
    }
}
