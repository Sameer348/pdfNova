/**
 * Homepage quick start: choose or drop a file → see the tools that accept it
 * → the chosen tool page opens with the file pre-loaded (via FileHandoff).
 * Files are NOT uploaded here; the tool page uploads them when the visitor runs it.
 */

import { formatBytes } from './app.js';
import { saveHandoff } from './components/file-handoff.js';

const root = document.querySelector('[data-quickstart]');

if (root) {
    const input = root.querySelector('[data-quickstart-input]');
    const drop = root.querySelector('[data-quickstart-drop]');
    const choose = root.querySelector('[data-quickstart-choose]');
    const errorBox = root.querySelector('[data-quickstart-error]');
    const nameEl = root.querySelector('[data-quickstart-name]');
    const sizeEl = root.querySelector('[data-quickstart-size]');
    const items = [...root.querySelectorAll('[data-quickstart-tools] li')];
    const maxBytes = Number(root.dataset.maxBytes || 0);

    const allowed = new Set(items.flatMap((li) => li.dataset.accept.split(',')));
    let selected = [];

    const extOf = (file) => (file.name.split('.').pop() || '').toLowerCase();

    const showError = (message) => {
        errorBox.querySelector('p').textContent = message;
        errorBox.hidden = false;
    };
    const clearError = () => { errorBox.hidden = true; };

    const reset = () => {
        selected = [];
        input.value = '';
        choose.hidden = true;
        drop.hidden = false;
        clearError();
    };

    const handleFiles = (fileList) => {
        clearError();
        const files = [...fileList];
        if (!files.length) return;

        const unsupported = files.find((f) => !allowed.has(extOf(f)));
        if (unsupported) {
            showError(`"${unsupported.name}" is not a supported file type. Use PDF, JPG, PNG, Word, Excel or PowerPoint files.`);
            return;
        }
        const empty = files.find((f) => f.size === 0);
        if (empty) {
            showError(`"${empty.name}" is empty.`);
            return;
        }
        const tooBig = maxBytes > 0 && files.find((f) => f.size > maxBytes);
        if (tooBig) {
            showError(`"${tooBig.name}" is ${formatBytes(tooBig.size)}. The maximum file size is ${formatBytes(maxBytes)}.`);
            return;
        }

        // Show tools that accept EVERY selected file (and multiple files, if needed).
        const exts = new Set(files.map(extOf));
        let matches = 0;
        items.forEach((li) => {
            const accept = li.dataset.accept.split(',');
            const ok = [...exts].every((ext) => accept.includes(ext)) && (files.length === 1 || li.dataset.multiple === '1');
            li.hidden = !ok;
            if (ok) matches++;
        });
        if (!matches) {
            showError('These files can’t be processed together. Try selecting files of one type, for example only PDFs or only images.');
            return;
        }

        selected = files;
        const total = files.reduce((sum, f) => sum + f.size, 0);
        nameEl.textContent = files.length === 1 ? files[0].name : `${files.length} files selected`;
        sizeEl.textContent = formatBytes(total);
        drop.hidden = true;
        choose.hidden = false;
        choose.querySelector('[data-quickstart-tool]:not([disabled])')?.focus({ preventScroll: true });
    };

    // Browse buttons (hero button + drop zone button)
    document.querySelectorAll('[data-quickstart-browse]').forEach((button) => {
        button.addEventListener('click', () => input.click());
    });
    input.addEventListener('change', () => handleFiles(input.files));
    root.querySelector('[data-quickstart-reset]').addEventListener('click', reset);

    // Drag & drop
    let depth = 0;
    root.addEventListener('dragenter', (event) => {
        event.preventDefault();
        depth++;
        root.classList.add('is-dragging');
    });
    root.addEventListener('dragover', (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    });
    root.addEventListener('dragleave', () => {
        depth = Math.max(0, depth - 1);
        if (!depth) root.classList.remove('is-dragging');
    });
    root.addEventListener('drop', (event) => {
        event.preventDefault();
        depth = 0;
        root.classList.remove('is-dragging');
        handleFiles(event.dataTransfer.files);
    });
    // A file dropped outside the zone must not navigate the browser away.
    window.addEventListener('dragover', (event) => event.preventDefault());
    window.addEventListener('drop', (event) => event.preventDefault());

    // Tool choice → hand the files over, then open the tool page.
    root.querySelectorAll('[data-quickstart-tool]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled || !selected.length) return;
            button.classList.add('is-busy');
            await saveHandoff(button.dataset.quickstartTool, selected);
            window.location.href = button.dataset.href;
        });
    });
}
