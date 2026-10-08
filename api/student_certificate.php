<?php
/**
 * GET /api/student_certificate.php
 * Streams the sample certificate for the LOGGED-IN student's own course. Takes no id
 * parameter at all — the course comes from the student's own enrollment record via
 * session identity, same IDOR-proof pattern as every other student_*.php endpoint.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
require __DIR__ . '/storage.php';
$student = require_student_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

// The course comes from the student's own enrollment (session-derived), not from any
// request parameter — a student can only ever reach their own course's certificate here.
$stmt = $pdo->prepare('SELECT course_id FROM students WHERE id = :id');
$stmt->execute([':id' => $student['id']]);
$courseId = (int) $stmt->fetchColumn();

if ($courseId <= 0) {
    send_json(['success' => false, 'message' => 'No certificate has been uploaded for your course yet'], 404);
}

$record = storage_current($pdo, 'course_certificate', $courseId);
if (!$record) {
    send_json(['success' => false, 'message' => 'No certificate has been uploaded for your course yet'], 404);
}
storage_stream($record['storage_provider'], $record['storage_key'], $record['mime_type']);
