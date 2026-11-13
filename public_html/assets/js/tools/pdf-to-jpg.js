/**
 * PDF to JPG — runs entirely in the browser with pdf.js. Nothing is uploaded.
 * One page → a JPG download; several pages → one ZIP (components/zip.js).
 */
import { registerToolHooks } from '../tools.js';
import { formatBytes } from '../app.js';
import { parseRanges } from '../components/page-ranges.js';
import { loadPdfJs } from '../components/page-grid.js';
import { createZip } from '../components/zip.js';

const form = document.querySelector('[data-tool-options]');
const pagesInput = form.querySelector('[data-jpg-pages]');
const feedback = form.querySelector('[data-jpg-feedback]');
const qualityOut = form.querySelector('[data-jpg-quality-out]');
const MAX_PIXELS = 25_000_000; // per page, protects the browser's memory

const state = { uid: null, doc: null, pages: 0, error: null, problem: null };

const val = (name) => form.querySelector(`[name="${name}"]:checked`)?.value ?? form.querySelector(`[name="${name}"]`)?.value;

async function open(file) {
    state.doc?.destroy();
    Object.assign(state, { doc: null, pages: 0, error: null });
    try {
        const pdfjs = await loadPdfJs();
        state.doc = await pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
        state.pages = state.doc.numPages;
    } catch (err) {
        state.error = err?.name === 'PasswordException'
            ? 'This PDF is password-protected. Remove the password with Unlock PDF first.'
            : 'This PDF could not be read. It may be damaged.';
    }
    form.dispatchEvent(new Event('change', { bubbles: true }));
}

function selectedPages() {
    if (val('apply') !== 'selected') return Array.from({ length: state.pages }, (_, i) => i + 1);
    const set = new Set();
    parseRanges(pagesInput.value, state.pages).forEach(([a, b]) => { for (let p = a; p <= b; p++) set.add(p); });
    return [...set];
}

function evaluate() {
    qualityOut.textContent = `${val('quality')}%`;
    pagesInput.hidden = val('apply') !== 'selected';
    if (state.error) return state.error;
    if (!state.doc) return 'Reading the PDF…';
    if (val('apply') === 'selected' && !pagesInput.value.trim()) return 'Enter the pages to convert.';
    try {
        const n = selectedPages().length;
        feedback.textContent = `Creates ${n} JPG ${n === 1 ? 'image' : 'images'}${n > 1 ? ' (downloaded as a ZIP)' : ''}.`;
        feedback.className = 'split-feedback is-ok';
        return null;
    } catch (err) {
        return err.message;
    }
}

form.addEventListener('input', () => form.dispatchEvent(new Event('change', { bubbles: true })));

registerToolHooks({
    onFilesChanged({ items }) {
        const item = items[0];
        if (!item) {
            state.doc?.destroy();
            Object.assign(state, { uid: null, doc: null, pages: 0, error: null });
            feedback.textContent = '';
            return;
        }
        if (state.uid !== item.uid) {
            state.uid = item.uid;
            open(item.file);
        }
        state.problem = evaluate();
        if (state.problem) {
            feedback.textContent = state.problem;
            feedback.className = `split-feedback${state.error || /exist|valid|start|backwards/.test(state.problem) ? ' is-error' : ''}`;
        }
    },
    validate() {
        return state.problem;
    },
    async run({ items, progress }) {
        const pages = selectedPages();
        const dpi = Number(val('dpi'));
        const quality = Number(val('quality')) / 100;
        const base = (items[0].name.replace(/\.pdf$/i, '') || 'document').replace(/[^\p{L}\p{N} ._()-]+/gu, '_').slice(0, 60);
        const outputs = [];

        for (let i = 0; i < pages.length; i++) {
            const page = await state.doc.getPage(pages[i]);
            let scale = dpi / 72;
            const vp1 = page.getViewport({ scale: 1 });
            if (vp1.width * vp1.height * scale * scale > MAX_PIXELS) {
                scale = Math.sqrt(MAX_PIXELS / (vp1.width * vp1.height)); // cap very large pages
            }
            const viewport = page.getViewport({ scale });
            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff'; // JPG has no transparency: paint a white page first
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            await page.render({ canvasContext: ctx, viewport }).promise;
            const blob = await new Promise((resolve, reject) =>
                canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('The image could not be created.'))), 'image/jpeg', quality));
            const pad = String(pages[pages.length - 1]).length;
            outputs.push({ name: `${base}_page_${String(pages[i]).padStart(pad, '0')}.jpg`, blob });
            page.cleanup();
            canvas.width = canvas.height = 0; // release memory early
            progress(((i + 1) / pages.length) * (pages.length > 1 ? 90 : 100));
        }

        const single = outputs.length === 1;
        const file = single ? outputs[0].blob : await createZip(outputs);
        progress(100);
        const total = outputs.reduce((s, o) => s + o.blob.size, 0);
        return {
            success: true,
            objectUrl: true,
            download_url: URL.createObjectURL(file),
            filename: single ? outputs[0].name : `${base}_jpg.zip`,
            size_label: formatBytes(file.size),
            title: single ? 'Your JPG is ready' : `${outputs.length} JPG images are ready`,
            note: 'Converted in your browser — your PDF was never uploaded.',
            stats: [
                { label: 'Images', value: String(outputs.length) },
                { label: 'Resolution', value: `${dpi} dpi` },
                { label: 'Total size', value: formatBytes(total) },
            ],
        };
    },
});
