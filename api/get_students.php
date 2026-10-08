<?php
/**
 * GET /api/get_students.php?branch=&status=&course=&search=&page=&per_page=
 * Filtered, paginated student list. Fees and status are derived from the course fee and payments.
 * Search matches name, phone, student code and course name (partial, case-insensitive).
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

// A branch_admin's branch is always taken from their session, never from this query param.
$branch = scoped_branch_id($admin, $_GET['branch'] ?? 'all');
$status = trim((string) ($_GET['status'] ?? ''));
$course = $_GET['course'] ?? '';
$search = trim((string) ($_GET['search'] ?? ''));

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;
$offset = ($page - 1) * $perPage;

$allowedStatuses = ['Paid in Full', 'Partial', 'Pending'];

$where = [];
$params = [];

if ($branch !== 'all' && $branch !== '' && $branch !== null) {
    $where[] = 's.branch_id = :branch_id';
    $params[':branch_id'] = (int) $branch;
}
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[] = '(' . FEE_STATUS_SQL . ') = :status';
    $params[':status'] = $status;
}
if ($course !== '' && $course !== 'all') {
    $where[] = 's.course_id = :course_id';
    $params[':course_id'] = (int) $course;
}
if ($search !== '') {
    $where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE :search_name
              OR s.phone LIKE :search_phone
              OR s.student_code LIKE :search_code
              OR s.display_id LIKE :search_display
              OR c.course_name LIKE :search_course)";
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_phone'] = '%' . $search . '%';
    $params[':search_code'] = '%' . $search . '%';
    $params[':search_display'] = '%' . $search . '%';
    $params[':search_course'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$baseFromSql = "FROM students s
            JOIN branches b ON b.id = s.branch_id
            JOIN courses c ON c.id = s.course_id";

// The fee-aggregate join is real work (it sums every matching transaction before the LIMIT
// is applied), so it is scoped to one branch whenever the request already targets one — a
// Branch Admin's own directory, or a Super Admin filtering to a single branch — instead of
// aggregating every branch's transactions system-wide to answer a single-branch page. "All
// branches" views (Super Admin only) are the one case that genuinely needs the full join.
$isSingleBranch = $branch !== 'all' && $branch !== '' && $branch !== null;
$feeJoinSql = $isSingleBranch ? fee_join_sql() : FEE_JOIN_SQL;
if ($isSingleBranch) {
    $params[':fee_scope_branch_id'] = (int) $branch;
}

// The COUNT query only needs the fee join when a status filter references it; otherwise it
// is dropped entirely so listing/searching students never pays for a fee aggregation it
// doesn't use.
$statusFilterActive = $status !== '' && in_array($status, $allowedStatuses, true);
$countFromSql = $baseFromSql . ($statusFilterActive ? " $feeJoinSql" : '') . " $whereSql";
$selectFromSql = "$baseFromSql $feeJoinSql $whereSql";

$countParams = $params;
if (!$statusFilterActive) {
    unset($countParams[':fee_scope_branch_id']);
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $countFromSql");
$countStmt->execute($countParams);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.display_id, s.branch_id, b.name AS branch_name,
            s.course_id, c.course_name,
            s.first_name, s.last_name, s.phone, s.address,
            " . FEE_SELECT_SQL . ",
            s.next_installment_date, s.created_at,
            (SELECT MAX(t.transaction_date) FROM transactions t WHERE t.student_id = s.id) AS last_payment_date,
            EXISTS(SELECT 1 FROM student_certificates sc WHERE sc.student_id = s.id) AS has_certificate
     $selectFromSql
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

// MySQL/PDO returns EXISTS() as the string "0"/"1", which JavaScript treats as truthy either
// way — cast explicitly so the Certificate column's icon state is actually correct.
$rows = array_map(function (array $row): array {
    $row['has_certificate'] = (bool) $row['has_certificate'];
    return $row;
}, $stmt->fetchAll());

send_json([
    'success' => true,
    'data' => $rows,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);
