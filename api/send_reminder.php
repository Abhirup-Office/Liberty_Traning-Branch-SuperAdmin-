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
    "SELECT s.first_name, s.last_name, s.phone, c.course_name,
            " . FEE_PAID_SQL . " AS amount_paid, s.total_fee, s.next_installment_date
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

if (empty($student['phone'])) {
    send_json(['success' => false, 'message' => 'This student has no phone number on file'], 422);
}

// Outstanding = course fee minus total of all payments. Negative results count as paid.
$outstanding = round(max((float) $student['total_fee'] - (float) $student['amount_paid'], 0), 2);
if ($outstanding <= 0) {
    send_json(['success' => false, 'message' => 'Payment is already complete. No reminder is required.'], 422);
}

$dueDate = $student['next_installment_date'];
$today = date('Y-m-d');
if ($dueDate === null) {
    $status = 'Pending';
} elseif ($dueDate < $today) {
    $status = 'Overdue';
} elseif ($dueDate === $today) {
    $status = 'Due Today';
} else {
    $status = 'Pending';
}

$studentName = trim($student['first_name'] . ' ' . $student['last_name']);
$amountText = number_format($outstanding, 2, '.', '');
$dueDateText = $dueDate !== null ? date('d M Y', strtotime($dueDate)) : null;

if ($status === 'Overdue') {
    $message = "Hello {$studentName},\n\nThis is a reminder that your course fee payment of ₹{$amountText} is overdue. Your installment was due on {$dueDateText}.\n\nPlease clear it at your earliest convenience.\n\nThank you.";
} elseif ($status === 'Due Today') {
    $message = "Hello {$studentName},\n\nThis is a reminder regarding your pending course fee of ₹{$amountText}.\n\nYour next installment is due today ({$dueDateText}).\n\nThank you.";
} else {
    $message = "Hello {$studentName},\n\nThis is a reminder regarding your pending course fee of ₹{$amountText}."
        . ($dueDateText !== null ? "\n\nYour next installment date is {$dueDateText}." : '')
        . "\n\nThank you.";
}

send_json([
    'success' => true,
    'data' => [
        'phone' => preg_replace('/\D/', '', (string) $student['phone']),
        'message' => $message,
        'outstanding' => $outstanding,
        'status' => $status,
    ],
]);
