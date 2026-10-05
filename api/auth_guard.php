<?php
/**
 * Session-based auth guard, shared by every protected page.
 * Include this at the very top of a page (before any HTML output).
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

const IDLE_TIMEOUT_SECONDS = 7200; // 2 hours without activity ends the session

/**
 * Returns the logged-in admin's session data, or null if not logged in or idle-expired.
 * `branch_id` is only ever present for a branch_admin session — it comes
 * from the branch_admins table at login time, never from the client.
 */
function current_admin(): ?array
{
    if (!isset($_SESSION['admin_id'], $_SESSION['role'])) {
        return null;
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = $now;

    return [
        'id' => (int) $_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? 'Admin',
        'role' => $_SESSION['role'],
        'branch_id' => isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : null,
    ];
}

/**
 * Branch-viewing context for a Super Admin. Stored under its own session key so
 * the account's role and home branch_id are never touched when browsing a branch.
 */
function current_view_branch_id(array $admin): ?int
{
    if ($admin['role'] !== 'super_admin' || !isset($_SESSION['view_branch_id'])) {
        return null;
    }
    return (int) $_SESSION['view_branch_id'];
}

function set_view_branch_id(int $branchId): void
{
    $_SESSION['view_branch_id'] = $branchId;
}

function clear_view_branch_id(): void
{
    unset($_SESSION['view_branch_id']);
}

/** Per-session token that state-changing requests must echo back in X-CSRF-Token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Invalid or missing security token']);
        exit;
    }
}

/** The dashboard a given role should land on, relative to /public. */
function dashboard_for_role(string $role): string
{
    return $role === 'super_admin' ? 'super_admin_dashboard.php' : 'branch_dashboard.php';
}

/**
 * Enforces that a page is only reachable by a logged-in admin, optionally
 * restricted to specific roles. Call this before any HTML is echoed.
 * Returns the admin's session data on success; redirects/exits otherwise.
 */
function require_login(array $allowedRoles = []): array
{
    $admin = current_admin();

    if (!$admin) {
        header('Location: login.php');
        exit;
    }

    if ($allowedRoles && !in_array($admin['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:4rem;">'
            . '<h2>403 — Access denied</h2><p>You do not have permission to view this page.</p>'
            . '<p><a href="' . dashboard_for_role($admin['role']) . '">Back to my dashboard</a></p>'
            . '</body></html>';
        exit;
    }

    return $admin;
}

/**
 * Resolves which branch a data query should be scoped to, given the logged-in
 * admin and whatever branch value the frontend asked for.
 *
 * A branch_admin's branch_id ALWAYS comes from their session — the
 * $requestedBranch argument (sourced from query params) is ignored for them,
 * so a branch admin can never read or write another branch's data no matter
 * what the client sends. A super_admin may pass 'all' or a specific id.
 */
function scoped_branch_id(array $admin, $requestedBranch)
{
    if ($admin['role'] === 'branch_admin') {
        return $admin['branch_id'];
    }
    return $requestedBranch;
}

/**
 * JSON-endpoint variant of require_login(): responds with a 401/403 JSON
 * body instead of redirecting, since the caller is fetch()/XHR, not a browser nav.
 */
function require_api_login(array $allowedRoles = []): array
{
    $admin = current_admin();

    if (!$admin) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    if ($allowedRoles && !in_array($admin['role'], $allowedRoles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    return $admin;
}
