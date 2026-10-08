<?php
/**
 * POST /api/reset_student_password.php
 * Body (JSON): { id }
 * Admin-only. (Re)sets a student's portal login password to their date of birth
 * (DD/MM/YYYY), the same bootstrap rule used when a login is first activated.
 * Requires the student to already have a date_of_birth on file.
 *
 * The freshly generated password is returned ONCE in this response so the admin can hand
 * it to the student — it is never stored in plaintext and never shown again after this.
 * Also clears any lockout, so this doubles as an "unlock account" action.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/student_password.php';
$admin = require_api_login(['super_admin', 'branch_admin']);
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

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
$stmt = $pdo->prepare("SELECT date_of_birth FROM students WHERE id = :id$branchClause");
$stmt->execute($params);
$student = $stmt->fetch();

if ($student === false) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}
if ($student['date_of_birth'] === null) {
    send_json(['success' => false, 'message' => "Set the student's date of birth before activating their login"], 422);
}

$newPassword = dob_default_password($student['date_of_birth']);
$updateStmt = $pdo->prepare(
    'UPDATE students
     SET password_hash = :hash, password_changed_at = NOW(), failed_login_count = 0, locked_until = NULL
     WHERE id = :id'
);
$updateStmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $id]);

send_json([
    'success' => true,
    'data' => [
        'id' => $id,
        'new_password' => $newPassword,
    ],
]);
