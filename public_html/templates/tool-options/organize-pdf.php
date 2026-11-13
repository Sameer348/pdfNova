<?php
/**
 * Organize PDF — options panel. Behaviour: assets/js/tools/organize-pdf.js
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<div class="summary">
    <p class="label">Your changes</p>
    <dl class="summary-list">
        <div><dt>Pages kept</dt><dd data-org-kept>0</dd></div>
        <div><dt>Removed</dt><dd data-org-removed>0</dd></div>
        <div><dt>Rotated</dt><dd data-org-rotated>0</dd></div>
        <div><dt>Selected</dt><dd data-org-selected>0</dd></div>
    </dl>
</div>

<div class="grid-toolbar">
    <button class="btn btn-secondary btn-sm" type="button" data-org="select-all">Select all</button>
    <button class="btn btn-secondary btn-sm" type="button" data-org="select-none">Clear selection</button>
    <button class="btn btn-ghost btn-sm" type="button" data-org="reset">Reset changes</button>
</div>

<fieldset class="field">
    <legend class="label">Create</legend>
    <div class="segmented segmented-stack">
        <div class="segment">
            <input type="radio" name="output" id="org-all" value="all" checked>
            <label for="org-all"><strong>Organized PDF</strong><span>All kept pages, in your new order</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="output" id="org-selected" value="selected">
            <label for="org-selected"><strong>Extract selected pages</strong><span>Only the pages you ticked</span></label>
        </div>
    </div>
</fieldset>
<p class="hint">Drag pages by the grip, or use the arrows, to reorder. Use the buttons under each page to rotate or remove it. Click a page to select it.</p>
