<?php
/**
 * Counters filled in while CompressingFpdi writes a file.
 * Kept in a separate object because TCPDF clears its own properties after
 * Output(), so statistics stored on the PDF object itself would be lost.
 */

declare(strict_types=1);

namespace PDFNova\Pdf\Compression;

final class CompressionStats
{
    public int $images = 0;
    public int $recompressed = 0;
    public int $imageBytesBefore = 0;
    public int $imageBytesAfter = 0;
    public int $streamsDeflated = 0;
}
