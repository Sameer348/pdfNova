<?php
/**
 * Protect PDF — options panel. Behaviour: assets/js/tools/protect-pdf.js
 * Vars: array $tool, array $limits
 */

declare(strict_types=1);

$eye = icon('eye', 'icon icon-eye') . icon('eye-off', 'icon icon-eye-off');
?>
<div class="field">
    <label class="label" for="protect-password">Password</label>
    <div class="input-group">
        <input class="input" id="protect-password" name="password" type="password" autocomplete="new-password"
               minlength="6" maxlength="128" aria-describedby="protect-strength">
        <button class="icon-btn" type="button" data-password-toggle="protect-password" aria-label="Show password" aria-pressed="false"><?= $eye ?></button>
    </div>
    <div class="strength" data-strength aria-hidden="true"><span></span></div>
    <p class="hint" id="protect-strength" data-strength-text>At least 6 characters. Longer is stronger.</p>
</div>

<div class="field">
    <label class="label" for="protect-confirm">Confirm password</label>
    <div class="input-group">
        <input class="input" id="protect-confirm" name="confirm" type="password" autocomplete="new-password" maxlength="128">
        <button class="icon-btn" type="button" data-password-toggle="protect-confirm" aria-label="Show password" aria-pressed="false"><?= $eye ?></button>
    </div>
</div>

<fieldset class="field">
    <legend class="label">People who open the PDF may</legend>
    <label class="check"><input type="checkbox" name="allow_print" value="1" checked> Print it</label>
    <label class="check"><input type="checkbox" name="allow_copy" value="1" checked> Copy text and images</label>
    <label class="check"><input type="checkbox" name="allow_modify" value="1"> Edit, comment and fill forms</label>
    <p class="hint">Standard PDF readers respect these settings. Encryption: AES-256.</p>
</fieldset>

<div class="alert alert-info"><?= icon('info') ?><p>We never store your password. If you forget it, the protected file can't be opened — keep a copy of the original.</p></div>

<p class="split-feedback" data-protect-feedback aria-live="polite"></p>
