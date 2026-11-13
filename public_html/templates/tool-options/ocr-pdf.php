<?php
/**
 * OCR PDF — options panel (language of the document).
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);

$ocr = \PDFNova\Ocr\OcrFactory::make((string) config('ocr.provider', 'none'), trim((string) config('ocr.api_key', '')));
?>
<div class="field">
    <label class="label" for="ocr-language">Document language</label>
    <select class="select" id="ocr-language" name="language">
        <?php foreach ($ocr->languages() as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= $code === 'eng' ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <p class="hint">Choosing the right language gives much better recognition.</p>
</div>
<div class="alert alert-info"><?= icon('info') ?><p>OCR is performed by an external OCR service. Your file is sent to it for processing.</p></div>
