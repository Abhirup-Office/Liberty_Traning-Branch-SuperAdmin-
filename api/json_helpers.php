<?php
/**
 * JSON request/response helpers shared by every API bootstrap (admin's config.php and
 * student_config.php). Loaded with require_once; contains only function declarations.
 */

declare(strict_types=1);

/** Reads and decodes a JSON request body into an associative array. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function send_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/**
 * Installed once per request by both API bootstraps (config.php, student_config.php).
 * Converts any uncaught exception/error into the same generic JSON shape every endpoint
 * already uses, and makes sure PHP never prints a raw stack trace, file path, or SQL text
 * into the response — regardless of the server's own display_errors setting. Full details
 * still go to the server's error log, just never to the client.
 */
function install_safe_error_handler(): void
{
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    set_exception_handler(function (Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
        exit;
    });
}
