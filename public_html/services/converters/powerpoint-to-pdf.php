<?php
/**
 * Conversion definition for /powerpoint-to-pdf (used by api/convert.php).
 *
 * Shared hosting cannot run conversion engines (LibreOffice etc.), so this
 * conversion is performed by the external service configured in
 * includes/config.php (converter.provider). With no provider configured the
 * tool page clearly says it is not available — nothing is faked.
 * See docs/CONVERTERS_AND_OCR.md to connect a service.
 */

declare(strict_types=1);

return [
    'from'  => ['ppt', 'pptx'],
    'to'    => 'pdf',
    'label' => 'PDF',
];
