/**
 * Watermark PDF — options, image watermark upload and a live preview on
 * page 1 (rendered with pdf.js; overlay drawn with the same geometry rules
 * as services/pdf/Watermarker.php — margins, rotation, shrink-to-fit).
 * The preview is a close approximation; fonts can differ slightly.
 */
import { registerToolHooks } from '../tools.js';
import { api, csrfToken, toast } from '../app.js';
import { parseRanges } from '../components/page-ranges.js';
import { loadPdfJs } from '../components/page-grid.js';

const app = document.querySelector('[data-tool-app]');
const form = document.querySelector('[data-tool-options]');
const canvasArea = document.querySelector('[data-tool-canvas]');
const feedback = form.querySelector('[data-wm-feedback]');
const pagesInput = form.querySelector('[data-wm-pages]');
const imgInput = form.querySelector('[data-wm-image-input]');
const imgPreview = form.querySelector('[data-wm-image-preview]');
const imgStatus = form.querySelector('[data-wm-image-status]');
const imgRemove = form.querySelector('[data-wm-image-remove]');

const MM = 72 / 25.4;          // points per millimetre
const MARGIN = 10 * MM;        // same 10 mm margin as the server
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

const state = { imageId: null, imageEl: null, imageUploading: false, problem: null, loadedFor: null };
const preview = { base: null, overlay: null, page: null, scale: 1 };

const val = (name) => form.querySelector(`[name="${name}"]:checked`)?.value ?? form.querySelector(`[name="${name}"]`)?.value;
const num = (name) => Number(val(name));

/* ---------------------------------------------------- option readouts */

function updateReadouts() {
    form.querySelector('[data-out="font_size"]').textContent = `${num('font_size')} pt`;
    form.querySelector('[data-out="image_scale"]').textContent = `${num('image_scale')}% of page width`;
    form.querySelector('[data-out="opacity"]').textContent = `${num('opacity')}%`;
    form.querySelector('[data-out="rotation"]').textContent = `${num('rotation')}°`;
    const type = val('type');
    form.querySelectorAll('[data-wm-panel]').forEach((p) => { p.hidden = p.dataset.wmPanel !== type; });
    pagesInput.hidden = val('apply') !== 'selected';
}

/* ------------------------------------------------- image watermark */

form.querySelector('[data-wm-image-pick]').addEventListener('click', () => imgInput.click());
imgInput.addEventListener('change', () => {
    const file = imgInput.files[0];
    imgInput.value = '';
    if (file) uploadImage(file);
});
imgRemove.addEventListener('click', () => {
    if (state.imageId) api(app.dataset.deleteUrl, { data: { file_id: state.imageId } });
    Object.assign(state, { imageId: null, imageEl: null });
    imgPreview.hidden = true;
    imgRemove.hidden = true;
    imgStatus.textContent = 'PNG or JPG, up to 5 MB. Transparent PNGs work best.';
    changed();
});

function uploadImage(file) {
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!['png', 'jpg', 'jpeg'].includes(ext)) { toast('Please choose a PNG or JPG image.', 'error'); return; }
    if (file.size > MAX_IMAGE_BYTES) { toast('The image must be 5 MB or smaller.', 'error'); return; }

    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { state.imageEl = img; drawOverlay(); };
    img.src = url;
    imgPreview.src = url;
    imgPreview.hidden = false;
    imgStatus.textContent = 'Uploading image…';
    state.imageUploading = true;
    state.imageId = null;
    changed();

    const body = new FormData();
    body.append('tool', app.dataset.tool);
    body.append('role', 'watermark-image');
    body.append('file', file, file.name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', app.dataset.uploadUrl);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken());
    xhr.responseType = 'json';
    xhr.onload = () => {
        state.imageUploading = false;
        if (xhr.status === 200 && xhr.response?.success) {
            state.imageId = xhr.response.file.id;
            imgStatus.textContent = `${file.name} · ready`;
            imgRemove.hidden = false;
        } else {
            imgStatus.textContent = xhr.response?.message || 'The image could not be uploaded.';
            imgPreview.hidden = true;
            state.imageEl = null;
        }
        changed();
    };
    xhr.onerror = () => {
        state.imageUploading = false;
        imgStatus.textContent = 'Network error. Please try again.';
        changed();
    };
    xhr.send(body);
}

/* ------------------------------------------------------- preview */

async function loadPreview(file) {
    canvasArea.hidden = false;
    canvasArea.innerHTML = `<p class="label">Preview · page 1</p>
        <div class="wm-preview" data-wm-preview><p class="hint">Loading preview…</p></div>
        <p class="hint">Approximate preview. The watermark is placed on top of the page content.</p>`;
    const box = canvasArea.querySelector('[data-wm-preview]');
    try {
        const pdfjs = await loadPdfJs();
        const doc = await pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
        const page = await doc.getPage(1);
        const base = page.getViewport({ scale: 1 });
        const scale = Math.min(460 / base.width, 600 / base.height) * Math.min(window.devicePixelRatio || 1, 2);
        const viewport = page.getViewport({ scale });
        const c = document.createElement('canvas');
        c.width = Math.ceil(viewport.width);
        c.height = Math.ceil(viewport.height);
        await page.render({ canvasContext: c.getContext('2d'), viewport }).promise;
        const overlay = document.createElement('canvas');
        overlay.width = c.width;
        overlay.height = c.height;
        overlay.className = 'wm-overlay';
        box.replaceChildren(c, overlay);
        Object.assign(preview, { base: c, overlay, page: { w: base.width, h: base.height }, scale });
        doc.destroy();
        drawOverlay();
    } catch {
        box.innerHTML = '<p class="hint">A preview is not available for this file, but the watermark will still be applied.</p>';
    }
}

function rotatedBox(w, h, deg) {
    const r = (deg * Math.PI) / 180;
    return [Math.abs(w * Math.cos(r)) + Math.abs(h * Math.sin(r)), Math.abs(w * Math.sin(r)) + Math.abs(h * Math.cos(r))];
}

function centre(W, H, bw, bh, position) {
    const [v, hz] = {
        'top-left': ['top', 'left'], 'top-center': ['top', 'center'], 'top-right': ['top', 'right'],
        'middle-left': ['middle', 'left'], 'middle-right': ['middle', 'right'],
        'bottom-left': ['bottom', 'left'], 'bottom-center': ['bottom', 'center'], 'bottom-right': ['bottom', 'right'],
    }[position] ?? ['middle', 'center'];
    const cx = hz === 'left' ? MARGIN + bw / 2 : hz === 'right' ? W - MARGIN - bw / 2 : W / 2;
    const cy = v === 'top' ? MARGIN + bh / 2 : v === 'bottom' ? H - MARGIN - bh / 2 : H / 2;
    return [cx, cy];
}

function drawOverlay() {
    const { overlay, page, scale } = preview;
    if (!overlay) return;
    const ctx = overlay.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, overlay.width, overlay.height);
    ctx.scale(scale, scale); // work in PDF points from here on

    const rotation = num('rotation');
    const position = val('position');
    ctx.globalAlpha = num('opacity') / 100;

    let w;
    let h;
    let draw;
    if (val('type') === 'text') {
        const text = (val('text') || '').trim();
        if (!text) return;
        let size = num('font_size');
        const font = (s) => `bold ${s}px Helvetica, Arial, sans-serif`;
        ctx.font = font(size);
        w = ctx.measureText(text).width;
        h = size * 1.15;
        let [bw, bh] = rotatedBox(w, h, rotation);
        const fit = Math.min(1, (page.w - 2 * MARGIN) / bw, (page.h - 2 * MARGIN) / bh);
        if (fit < 1) {
            size = Math.max(4, size * fit);
            ctx.font = font(size);
            w = ctx.measureText(text).width;
            h = size * 1.15;
        }
        const color = val('color');
        draw = () => {
            ctx.fillStyle = color;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, 0, 0);
        };
    } else {
        const img = state.imageEl;
        if (!img) return;
        w = page.w * (num('image_scale') / 100);
        h = (w * img.naturalHeight) / img.naturalWidth;
        const [bw0, bh0] = rotatedBox(w, h, rotation);
        const fit = Math.min(1, (page.w - 2 * MARGIN) / bw0, (page.h - 2 * MARGIN) / bh0);
        w *= fit;
        h *= fit;
        draw = () => ctx.drawImage(img, -w / 2, -h / 2, w, h);
    }

    const [bw, bh] = rotatedBox(w, h, rotation);
    const [cx, cy] = centre(page.w, page.h, bw, bh, position);
    ctx.translate(cx, cy);
    ctx.rotate((-rotation * Math.PI) / 180); // PDF rotation is counter-clockwise
    draw();
}

/* -------------------------------------------------------- validation */

function evaluate(pageCount) {
    if (val('type') === 'text' && !(val('text') || '').trim()) return 'Enter the watermark text.';
    if (val('type') === 'image') {
        if (state.imageUploading) return 'Waiting for the image to upload…';
        if (!state.imageId) return 'Choose an image for the watermark.';
    }
    if (val('apply') === 'selected') {
        if (!pagesInput.value.trim()) return 'Enter the pages to watermark.';
        try { parseRanges(pagesInput.value, pageCount); } catch (err) { return err.message; }
    }
    return null;
}

function changed() {
    form.dispatchEvent(new Event('change', { bubbles: true })); // re-run the tool controller checks
}

// "Start over" resets the form; the image was deleted on the server together with the job.
form.addEventListener('reset', () => {
    Object.assign(state, { imageId: null, imageEl: null, imageUploading: false });
    imgPreview.hidden = true;
    imgRemove.hidden = true;
    imgStatus.textContent = 'PNG or JPG, up to 5 MB. Transparent PNGs work best.';
    setTimeout(() => { updateReadouts(); drawOverlay(); });
});
form.addEventListener('input', () => { updateReadouts(); drawOverlay(); });
form.addEventListener('change', () => { updateReadouts(); drawOverlay(); });
updateReadouts();

registerToolHooks({
    onFilesChanged({ items }) {
        const item = items[0];
        if (!item?.pages) {
            if (!items.length) { canvasArea.hidden = true; state.loadedFor = null; }
            feedback.textContent = '';
            return;
        }
        if (state.loadedFor !== item.uid) {
            state.loadedFor = item.uid;
            loadPreview(item.file);
        }
        state.problem = evaluate(item.pages);
        feedback.textContent = state.problem ?? '';
        feedback.className = `split-feedback${state.problem ? ' is-error' : ''}`;
    },
    validate() {
        return state.problem;
    },
    collectOptions() {
        return {
            type: val('type'), text: val('text'), font_size: num('font_size'), color: val('color'),
            image_id: state.imageId, image_scale: num('image_scale'),
            opacity: num('opacity'), rotation: num('rotation'), position: val('position'),
            apply: val('apply'), pages: pagesInput.value,
        };
    },
});
