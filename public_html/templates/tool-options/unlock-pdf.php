<?php
/**
 * Unlock PDF — options panel.
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);
?>
<div class="field">
    <label class="label" for="unlock-password">Current password</label>
    <div class="input-group">
        <input class="input" id="unlock-password" name="password" type="password" autocomplete="current-password" maxlength="128"
               aria-describedby="unlock-help">
        <button class="icon-btn" type="button" data-password-toggle="unlock-password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 'icon icon-eye') . icon('eye-off', 'icon icon-eye-off') ?></button>
    </div>
    <p class="hint" id="unlock-help">Enter the password you use to open the PDF (the owner password also works). If the PDF opens without a password but is restricted, leave this empty.</p>
</div>
<div class="alert alert-info"><?= icon('shield') ?><p>Only unlock files you own or have permission to unlock. We never store your password.</p></div>
