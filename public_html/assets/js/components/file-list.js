/**
 * FileList component — renders the uploader's items and lets the user
 * remove, retry and reorder them.
 *
 * Reordering works three ways so it is usable everywhere:
 *   - drag the grip handle (mouse, pen AND touch — uses Pointer Events)
 *   - the ↑ / ↓ buttons (keyboard and screen-reader friendly)
 *   - keyboard arrows while the grip handle is focused
 *
 *   const list = new FileList(ul, { sortable, onRemove, onRetry, onMove, onReorder });
 *   list.sync(items);   // call whenever items change
 */
import { formatBytes } from '../app.js';
import { createProgressBar } from './progress-bar.js';

const ICON = {
    pdf: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.5-4.5L6 21"/>',
    doc: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M8.5 12.5h7M8.5 16.5h5"/>',
    grip: '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" stroke-width="3"/>',
    up: '<path d="m6 15 6-6 6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    retry: '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
    remove: '<path d="M6 6l12 12M18 6 6 18"/>',
    check: '<path d="m5 12 5 5L20 7"/>',
    alert: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
};
const svg = (name, cls = 'icon') =>
    `<svg class="${cls}" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${ICON[name]}</svg>`;

const kindOf = (ext) => (ext === 'pdf' ? 'pdf' : ['jpg', 'jpeg', 'png'].includes(ext) ? 'image' : 'doc');

export class FileList {
    constructor(ul, { sortable = false, onRemove, onRetry, onMove, onReorder } = {}) {
        this.ul = ul;
        this.sortable = sortable;
        this.handlers = { onRemove, onRetry, onMove, onReorder };
        this.elements = new Map(); // uid → { li, bar, refs }
        this.ul.addEventListener('click', (event) => this.handleClick(event));
        if (sortable) this.enableDrag();
    }

    /** Make the DOM match the items array (create, update, reorder, remove). */
    sync(items) {
        const seen = new Set();
        items.forEach((item, index) => {
            seen.add(item.uid);
            let entry = this.elements.get(item.uid);
            if (!entry) {
                entry = this.create(item);
                this.elements.set(item.uid, entry);
            }
            this.update(entry, item, index, items.length);
            if (this.ul.children[index] !== entry.li) {
                this.ul.insertBefore(entry.li, this.ul.children[index] ?? null);
            }
        });
        for (const [uid, entry] of this.elements) {
            if (!seen.has(uid)) {
                entry.li.remove();
                this.elements.delete(uid);
            }
        }
    }

    create(item) {
        const li = document.createElement('li');
        li.className = 'file-item';
        li.dataset.uid = item.uid;
        const kind = kindOf(item.ext);
        li.innerHTML = `
            ${this.sortable ? `<button class="file-handle" type="button" aria-label="Reorder ${''}" data-handle>${svg('grip')}</button>` : ''}
            <span class="file-thumb file-thumb-${kind}">${svg(kind)}</span>
            <div class="file-meta">
                <p class="file-name" data-name></p>
                <p class="file-sub"><span data-sub></span><span class="file-status" data-status></span></p>
                <div class="file-progress" data-progress></div>
                <p class="file-error" data-error hidden></p>
            </div>
            <div class="file-actions">
                ${this.sortable ? `<button class="icon-btn" type="button" data-act="up" aria-label="Move up">${svg('up')}</button>
                <button class="icon-btn" type="button" data-act="down" aria-label="Move down">${svg('down')}</button>` : ''}
                <button class="icon-btn" type="button" data-act="retry" aria-label="Retry upload" hidden>${svg('retry')}</button>
                <button class="icon-btn danger" type="button" data-act="remove" aria-label="Remove">${svg('remove')}</button>
            </div>`;
        // Real thumbnails for images (local preview only — nothing extra is uploaded).
        if (kind === 'image' && item.file) {
            const img = document.createElement('img');
            img.alt = '';
            img.className = 'file-thumb-img';
            img.src = URL.createObjectURL(item.file);
            img.onload = img.onerror = () => URL.revokeObjectURL(img.src);
            li.querySelector('.file-thumb').replaceChildren(img);
        }
        const bar = createProgressBar({ label: `Uploading ${item.name}`, size: 'sm' });
        li.querySelector('[data-progress]').append(bar.el);
        return {
            li,
            bar,
            name: li.querySelector('[data-name]'),
            sub: li.querySelector('[data-sub]'),
            status: li.querySelector('[data-status]'),
            progress: li.querySelector('[data-progress]'),
            error: li.querySelector('[data-error]'),
            retry: li.querySelector('[data-act="retry"]'),
            up: li.querySelector('[data-act="up"]'),
            down: li.querySelector('[data-act="down"]'),
            handle: li.querySelector('[data-handle]'),
        };
    }

    update(entry, item, index, total) {
        const { li } = entry;
        li.dataset.status = item.status;
        entry.name.textContent = item.name;
        entry.name.title = item.name;
        const parts = [formatBytes(item.size), item.ext.toUpperCase()];
        if (item.pages) parts.push(`${item.pages} page${item.pages === 1 ? '' : 's'}`);
        entry.sub.textContent = parts.join(' · ');

        const statusText = {
            queued: 'Waiting…',
            uploading: `Uploading ${Math.round(item.progress)}%`,
            processing: 'Checking file…',
            done: 'Ready',
            error: 'Failed',
            invalid: 'Not accepted',
        }[item.status] ?? '';
        entry.status.innerHTML = item.status === 'done' ? svg('check', 'icon icon-xs') : item.status === 'error' || item.status === 'invalid' ? svg('alert', 'icon icon-xs') : '';
        entry.status.append(document.createTextNode(` ${statusText}`));

        const showBar = item.status === 'uploading' || item.status === 'queued' || item.status === 'processing';
        entry.progress.hidden = !showBar;
        if (item.status === 'uploading') entry.bar.set(item.progress);
        else if (item.status === 'processing' || item.status === 'queued') entry.bar.indeterminate();

        entry.error.hidden = !item.error;
        entry.error.textContent = item.error ?? '';
        entry.retry.hidden = !(item.status === 'error' && item.retryable);

        if (this.sortable) {
            entry.up.disabled = index === 0;
            entry.down.disabled = index === total - 1;
            entry.handle.setAttribute('aria-label', `Reorder ${item.name} (position ${index + 1} of ${total}). Use arrow keys to move.`);
        }
        entry.li.querySelector('[data-act="remove"]').setAttribute('aria-label', `Remove ${item.name}`);
    }

    handleClick(event) {
        const button = event.target.closest('[data-act]');
        if (!button) return;
        const uid = button.closest('.file-item')?.dataset.uid;
        if (!uid) return;
        const act = button.dataset.act;
        if (act === 'remove') this.handlers.onRemove?.(uid);
        if (act === 'retry') this.handlers.onRetry?.(uid);
        if (act === 'up') this.handlers.onMove?.(uid, -1);
        if (act === 'down') this.handlers.onMove?.(uid, 1);
    }

    /** Pointer-based drag (works with touch, unlike the HTML5 drag & drop API). */
    enableDrag() {
        let dragging = null;

        this.ul.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-handle]');
            if (!handle || event.button > 0) return;
            dragging = handle.closest('.file-item');
            dragging.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        this.ul.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.file-item');
            if (!target || target === dragging || target.parentElement !== this.ul) return;
            const rect = target.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            this.ul.insertBefore(dragging, after ? target.nextSibling : target);
        });

        const finish = () => {
            if (!dragging) return;
            dragging.classList.remove('is-dragging');
            dragging = null;
            this.handlers.onReorder?.([...this.ul.children].map((li) => li.dataset.uid));
        };
        this.ul.addEventListener('pointerup', finish);
        this.ul.addEventListener('pointercancel', finish);

        // Keyboard: arrow keys on the focused handle.
        this.ul.addEventListener('keydown', (event) => {
            const handle = event.target.closest('[data-handle]');
            if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
            event.preventDefault();
            const uid = handle.closest('.file-item').dataset.uid;
            this.handlers.onMove?.(uid, event.key === 'ArrowUp' ? -1 : 1);
            requestAnimationFrame(() => this.elements.get(uid)?.handle?.focus());
        });
    }
}
