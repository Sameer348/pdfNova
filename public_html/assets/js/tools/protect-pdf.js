/**
 * Protect PDF — password + confirmation, strength meter, permissions.
 * (Show/hide buttons are handled globally by app.js.)
 */
import { registerToolHooks } from '../tools.js';

const form = document.querySelector('[data-tool-options]');
const pw = form.querySelector('#protect-password');
const confirm = form.querySelector('#protect-confirm');
const meter = form.querySelector('[data-strength]');
const meterText = form.querySelector('[data-strength-text]');
const feedback = form.querySelector('[data-protect-feedback]');

/** Simple, honest strength estimate: length + character variety. */
function strength(value) {
    if (value.length < 6) return 0;
    let score = value.length >= 12 ? 2 : 1;
    const kinds = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((re) => re.test(value)).length;
    if (kinds >= 3) score++;
    if (value.length >= 16 && kinds >= 2) score++;
    return Math.min(score, 4);
}

function problem() {
    if (pw.value.length < 6) return pw.value ? 'Use at least 6 characters.' : 'Choose a password.';
    if (!confirm.value) return 'Type the password again to confirm it.';
    if (pw.value !== confirm.value) return 'The passwords do not match.';
    return null;
}

function update() {
    const s = strength(pw.value);
    meter.dataset.level = String(s);
    meterText.textContent = pw.value
        ? ['Too short — at least 6 characters.', 'Weak', 'Fair', 'Good', 'Strong'][s]
        : 'At least 6 characters. Longer is stronger.';
    const p = problem();
    const mismatch = confirm.value && pw.value !== confirm.value;
    confirm.setAttribute('aria-invalid', mismatch ? 'true' : 'false');
    feedback.textContent = mismatch ? 'The passwords do not match.' : '';
    feedback.className = `split-feedback${mismatch ? ' is-error' : ''}`;
    return p;
}

form.addEventListener('reset', () => setTimeout(update));

registerToolHooks({
    onFilesChanged() { update(); },
    validate() { return update(); },
    collectOptions() {
        return {
            password: pw.value,
            confirm: confirm.value,
            allow_print: form.querySelector('[name="allow_print"]').checked,
            allow_copy: form.querySelector('[name="allow_copy"]').checked,
            allow_modify: form.querySelector('[name="allow_modify"]').checked,
        };
    },
});
