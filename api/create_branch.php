<?php
/**
 * POST /api/create_branch.php
 * Body (JSON): { name, location, manager_name, manager_email, manager_password }
 * Creates a branch and its Branch Manager account (a branch_admins row) in one transaction.
 * Super Admin only. The manager's email must be unique across super_admins and branch_admins,
 * because login checks super_admins first and would otherwise shadow the manager account.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/student_code.php';
require_api_login(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$name = trim((string) ($input['name'] ?? ''));
$location = trim((string) ($input['location'] ?? ''));
$managerName = trim((string) ($input['manager_name'] ?? ''));
$managerEmail = strtolower(trim((string) ($input['manager_email'] ?? '')));
$managerPassword = (string) ($input['manager_password'] ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Branch name is required (max 100 characters).';
}
if ($location === '' || mb_strlen($location) > 150) {
    $errors[] = 'Location is required (max 150 characters).';
}
if ($managerName === '' || mb_strlen($managerName) > 100) {
    $errors[] = 'Manager name is required (max 100 characters).';
}
if (!filter_var($managerEmail, FILTER_VALIDATE_EMAIL) || strlen($managerEmail) > 100) {
    $errors[] = 'A valid manager email is required.';
}
if (strlen($managerPassword) < 10 || strlen($managerPassword) > 72) {
    $errors[] = 'Manager password must be 10 to 72 characters.';
}
if ($errors) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$dupBranch = $pdo->prepare("SELECT 1 FROM branches WHERE LOWER(name) = LOWER(:name) LIMIT 1");
$dupBranch->execute([':name' => $name]);
if ($dupBranch->fetchColumn() !== false) {
    send_json(['success' => false, 'message' => 'A branch with this name already exists.'], 409);
}

$dupEmail = $pdo->prepare(
    "SELECT (SELECT COUNT(*) FROM super_admins WHERE email = :email_a)
          + (SELECT COUNT(*) FROM branch_admins WHERE email = :email_b)"
);
$dupEmail->execute([':email_a' => $managerEmail, ':email_b' => $managerEmail]);
if ((int) $dupEmail->fetchColumn() > 0) {
    send_json(['success' => false, 'message' => 'This email is already used by another account.'], 409);
}

try {
    $pdo->beginTransaction();

    $branchStmt = $pdo->prepare(
        "INSERT INTO branches (name, manager_name, location) VALUES (:name, :manager_name, :location)"
    );
    $branchStmt->execute([':name' => $name, ':manager_name' => $managerName, ':location' => $location]);
    $branchId = (int) $pdo->lastInsertId();

    $managerStmt = $pdo->prepare(
        "INSERT INTO branch_admins (branch_id, name, email, password_hash)
         VALUES (:branch_id, :name, :email, :password_hash)"
    );
    $managerStmt->execute([
        ':branch_id' => $branchId,
        ':name' => $managerName,
        ':email' => $managerEmail,
        ':password_hash' => password_hash($managerPassword, PASSWORD_DEFAULT),
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_duplicate_key_error($e)) {
        send_json(['success' => false, 'message' => 'This email is already used by another account.'], 409);
    }
    send_json(['success' => false, 'message' => 'Could not create branch. Please try again.'], 500);
}

send_json([
    'success' => true,
    'data' => [
        'id' => $branchId,
        'name' => $name,
        'manager_name' => $managerName,
        'location' => $location,
        'manager_email' => $managerEmail,
    ],
], 201);
