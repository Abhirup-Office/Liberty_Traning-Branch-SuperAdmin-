<?php
/**
 * POST /api/delete_student.php
 * Body (JSON): { id }
 * Hard-deletes a student (transactions cascade via FK).
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
$admin = require_api_login();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

// A branch_admin may only delete students that belong to their own branch,
// regardless of which id they send — verify ownership before deleting.
if ($admin['role'] === 'branch_admin') {
    $ownerStmt = $pdo->prepare("SELECT branch_id FROM students WHERE id = :id");
    $ownerStmt->execute([':id' => $id]);
    $owner = $ownerStmt->fetch();
    if (!$owner || (int) $owner['branch_id'] !== $admin['branch_id']) {
        send_json(['success' => false, 'message' => 'Student not found'], 404);
    }
}

$stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() === 0) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

send_json(['success' => true, 'message' => 'Student deleted']);
