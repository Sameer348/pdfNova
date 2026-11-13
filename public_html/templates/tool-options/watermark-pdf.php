<?php
/**
 * Watermark PDF — options panel. Behaviour + live preview: assets/js/tools/watermark-pdf.js
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);

$positions = [
    'top-left' => 'Top left', 'top-center' => 'Top', 'top-right' => 'Top right',
    'middle-left' => 'Left', 'center' => 'Centre', 'middle-right' => 'Right',
    'bottom-left' => 'Bottom left', 'bottom-center' => 'Bottom', 'bottom-right' => 'Bottom right',
];
?>
<fieldset class="field">
    <legend class="label">Watermark type</legend>
    <div class="segmented">
        <div class="segment"><input type="radio" name="type" id="wm-text" value="text" checked><label for="wm-text"><strong>Text</strong></label></div>
        <div class="segment"><input type="radio" name="type" id="wm-image" value="image"><label for="wm-image"><strong>Image</strong></label></div>
    </div>
</fieldset>

<div class="stack" data-wm-panel="text">
    <div class="field">
        <label class="label" for="wm-text-input">Text</label>
        <input class="input" id="wm-text-input" name="text" type="text" maxlength="100" value="CONFIDENTIAL" autocomplete="off">
    </div>
    <div class="form-row form-row-2">
        <div class="field">
            <div class="label-row"><label class="label" for="wm-size">Font size</label><output class="hint" data-out="font_size">60 pt</output></div>
            <input id="wm-size" name="font_size" type="range" min="8" max="200" step="1" value="60">
        </div>
        <div class="field">
            <label class="label" for="wm-color">Colour</label>
            <input id="wm-color" name="color" type="color" value="#d21f45">
        </div>
    </div>
</div>

<div class="stack" data-wm-panel="image" hidden>
    <div class="field">
        <span class="label">Image</span>
        <div class="wm-image-box" data-wm-image-box>
            <img alt="" data-wm-image-preview hidden>
            <p class="hint" data-wm-image-status>PNG or JPG, up to 5 MB. Transparent PNGs work best.</p>
            <div class="row">
                <button class="btn btn-secondary btn-sm" type="button" data-wm-image-pick>Choose image</button>
                <button class="btn btn-ghost btn-sm" type="button" data-wm-image-remove hidden>Remove</button>
            </div>
            <input class="visually-hidden" type="file" accept=".png,.jpg,.jpeg" tabindex="-1" aria-hidden="true" data-wm-image-input>
        </div>
    </div>
    <div class="field">
        <div class="label-row"><label class="label" for="wm-scale">Size</label><output class="hint" data-out="image_scale">40% of page width</output></div>
        <input id="wm-scale" name="image_scale" type="range" min="5" max="100" step="1" value="40">
    </div>
</div>

<div class="field">
    <div class="label-row"><label class="label" for="wm-opacity">Opacity</label><output class="hint" data-out="opacity">30%</output></div>
    <input id="wm-opacity" name="opacity" type="range" min="5" max="100" step="5" value="30">
</div>

<div class="field">
    <div class="label-row"><label class="label" for="wm-rotation">Rotation</label><output class="hint" data-out="rotation">45°</output></div>
    <input id="wm-rotation" name="rotation" type="range" min="-90" max="90" step="15" value="45">
</div>

<fieldset class="field">
    <legend class="label">Position</legend>
    <div class="position-grid">
        <?php foreach ($positions as $value => $label): ?>
        <label class="position-cell" title="<?= e($label) ?>">
            <input type="radio" name="position" value="<?= e($value) ?>"<?= $value === 'center' ? ' checked' : '' ?>>
            <span class="visually-hidden"><?= e($label) ?></span><span class="position-dot" aria-hidden="true"></span>
        </label>
        <?php endforeach; ?>
    </div>
</fieldset>

<fieldset class="field">
    <legend class="label">Pages</legend>
    <div class="segmented">
        <div class="segment"><input type="radio" name="apply" id="wm-all" value="all" checked><label for="wm-all"><strong>All pages</strong></label></div>
        <div class="segment"><input type="radio" name="apply" id="wm-selected" value="selected"><label for="wm-selected"><strong>Some pages</strong></label></div>
    </div>
    <input class="input mt-2" name="pages" type="text" inputmode="numeric" placeholder="e.g. 1-3, 6" aria-label="Pages to watermark" data-wm-pages hidden>
</fieldset>

<p class="split-feedback" data-wm-feedback aria-live="polite"></p>
