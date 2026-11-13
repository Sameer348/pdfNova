/**
 * Split PDF — mode switching, live validation and a preview of what will be
 * created. Range rules mirror services/pdf/PageRangeParser.php (the server
 * re-validates everything).
 */
import { registerToolHooks } from '../tools.js';
import { parseRanges } from '../components/page-ranges.js';

const form = document.querySelector('[data-tool-options]');
const feedback = document.querySelector('[data-split-feedback]');
const MAX_OUTPUTS = 300;

const uniquePages = (ranges) => {
    const set = new Set();
    ranges.forEach(([a, b]) => { for (let p = a; p <= b; p++) set.add(p); });
    return [...set];
};
const label = ([a, b]) => (a === b ? `${a}` : `${a}–${b}`);
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

function mode() {
    return form.querySelector('input[name="mode"]:checked')?.value ?? 'ranges';
}

/** Returns { error } or { summary } for the current settings. */
function evaluate(pageCount) {
    const values = new FormData(form);
    try {
        switch (mode()) {
            case 'ranges': {
                const ranges = parseRanges(values.get('ranges') ?? '', pageCount);
                const list = ranges.length > 6 ? `${ranges.slice(0, 6).map(label).join(', ')}, …` : ranges.map(label).join(', ');
                return { count: ranges.length, summary: `Creates ${plural(ranges.length, 'PDF')}: pages ${list}${ranges.length > 1 ? ' (downloaded as a ZIP)' : ''}.` };
            }
            case 'extract': {
                const pages = uniquePages(parseRanges(values.get('pages') ?? '', pageCount));
                const separate = values.get('separate') === '1';
                return separate && pages.length > 1
                    ? { count: pages.length, summary: `Creates ${plural(pages.length, 'PDF')}, one per page (downloaded as a ZIP).` }
                    : { count: 1, summary: `Creates 1 PDF with ${plural(pages.length, 'page')}.` };
            }
            case 'every': {
                const n = Number(values.get('every'));
                if (!Number.isInteger(n) || n < 1) throw new Error('Enter how many pages each file should have (1 or more).');
                if (n >= pageCount) throw new Error(`This PDF has ${plural(pageCount, 'page')}, so splitting every ${n} pages would not create more than one file.`);
                const parts = Math.ceil(pageCount / n);
                return { count: parts, summary: `Creates ${plural(parts, 'PDF')} of up to ${plural(n, 'page')} each (downloaded as a ZIP).` };
            }
            default:
                throw new Error('Choose how you want to split the PDF.');
        }
    } catch (err) {
        return { error: err.message };
    }
}

function showPanels() {
    const current = mode();
    form.querySelectorAll('[data-split-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.splitPanel !== current;
    });
}

let lastValidation = null;

registerToolHooks({
    onFilesChanged({ items }) {
        showPanels();
        const pageCount = items[0]?.pages;
        if (!pageCount) {
            feedback.textContent = '';
            feedback.className = 'split-feedback';
            return;
        }
        const activeInput = form.querySelector(`[data-split-panel="${mode()}"] .input`);
        if (activeInput && activeInput.value.trim() === '') {
            // Nothing typed yet: a neutral prompt, not an error.
            lastValidation = 'empty';
            feedback.textContent = `This PDF has ${plural(pageCount, 'page')}. Enter the pages above to see what will be created.`;
            feedback.className = 'split-feedback';
            activeInput.setAttribute('aria-invalid', 'false');
            return;
        }
        const result = evaluate(pageCount);
        if (!result.error && result.count > MAX_OUTPUTS) {
            result.error = `This would create ${result.count} files. Please create at most ${MAX_OUTPUTS} at once.`;
        }
        lastValidation = result.error ?? null;
        feedback.textContent = result.error ?? result.summary;
        feedback.className = `split-feedback ${result.error ? 'is-error' : 'is-ok'}`;
        const field = form.querySelector(`[data-split-panel="${mode()}"] .input`);
        field?.setAttribute('aria-invalid', result.error ? 'true' : 'false');
    },
    validate({ items }) {
        if (!items[0]?.pages) return null;
        if (lastValidation === 'empty') return 'Enter the pages you want.';
        return lastValidation ? 'Fix the page selection to continue.' : null;
    },
});
