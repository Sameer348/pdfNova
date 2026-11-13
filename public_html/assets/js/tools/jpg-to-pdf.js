/**
 * JPG to PDF — hides orientation/fit when "Same as image" is chosen
 * (the page then simply matches each image).
 */
import { registerToolHooks } from '../tools.js';

const form = document.querySelector('[data-tool-options]');

function sync() {
    const fit = form.querySelector('[name="page_size"]').value === 'fit';
    form.querySelectorAll('[data-img-when-fixed]').forEach((el) => { el.hidden = fit; });
}
form.addEventListener('change', sync);
form.addEventListener('reset', () => setTimeout(sync));
sync();

registerToolHooks({ onFilesChanged: sync });
