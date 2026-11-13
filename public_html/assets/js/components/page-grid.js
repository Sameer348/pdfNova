/**
 * PageGrid — page thumbnails for Rotate PDF and Organize PDF.
 *
 * Thumbnails are rendered IN THE BROWSER from the visitor's local file with
 * pdf.js (lazy-loaded, rendered only when a tile scrolls into view). The page
 * count comes from the server's validation, so the grid still works — with
 * numbered placeholders — if a preview can't be drawn.
 *
 *   const grid = new PageGrid(container, { selectable, sortable, pageControls, onChange });
 *   await grid.load(file, pageCount);
 *   grid.pages  → [{ n, rotate, deleted, selected }]  (in current order)
 */

const PDFJS_URL = new URL('../../vendor/pdfjs/pdf.min.mjs', import.meta.url).href;
const WORKER_URL = new URL('../../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;
const THUMB_WIDTH = 150;

let pdfjsPromise = null;
/** Lazy-load pdf.js once (shared by every tool that renders pages). */
export function loadPdfJs() {
    if (!pdfjsPromise) {
        pdfjsPromise = import(PDFJS_URL).then((lib) => {
            lib.GlobalWorkerOptions.workerSrc = WORKER_URL;
            return lib;
        });
    }
    return pdfjsPromise;
}

const ICON = {
    left: '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6L3.5 8.5"/><path d="M3.5 3.5v5h5"/>',
    right: '<path d="M20.5 12a8.5 8.5 0 1 1-2.5-6l2.5 2.5"/><path d="M20.5 3.5v5h-5"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/>',
    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
    grip: '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" stroke-width="3"/>',
    prev: '<path d="m15 6-6 6 6 6"/>',
    next: '<path d="m9 6 6 6-6 6"/>',
    check: '<path d="m5 12 5 5L20 7"/>',
};
const svg = (name) => `<svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${ICON[name]}</svg>`;

export class PageGrid {
    constructor(container, { selectable = false, sortable = false, pageControls = false, onChange } = {}) {
        this.container = container;
        this.opts = { selectable, sortable, pageControls };
        this.onChange = onChange;
        this.pages = [];
        this.previewRotation = () => 0; // extra rotation shown in previews (Rotate tool)
        this.doc = null;
        this.loadId = 0;

        this.list = document.createElement('ol');
        this.list.className = 'page-grid';
        this.list.setAttribute('aria-label', 'Pages');
        this.status = document.createElement('p');
        this.status.className = 'hint page-grid-status';
        this.status.setAttribute('aria-live', 'polite');
        container.replaceChildren(this.status, this.list);

        this.list.addEventListener('click', (event) => this.handleClick(event));
        if (sortable) this.enableDrag();
    }

    /** Show pages of a local File. pageCount comes from the server. */
    async load(file, pageCount) {
        const loadId = ++this.loadId;
        this.destroyDoc();
        this.pages = Array.from({ length: pageCount }, (_, i) => ({ n: i + 1, rotate: 0, deleted: false, selected: false }));
        this.list.replaceChildren(...this.pages.map((p) => this.createTile(p)));
        this.status.textContent = `Loading page previews…`;
        this.changed();

        try {
            const pdfjs = await loadPdfJs();
            const data = new Uint8Array(await file.arrayBuffer());
            const doc = await pdfjs.getDocument({ data, isEvalSupported: false }).promise;
            if (loadId !== this.loadId) { doc.destroy(); return; }
            this.doc = doc;
            this.status.textContent = '';
            this.observe();
        } catch (err) {
            if (loadId !== this.loadId) return;
            this.status.textContent = 'Page previews are not available for this file, but you can still work with the page numbers.';
        }
    }

    clear() {
        this.loadId++;
        this.destroyDoc();
        this.pages = [];
        this.list.replaceChildren();
        this.status.textContent = '';
    }

    destroyDoc() {
        this.observer?.disconnect();
        this.queue = [];
        if (this.doc) {
            this.doc.destroy();
            this.doc = null;
        }
    }

    /* ------------------------------------------------------- rendering */

    createTile(page) {
        const li = document.createElement('li');
        li.className = 'page-tile';
        li.dataset.n = String(page.n);
        const { selectable, sortable, pageControls } = this.opts;
        li.innerHTML = `
            <div class="page-thumb">
                ${selectable ? `<button class="page-select" type="button" aria-pressed="false" aria-label="Select page ${page.n}"><span class="page-check">${svg('check')}</span></button>` : ''}
                <div class="page-paper" data-paper><span class="page-placeholder">${page.n}</span></div>
                <span class="page-deleted-label">Removed</span>
            </div>
            <div class="page-bar">
                ${sortable ? `<button class="page-btn page-handle" type="button" data-handle aria-label="Drag page ${page.n} to reorder">${svg('grip')}</button>` : ''}
                <span class="page-num">${page.n}</span>
                ${pageControls ? `
                    <span class="page-actions">
                        ${sortable ? `<button class="page-btn" type="button" data-act="earlier" aria-label="Move page ${page.n} earlier">${svg('prev')}</button>
                        <button class="page-btn" type="button" data-act="later" aria-label="Move page ${page.n} later">${svg('next')}</button>` : ''}
                        <button class="page-btn" type="button" data-act="ccw" aria-label="Rotate page ${page.n} left">${svg('left')}</button>
                        <button class="page-btn" type="button" data-act="cw" aria-label="Rotate page ${page.n} right">${svg('right')}</button>
                        <button class="page-btn danger" type="button" data-act="delete" aria-label="Remove page ${page.n}">${svg('trash')}</button>
                    </span>` : ''}
            </div>`;
        return li;
    }

    /** Render tiles lazily as they scroll into view, one at a time. */
    observe() {
        this.queue = [];
        this.rendering = false;
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this.observer.unobserve(entry.target);
                    this.queue.push(Number(entry.target.dataset.n));
                }
            });
            this.pump();
        }, { rootMargin: '300px' });
        this.list.querySelectorAll('.page-tile').forEach((tile) => this.observer.observe(tile));
    }

    async pump() {
        if (this.rendering || !this.doc) return;
        this.rendering = true;
        const doc = this.doc;
        while (this.queue.length && doc === this.doc) {
            const n = this.queue.shift();
            try {
                const page = await doc.getPage(n);
                const base = page.getViewport({ scale: 1 });
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale: (THUMB_WIDTH / Math.max(base.width, base.height)) * 1.4 * ratio });
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);
                canvas.className = 'page-canvas';
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                const paper = this.list.querySelector(`.page-tile[data-n="${n}"] [data-paper]`);
                if (paper && doc === this.doc) {
                    paper.replaceChildren(canvas);
                    paper.classList.add('is-rendered');
                    this.applyTileState(paper.closest('.page-tile'));
                }
                page.cleanup();
            } catch {
                /* keep the numbered placeholder */
            }
        }
        this.rendering = false;
    }

    /* ---------------------------------------------------------- state */

    page(n) { return this.pages.find((p) => p.n === n); }

    applyTileState(tile) {
        const page = this.page(Number(tile.dataset.n));
        if (!page) return;
        const shown = (page.rotate + this.previewRotation(page)) % 360;
        const paper = tile.querySelector('[data-paper]');
        paper.style.transform = shown ? `rotate(${shown}deg)` : '';
        paper.classList.toggle('is-sideways', shown % 180 !== 0);
        tile.classList.toggle('is-deleted', page.deleted);
        tile.classList.toggle('is-selected', page.selected);
        tile.classList.toggle('is-rotated', shown !== 0);
        tile.querySelector('.page-select')?.setAttribute('aria-pressed', String(page.selected));
        const del = tile.querySelector('[data-act="delete"]');
        if (del) {
            del.innerHTML = svg(page.deleted ? 'undo' : 'trash');
            del.setAttribute('aria-label', `${page.deleted ? 'Restore' : 'Remove'} page ${page.n}`);
            del.classList.toggle('danger', !page.deleted);
        }
    }

    /** Re-apply visual state and DOM order after any change. */
    refresh() {
        this.pages.forEach((page, index) => {
            const tile = this.list.querySelector(`.page-tile[data-n="${page.n}"]`);
            if (!tile) return;
            if (this.list.children[index] !== tile) this.list.insertBefore(tile, this.list.children[index] ?? null);
            this.applyTileState(tile);
            tile.querySelectorAll('[data-act="earlier"]').forEach((b) => { b.disabled = index === 0; });
            tile.querySelectorAll('[data-act="later"]').forEach((b) => { b.disabled = index === this.pages.length - 1; });
        });
    }

    changed() {
        this.refresh();
        this.onChange?.(this);
    }

    setSelection(numbers) {
        const set = new Set(numbers);
        this.pages.forEach((p) => { p.selected = set.has(p.n); });
        this.changed();
    }

    selectAll(value = true) {
        this.pages.forEach((p) => { if (!p.deleted) p.selected = value; });
        this.changed();
    }

    resetChanges() {
        this.pages.sort((a, b) => a.n - b.n).forEach((p) => Object.assign(p, { rotate: 0, deleted: false, selected: false }));
        this.changed();
    }

    move(n, delta) {
        const i = this.pages.findIndex((p) => p.n === n);
        const j = i + delta;
        if (i < 0 || j < 0 || j >= this.pages.length) return;
        const [page] = this.pages.splice(i, 1);
        this.pages.splice(j, 0, page);
        this.changed();
    }

    handleClick(event) {
        const tile = event.target.closest('.page-tile');
        if (!tile) return;
        const page = this.page(Number(tile.dataset.n));
        const act = event.target.closest('[data-act]')?.dataset.act;
        if (act === 'cw') page.rotate = (page.rotate + 90) % 360;
        else if (act === 'ccw') page.rotate = (page.rotate + 270) % 360;
        else if (act === 'delete') { page.deleted = !page.deleted; if (page.deleted) page.selected = false; }
        else if (act === 'earlier') return this.move(page.n, -1);
        else if (act === 'later') return this.move(page.n, 1);
        else if (this.opts.selectable && !event.target.closest('[data-handle]') && !page.deleted) {
            page.selected = !page.selected;
        } else return;
        this.changed();
    }

    /** Drag tiles by their grip (pointer events: mouse, pen and touch). */
    enableDrag() {
        let dragging = null;
        this.list.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-handle]');
            if (!handle || event.button > 0) return;
            dragging = handle.closest('.page-tile');
            dragging.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);
            event.preventDefault();
        });
        this.list.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.page-tile');
            if (!target || target === dragging || target.parentElement !== this.list) return;
            const rect = target.getBoundingClientRect();
            const after = event.clientX > rect.left + rect.width / 2;
            this.list.insertBefore(dragging, after ? target.nextSibling : target);
        });
        const finish = () => {
            if (!dragging) return;
            dragging.classList.remove('is-dragging');
            dragging = null;
            const order = [...this.list.children].map((li) => Number(li.dataset.n));
            this.pages = order.map((n) => this.page(n));
            this.changed();
        };
        this.list.addEventListener('pointerup', finish);
        this.list.addEventListener('pointercancel', finish);
    }
}

/** Turn [1,2,3,5,7,8] into "1-3, 5, 7-8". */
export function compactRanges(numbers) {
    const sorted = [...new Set(numbers)].sort((a, b) => a - b);
    const parts = [];
    for (let i = 0; i < sorted.length; i++) {
        const start = sorted[i];
        while (i + 1 < sorted.length && sorted[i + 1] === sorted[i] + 1) i++;
        parts.push(start === sorted[i] ? `${start}` : `${start}-${sorted[i]}`);
    }
    return parts.join(', ');
}
