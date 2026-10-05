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
              OR c.course_name LIKE :search_course)";
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_phone'] = '%' . $search . '%';
    $params[':search_code'] = '%' . $search . '%';
    $params[':search_course'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$fromSql = "FROM students s
            JOIN branches b ON b.id = s.branch_id
            JOIN courses c ON c.id = s.course_id
            " . FEE_JOIN_SQL . "
            $whereSql";

$countStmt = $pdo->prepare("SELECT COUNT(*) $fromSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.branch_id, b.name AS branch_name,
            s.course_id, c.course_name,
            s.first_name, s.last_name, s.phone, s.address,
            " . FEE_SELECT_SQL . ",
            s.created_at
     $fromSql
     ORDER BY s.created_at DESC, s.id DESC
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
