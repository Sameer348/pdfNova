<?php
/**
 * PDF to JPG — options panel. Conversion runs in the browser (assets/js/tools/pdf-to-jpg.js).
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<div class="field">
    <label class="label" for="jpg-dpi">Image resolution</label>
    <select class="select" id="jpg-dpi" name="dpi">
        <option value="72">Screen (72 dpi) · smallest files</option>
        <option value="150" selected>Standard (150 dpi)</option>
        <option value="300">High (300 dpi) · for printing</option>
    </select>
</div>

<div class="field">
    <div class="label-row"><label class="label" for="jpg-quality">JPG quality</label><output class="hint" data-jpg-quality-out>85%</output></div>
    <input id="jpg-quality" name="quality" type="range" min="40" max="100" step="5" value="85">
</div>

<fieldset class="field">
    <legend class="label">Pages</legend>
    <div class="segmented">
        <div class="segment"><input type="radio" name="apply" id="jpg-all" value="all" checked><label for="jpg-all"><strong>All pages</strong></label></div>
        <div class="segment"><input type="radio" name="apply" id="jpg-some" value="selected"><label for="jpg-some"><strong>Some pages</strong></label></div>
    </div>
    <input class="input mt-2" name="pages" type="text" inputmode="numeric" placeholder="e.g. 1-3, 6" aria-label="Pages to convert" data-jpg-pages hidden>
</fieldset>

<p class="split-feedback" data-jpg-feedback aria-live="polite"></p>
<div class="alert alert-info"><?= icon('shield') ?><p>This conversion happens in your browser — your PDF is not uploaded.</p></div>
