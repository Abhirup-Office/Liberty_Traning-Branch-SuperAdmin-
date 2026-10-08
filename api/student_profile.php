<?php
/**
 * GET /api/student_profile.php
 * The logged-in student's own non-financial profile. Takes NO id parameter at all — the
 * student id comes only from the session. Payment and fee information is deliberately not
 * returned here or anywhere in the Student Portal API: students never see fees, balances,
 * payment status, payment history, or installment dates.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
$student = require_student_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.display_id, s.first_name, s.last_name,
            s.father_name, s.mother_name, s.date_of_birth, s.phone, s.address, s.photo_path,
            s.created_at, s.last_login_at,
            b.name AS branch_name, c.course_name, c.duration, c.certificate_path
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     WHERE s.id = :id"
);
$stmt->execute([':id' => $student['id']]);
$row = $stmt->fetch();

if (!$row) {
    // The student's own record was deleted after they logged in; end the session cleanly.
    $_SESSION = [];
    session_destroy();
    send_json(['success' => false, 'message' => 'Your account could not be found'], 404);
}

send_json(['success' => true, 'data' => ['student' => $row]]);
