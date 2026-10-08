<?php
/**
 * GET /api/get_student_certificate.php?student_id=<id>
 * Feeds the "Manage Certificate" modal: the student's name/course/duration (as defaults for
 * the editable certificate fields) plus their existing individual certificate row, if any.
 * Branch Admin only sees students in their own branch — same 404-not-403 scoping as every
 * other per-student admin endpoint, so branch ids can't be probed.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
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

$stmt = $pdo->prepare(
    "SELECT s.id, s.first_name, s.last_name, s.course_id, c.course_name, c.duration
     FROM students s
     JOIN courses c ON c.id = s.course_id
     WHERE s.id = :id$branchClause"
);
$stmt->execute($params);
$student = $stmt->fetch();
if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$certStmt = $pdo->prepare(
    'SELECT certificate_name, course_duration, mime_type, file_size_bytes, original_filename, created_at, updated_at
     FROM student_certificates WHERE student_id = :id'
);
$certStmt->execute([':id' => $studentId]);
$certificate = $certStmt->fetch() ?: null;

send_json([
    'success' => true,
    'data' => [
        'student_id' => (int) $student['id'],
        'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'course_id' => (int) $student['course_id'],
        'course_name' => $student['course_name'],
        'course_duration' => $student['duration'],
        'certificate' => $certificate,
    ],
]);
