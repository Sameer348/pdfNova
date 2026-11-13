/**
 * DownloadBox component — shown when a job succeeds.
 *
 *   renderDownloadBox(container, {
 *     title, filename, sizeLabel, url,
 *     stats: [{ label, value }],          // optional (e.g. compression results)
 *     note,                               // optional
 *     onRestart,                          // "Start over" callback
 *   })
 */
import { svgIcon } from '../app.js';

export function renderDownloadBox(container, { title = 'Your file is ready', filename, sizeLabel = '', url, stats = [], note = '', onRestart }) {
    container.replaceChildren();
    const box = document.createElement('div');
    box.className = 'download-box';
    box.innerHTML = `
        <div class="download-check">${svgIcon('success', 'icon icon-xl')}</div>
        <h2 class="h3" data-title></h2>
        <div class="download-file">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
            <span class="download-name" data-name></span>
            <span class="download-size" data-size></span>
        </div>
        <dl class="download-stats" data-stats hidden></dl>
        <div class="download-actions">
            <a class="btn btn-primary btn-lg" data-download>
                <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>
                Download
            </a>
            <button class="btn btn-secondary btn-lg" type="button" data-restart>Start over</button>
        </div>
        <p class="hint" data-note></p>`;

    box.querySelector('[data-title]').textContent = title;
    box.querySelector('[data-name]').textContent = filename;
    box.querySelector('[data-size]').textContent = sizeLabel;
    const link = box.querySelector('[data-download]');
    link.href = url;
    link.setAttribute('download', filename);

    if (stats.length) {
        const dl = box.querySelector('[data-stats]');
        stats.forEach(({ label, value, highlight }) => {
            const item = document.createElement('div');
            if (highlight) item.className = 'is-highlight';
            const dt = document.createElement('dt');
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.textContent = value;
            item.append(dt, dd);
            dl.append(item);
        });
        dl.hidden = false;
    }
    box.querySelector('[data-note]').textContent =
        note || 'For your privacy, this file is deleted from our server automatically.';
    box.querySelector('[data-restart]').addEventListener('click', () => onRestart?.());

    container.append(box);
    link.focus({ preventScroll: true });
    return box;
}
