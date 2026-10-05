<?php
/**
 * GET /api/get_kpis.php?branch=<id|all>
 * Aggregate fee and enrollment figures, calculated from course fees and payments.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$branch = scoped_branch_id($admin, $_GET['branch'] ?? 'all');

$where = '';
$params = [];
if ($branch !== 'all' && $branch !== '' && $branch !== null) {
    $where = 'WHERE s.branch_id = :branch_id';
    $params[':branch_id'] = (int) $branch;
}

$sql = "SELECT
            COUNT(*) AS enrolled,
            COALESCE(SUM(" . FEE_PAID_SQL . "), 0) AS collected,
            COALESCE(SUM(GREATEST(c.total_fee - " . FEE_PAID_SQL . ", 0)), 0) AS pending,
            COALESCE(SUM(c.total_fee), 0) AS total_fees,
            SUM(CASE WHEN (" . FEE_STATUS_SQL . ") = 'Pending' THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN (" . FEE_STATUS_SQL . ") = 'Partial' THEN 1 ELSE 0 END) AS partial_count,
            SUM(CASE WHEN (" . FEE_STATUS_SQL . ") = 'Paid in Full' THEN 1 ELSE 0 END) AS paid_count
        FROM students s
        JOIN courses c ON c.id = s.course_id
        " . FEE_JOIN_SQL . "
        $where";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch();

send_json([
    'success' => true,
    'data' => [
        'enrolled' => (int) $row['enrolled'],
        'total_fees' => (float) $row['total_fees'],
        'collected' => (float) $row['collected'],
        'pending' => (float) $row['pending'],
        'overdue_count' => (int) $row['overdue_count'],
        'partial_count' => (int) $row['partial_count'],
        'paid_count' => (int) $row['paid_count'],
    ],
]);
