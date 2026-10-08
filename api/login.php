<?php
/**
 * POST /api/login.php
 * Body (JSON): { email, password }
 *
 * Super admins and branch admins live in two separate tables with no
 * shared credential store. We check super_admins first, then
 * branch_admins — whichever table matches determines the session role.
 *
 * Brute-force lockout: 5 failed attempts locks that one account for 15 minutes
 * (super_admins.failed_login_count/locked_until or branch_admins.*, same shape as the
 * student login lockout). The response is deliberately identical — same message, same
 * HTTP status — whether the email doesn't exist, the password is wrong, or the account is
 * currently locked, so nothing in the response lets an attacker tell those cases apart.
 *
 * A Branch Admin whose branch has been deactivated (branches.status = 'Inactive') is refused
 * the same generic way even with the correct password — see migrations/011_branch_status.php.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    send_json(['success' => false, 'message' => 'Email and password are required'], 422);
}

// Single generic response for every failure path (bad email, bad password, or a locked
// account) — see the docblock above for why these must not be distinguishable.
$invalid = ['success' => false, 'message' => 'Invalid credentials'];

// --- Step 1: Super Admins ---
$stmt = $pdo->prepare("SELECT id, name, email, password_hash, failed_login_count, locked_until FROM super_admins WHERE email = :email");
$stmt->execute([':email' => $email]);
$superAdmin = $stmt->fetch();

if ($superAdmin && admin_login_unlocked($superAdmin)) {
    if (password_verify($password, $superAdmin['password_hash'])) {
        clear_admin_login_lockout($pdo, 'super_admins', (int) $superAdmin['id']);
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['admin_id'] = (int) $superAdmin['id'];
        $_SESSION['admin_name'] = $superAdmin['name'];
        $_SESSION['role'] = 'super_admin';

        send_json(['success' => true, 'redirect' => 'super_admin_dashboard.php']);
    }
    register_admin_login_failure($pdo, 'super_admins', (int) $superAdmin['id'], (int) $superAdmin['failed_login_count']);
}

// --- Step 2: Branch Admins ---
$stmt = $pdo->prepare(
    "SELECT ba.id, ba.branch_id, ba.name, ba.email, ba.password_hash, ba.failed_login_count, ba.locked_until,
            b.status AS branch_status
     FROM branch_admins ba
     JOIN branches b ON b.id = ba.branch_id
     WHERE ba.email = :email"
);
$stmt->execute([':email' => $email]);
$branchAdmin = $stmt->fetch();

if ($branchAdmin && admin_login_unlocked($branchAdmin)) {
    if ($branchAdmin['branch_status'] === 'Inactive') {
        // Correct credentials, but the branch is deactivated — refused without touching the
        // lockout counter at all, since this isn't a credential failure.
        send_json($invalid, 401);
    }
    if (password_verify($password, $branchAdmin['password_hash'])) {
        clear_admin_login_lockout($pdo, 'branch_admins', (int) $branchAdmin['id']);
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['admin_id'] = (int) $branchAdmin['id'];
        $_SESSION['admin_name'] = $branchAdmin['name'];
        $_SESSION['role'] = 'branch_admin';
        $_SESSION['branch_id'] = (int) $branchAdmin['branch_id'];

        send_json(['success' => true, 'redirect' => 'branch_dashboard.php']);
    }
    register_admin_login_failure($pdo, 'branch_admins', (int) $branchAdmin['id'], (int) $branchAdmin['failed_login_count']);
}

// --- Step 3: no table matched, wrong password, or an account is currently locked ---
send_json($invalid, 401);
