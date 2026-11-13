<?php
/**
 * Uploaded-file validation. NOTHING sent by the browser is trusted:
 * the filename, extension and MIME type are all re-checked against the
 * file's actual bytes.
 *
 * Checks, in order:
 *   1. PHP upload error codes / is_uploaded_file()
 *   2. Size (non-empty, within the visitor's limit)
 *   3. Extension allowed for this tool
 *   4. Magic bytes match the extension
 *   5. finfo-detected MIME type is one we expect for that extension
 *   6. Format-specific checks (PDF structure + encryption, image dimensions,
 *      Office ZIP structure)
 */

declare(strict_types=1);

final class UploadValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

/** Allowed MIME types (as detected by finfo) per extension. */
function allowed_mimes(): array
{
    $ooxml = ['application/zip', 'application/octet-stream'];
    $ole   = ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'];
    return [
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $ooxml),
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $ooxml),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $ooxml),
        'doc'  => array_merge(['application/msword'], $ole),
        'xls'  => array_merge(['application/vnd.ms-excel'], $ole),
        'ppt'  => array_merge(['application/vnd.ms-powerpoint'], $ole),
    ];
}

/** Canonical MIME type we store/serve for each extension. */
function canonical_mime(string $ext): string
{
    return [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint',
        'zip' => 'application/zip',
    ][$ext] ?? 'application/octet-stream';
}

/** Make a filename safe for display and Content-Disposition (never used as a path). */
function clean_display_name(string $name, string $fallbackExt = ''): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) > 150) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140) . ($ext !== '' ? '.' . $ext : '');
    }
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'file' . ($fallbackExt !== '' ? '.' . $fallbackExt : '');
    }
    return $name;
}

/**
 * Validate one entry from $_FILES for a tool.
 * @return array{ext:string, mime:string, size:int, name:string, pages:?int, encrypted:bool}
 * @throws UploadValidationException
 */
function validate_upload(array $file, array $tool, int $maxBytes): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    switch ($error) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadValidationException('This file is larger than the server allows (' . format_bytes($maxBytes) . ').', 'file_too_large', 413);
        case UPLOAD_ERR_PARTIAL:
            throw new UploadValidationException('The upload was interrupted. Please try again.', 'upload_partial');
        case UPLOAD_ERR_NO_FILE:
            throw new UploadValidationException('No file was received.', 'no_file');
        default:
            app_log('error', 'Upload failed with PHP error', ['code' => $error]);
            throw new UploadValidationException('The server could not save the upload. Please try again later.', 'upload_failed', 500);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new UploadValidationException('Invalid upload.', 'invalid_upload', 400);
    }

    $size = (int) filesize($tmp);
    if ($size <= 0) {
        throw new UploadValidationException('This file is empty.', 'empty_file');
    }
    if ($size > $maxBytes) {
        throw new UploadValidationException(
            'This file is ' . format_bytes($size) . '. The maximum for your plan is ' . format_bytes($maxBytes) . '.',
            'file_too_large',
            413
        );
    }

    $name = clean_display_name((string) ($file['name'] ?? ''));
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $tool['accept'], true)) {
        throw new UploadValidationException(
            $tool['name'] . ' accepts ' . strtoupper(implode(', ', $tool['accept'])) . ' files only.',
            'invalid_type',
            415
        );
    }

    // Magic bytes
    $head = (string) file_get_contents($tmp, false, null, 0, 1024);
    $magicOk = match ($ext) {
        'pdf'                 => str_contains($head, '%PDF-'),
        'jpg', 'jpeg'         => str_starts_with($head, "\xFF\xD8\xFF"),
        'png'                 => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
        'docx', 'xlsx', 'pptx'=> str_starts_with($head, "PK\x03\x04"),
        'doc', 'xls', 'ppt'   => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
        default               => false,
    };
    if (!$magicOk) {
        throw new UploadValidationException(
            'This file is not a valid ' . strtoupper($ext) . ' file. It may be damaged or have the wrong extension.',
            'invalid_content',
            415
        );
    }

    // finfo MIME detection
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = (string) $finfo->file($tmp);
    if (!in_array($detected, allowed_mimes()[$ext] ?? [], true)) {
        app_log('notice', 'Upload rejected by MIME check', ['ext' => $ext, 'mime' => $detected]);
        throw new UploadValidationException(
            'This file is not a valid ' . strtoupper($ext) . ' file.',
            'invalid_content',
            415
        );
    }

    $pages = null;
    $encrypted = false;

    if ($ext === 'pdf') {
        [$pages, $encrypted] = inspect_pdf($tmp);
        if ($encrypted && $tool['slug'] !== 'unlock-pdf') {
            throw new UploadValidationException(
                'This PDF is password-protected. Remove the password with Unlock PDF first.',
                'encrypted_pdf'
            );
        }
        if (!$encrypted && $tool['slug'] === 'unlock-pdf') {
            throw new UploadValidationException('This PDF is not password-protected, so there is nothing to unlock.', 'not_encrypted');
        }
    } elseif ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'png') {
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new UploadValidationException('This image could not be read. It may be damaged.', 'invalid_content', 415);
        }
        // Protect server memory from "decompression bomb" images.
        if ($info[0] * $info[1] > 60_000_000) {
            throw new UploadValidationException('This image is too large (over 60 megapixels).', 'image_too_large', 413);
        }
    } elseif (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
        $expectedDir = ['docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/'][$ext];
        $zip = new ZipArchive();
        $ok = $zip->open($tmp, ZipArchive::RDONLY) === true;
        $valid = $ok && $zip->locateName('[Content_Types].xml') !== false;
        if ($valid) {
            $valid = false;
            for ($i = 0; $i < min($zip->numFiles, 2000); $i++) {
                if (str_starts_with((string) $zip->getNameIndex($i), $expectedDir)) {
                    $valid = true;
                    break;
                }
            }
        }
        if ($ok) {
            $zip->close();
        }
        if (!$valid) {
            throw new UploadValidationException('This is not a valid ' . strtoupper($ext) . ' document.', 'invalid_content', 415);
        }
    }

    return [
        'ext'       => $ext === 'jpeg' ? 'jpg' : $ext,
        'mime'      => canonical_mime($ext),
        'size'      => $size,
        'name'      => $name,
        'pages'     => $pages,
        'encrypted' => $encrypted,
    ];
}

/**
 * Read a PDF's page count and detect encryption.
 * Encryption is detected from the trailer (/Encrypt) because FPDI cannot open
 * encrypted files. Page counting uses NovaFpdi (the same parser used for processing),
 * so a PDF that passes here can also be processed.
 *
 * @return array{0: ?int, 1: bool} [pages, encrypted]
 * @throws UploadValidationException for unreadable PDFs
 */
function inspect_pdf(string $path): array
{
    // Look for an /Encrypt entry in the last part of the file (trailer / xref stream dict).
    $size = (int) filesize($path);
    $tail = (string) file_get_contents($path, false, null, max(0, $size - 4096));
    $encrypted = (bool) preg_match('#/Encrypt\s+\d+\s+\d+\s+R#', $tail);
    if (!$encrypted && $size <= 64 * 1024 * 1024) {
        // Some writers put the trailer at the start (linearized files).
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        $encrypted = (bool) preg_match('#/Encrypt\s+\d+\s+\d+\s+R#', $head);
    }
    if ($encrypted) {
        return [null, true];
    }

    if (!class_exists(\PDFNova\Pdf\NovaFpdi::class)) {
        return [null, false];
    }
    try {
        // NovaFpdi = FPDI + our reader for PDF 1.5+ compressed cross-references.
        $pdf = new \PDFNova\Pdf\NovaFpdi();
        $pages = $pdf->setSourceFile($path);
        return [$pages, false];
    } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
        if ($e->getCode() === \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException::ENCRYPTED) {
            return [null, true];
        }
        if ($e->getCode() === \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException::COMPRESSED_XREF) {
            // Only reachable if the stock parser is used; NovaFpdi reads these files.
            throw new UploadValidationException(
                'This PDF uses a compression format our PDF engine cannot read.',
                'unsupported_pdf'
            );
        }
        app_log('notice', 'PDF rejected: cross-reference error', ['error' => $e->getMessage()]);
        throw new UploadValidationException('We could not read this PDF. It may be damaged.', 'corrupt_pdf');
    } catch (Throwable $e) {
        app_log('notice', 'PDF rejected: parser error', ['error' => $e->getMessage()]);
        throw new UploadValidationException('We could not read this PDF. It may be damaged or use an unsupported format.', 'corrupt_pdf');
    }
}
