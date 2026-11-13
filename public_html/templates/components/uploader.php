<?php
/**
 * FileUploader drop zone (server-rendered; behaviour in assets/js/uploader.js).
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);

$exts      = $tool['accept'];
$acceptAttr = implode(',', array_map(static fn(string $e): string => '.' . $e, $exts));
$typeLabel = strtoupper(implode(', ', array_unique(array_map(static fn(string $e): string => $e === 'jpeg' ? 'jpg' : $e, $exts))));
$noun      = $tool['multiple'] ? 'files' : 'file';
?>
<div class="dropzone" data-dropzone>
    <div class="dropzone-icon" aria-hidden="true"><?= icon('upload', 'icon icon-xl') ?></div>
    <p class="dropzone-title">Drop your <?= e($typeLabel) ?> <?= e($noun) ?> here</p>
    <p class="dropzone-or">or</p>
    <button class="btn btn-primary btn-lg" type="button" data-browse>
        <?= icon('plus') ?> Browse <?= e($noun) ?>
    </button>
    <p class="hint dropzone-limits">
        <?= e($typeLabel) ?> · up to <?= e(format_bytes($limits['max_file_bytes'])) ?> per file<?php if ($tool['multiple']): ?> · up to <?= (int) $limits['max_files'] ?> files<?php endif; ?>
    </p>
    <input class="visually-hidden" type="file" tabindex="-1" aria-hidden="true" data-file-input
           accept="<?= e($acceptAttr) ?>"<?= $tool['multiple'] ? ' multiple' : '' ?>>
</div>
