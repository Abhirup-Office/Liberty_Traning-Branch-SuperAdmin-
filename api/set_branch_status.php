<?php
/**
 * POST /api/set_branch_status.php
 * Body (JSON): { id, status }  where status is Active or Inactive.
 * Soft delete / deactivation only, mirroring api/set_course_status.php's pattern. A branch is
 * never hard-deleted — see migrations/011_branch_status.php for why. Setting Inactive:
 *   - hides the branch from active-branch dropdowns (api/get_meta.php)
 *   - blocks that branch's manager(s) from logging in (api/login.php)
 *   - does NOT touch students, courses, transactions, or branch_admins rows — all history stays
 * Super Admin only.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_api_login(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$id = (int) ($input['id'] ?? 0);
$status = (string) ($input['status'] ?? '');

if ($id <= 0 || !in_array($status, ['Active', 'Inactive'], true)) {
    send_json(['success' => false, 'message' => 'Invalid branch or status'], 422);
}

$check = $pdo->prepare('SELECT id FROM branches WHERE id = :id');
$check->execute([':id' => $id]);
if ($check->fetchColumn() === false) {
    send_json(['success' => false, 'message' => 'Branch not found'], 404);
}

$stmt = $pdo->prepare('UPDATE branches SET status = :status WHERE id = :id');
$stmt->execute([':status' => $status, ':id' => $id]);

send_json(['success' => true, 'data' => ['id' => $id, 'status' => $status]]);
