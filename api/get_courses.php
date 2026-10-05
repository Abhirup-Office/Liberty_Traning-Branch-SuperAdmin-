<?php
/**
 * GET /api/get_courses.php?search=&status=&branch=&page=&per_page=
 * Paginated course list with enrollment statistics, scoped to the caller's branch rules.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$scope = course_scope_branch($admin);
if ($scope === null) {
    $requested = $_GET['branch'] ?? 'all';
    $scope = ($requested === 'all' || $requested === '') ? null : (int) $requested;
}

$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;
$offset = ($page - 1) * $perPage;

// Statistics cover the branch scope only, not the search and status filters.
// PDO with emulated prepares off forbids reusing a named placeholder, so each
// subquery gets its own. The list query below uses the same scope rule.
$scopeSql = $scope !== null ? 'c.branch_id = :scope_branch' : '1 = 1';
$scopeParams = $scope !== null ? [':scope_branch' => $scope] : [];

$statsStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM courses c WHERE " . ($scope !== null ? 'c.branch_id = :b1' : '1 = 1') . ") AS total_courses,
        (SELECT COUNT(*) FROM courses c WHERE " . ($scope !== null ? 'c.branch_id = :b2' : '1 = 1') . " AND c.status = 'Active') AS active_courses,
        (SELECT COUNT(*) FROM courses c WHERE " . ($scope !== null ? 'c.branch_id = :b3' : '1 = 1') . " AND c.status = 'Inactive') AS inactive_courses,
        (SELECT COUNT(*) FROM students s JOIN courses c ON c.id = s.course_id WHERE " . ($scope !== null ? 'c.branch_id = :b4' : '1 = 1') . ") AS total_enrollments"
);
$statsStmt->execute($scope !== null ? [':b1' => $scope, ':b2' => $scope, ':b3' => $scope, ':b4' => $scope] : []);
$stats = $statsStmt->fetch();

$where = [$scopeSql];
$params = $scopeParams;
if (in_array($status, ['Active', 'Inactive'], true)) {
    $where[] = 'c.status = :status';
    $params[':status'] = $status;
}
if ($search !== '') {
    $where[] = '(c.course_name LIKE :search_name OR c.course_code LIKE :search_code)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_code'] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM courses c $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT c.id, c.branch_id, b.name AS branch_name, c.course_name, c.course_code,
            c.description, c.duration, c.total_fee, c.start_date, c.end_date, c.status,
            c.created_at,
            (SELECT COUNT(*) FROM students s WHERE s.course_id = c.id) AS enrolled_count
     FROM courses c
     LEFT JOIN branches b ON b.id = c.branch_id
     $whereSql
     ORDER BY c.course_name ASC, c.id ASC
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
    'stats' => [
        'total_courses' => (int) $stats['total_courses'],
        'active_courses' => (int) $stats['active_courses'],
        'inactive_courses' => (int) $stats['inactive_courses'],
        'total_enrollments' => (int) $stats['total_enrollments'],
    ],
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
    ],
]);
