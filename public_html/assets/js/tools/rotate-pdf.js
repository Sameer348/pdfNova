/**
 * Rotate PDF — live thumbnail preview of the rotation, page selection by
 * clicking thumbnails or typing ranges (kept in sync both ways).
 */
import { registerToolHooks } from '../tools.js';
import { parseRanges } from '../components/page-ranges.js';
import { PageGrid, compactRanges } from '../components/page-grid.js';

const form = document.querySelector('[data-tool-options]');
const canvas = document.querySelector('[data-tool-canvas]');
const pagesField = form.querySelector('[data-rotate-pages]');
const pagesInput = form.querySelector('#rotate-pages');
const feedback = form.querySelector('[data-rotate-feedback]');

let grid = null;
let loadedFor = null;   // uploader item uid currently shown
let problem = null;

const angle = () => Number(form.querySelector('input[name="angle"]:checked')?.value || 90);
const apply = () => form.querySelector('input[name="apply"]:checked')?.value || 'all';

function selectionFromInput(pageCount) {
    if (pagesInput.value.trim() === '') return { pages: [], error: null };
    try {
        const pages = new Set();
        parseRanges(pagesInput.value, pageCount).forEach(([a, b]) => { for (let p = a; p <= b; p++) pages.add(p); });
        return { pages: [...pages], error: null };
    } catch (err) {
        return { pages: null, error: err.message };
    }
}

function update(pageCount) {
    const all = apply() === 'all';
    pagesField.hidden = all;
    if (!grid) return;

    let targets;
    if (all) {
        targets = new Set(grid.pages.map((p) => p.n));
        problem = null;
    } else {
        const { pages, error } = selectionFromInput(pageCount);
        problem = error ?? (pages.length ? null : 'empty');
        targets = new Set(pages ?? []);
        if (!error) grid.pages.forEach((p) => { p.selected = targets.has(p.n); });
    }
    grid.opts.selectable = !all;
    grid.container.classList.toggle('is-selecting', !all);
    grid.previewRotation = (page) => (targets.has(page.n) ? angle() : 0);
    grid.refresh();

    if (problem === 'empty') {
        feedback.textContent = 'Click the pages you want to rotate, or type them above.';
        feedback.className = 'split-feedback';
    } else if (problem) {
        feedback.textContent = problem;
        feedback.className = 'split-feedback is-error';
    } else {
        const n = targets.size;
        feedback.textContent = `${n === pageCount ? 'All' : n} ${n === 1 ? 'page' : 'pages'} will be rotated. The preview shows the result.`;
        feedback.className = 'split-feedback is-ok';
    }
}

registerToolHooks({
    onFilesChanged({ items, allItems }) {
        const item = items[0];
        if (!item?.pages) {
            if (!allItems.length && grid) { grid.clear(); grid = null; loadedFor = null; }
            canvas.hidden = true;
            return;
        }
        canvas.hidden = false;
        if (loadedFor !== item.uid) {
            loadedFor = item.uid;
            grid = new PageGrid(canvas, {
                selectable: true, // tick boxes are drawn; clicks only count in "Selected pages" mode
                onChange: (g) => {
                    // A thumbnail click changed the selection → write it into the text field.
                    if (apply() === 'selected' && g.pages.length) {
                        pagesInput.value = compactRanges(g.pages.filter((p) => p.selected).map((p) => p.n));
                        // Setting .value from code fires no event: tell the tool controller to re-check the button.
                        form.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },
            });
            grid.load(item.file, item.pages);
        }
        update(item.pages);
    },
    validate() {
        if (problem === 'empty') return 'Select the pages to rotate.';
        return problem ? 'Fix the page selection to continue.' : null;
    },
    collectOptions() {
        return { angle: angle(), apply: apply(), pages: pagesInput.value };
    },
});
