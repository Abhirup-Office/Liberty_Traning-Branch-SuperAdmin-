<?php
/**
 * GET /api/get_course.php?id=<course id>
 * One course with its enrollment breakdown. Returns 404 for courses outside the caller's scope.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid course id'], 422);
}

$course = fetch_course_for_admin($pdo, $admin, $id);
if (!$course) {
    send_json(['success' => false, 'message' => 'Course not found'], 404);
}

// Enrollments are students.course_id. Students' payment statuses are the only
// enrollment states the project records today.
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_students,
            SUM((" . FEE_STATUS_SQL . ") = 'Paid in Full') AS paid_in_full,
            SUM((" . FEE_STATUS_SQL . ") = 'Partial') AS partial,
            SUM((" . FEE_STATUS_SQL . ") = 'Pending') AS overdue,
            COALESCE(SUM(" . FEE_PAID_SQL . "), 0) AS collected,
            COALESCE(SUM(GREATEST(s.total_fee - " . FEE_PAID_SQL . ", 0)), 0) AS pending
     FROM students s
     " . FEE_JOIN_SQL . "
     WHERE s.course_id = :course_id"
);
$statsStmt->execute([':course_id' => $id]);
$stats = $statsStmt->fetch();

send_json([
    'success' => true,
    'data' => [
        'course' => $course,
        'enrollment' => [
            'total_students' => (int) $stats['total_students'],
            'paid_in_full' => (int) $stats['paid_in_full'],
            'partial' => (int) $stats['partial'],
            'overdue' => (int) $stats['overdue'],
            'collected' => (float) $stats['collected'],
            'pending' => (float) $stats['pending'],
        ],
    ],
]);
