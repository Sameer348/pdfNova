<?php
/**
 * PDFNova icon set — original, minimal 24×24 stroke icons rendered as inline
 * SVG (no icon font, no extra HTTP requests, inherits currentColor).
 *
 * Usage: <?= icon('merge') ?>  or  <?= icon('lock', 'icon icon-lg', 'Protected') ?>
 */

declare(strict_types=1);

function icon_paths(): array
{
    return [
        // ---- Tools -------------------------------------------------------
        'merge'        => '<rect x="3" y="3" width="11" height="14" rx="2"/><path d="M10 7h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-2"/><path d="M8.5 7.5v5M6 10h5"/>',
        'split'        => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M2 12h3M8 12h2.5M13.5 12H16M19 12h3"/><path d="M9 7h6M9 16.5h6"/>',
        'compress'     => '<path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/>',
        'doc-text'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M8.5 12.5h7M8.5 16.5h5"/>',
        'table'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/>',
        'presentation' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M12 16v4M8 20h8M7 12l3-3 2 2 4-4"/>',
        'image'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L6 21"/>',
        'rotate'       => '<path d="M20.5 12a8.5 8.5 0 1 1-2.5-6l2.5 2.5"/><path d="M20.5 3.5v5h-5"/>',
        'organize'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'watermark'    => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/><path d="M9.5 14.5a2.5 2.5 0 0 0 2.5 2.5"/>',
        'lock'         => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2"/>',
        'unlock'       => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2M12 15v2"/>',
        'ocr'          => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M8 9h8M8 12h8M8 15h5"/>',
        'file'         => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',

        // ---- UI ----------------------------------------------------------
        'menu'         => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'=> '<path d="m9 6 6 6-6 6"/>',
        'arrow-right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'upload'       => '<path d="M12 15V4M7 9l5-5 5 5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
        'download'     => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
        'check'        => '<path d="m5 12 5 5L20 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5v.01"/>',
        'shield'       => '<path d="M12 3 5 6v5c0 4.5 3 8.5 7 10 4-1.5 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'zap'          => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'smartphone'   => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'sparkles'     => '<path d="m12 3 1.8 4.7 4.7 1.8-4.7 1.8L12 16l-1.8-4.7-4.7-1.8 4.7-1.8z"/><path d="m19 15 .8 2.2 2.2.8-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'cursor'       => '<path d="m4 4 7 17 2.5-7.5L21 11z"/>',
        'box'          => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'user'         => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout'       => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'settings'     => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'folder'       => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'activity'     => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'credit-card'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'trash'        => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/>',
        'grip'         => '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" stroke-width="3"/>',
        'refresh'      => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
        'eye'          => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'      => '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'mail'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
}

function icon(string $name, string $class = 'icon', ?string $label = null): string
{
    $paths = icon_paths()[$name] ?? icon_paths()['file'];
    $a11y = $label === null
        ? 'aria-hidden="true" focusable="false"'
        : 'role="img" aria-label="' . e($label) . '"';
    return '<svg class="' . e($class) . '" ' . $a11y . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

/** The PDFNova logo: original mark (document with a four-point "nova" star) + wordmark. */
function logo(string $class = 'logo'): string
{
    return '<span class="' . e($class) . '">'
        . '<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<path class="logo-doc" d="M7 2h12.5L27 9.5V27a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3z"/>'
        . '<path class="logo-fold" d="M19.5 2v5.5a2 2 0 0 0 2 2H27z"/>'
        . '<path class="logo-star" d="M15.5 11.5c.5 3.6 1.4 4.5 5 5-3.6.5-4.5 1.4-5 5-.5-3.6-1.4-4.5-5-5 3.6-.5 4.5-1.4 5-5z"/>'
        . '</svg>'
        . '<span class="logo-text">PDF<span class="logo-accent">Nova</span></span>'
        . '</span>';
}
