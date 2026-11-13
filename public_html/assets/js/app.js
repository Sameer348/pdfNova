/**
 * PDFNova — global UI (loaded on every page as an ES module).
 *
 * Exports shared helpers used by other modules:
 *   api(url, options)   fetch wrapper: CSRF header, JSON parsing, friendly errors
 *   toast(message, type)
 *   formatBytes(bytes)
 *
 * Also wires up: header scroll state, desktop dropdown menus, mobile menu,
 * cookie notice and password show/hide buttons.
 */

document.documentElement.classList.add('js');

/* -------------------------------------------------------------------------- */
/* Shared helpers                                                             */
/* -------------------------------------------------------------------------- */

export const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * Fetch wrapper for PDFNova JSON endpoints.
 * Always resolves to { success, message, ...data } — never throws for HTTP or
 * network errors, so callers can render one consistent error state.
 */
export async function api(url, { method = 'POST', data = null, formData = null, signal } = {}) {
    const headers = { 'X-CSRF-Token': csrfToken(), Accept: 'application/json' };
    let body;
    if (formData) {
        body = formData;
    } else if (data) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }
    let response;
    try {
        response = await fetch(url, { method, headers, body, signal, credentials: 'same-origin' });
    } catch (err) {
        if (err.name === 'AbortError') return { success: false, aborted: true, message: 'Cancelled.' };
        return { success: false, network: true, message: 'Network error. Check your connection and try again.' };
    }
    let json = null;
    try {
        json = await response.json();
    } catch {
        /* non-JSON response (e.g. PHP fatal error or proxy page) */
    }
    if (json && typeof json === 'object') {
        return { status: response.status, ...json, success: Boolean(json.success) && response.ok };
    }
    return {
        success: false,
        status: response.status,
        message: response.status === 413
            ? 'The file is too large for the server.'
            : `Server error (${response.status}). Please try again.`,
    };
}

export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let value = bytes;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    return `${i === 0 ? value : value.toFixed(value < 10 ? 1 : 0)} ${units[i]}`;
}

const ICONS = {
    success: '<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"/><path d="m8 12 3 3 5-6"/>',
    error: '<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
    info: '<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"/><path d="M12 11v5.5M12 7.5v.01"/>',
    close: '<path d="M6 6l12 12M18 6 6 18"/>',
};

export function svgIcon(name, cls = 'icon') {
    return `<svg class="${cls}" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${ICONS[name] ?? ''}</svg>`;
}

/** Show a short, auto-dismissing notification. Text is inserted safely (textContent). */
export function toast(message, type = 'info', timeout = 5000) {
    const region = document.querySelector('[data-toast-region]');
    if (!region) return;
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `${svgIcon(type === 'success' ? 'success' : type === 'error' ? 'error' : 'info')}<p></p>
        <button class="icon-btn" type="button" aria-label="Dismiss">${svgIcon('close', 'icon icon-sm')}</button>`;
    el.querySelector('p').textContent = message;
    const remove = () => {
        el.classList.add('is-leaving');
        setTimeout(() => el.remove(), 200);
    };
    el.querySelector('button').addEventListener('click', remove);
    region.appendChild(el);
    if (timeout) setTimeout(remove, timeout);
}

/** localStorage that never throws (private mode, blocked storage). */
export const safeStorage = {
    get(key) { try { return window.localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch { /* ignore */ } },
};

/* -------------------------------------------------------------------------- */
/* Header                                                                     */
/* -------------------------------------------------------------------------- */

const header = document.querySelector('[data-site-header]');
const desktopQuery = window.matchMedia('(min-width: 1024px)');
const hoverQuery = window.matchMedia('(hover: hover) and (pointer: fine)');

function initHeaderShadow() {
    if (!header) return;
    const update = () => header.classList.toggle('is-scrolled', window.scrollY > 4);
    update();
    window.addEventListener('scroll', update, { passive: true });
}

function initDropdowns() {
    const items = [...document.querySelectorAll('[data-dropdown]')];
    if (!items.length) return;

    const close = (item) => {
        item.classList.remove('is-open');
        item.querySelector('.nav-link')?.setAttribute('aria-expanded', 'false');
    };
    const open = (item) => {
        items.forEach((other) => other !== item && close(other));
        item.classList.add('is-open');
        item.querySelector('.nav-link')?.setAttribute('aria-expanded', 'true');
    };
    const closeAll = () => items.forEach(close);

    items.forEach((item) => {
        const trigger = item.querySelector('.nav-link');
        let hoverTimer;
        let openedByHoverAt = 0;

        trigger.addEventListener('click', () => {
            // A mouse user's click right after hover-open should not close the
            // menu again (hover already opened it).
            if (item.classList.contains('is-open') && Date.now() - openedByHoverAt < 600) {
                openedByHoverAt = 0;
                return;
            }
            item.classList.contains('is-open') ? close(item) : open(item);
        });

        // Hover intent for mouse users only (touch devices use click).
        item.addEventListener('mouseenter', () => {
            if (!hoverQuery.matches) return;
            clearTimeout(hoverTimer);
            if (!item.classList.contains('is-open')) openedByHoverAt = Date.now();
            open(item);
        });
        item.addEventListener('mouseleave', () => {
            if (!hoverQuery.matches) return;
            hoverTimer = setTimeout(() => close(item), 160);
        });

        // Close when keyboard focus leaves the menu.
        item.addEventListener('focusout', (event) => {
            if (!item.contains(event.relatedTarget)) close(item);
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) closeAll();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openItem = items.find((item) => item.classList.contains('is-open'));
        if (openItem) {
            close(openItem);
            openItem.querySelector('.nav-link')?.focus();
        }
    });
}

function initMobileMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const panel = document.querySelector('[data-mobile-nav]');
    if (!toggle || !panel) return;

    const setOpen = (isOpen) => {
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
        panel.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('menu-open', isOpen);
    };

    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    panel.addEventListener('click', (event) => {
        if (event.target.closest('a')) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    desktopQuery.addEventListener('change', (event) => {
        if (event.matches) setOpen(false);
    });
}

/* -------------------------------------------------------------------------- */
/* Cookie notice (informational: essential cookies only)                      */
/* -------------------------------------------------------------------------- */

function initCookieNotice() {
    const notice = document.querySelector('[data-cookie-notice]');
    if (!notice) return;
    const KEY = 'pdfnova_cookie_notice';
    if (safeStorage.get(KEY) === 'dismissed') return;
    notice.classList.add('is-visible');
    notice.querySelector('[data-cookie-dismiss]')?.addEventListener('click', () => {
        safeStorage.set(KEY, 'dismissed');
        notice.classList.remove('is-visible');
    });
}

/* -------------------------------------------------------------------------- */
/* Password show/hide: <button data-password-toggle="inputId">                */
/* -------------------------------------------------------------------------- */

function initPasswordToggles() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.querySelector('.icon-eye')?.toggleAttribute('hidden', show);
        button.querySelector('.icon-eye-off')?.toggleAttribute('hidden', !show);
    });
}

initHeaderShadow();
initDropdowns();
initMobileMenu();
initCookieNotice();
initPasswordToggles();
