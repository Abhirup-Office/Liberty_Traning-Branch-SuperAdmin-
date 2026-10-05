<?php
/**
 * GET /api/get_student.php?id=<student id>
 * One student's profile with recent payments. A Branch Admin only gets students
 * from their own branch; any other id returns 404, so IDs cannot be probed.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/fee_calc.php';
$admin = require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND s.branch_id = :branch_id' : '';
$stmt = $pdo->prepare(
    "SELECT s.id, s.student_code, s.first_name, s.last_name, s.phone, s.address,
            s.branch_id, b.name AS branch_name, s.course_id, c.course_name, c.duration,
            " . FEE_SELECT_SQL . ", s.created_at
     FROM students s
     JOIN branches b ON b.id = s.branch_id
     JOIN courses c ON c.id = s.course_id
     " . FEE_JOIN_SQL . "
     WHERE s.id = :id$branchClause"
);
$params = [':id' => $id];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt->execute($params);
$student = $stmt->fetch();

if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$txStmt = $pdo->prepare(
    "SELECT amount, payment_mode, transaction_date, receipt_no
     FROM transactions
     WHERE student_id = :id
     ORDER BY transaction_date DESC, id DESC
     LIMIT 5"
);
$txStmt->execute([':id' => $id]);

send_json([
    'success' => true,
    'data' => [
        'student' => $student,
        'payments' => $txStmt->fetchAll(),
    ],
]);
