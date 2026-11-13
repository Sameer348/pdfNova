<?php
/**
 * Shared setup for every JSON API endpoint in /api.
 *
 *   require __DIR__ . '/_bootstrap.php';
 *   api_require_method('POST');   // also verifies CSRF for POST
 *
 * All responses use: { "success": bool, "message": string, ...data }
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/jobs.php';

header('X-Robots-Tag: noindex');

// The leading underscore file itself must never be called directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    api_error('Not found.', 404);
}

if (setting('maintenance_mode', '0') === '1' && !has_role('admin')) {
    api_error('PDFNova is undergoing maintenance. Please try again shortly.', 503, 'maintenance');
}

function api_success(array $data = [], string $message = 'Done.'): never
{
    json_response(['success' => true, 'message' => $message] + $data);
}

function api_error(string $message, int $status = 400, ?string $code = null, array $extra = []): never
{
    $payload = ['success' => false, 'message' => $message];
    if ($code !== null) {
        $payload['code'] = $code;
    }
    json_response($payload + $extra, $status);
}

/** Enforce the HTTP method; POST requests must carry a valid CSRF token. */
function api_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        api_error('Method not allowed.', 405, 'method_not_allowed');
    }
    if ($method === 'POST') {
        csrf_verify();
    }
}

/** Parsed JSON request body (for endpoints that accept JSON). */
function api_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 1024 * 1024);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error('Invalid request.', 400, 'invalid_json');
    }
    return $data;
}

/** Per-IP rate limit; responds 429 when exceeded. */
function api_rate_limit(string $action, int $maxHits, int $windowSeconds): void
{
    if (!rate_limit($action, client_ip(), $maxHits, $windowSeconds)) {
        header('Retry-After: ' . $windowSeconds);
        api_error('Too many requests. Please wait a moment and try again.', 429, 'rate_limited');
    }
}

/** Resolve a tool from the request and make sure it can run on this server. */
function api_tool(string $slug): array
{
    $tool = tool($slug);
    if ($tool === null) {
        api_error('Unknown tool.', 404, 'unknown_tool');
    }
    $status = tool_status($tool);
    if ($status['state'] === 'unavailable') {
        api_error($status['note'], 503, 'tool_unavailable');
    }
    return $tool;
}

maybe_run_request_cleanup();
