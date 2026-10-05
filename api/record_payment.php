<?php
/**
 * POST /api/record_payment.php
 * Body (JSON): { student_id, amount, payment_mode }
 * Inserts a transaction and updates the student's paid/balance/status.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$studentId = (int) ($input['student_id'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
$paymentMode = (string) ($input['payment_mode'] ?? '');

$allowedModes = ['UPI', 'Cash', 'Bank'];
if ($studentId <= 0 || $amount <= 0 || !in_array($paymentMode, $allowedModes, true)) {
    send_json(['success' => false, 'message' => 'Missing or invalid required fields'], 422);
}

// Fee is the course's current fee; paid is the sum of all existing payments.
$stmt = $pdo->prepare(
    "SELECT s.branch_id, s.first_name, s.last_name, c.total_fee, " . FEE_PAID_SQL . " AS amount_paid
     FROM students s
     JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     WHERE s.id = :id"
);
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

// A branch_admin may only record payments for students in their own branch.
if ($admin['role'] === 'branch_admin' && (int) $student['branch_id'] !== $admin['branch_id']) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$totalFee = (float) $student['total_fee'];
$newAmountPaid = (float) $student['amount_paid'] + $amount;

if ($newAmountPaid > $totalFee) {
    send_json(['success' => false, 'message' => 'Payment exceeds remaining balance'], 422);
}

$newBalance = round($totalFee - $newAmountPaid, 2);
$newStatus = derive_status($totalFee, $newAmountPaid);

$pdo->beginTransaction();
try {
    $receiptNo = 'RCPT-' . time() . '-' . $studentId . '-' . bin2hex(random_bytes(3));
    $txStmt = $pdo->prepare(
        "INSERT INTO transactions (student_id, amount, payment_mode, receipt_no)
         VALUES (:student_id, :amount, :payment_mode, :receipt_no)"
    );
    $txStmt->execute([
        ':student_id' => $studentId,
        ':amount' => $amount,
        ':payment_mode' => $paymentMode,
        ':receipt_no' => $receiptNo,
    ]);

    $updateStmt = $pdo->prepare(
        "UPDATE students SET amount_paid = :amount_paid, balance_due = :balance_due, status = :status WHERE id = :id"
    );
    $updateStmt->execute([
        ':amount_paid' => $newAmountPaid,
        ':balance_due' => $newBalance,
        ':status' => $newStatus,
        ':id' => $studentId,
    ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    send_json(['success' => false, 'message' => 'Failed to record payment'], 500);
}

send_json([
    'success' => true,
    'data' => [
        'student_id' => $studentId,
        'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'amount_paid' => $newAmountPaid,
        'balance_due' => $newBalance,
        'status' => $newStatus,
        'receipt_no' => $receiptNo,
        'amount' => $amount,
        'payment_mode' => $paymentMode,
    ],
]);
