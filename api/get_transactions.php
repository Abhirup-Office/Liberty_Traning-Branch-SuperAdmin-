<?php
/**
 * GET /api/get_transactions.php?branch=<id|all>&payment_mode=<mode>&search=<text>&page=<n>&per_page=<n>
 * Paginated fee transactions joined with student and branch.
 * `limit` is accepted as an alias for `per_page` (used by the overview feed).
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$branch = scoped_branch_id($admin, $_GET['branch'] ?? 'all');
$mode = trim((string) ($_GET['payment_mode'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? $_GET['limit'] ?? 10);
$perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($branch !== 'all' && $branch !== '' && $branch !== null) {
    $where[] = 's.branch_id = :branch_id';
    $params[':branch_id'] = (int) $branch;
}
if (in_array($mode, ['UPI', 'Cash', 'Bank'], true)) {
    $where[] = 't.payment_mode = :payment_mode';
    $params[':payment_mode'] = $mode;
}
if ($search !== '') {
    $where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE :search_name OR t.receipt_no LIKE :search_receipt)";
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_receipt'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$fromSql = "FROM transactions t
            JOIN students s ON s.id = t.student_id
            JOIN branches b ON b.id = s.branch_id
            $whereSql";

$countStmt = $pdo->prepare("SELECT COUNT(*) $fromSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT t.id, t.amount, t.payment_mode, t.transaction_date, t.receipt_no,
            s.first_name, s.last_name, b.name AS branch_name
     $fromSql
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

send_json([
    'success' => true,
    'data' => $stmt->fetchAll(),
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);
