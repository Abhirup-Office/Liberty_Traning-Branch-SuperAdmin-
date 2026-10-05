<?php
/**
 * POST /api/login.php
 * Body (JSON): { email, password }
 *
 * Super admins and branch admins live in two separate tables with no
 * shared credential store. We check super_admins first, then
 * branch_admins — whichever table matches determines the session role.
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

// --- Step 1: Super Admins ---
$stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM super_admins WHERE email = :email");
$stmt->execute([':email' => $email]);
$superAdmin = $stmt->fetch();

if ($superAdmin && password_verify($password, $superAdmin['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id'] = (int) $superAdmin['id'];
    $_SESSION['admin_name'] = $superAdmin['name'];
    $_SESSION['role'] = 'super_admin';

    send_json(['success' => true, 'redirect' => 'super_admin_dashboard.php']);
}

// --- Step 2: Branch Admins ---
$stmt = $pdo->prepare("SELECT id, branch_id, name, email, password_hash FROM branch_admins WHERE email = :email");
$stmt->execute([':email' => $email]);
$branchAdmin = $stmt->fetch();

if ($branchAdmin && password_verify($password, $branchAdmin['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id'] = (int) $branchAdmin['id'];
    $_SESSION['admin_name'] = $branchAdmin['name'];
    $_SESSION['role'] = 'branch_admin';
    $_SESSION['branch_id'] = (int) $branchAdmin['branch_id'];

    send_json(['success' => true, 'redirect' => 'branch_dashboard.php']);
}

// --- Step 3: Neither table matched ---
send_json(['success' => false, 'message' => 'Invalid credentials'], 401);
