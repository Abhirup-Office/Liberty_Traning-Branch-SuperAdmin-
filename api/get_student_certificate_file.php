<?php
/**
 * GET /api/get_student_certificate_file.php?student_id=<id>
 * Streams a student's individual certificate for the admin "View" action. Same branch
 * scoping as every other per-student admin endpoint.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$studentId = (int) ($_GET['student_id'] ?? 0);
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

$certStmt = $pdo->prepare('SELECT storage_provider, storage_key, mime_type FROM student_certificates WHERE student_id = :id');
$certStmt->execute([':id' => $studentId]);
$cert = $certStmt->fetch();
if (!$cert) {
    send_json(['success' => false, 'message' => 'No certificate uploaded for this student'], 404);
}

storage_stream($cert['storage_provider'], $cert['storage_key'], $cert['mime_type']);
