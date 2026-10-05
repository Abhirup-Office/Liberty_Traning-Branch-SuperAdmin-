<?php
/**
 * POST /api/add_student.php
 * Body (JSON): { branch_id, course_id, first_name, last_name, phone, total_fee, amount_paid }
 * Inserts a new student, computing balance_due and status from the fee figures.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/student_code.php';
$admin = require_api_login();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();

// A branch_admin can only ever enroll students into their OWN branch —
// whatever branch_id the client sends is ignored for them.
$branchId = $admin['role'] === 'branch_admin'
    ? $admin['branch_id']
    : (int) ($input['branch_id'] ?? 0);
$courseId = (int) ($input['course_id'] ?? 0);
$firstName = trim((string) ($input['first_name'] ?? ''));
$lastName = trim((string) ($input['last_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$address = trim((string) ($input['address'] ?? ''));
if (mb_strlen($address) > 500) {
    send_json(['success' => false, 'message' => 'Address must be at most 500 characters'], 422);
}
// The fee is always the course's current fee; any total_fee sent by the client is ignored.
$amountPaid = (float) ($input['amount_paid'] ?? 0);

if ($branchId <= 0 || $courseId <= 0 || $firstName === '' || $phone === '') {
    send_json(['success' => false, 'message' => 'Missing or invalid required fields'], 422);
}

// New enrollments only into active courses that the admin is allowed to use.
$courseStmt = $pdo->prepare("SELECT status, branch_id, total_fee FROM courses WHERE id = :id");
$courseStmt->execute([':id' => $courseId]);
$course = $courseStmt->fetch();
if (!$course) {
    send_json(['success' => false, 'message' => 'Course not found'], 422);
}
if ($course['status'] !== 'Active') {
    send_json(['success' => false, 'message' => 'This course is inactive and cannot take new students'], 422);
}
if ($admin['role'] === 'branch_admin' && $course['branch_id'] !== null && (int) $course['branch_id'] !== $admin['branch_id']) {
    send_json(['success' => false, 'message' => 'This course belongs to another branch'], 403);
}

$totalFee = (float) $course['total_fee'];
if ($totalFee <= 0) {
    send_json(['success' => false, 'message' => 'This course has no fee set. Set the course fee first.'], 422);
}
if ($amountPaid < 0 || $amountPaid > $totalFee) {
    send_json(['success' => false, 'message' => 'Amount paid cannot exceed the course fee'], 422);
}

$balanceDue = round($totalFee - $amountPaid, 2);
$status = derive_status($totalFee, $amountPaid);

// The student code is always generated here; anything sent by the client is ignored.
// The UNIQUE index is the final guard, so a collision retries with a fresh code.
$stmt = $pdo->prepare(
    "INSERT INTO students (student_code, branch_id, course_id, first_name, last_name, phone, address, total_fee, amount_paid, balance_due, status)
     VALUES (:student_code, :branch_id, :course_id, :first_name, :last_name, :phone, :address, :total_fee, :amount_paid, :balance_due, :status)"
);

$newId = null;
for ($attempt = 0; $attempt < STUDENT_CODE_MAX_ATTEMPTS && $newId === null; $attempt++) {
    try {
        $stmt->execute([
            ':student_code' => generate_unique_student_code($pdo),
            ':branch_id' => $branchId,
            ':course_id' => $courseId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone,
            ':address' => $address !== '' ? $address : null,
            ':total_fee' => $totalFee,
            ':amount_paid' => $amountPaid,
            ':balance_due' => $balanceDue,
            ':status' => $status,
        ]);
        $newId = (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        if (!is_duplicate_key_error($e)) {
            throw $e;
        }
    }
}

if ($newId === null) {
    send_json(['success' => false, 'message' => 'Could not create student, please retry'], 500);
}

// Record the initial payment as a transaction too, if any was made.
if ($amountPaid > 0) {
    $receiptNo = 'RCPT-' . time() . '-' . $newId . '-' . bin2hex(random_bytes(3));
    $txStmt = $pdo->prepare(
        "INSERT INTO transactions (student_id, amount, payment_mode, receipt_no)
         VALUES (:student_id, :amount, 'Cash', :receipt_no)"
    );
    $txStmt->execute([
        ':student_id' => $newId,
        ':amount' => $amountPaid,
        ':receipt_no' => $receiptNo,
    ]);
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.branch_id, b.name AS branch_name, s.course_id, c.course_name,
            s.first_name, s.last_name, s.phone, s.total_fee, s.amount_paid,
            s.balance_due, s.status, s.created_at
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     WHERE s.id = :id"
);
$stmt->execute([':id' => $newId]);
$student = $stmt->fetch();

send_json(['success' => true, 'data' => $student], 201);
