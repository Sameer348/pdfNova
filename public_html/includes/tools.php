<?php
/**
 * TOOL REGISTRY — the single source of truth for every PDF tool.
 *
 * One entry here drives: the tool page (pages/tool.php), navigation menus,
 * footer links, homepage cards, sitemap.xml and API validation.
 * To add a tool: add an entry, an options template (optional) and an API endpoint.
 *
 * Keys:
 *   name        Display name
 *   category    organize | optimize | convert-to | convert-from | edit | security
 *   icon        icons.php key
 *   short       One-line description (cards, menus)
 *   title       <title> tag (SEO)
 *   description Meta description (SEO, ≤ 160 chars)
 *   heading     H1 on the tool page
 *   subtitle    Text under the H1
 *   accept      Allowed input extensions (validated server-side by content too)
 *   multiple    Accepts more than one file
 *   min_files   Minimum files required to run
 *   engine      Which backend runs it:
 *                 pdf        → FPDI/TCPDF on the server (works on shared hosting)
 *                 client     → runs in the browser (pdf.js); server not needed
 *                 compress   → Ghostscript if available, else limited fallback
 *                 unlock     → qpdf or external API (adapter)
 *                 converter  → external conversion API (adapter)
 *                 ocr        → external OCR API (adapter)
 *   popular     Position in the homepage "Popular tools" grid (null = not shown)
 */

declare(strict_types=1);

function tool_categories(): array
{
    return [
        'organize'     => ['label' => 'Organize PDF',    'tone' => 'red'],
        'optimize'     => ['label' => 'Optimize PDF',    'tone' => 'green'],
        'convert-to'   => ['label' => 'Convert to PDF',  'tone' => 'amber'],
        'convert-from' => ['label' => 'Convert from PDF','tone' => 'blue'],
        'edit'         => ['label' => 'Edit PDF',        'tone' => 'violet'],
        'security'     => ['label' => 'PDF Security',    'tone' => 'cyan'],
    ];
}

function tools(): array
{
    static $tools = null;
    if ($tools !== null) {
        return $tools;
    }

    $tools = [
        // ---------------- Organize ----------------
        'merge-pdf' => [
            'name' => 'Merge PDF', 'category' => 'organize', 'icon' => 'merge',
            'short' => 'Combine several PDFs into one file in the order you choose.',
            'title' => 'Merge PDF – Combine PDF Files Online | PDFNova',
            'description' => 'Merge multiple PDF files into a single document. Drag to reorder, then download your combined PDF in seconds.',
            'heading' => 'Merge PDF files',
            'subtitle' => 'Combine multiple PDFs into one document. Drag the files to set their order.',
            'accept' => ['pdf'], 'multiple' => true, 'min_files' => 2,
            'engine' => 'pdf', 'popular' => 1,
        ],
        'split-pdf' => [
            'name' => 'Split PDF', 'category' => 'organize', 'icon' => 'split',
            'short' => 'Extract page ranges or split a PDF into several files.',
            'title' => 'Split PDF – Extract Pages or Split by Range | PDFNova',
            'description' => 'Split a PDF by page ranges, extract selected pages or split every N pages. Multiple results are bundled in a ZIP.',
            'heading' => 'Split PDF',
            'subtitle' => 'Extract the pages you need, split by ranges, or cut the document every N pages.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => 2,
        ],
        'organize-pdf' => [
            'name' => 'Organize PDF', 'category' => 'organize', 'icon' => 'organize',
            'short' => 'Reorder, delete, rotate and extract pages visually.',
            'title' => 'Organize PDF – Reorder, Delete & Rotate Pages | PDFNova',
            'description' => 'Rearrange PDF pages with drag and drop, delete or rotate pages, and extract a selection into a new PDF.',
            'heading' => 'Organize PDF pages',
            'subtitle' => 'Drag page thumbnails to reorder them, rotate or delete pages, then save your new PDF.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => null,
        ],
        'rotate-pdf' => [
            'name' => 'Rotate PDF', 'category' => 'organize', 'icon' => 'rotate',
            'short' => 'Rotate all pages or only the pages you select.',
            'title' => 'Rotate PDF – Rotate Pages Online | PDFNova',
            'description' => 'Rotate every page of a PDF or just selected pages by 90° or 180°, then download the corrected document.',
            'heading' => 'Rotate PDF',
            'subtitle' => 'Turn all pages or only selected ones 90° clockwise, counter-clockwise or 180°.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => 9,
        ],

        // ---------------- Optimize ----------------
        'compress-pdf' => [
            'name' => 'Compress PDF', 'category' => 'optimize', 'icon' => 'compress',
            'short' => 'Reduce PDF file size for email and uploads.',
            'title' => 'Compress PDF – Reduce PDF File Size | PDFNova',
            'description' => 'Make PDF files smaller. Choose a compression level and see the original size, new size and percentage saved.',
            'heading' => 'Compress PDF',
            'subtitle' => 'Reduce the size of your PDF. We show the exact size before and after.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'compress', 'popular' => 3,
        ],
        'ocr-pdf' => [
            'name' => 'OCR PDF', 'category' => 'optimize', 'icon' => 'ocr',
            'short' => 'Turn scanned PDFs into searchable, selectable text.',
            'title' => 'OCR PDF – Make Scanned PDFs Searchable | PDFNova',
            'description' => 'Recognize text in scanned PDF documents and download a searchable PDF. Choose the document language for best results.',
            'heading' => 'OCR PDF',
            'subtitle' => 'Recognize text in scanned documents to create a searchable PDF.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'ocr', 'popular' => null,
        ],

        // ---------------- Convert to PDF ----------------
        'jpg-to-pdf' => [
            'name' => 'JPG to PDF', 'category' => 'convert-to', 'icon' => 'image',
            'short' => 'Turn JPG and PNG images into a PDF document.',
            'title' => 'JPG to PDF – Convert Images to PDF | PDFNova',
            'description' => 'Convert JPG and PNG images to PDF. Reorder images and choose page size, orientation, margins and fit.',
            'heading' => 'JPG to PDF',
            'subtitle' => 'Convert JPG and PNG images into a single PDF. Set page size, orientation and margins.',
            'accept' => ['jpg', 'jpeg', 'png'], 'multiple' => true, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => 6,
        ],
        'word-to-pdf' => [
            'name' => 'Word to PDF', 'category' => 'convert-to', 'icon' => 'doc-text',
            'short' => 'Convert DOC and DOCX documents to PDF.',
            'title' => 'Word to PDF – Convert DOCX to PDF | PDFNova',
            'description' => 'Convert Microsoft Word documents (DOC, DOCX) to PDF while keeping your layout.',
            'heading' => 'Word to PDF',
            'subtitle' => 'Convert Word documents (DOC, DOCX) to PDF.',
            'accept' => ['doc', 'docx'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => 8,
        ],
        'excel-to-pdf' => [
            'name' => 'Excel to PDF', 'category' => 'convert-to', 'icon' => 'table',
            'short' => 'Convert XLS and XLSX spreadsheets to PDF.',
            'title' => 'Excel to PDF – Convert XLSX to PDF | PDFNova',
            'description' => 'Convert Microsoft Excel spreadsheets (XLS, XLSX) into PDF documents.',
            'heading' => 'Excel to PDF',
            'subtitle' => 'Convert Excel spreadsheets (XLS, XLSX) to PDF.',
            'accept' => ['xls', 'xlsx'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => null,
        ],
        'powerpoint-to-pdf' => [
            'name' => 'PowerPoint to PDF', 'category' => 'convert-to', 'icon' => 'presentation',
            'short' => 'Convert PPT and PPTX presentations to PDF.',
            'title' => 'PowerPoint to PDF – Convert PPTX to PDF | PDFNova',
            'description' => 'Convert Microsoft PowerPoint presentations (PPT, PPTX) into PDF files.',
            'heading' => 'PowerPoint to PDF',
            'subtitle' => 'Convert PowerPoint presentations (PPT, PPTX) to PDF.',
            'accept' => ['ppt', 'pptx'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => null,
        ],

        // ---------------- Convert from PDF ----------------
        'pdf-to-jpg' => [
            'name' => 'PDF to JPG', 'category' => 'convert-from', 'icon' => 'image',
            'short' => 'Save PDF pages as high-quality JPG images.',
            'title' => 'PDF to JPG – Convert PDF Pages to Images | PDFNova',
            'description' => 'Convert PDF pages to JPG images. Choose quality and pages; multiple images download as a ZIP.',
            'heading' => 'PDF to JPG',
            'subtitle' => 'Convert each page into a JPG image. Pages are rendered right in your browser.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'client', 'popular' => 5,
        ],
        'pdf-to-word' => [
            'name' => 'PDF to Word', 'category' => 'convert-from', 'icon' => 'doc-text',
            'short' => 'Convert PDFs into editable Word documents.',
            'title' => 'PDF to Word – Convert PDF to DOCX | PDFNova',
            'description' => 'Convert PDF files into editable Microsoft Word (DOCX) documents.',
            'heading' => 'PDF to Word',
            'subtitle' => 'Convert a PDF into an editable Word (DOCX) document.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => 4,
        ],
        'pdf-to-excel' => [
            'name' => 'PDF to Excel', 'category' => 'convert-from', 'icon' => 'table',
            'short' => 'Pull tables from PDFs into Excel spreadsheets.',
            'title' => 'PDF to Excel – Convert PDF Tables to XLSX | PDFNova',
            'description' => 'Extract tables from PDF documents into editable Excel (XLSX) spreadsheets.',
            'heading' => 'PDF to Excel',
            'subtitle' => 'Extract tables from a PDF into an Excel (XLSX) spreadsheet.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => 7,
        ],
        'pdf-to-powerpoint' => [
            'name' => 'PDF to PowerPoint', 'category' => 'convert-from', 'icon' => 'presentation',
            'short' => 'Turn PDF pages into PowerPoint slides.',
            'title' => 'PDF to PowerPoint – Convert PDF to PPTX | PDFNova',
            'description' => 'Convert PDF documents into PowerPoint (PPTX) presentations.',
            'heading' => 'PDF to PowerPoint',
            'subtitle' => 'Convert a PDF into a PowerPoint (PPTX) presentation.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'converter', 'popular' => null,
        ],

        // ---------------- Edit ----------------
        'watermark-pdf' => [
            'name' => 'Watermark PDF', 'category' => 'edit', 'icon' => 'watermark',
            'short' => 'Stamp text or an image over your PDF pages.',
            'title' => 'Watermark PDF – Add Text or Image Watermarks | PDFNova',
            'description' => 'Add a text or image watermark to PDF pages. Control font size, opacity, rotation, position and color.',
            'heading' => 'Add a watermark',
            'subtitle' => 'Stamp text or an image on your pages. Adjust size, opacity, rotation, position and color.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => 10,
        ],

        // ---------------- Security ----------------
        'protect-pdf' => [
            'name' => 'Protect PDF', 'category' => 'security', 'icon' => 'lock',
            'short' => 'Add a password to keep your PDF private.',
            'title' => 'Protect PDF – Add a Password to PDF | PDFNova',
            'description' => 'Encrypt a PDF with a password so only people with the password can open it.',
            'heading' => 'Protect PDF',
            'subtitle' => 'Encrypt your PDF with a password.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'pdf', 'popular' => 11,
        ],
        'unlock-pdf' => [
            'name' => 'Unlock PDF', 'category' => 'security', 'icon' => 'unlock',
            'short' => 'Remove the password from a PDF you can open.',
            'title' => 'Unlock PDF – Remove PDF Password | PDFNova',
            'description' => 'Remove password protection from a PDF when you know the password.',
            'heading' => 'Unlock PDF',
            'subtitle' => 'Remove the password from a PDF. You need to know the current password.',
            'accept' => ['pdf'], 'multiple' => false, 'min_files' => 1,
            'engine' => 'unlock', 'popular' => 12,
        ],
    ];

    // endpoint: API file in /api that runs the tool (null = runs in the browser)
    // action:   label of the main button
    $runtime = [
        'merge-pdf'         => ['merge',      'Merge PDF'],
        'split-pdf'         => ['split',      'Split PDF'],
        'organize-pdf'      => ['organize',   'Save changes'],
        'rotate-pdf'        => ['rotate',     'Rotate PDF'],
        'compress-pdf'      => ['compress',   'Compress PDF'],
        'ocr-pdf'           => ['ocr',        'Make searchable'],
        'jpg-to-pdf'        => ['jpg-to-pdf', 'Convert to PDF'],
        'word-to-pdf'       => ['convert',    'Convert to PDF'],
        'excel-to-pdf'      => ['convert',    'Convert to PDF'],
        'powerpoint-to-pdf' => ['convert',    'Convert to PDF'],
        'pdf-to-jpg'        => [null,         'Convert to JPG'],
        'pdf-to-word'       => ['convert',    'Convert to Word'],
        'pdf-to-excel'      => ['convert',    'Convert to Excel'],
        'pdf-to-powerpoint' => ['convert',    'Convert to PowerPoint'],
        'watermark-pdf'     => ['watermark',  'Add watermark'],
        'protect-pdf'       => ['protect',    'Protect PDF'],
        'unlock-pdf'        => ['unlock',     'Unlock PDF'],
    ];

    foreach ($tools as $slug => &$tool) {
        $tool['slug']     = $slug;
        $tool['endpoint'] = $runtime[$slug][0] ?? null;
        $tool['action']   = $runtime[$slug][1] ?? $tool['name'];
        $tool['upload']   = $tool['engine'] !== 'client'; // client tools never upload
        // Extra single files a tool can take besides its main input (role => extensions).
        $tool['extra_uploads'] = $slug === 'watermark-pdf' ? ['watermark-image' => ['png', 'jpg', 'jpeg']] : [];
    }
    unset($tool);
    return $tools;
}

function tool(string $slug): ?array
{
    return tools()[$slug] ?? null;
}

/** Tools grouped by category, preserving category order. */
function tools_by_category(): array
{
    $grouped = array_fill_keys(array_keys(tool_categories()), []);
    foreach (tools() as $slug => $tool) {
        $grouped[$tool['category']][$slug] = $tool;
    }
    return array_filter($grouped);
}

/** Homepage "Popular tools", ordered. */
function popular_tools(): array
{
    $popular = array_filter(tools(), static fn(array $t): bool => $t['popular'] !== null);
    uasort($popular, static fn(array $a, array $b): int => $a['popular'] <=> $b['popular']);
    return $popular;
}

/**
 * Is an optional server binary (Ghostscript, qpdf) configured AND runnable?
 * Shared hosting usually has neither the binary nor exec functions enabled.
 */
function engine_binary_available(string $name): bool
{
    static $cache = [];
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }
    $path = (string) config("engines.$name", '');
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $cache[$name] = $path !== ''
        && function_exists('proc_open') && !in_array('proc_open', $disabled, true)
        && @is_file($path) && @is_executable($path);
    return $cache[$name];
}

/**
 * True when an external API adapter is fully configured: a provider we have an
 * adapter for, an API key, and cURL to reach it.
 */
function external_api_configured(string $section): bool
{
    $supported = ['converter' => ['cloudconvert'], 'ocr' => ['ocrspace']][$section] ?? [];
    return in_array((string) config("$section.provider", 'none'), $supported, true)
        && trim((string) config("$section.api_key", '')) !== ''
        && function_exists('curl_init');
}

/**
 * Real availability of a tool on THIS server, based on installed libraries
 * and configuration — never hard-coded. Used to show honest badges and to
 * disable tools that cannot run.
 *
 * @return array{state: 'available'|'limited'|'unavailable', note: string}
 */
function tool_status(array $tool): array
{
    $pdfLib = class_exists(\PDFNova\Pdf\NovaFpdi::class);

    switch ($tool['engine']) {
        case 'client':
            return ['state' => 'available', 'note' => 'Runs in your browser.'];

        case 'pdf':
            return $pdfLib
                ? ['state' => 'available', 'note' => '']
                : ['state' => 'unavailable', 'note' => 'The PDF library is not installed on this server.'];

        case 'compress':
            if (!$pdfLib || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
                return ['state' => 'unavailable', 'note' => 'Compression needs the PDF library and PHP\'s GD image extension.'];
            }
            return engine_binary_available('ghostscript')
                ? ['state' => 'available', 'note' => '']
                : ['state' => 'limited', 'note' => 'Images are resized and re-compressed to reduce size. Bookmarks, form fields and attachments are not kept in the compressed file.'];

        case 'unlock':
            // Pure-PHP decryption (services/pdf/Parser/StandardSecurityHandler.php) needs OpenSSL for AES.
            return $pdfLib && extension_loaded('openssl')
                ? ['state' => 'available', 'note' => '']
                : ['state' => 'unavailable', 'note' => 'Unlocking needs PHP\'s OpenSSL extension, which is not enabled on this server.'];

        case 'converter':
            return external_api_configured('converter')
                ? ['state' => 'available', 'note' => '']
                : ['state' => 'unavailable', 'note' => 'This conversion needs an external conversion service that is not configured yet.'];

        case 'ocr':
            return external_api_configured('ocr')
                ? ['state' => 'available', 'note' => '']
                : ['state' => 'unavailable', 'note' => 'OCR needs an external OCR service that is not configured yet.'];
    }
    return ['state' => 'unavailable', 'note' => 'Unknown engine.'];
}

/**
 * Has the processing code for this tool been built yet? (Its API endpoint
 * file exists, or it runs entirely in the browser and its script exists.)
 * Tool pages disable the action button until this is true — no fake buttons.
 */
function tool_ready(array $tool): bool
{
    if ($tool['endpoint'] === null) {
        return is_file(BASE_PATH . '/assets/js/tools/' . $tool['slug'] . '.js');
    }
    return is_file(BASE_PATH . '/api/' . $tool['endpoint'] . '.php');
}

function tool_available(array $tool): bool
{
    return tool_status($tool)['state'] !== 'unavailable';
}

/** Tools NOT in the popular list (for the "More tools" row). */
function other_tools(): array
{
    return array_filter(tools(), static fn(array $t): bool => $t['popular'] === null);
}

/** CSS tone class for a tool's icon tile, e.g. "tone-red". */
function tool_tone(array $tool): string
{
    return 'tone-' . (tool_categories()[$tool['category']]['tone'] ?? 'red');
}
