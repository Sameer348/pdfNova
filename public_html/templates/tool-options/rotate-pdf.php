<?php
/**
 * Rotate PDF — options panel. Behaviour: assets/js/tools/rotate-pdf.js
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<fieldset class="field">
    <legend class="label">Rotation</legend>
    <div class="segmented segmented-stack">
        <div class="segment">
            <input type="radio" name="angle" id="angle-90" value="90" checked>
            <label for="angle-90"><strong>↻ 90° clockwise</strong><span>Turn right</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="angle" id="angle-270" value="270">
            <label for="angle-270"><strong>↺ 90° counter-clockwise</strong><span>Turn left</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="angle" id="angle-180" value="180">
            <label for="angle-180"><strong>180°</strong><span>Upside down</span></label>
        </div>
    </div>
</fieldset>

<fieldset class="field">
    <legend class="label">Apply to</legend>
    <div class="segmented">
        <div class="segment">
            <input type="radio" name="apply" id="apply-all" value="all" checked>
            <label for="apply-all"><strong>All pages</strong></label>
        </div>
        <div class="segment">
            <input type="radio" name="apply" id="apply-selected" value="selected">
            <label for="apply-selected"><strong>Selected pages</strong></label>
        </div>
    </div>
</fieldset>

<div class="field" data-rotate-pages hidden>
    <label class="label" for="rotate-pages">Pages to rotate</label>
    <input class="input" id="rotate-pages" name="pages" type="text" inputmode="numeric" autocomplete="off"
           placeholder="e.g. 1-3, 6" aria-describedby="rotate-pages-help rotate-feedback">
    <p class="hint" id="rotate-pages-help">Click page thumbnails to select them, or type page numbers and ranges.</p>
</div>

<p class="split-feedback" id="rotate-feedback" data-rotate-feedback aria-live="polite"></p>
