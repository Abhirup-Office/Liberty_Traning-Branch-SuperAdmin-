<?php
/**
 * GET /api/get_meta.php
 * Returns branches + courses (for dropdowns) and campus performance stats.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

// A branch_admin only ever sees their own branch — campus performance
// numbers are other branches' confidential financials otherwise.
$isBranchAdmin = $admin['role'] === 'branch_admin';
$branchFilter = $isBranchAdmin ? ' WHERE id = :branch_id' : '';
$branchParams = $isBranchAdmin ? [':branch_id' => $admin['branch_id']] : [];

$branchStmt = $pdo->prepare("SELECT id, name, manager_name, location FROM branches$branchFilter ORDER BY id");
$branchStmt->execute($branchParams);
$branches = $branchStmt->fetchAll();

// Enrollment dropdowns: a Branch Admin sees active global courses and their own branch's active courses.
if ($isBranchAdmin) {
    $courseStmt = $pdo->prepare(
        "SELECT id, course_name, duration, total_fee FROM courses
         WHERE status = 'Active' AND (branch_id IS NULL OR branch_id = :branch_id) ORDER BY id"
    );
    $courseStmt->execute([':branch_id' => $admin['branch_id']]);
    $courses = $courseStmt->fetchAll();
} else {
    $courses = $pdo->query("SELECT id, course_name, duration, total_fee FROM courses ORDER BY id")->fetchAll();
}

$perfFilter = $isBranchAdmin ? ' WHERE b.id = :branch_id' : '';
$perfStmt = $pdo->prepare(
    "SELECT b.id, b.name, b.manager_name,
            COUNT(s.id) AS student_count,
            COALESCE(SUM(COALESCE(tx.paid, 0)), 0) AS collected,
            COALESCE(SUM(GREATEST(c.total_fee - COALESCE(tx.paid, 0), 0)), 0) AS pending
     FROM branches b
     LEFT JOIN students s ON s.branch_id = b.id
     LEFT JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     $perfFilter
     GROUP BY b.id, b.name, b.manager_name
     ORDER BY b.id"
);
$perfStmt->execute($branchParams);
$performance = $perfStmt->fetchAll();

send_json([
    'success' => true,
    'data' => [
        'branches' => $branches,
        'courses' => $courses,
        'performance' => $performance,
    ],
]);
