<?php
/**
 * POST /api/student_login.php
 * Body (JSON): { phone, password }
 * Username is the student's phone number (unique). Initial password is the student's date
 * of birth, written DD/MM/YYYY, set by an admin via api/reset_student_password.php.
 *
 * Login protection: 5 failed attempts locks the account for 15 minutes (locked_until).
 * While locked, login is refused even with the correct password — the lock must expire.
 * Attempts are tracked per-student in the database, not per-IP, since this is a small
 * internal portal and students share networks (home Wi-Fi, campus Wi-Fi) where IP-based
 * limiting would lock out unrelated people.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$phone = trim((string) ($input['phone'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($phone === '' || $password === '') {
    send_json(['success' => false, 'message' => 'Phone number and password are required'], 422);
}

$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, password_hash, failed_login_count, locked_until
     FROM students WHERE phone = :phone"
);
$stmt->execute([':phone' => $phone]);
$student = $stmt->fetch();

// Same generic message whether the phone doesn't exist or the password is wrong —
// never reveal which one it was.
$invalid = ['success' => false, 'message' => 'Invalid phone number or password'];

if (!$student) {
    send_json($invalid, 401);
}

if ($student['locked_until'] !== null && strtotime($student['locked_until']) > time()) {
    $until = date('g:i A', strtotime($student['locked_until']));
    send_json(['success' => false, 'message' => "Too many failed attempts. Try again after $until."], 423);
}

if ($student['password_hash'] === null) {
    send_json(['success' => false, 'message' => 'Your account is not yet activated. Please contact your branch office.'], 403);
}

if (!password_verify($password, $student['password_hash'])) {
    $attempts = (int) $student['failed_login_count'] + 1;
    $lockUntil = null;
    if ($attempts >= STUDENT_LOGIN_MAX_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + STUDENT_LOGIN_LOCKOUT_SECONDS);
        $attempts = 0; // the lock itself is the penalty; the counter restarts after it expires
    }
    $updateStmt = $pdo->prepare('UPDATE students SET failed_login_count = :n, locked_until = :locked WHERE id = :id');
    $updateStmt->execute([':n' => $attempts, ':locked' => $lockUntil, ':id' => $student['id']]);

    if ($lockUntil !== null) {
        $until = date('g:i A', strtotime($lockUntil));
        send_json(['success' => false, 'message' => "Too many failed attempts. Try again after $until."], 423);
    }
    send_json($invalid, 401);
}

// Success: clear the lockout state and start a fresh session (regenerated id, as on admin login).
$pdo->prepare('UPDATE students SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id')
    ->execute([':id' => $student['id']]);

session_regenerate_id(true);
$_SESSION = [];
$_SESSION['student_id'] = (int) $student['id'];
$_SESSION['student_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
$_SESSION['student_last_activity'] = time();

send_json(['success' => true, 'redirect' => 'dashboard.php']);
