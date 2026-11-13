<?php
/**
 * Split PDF — options panel. Behaviour: assets/js/tools/split-pdf.js
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<fieldset class="field">
    <legend class="label">How do you want to split?</legend>
    <div class="segmented segmented-stack">
        <div class="segment">
            <input type="radio" name="mode" id="split-mode-ranges" value="ranges" checked>
            <label for="split-mode-ranges"><strong>By ranges</strong><span>One PDF for each range</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="mode" id="split-mode-extract" value="extract">
            <label for="split-mode-extract"><strong>Extract pages</strong><span>Pick pages for a new PDF</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="mode" id="split-mode-every" value="every">
            <label for="split-mode-every"><strong>Every N pages</strong><span>Equal-sized parts</span></label>
        </div>
    </div>
</fieldset>

<div class="field" data-split-panel="ranges">
    <label class="label" for="split-ranges">Page ranges</label>
    <input class="input" id="split-ranges" name="ranges" type="text" inputmode="numeric" autocomplete="off"
           placeholder="e.g. 1-3, 5-7, 10" aria-describedby="split-ranges-help split-feedback">
    <p class="hint" id="split-ranges-help">Separate ranges with commas. <code>1-3, 5-7, 10</code> creates three PDFs. <code>8-</code> means page 8 to the end.</p>
</div>

<div class="field" data-split-panel="extract" hidden>
    <label class="label" for="split-pages">Pages to extract</label>
    <input class="input" id="split-pages" name="pages" type="text" inputmode="numeric" autocomplete="off"
           placeholder="e.g. 1, 3, 5-7" aria-describedby="split-pages-help split-feedback">
    <p class="hint" id="split-pages-help">The pages are combined into one new PDF, in the order you type them.</p>
    <label class="check"><input type="checkbox" name="separate" value="1"> Save each selected page as a separate PDF</label>
</div>

<div class="field" data-split-panel="every" hidden>
    <label class="label" for="split-every">Pages per file</label>
    <input class="input" id="split-every" name="every" type="number" min="1" step="1" inputmode="numeric" value="1"
           aria-describedby="split-every-help split-feedback">
    <p class="hint" id="split-every-help">For example, 2 turns a 10-page PDF into five 2-page PDFs.</p>
</div>

<p class="split-feedback" id="split-feedback" data-split-feedback aria-live="polite"></p>
