<?php
/**
 * GET /api/get_all_branches.php
 * Every branch regardless of status, with the same performance figures as get_meta.php's
 * campus stats. Super Admin only — this is the Branch Management (All Branches) table's data
 * source, deliberately separate from get_meta.php, whose `branches` list is filtered to Active
 * only (it feeds dropdowns elsewhere that shouldn't offer a deactivated branch). The management
 * page itself must still see every branch, active or not, so it can review and reactivate one.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
require_api_login(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->query(
    "SELECT b.id, b.name, b.manager_name, b.location, b.status,
            COUNT(s.id) AS student_count,
            COALESCE(SUM(COALESCE(tx.paid, 0)), 0) AS collected,
            COALESCE(SUM(GREATEST(s.total_fee - COALESCE(tx.paid, 0), 0)), 0) AS pending
     FROM branches b
     LEFT JOIN students s ON s.branch_id = b.id
     " . FEE_JOIN_SQL . "
     GROUP BY b.id, b.name, b.manager_name, b.location, b.status
     ORDER BY b.id"
);

send_json(['success' => true, 'data' => ['branches' => $stmt->fetchAll()]]);
