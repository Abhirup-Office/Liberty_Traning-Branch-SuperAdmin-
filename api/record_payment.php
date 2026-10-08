<?php
/**
 * POST /api/record_payment.php
 * Body (JSON): { student_id, amount, payment_mode, reference_no?, notes?, next_installment_date? }
 * Inserts a transaction (never overwritten — this is the permanent payment history) and
 * syncs the student's cached paid/balance/status/next_installment_date.
 *
 * Supports both "full payment" and "installment" the same way: they are not separate
 * modes or a separate table, just how many transaction rows a student ends up with and
 * how large each one is. The fee math (api/fee_calc.php) already sums every row, so this
 * endpoint only ever needs to insert one more row — a full payment is simply one row equal
 * to the whole remaining balance.
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
$referenceNo = trim((string) ($input['reference_no'] ?? ''));
$notes = trim((string) ($input['notes'] ?? ''));
$nextInstallmentDate = trim((string) ($input['next_installment_date'] ?? ''));

$allowedModes = ['UPI', 'Cash', 'Bank'];
if ($studentId <= 0 || $amount <= 0 || !in_array($paymentMode, $allowedModes, true)) {
    send_json(['success' => false, 'message' => 'Missing or invalid required fields'], 422);
}
if (mb_strlen($referenceNo) > 50) {
    send_json(['success' => false, 'message' => 'Reference number must be at most 50 characters'], 422);
}
if (mb_strlen($notes) > 500) {
    send_json(['success' => false, 'message' => 'Notes must be at most 500 characters'], 422);
}
if ($nextInstallmentDate !== '') {
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextInstallmentDate)
        && checkdate((int) substr($nextInstallmentDate, 5, 2), (int) substr($nextInstallmentDate, 8, 2), (int) substr($nextInstallmentDate, 0, 4));
    if (!$validDate || $nextInstallmentDate < date('Y-m-d')) {
        send_json(['success' => false, 'message' => 'Next installment date must be today or a future date'], 422);
    }
}

// Fee is the course's current fee; paid is the sum of all existing payments.
$stmt = $pdo->prepare(
    "SELECT s.branch_id, s.first_name, s.last_name, s.total_fee, " . FEE_PAID_SQL . " AS amount_paid
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

// The approved architecture does not support overpayment; a payment can never push the
// balance below zero, so it is rejected outright rather than clamped.
if ($newAmountPaid > $totalFee) {
    send_json(['success' => false, 'message' => 'Payment exceeds remaining balance'], 422);
}

$newBalance = round($totalFee - $newAmountPaid, 2);
$newStatus = derive_status($totalFee, $newAmountPaid);
$isFullyPaid = $newAmountPaid >= $totalFee;
// Fully paid: no further installment is owed, so any due date (submitted or existing) is cleared.
// This is what disables the installment/fee reminder once PAID.
$effectiveNextInstallment = null;
if (!$isFullyPaid && $nextInstallmentDate !== '') {
    $effectiveNextInstallment = $nextInstallmentDate;
}

$pdo->beginTransaction();
try {
    $receiptNo = 'RCPT-' . time() . '-' . $studentId . '-' . bin2hex(random_bytes(3));
    $txStmt = $pdo->prepare(
        "INSERT INTO transactions
            (student_id, amount, payment_mode, reference_no, notes, next_installment_date,
             recorded_by_role, recorded_by_id, receipt_no)
         VALUES
            (:student_id, :amount, :payment_mode, :reference_no, :notes, :next_installment_date,
             :recorded_by_role, :recorded_by_id, :receipt_no)"
    );
    $txStmt->execute([
        ':student_id' => $studentId,
        ':amount' => $amount,
        ':payment_mode' => $paymentMode,
        ':reference_no' => $referenceNo !== '' ? $referenceNo : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':next_installment_date' => $effectiveNextInstallment,
        ':recorded_by_role' => $admin['role'],
        ':recorded_by_id' => $admin['id'],
        ':receipt_no' => $receiptNo,
    ]);

    $updateStmt = $pdo->prepare(
        "UPDATE students
         SET amount_paid = :amount_paid, balance_due = :balance_due, status = :status,
             next_installment_date = :next_installment_date
         WHERE id = :id"
    );
    $updateStmt->execute([
        ':amount_paid' => $newAmountPaid,
        ':balance_due' => $newBalance,
        ':status' => $newStatus,
        ':next_installment_date' => $effectiveNextInstallment,
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
        'next_installment_date' => $effectiveNextInstallment,
    ],
]);
