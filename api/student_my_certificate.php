<?php
/**
 * GET /api/student_my_certificate.php
 * The logged-in student's own individual certificate metadata, if any. Takes NO id
 * parameter at all — scoped entirely by session, the same IDOR-proof pattern as every other
 * student_*.php endpoint. This is the NEW per-student certificate, separate from the
 * existing global/sample course certificate (api/student_certificate.php), which is untouched.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
$student = require_student_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->prepare(
    'SELECT sc.certificate_name, sc.course_duration, sc.mime_type, sc.created_at, sc.updated_at,
            c.course_name
     FROM student_certificates sc
     JOIN students s ON s.id = sc.student_id
     JOIN courses c ON c.id = sc.course_id
     WHERE sc.student_id = :id'
);
$stmt->execute([':id' => $student['id']]);
$cert = $stmt->fetch();

send_json(['success' => true, 'data' => ['certificate' => $cert ?: null]]);
