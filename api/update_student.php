<?php
/**
 * POST /api/update_student.php
 * Body (JSON): { id, first_name, last_name, phone, address }
 * Updates only the editable profile fields. Code, branch, course, fees and dates are system-controlled.
 * A Branch Admin can only edit students of their own branch; other ids return 404.
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
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND branch_id = :branch_id' : '';
$params = [':id' => $id];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$existsStmt = $pdo->prepare("SELECT id FROM students WHERE id = :id$branchClause");
$existsStmt->execute($params);
if ($existsStmt->fetchColumn() === false) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$firstName = trim((string) ($input['first_name'] ?? ''));
$lastName = trim((string) ($input['last_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$address = trim((string) ($input['address'] ?? ''));

$errors = [];
if ($firstName === '' || mb_strlen($firstName) > 80) {
    $errors[] = 'First name is required (max 80 characters).';
}
if (mb_strlen($lastName) > 80) {
    $errors[] = 'Last name must be at most 80 characters.';
}
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    $errors[] = 'Phone number must be exactly 10 digits.';
}
if (mb_strlen($address) > 500) {
    $errors[] = 'Address must be at most 500 characters.';
}
if ($errors) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$stmt = $pdo->prepare(
    "UPDATE students SET first_name = :first_name, last_name = :last_name, phone = :phone, address = :address
     WHERE id = :id"
);
$stmt->execute([
    ':first_name' => $firstName,
    ':last_name' => $lastName,
    ':phone' => $phone,
    ':address' => $address !== '' ? $address : null,
    ':id' => $id,
]);

$readParams = [':id' => $id];
$readStmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.first_name, s.last_name, s.phone, s.address, s.branch_id, b.name AS branch_name,
            s.course_id, c.course_name, " . FEE_SELECT_SQL . ", s.created_at
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     WHERE s.id = :id"
);
$readStmt->execute($readParams);

send_json(['success' => true, 'data' => $readStmt->fetch()]);
