<?php
/**
 * POST /api/change_branch_admin.php
 * Body (JSON): { branch_id, manager_name, manager_email, manager_password }
 * Replaces a branch's manager with a brand-new Branch Admin account — same validation and
 * same account-creation shape as api/create_branch.php's manager fieldset. Super Admin only.
 *
 * Any existing branch_admins row(s) for this branch are removed as part of the same
 * transaction that creates the new one, so the branch always has exactly one current manager
 * and the old manager immediately loses access to it. Nothing else is touched: students,
 * courses, transactions and the branch itself are unaffected.
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
$branchId = (int) ($input['branch_id'] ?? 0);
$managerName = trim((string) ($input['manager_name'] ?? ''));
$managerEmail = strtolower(trim((string) ($input['manager_email'] ?? '')));
$managerPassword = (string) ($input['manager_password'] ?? '');

$errors = [];
if ($branchId <= 0) {
    $errors[] = 'Invalid branch.';
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

$branchStmt = $pdo->prepare('SELECT id FROM branches WHERE id = :id');
$branchStmt->execute([':id' => $branchId]);
if ($branchStmt->fetchColumn() === false) {
    send_json(['success' => false, 'message' => 'Branch not found'], 404);
}

// The email must be free across both admin tables, except for a branch_admins row that
// already belongs to THIS branch — that row is about to be replaced anyway, so a manager
// re-using their own current email is not a conflict.
$dupEmail = $pdo->prepare(
    "SELECT (SELECT COUNT(*) FROM super_admins WHERE email = :email_a)
          + (SELECT COUNT(*) FROM branch_admins WHERE email = :email_b AND branch_id <> :branch_id)"
);
$dupEmail->execute([':email_a' => $managerEmail, ':email_b' => $managerEmail, ':branch_id' => $branchId]);
if ((int) $dupEmail->fetchColumn() > 0) {
    send_json(['success' => false, 'message' => 'This email is already used by another account.'], 409);
}

try {
    $pdo->beginTransaction();

    $pdo->prepare('DELETE FROM branch_admins WHERE branch_id = :branch_id')->execute([':branch_id' => $branchId]);

    $managerStmt = $pdo->prepare(
        'INSERT INTO branch_admins (branch_id, name, email, password_hash)
         VALUES (:branch_id, :name, :email, :password_hash)'
    );
    $managerStmt->execute([
        ':branch_id' => $branchId,
        ':name' => $managerName,
        ':email' => $managerEmail,
        ':password_hash' => password_hash($managerPassword, PASSWORD_DEFAULT),
    ]);

    // branches.manager_name is a plain display-text column (see api/get_meta.php /
    // api/get_all_branches.php), kept in sync so branch lists show the new manager immediately.
    $pdo->prepare('UPDATE branches SET manager_name = :manager_name WHERE id = :id')
        ->execute([':manager_name' => $managerName, ':id' => $branchId]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_duplicate_key_error($e)) {
        send_json(['success' => false, 'message' => 'This email is already used by another account.'], 409);
    }
    send_json(['success' => false, 'message' => 'Could not change the branch manager. Please try again.'], 500);
}

send_json([
    'success' => true,
    'data' => [
        'branch_id' => $branchId,
        'manager_name' => $managerName,
        'manager_email' => $managerEmail,
    ],
]);
