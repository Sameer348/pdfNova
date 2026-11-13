/**
 * Organize PDF — visual page editor: reorder (drag or arrows), rotate,
 * remove/restore and select pages; the server rebuilds the PDF from the plan.
 */
import { registerToolHooks } from '../tools.js';
import { PageGrid } from '../components/page-grid.js';

const form = document.querySelector('[data-tool-options]');
const canvas = document.querySelector('[data-tool-canvas]');
const out = (key) => form.querySelector(`[data-org-${key}]`);

let grid = null;
let loadedFor = null;

const output = () => form.querySelector('input[name="output"]:checked')?.value || 'all';

function summarize() {
    if (!grid) return;
    const kept = grid.pages.filter((p) => !p.deleted);
    out('kept').textContent = String(kept.length);
    out('removed').textContent = String(grid.pages.length - kept.length);
    out('rotated').textContent = String(kept.filter((p) => p.rotate).length);
    out('selected').textContent = String(kept.filter((p) => p.selected).length);
}

form.addEventListener('click', (event) => {
    const action = event.target.closest('[data-org]')?.dataset.org;
    if (!action || !grid) return;
    if (action === 'select-all') grid.selectAll(true);
    if (action === 'select-none') grid.selectAll(false);
    if (action === 'reset') grid.resetChanges();
});

registerToolHooks({
    onFilesChanged({ items, allItems }) {
        const item = items[0];
        if (!item?.pages) {
            if (!allItems.length && grid) { grid.clear(); grid = null; loadedFor = null; }
            canvas.hidden = true;
            return;
        }
        canvas.hidden = false;
        canvas.classList.add('is-selecting');
        if (loadedFor !== item.uid) {
            loadedFor = item.uid;
            grid = new PageGrid(canvas, {
                selectable: true,
                sortable: true,
                pageControls: true,
                onChange: () => {
                    summarize();
                    // Let the tool controller re-check the run button.
                    form.dispatchEvent(new Event('change', { bubbles: true }));
                },
            });
            grid.load(item.file, item.pages);
        }
        summarize();
    },
    validate() {
        if (!grid) return null;
        const kept = grid.pages.filter((p) => !p.deleted);
        if (!kept.length) return 'All pages are removed. Restore at least one page.';
        if (output() === 'selected' && !kept.some((p) => p.selected)) return 'Select the pages you want to extract.';
        return null;
    },
    collectOptions() {
        const kept = grid.pages.filter((p) => !p.deleted && (output() === 'all' || p.selected));
        return { output: output(), plan: kept.map((p) => ({ page: p.n, rotate: p.rotate })) };
    },
});
