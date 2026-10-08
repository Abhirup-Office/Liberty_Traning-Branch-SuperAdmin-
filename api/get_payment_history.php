<?php
/**
 * GET /api/get_payment_history.php?student_id=&page=&per_page=
 * The student's full payment history, paginated — the profile/Quick Fee Counter only ever
 * loads the latest 5 payments; this endpoint is for "view full history" on demand, so a
 * student with hundreds of installments never forces the whole table to load at once.
 * Same branch-ownership rule as every other student endpoint.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/admin_lookup.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$studentId = (int) ($_GET['student_id'] ?? 0);
if ($studentId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;
$offset = ($page - 1) * $perPage;

// Ownership check uses the same scoped-branch pattern as get_student.php, so a Branch Admin
// gets 404 (never a bare 403) for another branch's student — IDs cannot be probed either way.
$branchClause = $admin['role'] === 'branch_admin' ? ' AND branch_id = :branch_id' : '';
$ownerParams = [':id' => $studentId];
if ($branchClause) {
    $ownerParams[':branch_id'] = $admin['branch_id'];
}
$ownerStmt = $pdo->prepare("SELECT id FROM students WHERE id = :id$branchClause");
$ownerStmt->execute($ownerParams);
if ($ownerStmt->fetchColumn() === false) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE student_id = :id');
$countStmt->execute([':id' => $studentId]);
$total = (int) $countStmt->fetchColumn();

// idx_transactions_student_date (student_id, transaction_date) makes this an index range
// scan rather than a full table scan, regardless of how many students/payments exist.
$stmt = $pdo->prepare(
    'SELECT amount, payment_mode, reference_no, notes, next_installment_date,
            transaction_date, receipt_no, recorded_by_role, recorded_by_id
     FROM transactions
     WHERE student_id = :id
     ORDER BY transaction_date DESC, id DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':id', $studentId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

send_json([
    'success' => true,
    'data' => resolve_recorded_by_many($pdo, $stmt->fetchAll()),
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);
