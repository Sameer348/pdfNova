<?php
/**
 * JPG to PDF — options panel.
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<div class="field">
    <label class="label" for="img-page-size">Page size</label>
    <select class="select" id="img-page-size" name="page_size">
        <option value="a4" selected>A4 (210 × 297 mm)</option>
        <option value="letter">US Letter (8.5 × 11 in)</option>
        <option value="fit">Same as image</option>
    </select>
</div>

<fieldset class="field" data-img-when-fixed>
    <legend class="label">Orientation</legend>
    <div class="segmented">
        <div class="segment"><input type="radio" name="orientation" id="img-auto" value="auto" checked><label for="img-auto"><strong>Auto</strong></label></div>
        <div class="segment"><input type="radio" name="orientation" id="img-portrait" value="portrait"><label for="img-portrait"><strong>Portrait</strong></label></div>
        <div class="segment"><input type="radio" name="orientation" id="img-landscape" value="landscape"><label for="img-landscape"><strong>Landscape</strong></label></div>
    </div>
    <p class="hint">Auto turns each page to match its image.</p>
</fieldset>

<div class="field">
    <label class="label" for="img-margin">Margin</label>
    <select class="select" id="img-margin" name="margin">
        <option value="0">No margin</option>
        <option value="10" selected>Small (10 mm)</option>
        <option value="20">Large (20 mm)</option>
    </select>
</div>

<fieldset class="field" data-img-when-fixed>
    <legend class="label">Image fit</legend>
    <div class="segmented segmented-stack">
        <div class="segment"><input type="radio" name="fit" id="fit-contain" value="contain" checked><label for="fit-contain"><strong>Fit</strong><span>Whole image visible</span></label></div>
        <div class="segment"><input type="radio" name="fit" id="fit-cover" value="cover"><label for="fit-cover"><strong>Fill</strong><span>Covers the page, edges may be cropped</span></label></div>
        <div class="segment"><input type="radio" name="fit" id="fit-original" value="original"><label for="fit-original"><strong>Original size</strong><span>Never enlarged</span></label></div>
    </div>
</fieldset>
<p class="hint">Photos taken on phones are turned upright automatically.</p>
