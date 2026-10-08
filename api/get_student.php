<?php
/**
 * GET /api/get_student.php?id=<student id>
 * One student's profile with recent payments. A Branch Admin only gets students
 * from their own branch; any other id returns 404, so IDs cannot be probed.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
require_once __DIR__ . '/admin_lookup.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND s.branch_id = :branch_id' : '';
$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.display_id, s.first_name, s.last_name,
            s.father_name, s.mother_name, s.date_of_birth, s.phone, s.address, s.photo_path,
            s.branch_id, b.name AS branch_name, s.course_id, c.course_name, c.duration,
            " . FEE_SELECT_SQL . ", s.next_installment_date, s.created_at,
            (s.password_hash IS NOT NULL) AS password_hash_set
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     WHERE s.id = :id$branchClause"
);
$params = [':id' => $id];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt->execute($params);
$student = $stmt->fetch();

if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}
// PDO returns this as the string "0"/"1" (truthy either way in JS), so it must be cast here.
$student['password_hash_set'] = (bool) $student['password_hash_set'];

$txStmt = $pdo->prepare(
    "SELECT amount, payment_mode, reference_no, notes, transaction_date, receipt_no,
            recorded_by_role, recorded_by_id
     FROM transactions
     WHERE student_id = :id
     ORDER BY transaction_date DESC, id DESC
     LIMIT 5"
);
$txStmt->execute([':id' => $id]);
$payments = resolve_recorded_by_many($pdo, $txStmt->fetchAll());

// Installment status is derived, not stored: "overdue" is simply "the due date has passed
// and the student still owes money" — it changes with the calendar, not with a write.
$isFullyPaid = $student['status'] === 'Paid in Full';
$nextDate = $isFullyPaid ? null : $student['next_installment_date'];
$daysRemaining = null;
$isOverdue = false;
if ($nextDate !== null) {
    $daysRemaining = (int) ((strtotime($nextDate) - strtotime(date('Y-m-d'))) / 86400);
    $isOverdue = $daysRemaining < 0;
}

send_json([
    'success' => true,
    'data' => [
        'student' => $student,
        'payments' => $payments,
        'last_payment' => $payments[0] ?? null,
        'installment' => [
            'next_installment_date' => $nextDate,
            'days_remaining' => $daysRemaining,
            'is_overdue' => $isOverdue,
        ],
    ],
]);
