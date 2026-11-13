/**
 * ProgressBar component.
 *
 *   const bar = createProgressBar({ label: 'Uploading report.pdf' });
 *   container.append(bar.el);
 *   bar.set(42);          // determinate (0–100)
 *   bar.indeterminate();  // unknown duration (animated)
 */
export function createProgressBar({ label = 'Progress', size = 'md' } = {}) {
    const el = document.createElement('div');
    el.className = `progress progress-${size}`;
    el.setAttribute('role', 'progressbar');
    el.setAttribute('aria-label', label);
    el.setAttribute('aria-valuemin', '0');
    el.setAttribute('aria-valuemax', '100');
    const fill = document.createElement('div');
    fill.className = 'progress-fill';
    el.append(fill);

    return {
        el,
        set(percent) {
            const value = Math.max(0, Math.min(100, Math.round(percent)));
            el.classList.remove('is-indeterminate');
            el.setAttribute('aria-valuenow', String(value));
            fill.style.width = `${value}%`;
        },
        indeterminate() {
            el.classList.add('is-indeterminate');
            el.removeAttribute('aria-valuenow');
            fill.style.width = '';
        },
    };
}
