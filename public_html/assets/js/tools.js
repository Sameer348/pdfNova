/**
 * Tool page controller — ONE script for every tool page.
 *
 * Reads the tool's configuration from data-* attributes on [data-tool-app]
 * (rendered by pages/tool.php), wires FileUploader + FileList, and drives the
 * stages:  pick → work → processing → result  (with error states throughout).
 *
 * Tool-specific behaviour plugs in through registerToolHooks():
 *   collectOptions(form)  → object sent as "options" (default: form fields)
 *   validate(ctx)         → error string to block the run, or null
 *   run(ctx)              → for browser-only tools (no server endpoint)
 *   onFilesChanged(ctx)   → e.g. render page thumbnails
 */
import { api, toast } from './app.js';
import { FileUploader } from './uploader.js';
import { FileList } from './components/file-list.js';
import { showError, clearMessages, describeFailure } from './components/messages.js';
import { renderDownloadBox } from './components/download-box.js';
import { createProgressBar } from './components/progress-bar.js';
import { takeHandoff } from './components/file-handoff.js';

const hooks = {};
let controller = null;

export function registerToolHooks(newHooks) {
    Object.assign(hooks, newHooks);
    controller?.refresh();
}

/** Default options collector: form fields → plain object (repeated names → arrays). */
export function formToObject(form) {
    const data = {};
    if (!form) return data;
    for (const [key, value] of new FormData(form).entries()) {
        if (key in data) data[key] = [].concat(data[key], value);
        else data[key] = value;
    }
    form.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        if (box.name && !(box.name in data)) data[box.name] = '';
    });
    return data;
}

class ToolController {
    constructor(app) {
        this.app = app;
        const d = app.dataset;
        this.cfg = {
            tool: d.tool,
            action: d.action,
            endpoint: d.endpoint,
            ready: d.ready === '1',
            upload: d.upload === '1',
            exts: d.exts.split(','),
            multiple: d.multiple === '1',
            minFiles: Number(d.minFiles || 1),
            maxFiles: Number(d.maxFiles || 1),
            maxBytes: Number(d.maxBytes || 0),
        };
        this.stages = Object.fromEntries([...app.querySelectorAll('[data-stage]')].map((el) => [el.dataset.stage, el]));
        this.runButton = app.querySelector('[data-run]');
        this.runHint = app.querySelector('[data-run-hint]');
        this.countBadge = app.querySelector('[data-file-count]');
        this.form = app.querySelector('[data-tool-options]');
        this.errors = app.querySelector('[data-tool-errors]');
        this.processing = false;

        this.uploader = new FileUploader(app, {
            tool: this.cfg.tool,
            exts: this.cfg.exts,
            multiple: this.cfg.multiple,
            maxFiles: this.cfg.maxFiles,
            maxBytes: this.cfg.maxBytes,
            upload: this.cfg.upload,
            uploadUrl: d.uploadUrl,
            deleteUrl: d.deleteUrl,
            onChange: () => this.refresh(),
            onNotice: (message) => toast(message, 'info'),
        });

        this.list = new FileList(app.querySelector('[data-file-list]'), {
            sortable: this.cfg.multiple,
            onRemove: (uid) => this.uploader.remove(uid),
            onRetry: (uid) => this.uploader.retry(uid),
            onMove: (uid, delta) => this.uploader.move(uid, delta),
            onReorder: (uids) => this.uploader.reorder(uids),
        });

        this.runButton.addEventListener('click', () => this.run());
        this.form?.addEventListener('input', () => this.refresh());
        this.form?.addEventListener('change', () => this.refresh());
        this.form?.addEventListener('submit', (event) => { event.preventDefault(); this.run(); });

        // Warn before leaving while work is in progress.
        window.addEventListener('beforeunload', (event) => {
            if (this.uploader.busy || this.processing) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        this.refresh();
        this.loadHandoff();
    }

    async loadHandoff() {
        const files = await takeHandoff(this.cfg.tool);
        if (files.length) this.uploader.addFiles(files);
    }

    get context() {
        return { tool: this.cfg.tool, items: this.uploader.readyItems, allItems: this.uploader.items, form: this.form, app: this.app };
    }

    show(stage) {
        Object.entries(this.stages).forEach(([name, el]) => { el.hidden = name !== stage; });
    }

    /** Re-render list + stage + run button state. */
    refresh() {
        if (this.processing || !this.stages.result.hidden) return;
        const items = this.uploader.items;
        this.list.sync(items);
        this.show(items.length ? 'work' : 'pick');
        if (this.countBadge) this.countBadge.textContent = String(items.length);
        hooks.onFilesChanged?.(this.context);

        const hint = this.blockingReason();
        this.runButton.disabled = hint !== null;
        this.runHint.textContent = hint ?? '';
    }

    /** Why the run button is disabled (null = ready to run). */
    blockingReason() {
        const { ready, minFiles, exts } = this.cfg;
        if (!ready) return 'Processing for this tool is not switched on yet.';
        if (this.uploader.busy) return this.cfg.upload ? 'Waiting for uploads to finish…' : 'Checking files…';
        if (this.uploader.problemItems.length) return 'Remove or retry the files marked with an error.';
        const count = this.uploader.readyItems.length;
        if (count < minFiles) {
            const type = exts[0] === 'pdf' ? 'PDF ' : '';
            return `Add at least ${minFiles} ${type}file${minFiles === 1 ? '' : 's'}.`;
        }
        return hooks.validate?.(this.context) ?? null;
    }

    async run() {
        if (this.blockingReason() !== null || this.processing) return;
        clearMessages(this.errors);
        const options = hooks.collectOptions ? hooks.collectOptions(this.form, this.context) : formToObject(this.form);

        this.processing = true;
        this.show('processing');
        const progressSlot = this.app.querySelector('[data-processing-progress]');
        const bar = createProgressBar({ label: 'Processing' });
        bar.indeterminate();
        progressSlot.replaceChildren(bar.el);
        this.app.querySelector('[data-processing-title]').textContent = `${this.cfg.action}…`;
        this.stages.processing.scrollIntoView({ behavior: 'smooth', block: 'center' });

        let result;
        if (hooks.run) {
            // Browser-only tool
            try {
                result = await hooks.run({ ...this.context, options, progress: (pct) => bar.set(pct) });
            } catch (err) {
                result = { success: false, message: err?.message || 'Processing failed in your browser.' };
            }
        } else {
            result = await api(this.cfg.endpoint, {
                data: { tool: this.cfg.tool, files: this.uploader.readyItems.map((i) => i.serverId), options },
            });
        }
        this.processing = false;

        if (result.success) {
            this.showResult(result);
        } else {
            this.show('work');
            const { title, text } = describeFailure(result);
            showError(this.errors, {
                title: title === 'Something went wrong' ? 'Unable to process your file' : title,
                text,
                actionLabel: result.status === 419 ? 'Reload page' : 'Try again',
                onAction: () => (result.status === 419 ? window.location.reload() : this.run()),
            });
            this.refresh();
        }
    }

    showResult(result) {
        // The server deletes input files once a job finishes, so don't delete them again.
        this.uploader.reset({ deleteRemote: false });
        this.show('result');
        // Browser-made results are object URLs: remember them so "Start over" can release the memory.
        if (result.objectUrl) this.stages.result.dataset.objectUrl = result.download_url;
        renderDownloadBox(this.stages.result, {
            title: result.title || 'Your file is ready',
            filename: result.filename,
            sizeLabel: result.size_label || '',
            url: result.download_url,
            stats: result.stats || [],
            note: result.note || '',
            onRestart: () => this.restart(),
        });
        this.stages.result.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    restart() {
        clearMessages(this.errors);
        if (this.stages.result.dataset.objectUrl) {
            URL.revokeObjectURL(this.stages.result.dataset.objectUrl);
            delete this.stages.result.dataset.objectUrl;
        }
        this.stages.result.replaceChildren();
        this.stages.result.hidden = true;
        this.form?.reset();
        this.refresh();
        this.app.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

const app = document.querySelector('[data-tool-app]');
if (app) controller = new ToolController(app);
