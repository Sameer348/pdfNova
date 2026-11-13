/**
 * Merge PDF — live summary of the files that will be merged.
 * (Processing happens on the server via /api/merge.php.)
 */
import { registerToolHooks } from '../tools.js';
import { formatBytes } from '../app.js';

const filesEl = document.querySelector('[data-merge-files]');
const pagesEl = document.querySelector('[data-merge-pages]');
const sizeEl = document.querySelector('[data-merge-size]');

registerToolHooks({
    onFilesChanged({ items }) {
        if (!filesEl) return;
        filesEl.textContent = String(items.length);
        pagesEl.textContent = String(items.reduce((sum, item) => sum + (item.pages || 0), 0));
        sizeEl.textContent = formatBytes(items.reduce((sum, item) => sum + item.size, 0));
    },
});
