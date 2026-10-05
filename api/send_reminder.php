<?php
/**
 * POST /api/send_reminder.php
 * Body (JSON): { student_id }
 * Validates the student's current outstanding balance on the server before a WhatsApp reminder
 * is prepared. Fully paid students are refused. Branch Admins may only target their own branch.
 * Response on success: the phone number and message the browser then opens in WhatsApp.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
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
$stmt = $pdo->prepare(
    "SELECT s.first_name, s.phone, c.course_name, " . FEE_PAID_SQL . " AS amount_paid, c.total_fee
     FROM students s
     JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     WHERE s.id = :id$branchClause"
);
$stmt->execute($params);
$student = $stmt->fetch();

if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

// Outstanding = course fee minus total of all payments. Negative results count as paid.
$outstanding = round(max((float) $student['total_fee'] - (float) $student['amount_paid'], 0), 2);
if ($outstanding <= 0) {
    send_json(['success' => false, 'message' => 'Payment is already complete. No reminder is required.'], 422);
}

$message = sprintf(
    'Hi %s, this is a reminder from Liberty Training that ₹%s is pending for your %s course. Please clear it at your earliest convenience.',
    $student['first_name'],
    number_format($outstanding, 2, '.', ''),
    $student['course_name']
);

send_json([
    'success' => true,
    'data' => [
        'phone' => preg_replace('/\D/', '', (string) $student['phone']),
        'message' => $message,
        'outstanding' => $outstanding,
    ],
]);
