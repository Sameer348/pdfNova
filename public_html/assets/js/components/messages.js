/**
 * ErrorMessage / SuccessMessage components.
 * All text is inserted with textContent — never as HTML — so server or
 * filename text can't inject markup.
 *
 *   showError(container, { title, text, actionLabel, onAction })
 *   showSuccess(container, { title, text })
 *   clearMessages(container)
 */
import { svgIcon } from '../app.js';

function render(container, type, { title = '', text = '', actionLabel = '', onAction = null } = {}) {
    clearMessages(container);
    const el = document.createElement('div');
    el.className = `alert alert-${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = svgIcon(type === 'error' ? 'error' : 'success');
    const body = document.createElement('div');
    body.className = 'alert-body';
    if (title) {
        const strong = document.createElement('strong');
        strong.textContent = title;
        body.append(strong);
    }
    if (text) {
        const p = document.createElement('p');
        p.textContent = text;
        body.append(p);
    }
    if (actionLabel && onAction) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary btn-sm alert-action';
        button.textContent = actionLabel;
        button.addEventListener('click', onAction);
        body.append(button);
    }
    el.append(body);
    container.append(el);
    return el;
}

export const showError = (container, options) => render(container, 'error', options);
export const showSuccess = (container, options) => render(container, 'success', options);

export function clearMessages(container) {
    container.querySelectorAll('.alert').forEach((el) => el.remove());
}

/**
 * Turn an api() result into a friendly { title, text } pair for the
 * standard failure states: network, too large, invalid file, rate limit,
 * session expired, server error.
 */
export function describeFailure(result) {
    if (result.network) return { title: 'Network error', text: result.message };
    switch (result.code) {
        case 'file_too_large': return { title: 'File too large', text: result.message };
        case 'invalid_type':
        case 'invalid_content':
        case 'corrupt_pdf':
        case 'unsupported_pdf':
        case 'encrypted_pdf':
        case 'not_encrypted':
        case 'empty_file':
            return { title: 'Invalid file', text: result.message };
        case 'rate_limited':
        case 'too_many_files':
        case 'quota_exceeded':
            return { title: 'Limit reached', text: result.message };
        case 'tool_unavailable': return { title: 'Tool unavailable', text: result.message };
        case 'wrong_password': return { title: 'Incorrect password', text: result.message };
        case 'unsupported_encryption': return { title: 'Encryption not supported', text: result.message };
        default:
            if (result.status === 419) return { title: 'Session expired', text: result.message };
            if (result.status >= 500 || !result.status) return { title: 'Server error', text: result.message || 'Something went wrong on our side. Please try again.' };
            return { title: 'Something went wrong', text: result.message };
    }
}
