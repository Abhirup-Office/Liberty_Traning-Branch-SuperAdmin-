<?php
/**
 * POST /api/set_view_branch.php
 * Body (JSON): { branch_id } — a branch id to start viewing, or null to return to the overview.
 * Super Admin only. Changes only the view context; the account's role is untouched.
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
$branchId = $input['branch_id'] ?? null;

if ($branchId === null) {
    clear_view_branch_id();
    send_json(['success' => true, 'data' => ['view_branch_id' => null]]);
}

$branchId = (int) $branchId;
$stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = :id");
$stmt->execute([':id' => $branchId]);
$branch = $stmt->fetch();

if (!$branch) {
    send_json(['success' => false, 'message' => 'Branch not found'], 404);
}

set_view_branch_id($branchId);
send_json(['success' => true, 'data' => ['view_branch_id' => $branchId, 'branch_name' => $branch['name']]]);
