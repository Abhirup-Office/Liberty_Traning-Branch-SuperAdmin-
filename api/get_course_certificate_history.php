<?php
/**
 * GET /api/get_course_certificate_history.php?id=<course id>
 * Admin-only list of every certificate ever uploaded for a course (newest first), since
 * uploads are kept as history instead of being overwritten. Scope is enforced by
 * fetch_course_for_admin() exactly like every other course endpoint.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
require __DIR__ . '/admin_lookup.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid course id'], 422);
}

$course = fetch_course_for_admin($pdo, $admin, $id);
if (!$course) {
    send_json(['success' => false, 'message' => 'Course not found'], 404);
}

$stmt = $pdo->prepare(
    "SELECT id, storage_provider, mime_type, file_size_bytes, is_active, uploaded_by_role, uploaded_by_id, created_at
     FROM uploaded_files
     WHERE file_type = 'course_certificate' AND reference_id = :id
     ORDER BY id DESC"
);
$stmt->execute([':id' => $id]);
$rows = resolve_recorded_by_many(
    $pdo,
    array_map(function (array $r): array {
        $r['recorded_by_role'] = $r['uploaded_by_role'];
        $r['recorded_by_id'] = $r['uploaded_by_id'];
        return $r;
    }, $stmt->fetchAll())
);

send_json(['success' => true, 'data' => ['history' => $rows]]);
