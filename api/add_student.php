<?php
/**
 * POST /api/add_student.php
 * Body (JSON): { branch_id, course_id, first_name, last_name, phone, total_fee?, amount_paid }
 * Inserts a new student, computing balance_due and status from the fee figures. total_fee is
 * optional: if omitted, the course's current fee is used; if provided, it overrides that
 * default (e.g. a negotiated discount) and becomes this student's own fee from then on.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/student_code.php';
require_once __DIR__ . '/student_id.php';
require_once __DIR__ . '/student_password.php';
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
$fatherName = trim((string) ($input['father_name'] ?? ''));
$motherName = trim((string) ($input['mother_name'] ?? ''));
$dateOfBirth = trim((string) ($input['date_of_birth'] ?? ''));

if (mb_strlen($address) > 500) {
    send_json(['success' => false, 'message' => 'Address must be at most 500 characters'], 422);
}
if (mb_strlen($fatherName) > 100 || mb_strlen($motherName) > 100) {
    send_json(['success' => false, 'message' => "Father's/mother's name must be at most 100 characters"], 422);
}
if ($dateOfBirth !== '') {
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)
        && checkdate((int) substr($dateOfBirth, 5, 2), (int) substr($dateOfBirth, 8, 2), (int) substr($dateOfBirth, 0, 4));
    if (!$validDate || $dateOfBirth > date('Y-m-d')) {
        send_json(['success' => false, 'message' => 'Date of birth must be a valid past date'], 422);
    }
}
// The course's current fee is only the default: an admin may override it per student at
// enrollment (e.g. a negotiated discount). Once set, it's independent of the course's own
// fee — see api/fee_calc.php's docblock for why.
$totalFeeOverride = array_key_exists('total_fee', $input) && $input['total_fee'] !== '' && $input['total_fee'] !== null
    ? (float) $input['total_fee']
    : null;
$amountPaid = (float) ($input['amount_paid'] ?? 0);

if ($branchId <= 0 || $courseId <= 0 || $firstName === '' || $phone === '') {
    send_json(['success' => false, 'message' => 'Missing or invalid required fields'], 422);
}

// Phone is unique (it is also the student portal login username) — checked explicitly so a
// clash gives a clear message instead of falling through to a generic failure, since the
// retry loop below only knows how to retry student_code collisions, not phone ones.
$phoneCheck = $pdo->prepare('SELECT 1 FROM students WHERE phone = :phone LIMIT 1');
$phoneCheck->execute([':phone' => $phone]);
if ($phoneCheck->fetchColumn() !== false) {
    send_json(['success' => false, 'message' => 'A student with this phone number already exists'], 409);
}

// New enrollments only into active courses that the admin is allowed to use.
$courseStmt = $pdo->prepare("SELECT status, branch_id, total_fee, course_code FROM courses WHERE id = :id");
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

$totalFee = $totalFeeOverride ?? (float) $course['total_fee'];
if ($totalFee <= 0) {
    send_json(['success' => false, 'message' => 'Course fee must be a positive amount.'], 422);
}
if ($totalFee > 9999999.99) {
    send_json(['success' => false, 'message' => 'Course fee is too large.'], 422);
}
if ($amountPaid < 0 || $amountPaid > $totalFee) {
    send_json(['success' => false, 'message' => 'Amount paid cannot exceed the course fee'], 422);
}
if (!$course['course_code']) {
    send_json(['success' => false, 'message' => 'This course has no course code set, so a Student ID cannot be generated. Set the course code first.'], 422);
}

$balanceDue = round($totalFee - $amountPaid, 2);
$status = derive_status($totalFee, $amountPaid);

// The display id's uniqueness comes from student_id_sequences (atomic INSERT), so it is
// generated once, outside the retry loop below — a student_code collision retry must not
// burn an extra sequence number.
$displayId = generate_unique_display_id($pdo, $course['course_code']);

// The student code is always generated here; anything sent by the client is ignored.
// The UNIQUE index is the final guard, so a collision retries with a fresh code.
// A student portal login is activated immediately when a DOB is provided at creation —
// the password is never stored in plaintext, only password_hash() of it.
$initialPasswordHash = $dateOfBirth !== '' ? password_hash(dob_default_password($dateOfBirth), PASSWORD_DEFAULT) : null;

$stmt = $pdo->prepare(
    "INSERT INTO students (student_code, display_id, branch_id, course_id, first_name, last_name,
            father_name, mother_name, date_of_birth, phone, address, total_fee, amount_paid, balance_due, status,
            password_hash)
     VALUES (:student_code, :display_id, :branch_id, :course_id, :first_name, :last_name,
            :father_name, :mother_name, :date_of_birth, :phone, :address, :total_fee, :amount_paid, :balance_due, :status,
            :password_hash)"
);

$newId = null;
for ($attempt = 0; $attempt < STUDENT_CODE_MAX_ATTEMPTS && $newId === null; $attempt++) {
    try {
        $stmt->execute([
            ':student_code' => generate_unique_student_code($pdo),
            ':display_id' => $displayId,
            ':branch_id' => $branchId,
            ':course_id' => $courseId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':father_name' => $fatherName !== '' ? $fatherName : null,
            ':mother_name' => $motherName !== '' ? $motherName : null,
            ':date_of_birth' => $dateOfBirth !== '' ? $dateOfBirth : null,
            ':phone' => $phone,
            ':address' => $address !== '' ? $address : null,
            ':total_fee' => $totalFee,
            ':amount_paid' => $amountPaid,
            ':balance_due' => $balanceDue,
            ':status' => $status,
            ':password_hash' => $initialPasswordHash,
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
        "INSERT INTO transactions (student_id, amount, payment_mode, recorded_by_role, recorded_by_id, receipt_no)
         VALUES (:student_id, :amount, 'Cash', :recorded_by_role, :recorded_by_id, :receipt_no)"
    );
    $txStmt->execute([
        ':student_id' => $newId,
        ':amount' => $amountPaid,
        ':recorded_by_role' => $admin['role'],
        ':recorded_by_id' => $admin['id'],
        ':receipt_no' => $receiptNo,
    ]);
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.display_id, s.branch_id, b.name AS branch_name, s.course_id, c.course_name,
            s.first_name, s.last_name, s.father_name, s.mother_name, s.date_of_birth,
            s.phone, s.address, s.photo_path, s.total_fee, s.amount_paid,
            s.balance_due, s.status, s.created_at
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     WHERE s.id = :id"
);
$stmt->execute([':id' => $newId]);
$student = $stmt->fetch();

// Shown once, same as the reset-password action: the admin needs to know it to tell the
// student. Never stored in plaintext and never returned by any other endpoint.
$student['portal_login_activated'] = $initialPasswordHash !== null;
$student['portal_initial_password'] = $initialPasswordHash !== null ? dob_default_password($dateOfBirth) : null;

send_json(['success' => true, 'data' => $student], 201);
