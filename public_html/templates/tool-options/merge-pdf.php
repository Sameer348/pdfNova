<?php
/**
 * Merge PDF — side panel. Merge has no settings; this shows a live summary
 * of what will be merged (updated by assets/js/tools/merge-pdf.js).
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<div class="summary">
    <p class="label">Merge summary</p>
    <dl class="summary-list">
        <div><dt>Files</dt><dd data-merge-files>0</dd></div>
        <div><dt>Total pages</dt><dd data-merge-pages>0</dd></div>
        <div><dt>Total size</dt><dd data-merge-size>0 B</dd></div>
    </dl>
    <p class="hint">Files are combined from top to bottom. Each page keeps its original size and orientation.</p>
</div>
