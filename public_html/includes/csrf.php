<?php
/**
 * CSRF protection (synchronizer token pattern).
 *
 * Forms:   <?= csrf_field() ?>   → hidden input "_csrf"
 * AJAX:    header "X-CSRF-Token" (app.js reads it from <meta name="csrf-token">)
 * Verify:  csrf_verify() at the top of every state-changing endpoint.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** True if the request carries a valid token. */
function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && !empty($_SESSION['_csrf'])
        && hash_equals((string) $_SESSION['_csrf'], $sent);
}

/** Abort with 419 if the token is missing/invalid (JSON for API requests). */
function csrf_verify(): void
{
    if (csrf_valid()) {
        return;
    }
    if (is_api_request()) {
        json_response(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 419);
    }
    render_error_page(419, 'Session expired', 'Your session expired. Please go back, refresh the page and try again.');
    exit;
}
