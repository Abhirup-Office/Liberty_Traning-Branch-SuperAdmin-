<?php
/**
 * POST /api/delete_student_certificate.php
 * Body (JSON): { student_id }
 * Deletes one student's INDIVIDUAL certificate only — never the course's global/sample
 * certificate. Branch Admin may only delete a certificate for a student in their own branch.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$studentId = (int) ($input['student_id'] ?? 0);
if ($studentId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND s.branch_id = :branch_id' : '';
$params = [':id' => $studentId];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt = $pdo->prepare("SELECT s.id FROM students s WHERE s.id = :id$branchClause");
$stmt->execute($params);
if ($stmt->fetchColumn() === false) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$certStmt = $pdo->prepare('SELECT storage_provider, storage_key FROM student_certificates WHERE student_id = :id');
$certStmt->execute([':id' => $studentId]);
$cert = $certStmt->fetch();
if (!$cert) {
    send_json(['success' => false, 'message' => 'No certificate to delete'], 404);
}

$pdo->prepare('DELETE FROM student_certificates WHERE student_id = :id')->execute([':id' => $studentId]);
storage_delete($cert['storage_provider'], $cert['storage_key']);

send_json(['success' => true, 'message' => 'Certificate deleted.']);
