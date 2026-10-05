<?php
/**
 * GET /api/get_branch_managers.php?branch_id=<id>
 * Branch Manager (branch_admins) accounts for one branch. Super Admin only.
 * Password hashes and Google IDs are never returned.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_api_login(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$branchId = (int) ($_GET['branch_id'] ?? 0);
if ($branchId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid branch id'], 422);
}

$branchStmt = $pdo->prepare("SELECT id, name, location, manager_name FROM branches WHERE id = :id");
$branchStmt->execute([':id' => $branchId]);
$branch = $branchStmt->fetch();
if (!$branch) {
    send_json(['success' => false, 'message' => 'Branch not found'], 404);
}

$managerStmt = $pdo->prepare(
    "SELECT id, name, email, created_at FROM branch_admins WHERE branch_id = :branch_id ORDER BY created_at ASC"
);
$managerStmt->execute([':branch_id' => $branchId]);

send_json([
    'success' => true,
    'data' => [
        'branch' => $branch,
        'managers' => $managerStmt->fetchAll(),
    ],
]);
