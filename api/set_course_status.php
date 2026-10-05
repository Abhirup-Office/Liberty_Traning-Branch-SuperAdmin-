<?php
/**
 * POST /api/set_course_status.php
 * Body (JSON): { id, status }  where status is Active or Inactive.
 * Soft status change only. Courses are never deleted.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$id = (int) ($input['id'] ?? 0);
$status = (string) ($input['status'] ?? '');

if ($id <= 0 || !in_array($status, ['Active', 'Inactive'], true)) {
    send_json(['success' => false, 'message' => 'Invalid course or status'], 422);
}

if (!fetch_course_for_admin($pdo, $admin, $id)) {
    send_json(['success' => false, 'message' => 'Course not found'], 404);
}

$stmt = $pdo->prepare("UPDATE courses SET status = :status WHERE id = :id");
$stmt->execute([':status' => $status, ':id' => $id]);

send_json(['success' => true, 'data' => fetch_course_for_admin($pdo, $admin, $id)]);
