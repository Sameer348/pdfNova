<?php
/**
 * Builds a ZIP archive from files on disk (PHP's ZipArchive — enabled on
 * Hostinger by default). Entry names are sanitised and made unique.
 */

declare(strict_types=1);

namespace PDFNova\Pdf;

final class ZipBuilder
{
    /**
     * @param array<int, array{path:string, name:string}> $files
     */
    public static function create(array $files, string $zipPath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new PdfProcessingException('ZIP support is not available on this server.', 'zip_unavailable');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new PdfProcessingException('The ZIP file could not be created.', 'zip_failed');
        }

        $used = [];
        foreach ($files as $file) {
            $name = self::uniqueName(self::safeName($file['name']), $used);
            // PDFs and JPGs are already compressed: storing is faster and barely larger.
            if (!$zip->addFile($file['path'], $name)) {
                $zip->close();
                throw new PdfProcessingException('The ZIP file could not be created.', 'zip_failed');
            }
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);
        }

        if (!$zip->close() || !is_file($zipPath)) {
            throw new PdfProcessingException('The ZIP file could not be created.', 'zip_failed');
        }
    }

    /** No directories, no control characters, no leading dots. */
    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F<>:"|?*]+/u', '_', $name) ?? 'file';
        $name = ltrim($name, '.');
        return $name === '' ? 'file' : $name;
    }

    private static function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = $ext !== '' ? substr($name, 0, -\strlen($ext) - 1) : $name;
        for ($i = 2; isset($used[strtolower($candidate)]); $i++) {
            $candidate = $base . " ($i)" . ($ext !== '' ? ".$ext" : '');
        }
        $used[strtolower($candidate)] = true;
        return $candidate;
    }
}
