<?php
/**
 * Compress PDF — options panel.
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<fieldset class="field">
    <legend class="label">Compression level</legend>
    <div class="segmented segmented-stack">
        <div class="segment">
            <input type="radio" name="level" id="level-high" value="high">
            <label for="level-high"><strong>High compression</strong><span>Smallest file · images visibly softer</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="level" id="level-recommended" value="recommended" checked>
            <label for="level-recommended"><strong>Recommended</strong><span>Good balance of size and quality</span></label>
        </div>
        <div class="segment">
            <input type="radio" name="level" id="level-low" value="low">
            <label for="level-low"><strong>Low compression</strong><span>Best quality · smaller savings</span></label>
        </div>
    </div>
</fieldset>
<p class="hint">Savings come mostly from images. Text-only PDFs are often already small, and we'll tell you if a file can't be reduced.</p>
