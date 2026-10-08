<?php
/**
 * POST /api/student_change_password.php
 * Body (JSON): { current_password, new_password, confirm_password }
 * The student's own id comes only from the session — this endpoint accepts no id
 * parameter at all, so there is nothing for a student to manipulate to target another
 * account.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
$student = require_student_api_login();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    send_json(['success' => false, 'message' => 'All fields are required'], 422);
}
if ($newPassword !== $confirmPassword) {
    send_json(['success' => false, 'message' => 'New password and confirmation do not match'], 422);
}
if (mb_strlen($newPassword) < 8) {
    send_json(['success' => false, 'message' => 'New password must be at least 8 characters'], 422);
}
if ($newPassword === $currentPassword) {
    send_json(['success' => false, 'message' => 'New password must be different from the current password'], 422);
}

$stmt = $pdo->prepare('SELECT password_hash FROM students WHERE id = :id');
$stmt->execute([':id' => $student['id']]);
$hash = $stmt->fetchColumn();

if ($hash === false || $hash === null || !password_verify($currentPassword, $hash)) {
    send_json(['success' => false, 'message' => 'Current password is incorrect'], 422);
}

$updateStmt = $pdo->prepare(
    'UPDATE students SET password_hash = :hash, password_changed_at = NOW() WHERE id = :id'
);
$updateStmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $student['id']]);

send_json(['success' => true, 'message' => 'Password changed successfully']);
