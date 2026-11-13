/**
 * Unlock PDF — sends the password; a wrong password shows the server's
 * clear message with "Try again" (handled by tools.js).
 */
import { registerToolHooks } from '../tools.js';

const form = document.querySelector('[data-tool-options]');
const input = form.querySelector('#unlock-password');

registerToolHooks({
    collectOptions() {
        return { password: input.value };
    },
});
