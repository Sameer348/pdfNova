/**
 * FileUploader — drag & drop / browse, client-side validation, per-file
 * upload with progress (XHR), retry, remove and reorder.
 *
 * Client-side checks are for quick feedback only; the server re-validates
 * everything (extension, magic bytes, MIME, size, structure).
 *
 *   const uploader = new FileUploader(appEl, {
 *     tool, exts, multiple, maxFiles, maxBytes,
 *     upload: true, uploadUrl, deleteUrl,
 *     onChange(items), onNotice(message)
 *   });
 */
import { api, csrfToken } from './app.js';

/** First bytes each format must start with (PDF may have a short preamble). */
const SIGNATURES = {
    pdf: (b) => new TextDecoder('latin1').decode(b).includes('%PDF-'),
    jpg: (b) => b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff,
    png: (b) => b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4e && b[3] === 0x47,
    zip: (b) => b[0] === 0x50 && b[1] === 0x4b && b[2] === 0x03 && b[3] === 0x04,
    ole: (b) => b[0] === 0xd0 && b[1] === 0xcf && b[2] === 0x11 && b[3] === 0xe0,
};
const SIGNATURE_FOR = { pdf: 'pdf', jpg: 'jpg', jpeg: 'jpg', png: 'png', docx: 'zip', xlsx: 'zip', pptx: 'zip', doc: 'ole', xls: 'ole', ppt: 'ole' };

const CONCURRENCY = 2;
let uidCounter = 0;

export class FileUploader {
    constructor(root, options) {
        this.root = root;
        this.opts = options;
        this.items = [];
        this.active = 0;
        this.input = root.querySelector('[data-file-input]');
        this.bindPicker();
    }

    /* ----------------------------------------------------------- picking */

    bindPicker() {
        this.root.addEventListener('click', (event) => {
            if (event.target.closest('[data-browse]')) this.input.click();
        });
        this.input.addEventListener('change', () => {
            this.addFiles(this.input.files);
            this.input.value = ''; // allow choosing the same file again
        });

        let depth = 0;
        const zone = this.root;
        zone.addEventListener('dragenter', (event) => {
            if (!event.dataTransfer?.types?.includes('Files')) return;
            event.preventDefault();
            depth++;
            zone.classList.add('is-dragging');
        });
        zone.addEventListener('dragover', (event) => {
            if (!event.dataTransfer?.types?.includes('Files')) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        });
        zone.addEventListener('dragleave', () => {
            depth = Math.max(0, depth - 1);
            if (!depth) zone.classList.remove('is-dragging');
        });
        zone.addEventListener('drop', (event) => {
            if (!event.dataTransfer?.files?.length) return;
            event.preventDefault();
            depth = 0;
            zone.classList.remove('is-dragging');
            this.addFiles(event.dataTransfer.files);
        });
        // A file dropped elsewhere on the page must not open in the browser.
        window.addEventListener('dragover', (event) => event.preventDefault());
        window.addEventListener('drop', (event) => event.preventDefault());
    }

    /* -------------------------------------------------------- validation */

    async addFiles(fileList) {
        let files = [...fileList];
        if (!files.length) return;
        const { multiple, maxFiles } = this.opts;

        if (!multiple) {
            // Single-file tools: a new file replaces the current one.
            if (files.length > 1) this.notice('This tool works with one file at a time. We used the first one.');
            files = files.slice(0, 1);
            [...this.items].forEach((item) => this.remove(item.uid));
        } else {
            const room = maxFiles - this.items.filter((i) => i.status !== 'invalid').length;
            if (room <= 0) {
                this.notice(`You can add up to ${maxFiles} files on your plan.`);
                return;
            }
            if (files.length > room) {
                this.notice(`Only ${room} more file${room === 1 ? '' : 's'} can be added (limit ${maxFiles}). The rest were skipped.`);
                files = files.slice(0, room);
            }
        }

        for (const file of files) {
            const item = {
                uid: `f${++uidCounter}`,
                file,
                name: file.name,
                size: file.size,
                ext: (file.name.split('.').pop() || '').toLowerCase(),
                status: 'processing',
                progress: 0,
                error: null,
                retryable: false,
                serverId: null,
                pages: null,
                xhr: null,
            };
            this.items.push(item);
            this.changed();
            const problem = await this.check(item);
            if (problem) {
                Object.assign(item, { status: 'invalid', error: problem });
            } else if (this.opts.upload) {
                item.status = 'queued';
            } else {
                item.status = 'done'; // browser-only tool: nothing to upload
            }
            this.changed();
        }
        this.pump();
    }

    async check(item) {
        const { exts, maxBytes } = this.opts;
        if (!exts.includes(item.ext)) {
            return `Not a supported file type. Use ${exts.filter((e) => e !== 'jpeg').map((e) => e.toUpperCase()).join(', ')}.`;
        }
        if (item.size === 0) return 'This file is empty.';
        if (item.size > maxBytes) {
            return `File too large (${(item.size / 1048576).toFixed(1)} MB). Maximum is ${(maxBytes / 1048576).toFixed(0)} MB.`;
        }
        try {
            const head = new Uint8Array(await item.file.slice(0, 1024).arrayBuffer());
            const sig = SIGNATURES[SIGNATURE_FOR[item.ext]];
            if (sig && !sig(head)) return `This doesn't look like a valid ${item.ext.toUpperCase()} file.`;
        } catch {
            return 'This file could not be read. It may have been moved or deleted.';
        }
        return null;
    }

    /* ----------------------------------------------------------- upload */

    pump() {
        while (this.active < CONCURRENCY) {
            const next = this.items.find((i) => i.status === 'queued');
            if (!next) break;
            this.upload(next);
        }
    }

    upload(item) {
        this.active++;
        Object.assign(item, { status: 'uploading', progress: 0, error: null });
        this.changed();

        const form = new FormData();
        form.append('tool', this.opts.tool);
        form.append('file', item.file, item.name);

        const xhr = new XMLHttpRequest();
        item.xhr = xhr;
        xhr.open('POST', this.opts.uploadUrl);
        xhr.setRequestHeader('X-CSRF-Token', csrfToken());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.timeout = 10 * 60 * 1000;
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;
            // Keep the last 2% for server-side validation time.
            item.progress = Math.min(98, (event.loaded / event.total) * 100);
            this.changed();
        });

        const done = (status, error = null, retryable = false) => {
            this.active--;
            item.xhr = null;
            Object.assign(item, { status, error, retryable });
            this.changed();
            this.pump();
        };

        xhr.addEventListener('load', () => {
            const body = xhr.response && typeof xhr.response === 'object' ? xhr.response : null;
            if (xhr.status >= 200 && xhr.status < 300 && body?.success) {
                item.serverId = body.file.id;
                item.pages = body.file.pages;
                item.progress = 100;
                done('done');
                return;
            }
            if (xhr.status === 413) {
                done('error', body?.message || 'This file is larger than the server allows.', false);
                return;
            }
            const message = body?.message || `Upload failed (server error ${xhr.status}).`;
            // Validation failures (4xx except 408/419/429) won't succeed on retry.
            const retryable = !body || xhr.status >= 500 || [408, 419, 429].includes(xhr.status);
            done('error', message, retryable);
        });
        xhr.addEventListener('error', () => done('error', 'Network error. Check your connection and retry.', true));
        xhr.addEventListener('timeout', () => done('error', 'The upload timed out. Please retry.', true));
        xhr.addEventListener('abort', () => {
            this.active--;
            this.pump();
        });

        xhr.send(form);
    }

    /* ---------------------------------------------------- list actions */

    retry(uid) {
        const item = this.find(uid);
        if (item && item.status === 'error' && item.retryable) {
            item.status = 'queued';
            item.error = null;
            this.changed();
            this.pump();
        }
    }

    remove(uid) {
        const item = this.find(uid);
        if (!item) return;
        if (item.xhr) item.xhr.abort();
        if (item.serverId) this.deleteRemote(item.serverId);
        this.items = this.items.filter((i) => i !== item);
        this.changed();
    }

    move(uid, delta) {
        const index = this.items.findIndex((i) => i.uid === uid);
        const target = index + delta;
        if (index < 0 || target < 0 || target >= this.items.length) return;
        const [item] = this.items.splice(index, 1);
        this.items.splice(target, 0, item);
        this.changed();
    }

    reorder(uids) {
        const byUid = new Map(this.items.map((i) => [i.uid, i]));
        this.items = uids.map((uid) => byUid.get(uid)).filter(Boolean);
        this.changed();
    }

    /** Remove everything. Server copies are deleted unless they were already consumed by a job. */
    reset({ deleteRemote = true } = {}) {
        this.items.forEach((item) => {
            if (item.xhr) item.xhr.abort();
            if (deleteRemote && item.serverId) this.deleteRemote(item.serverId);
        });
        this.items = [];
        this.changed();
    }

    deleteRemote(fileId) {
        api(this.opts.deleteUrl, { data: { file_id: fileId } }); // best effort; cron deletes it anyway
    }

    /* ------------------------------------------------------------ state */

    find(uid) { return this.items.find((i) => i.uid === uid); }
    get busy() { return this.items.some((i) => ['queued', 'uploading', 'processing'].includes(i.status)); }
    get readyItems() { return this.items.filter((i) => i.status === 'done'); }
    get problemItems() { return this.items.filter((i) => i.status === 'error' || i.status === 'invalid'); }

    changed() { this.opts.onChange?.(this.items); }
    notice(message) { this.opts.onNotice?.(message); }
}
