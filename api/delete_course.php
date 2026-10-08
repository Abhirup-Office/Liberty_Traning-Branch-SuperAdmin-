<?php
/**
 * POST /api/delete_course.php
 * Body (JSON): { id }
 * Deletes a course only when it has no enrolled students, so enrollment history is never lost.
 * Courses with students must be deactivated instead. Scope is enforced by fetch_course_for_admin().
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid course id'], 422);
}

$course = fetch_course_for_admin($pdo, $admin, $id);
if (!$course) {
    send_json(['success' => false, 'message' => 'Course not found'], 404);
}

if ((int) $course['enrolled_count'] > 0) {
    send_json([
        'success' => false,
        'message' => sprintf('This course has %d enrolled student(s) and cannot be deleted. Deactivate it instead.', (int) $course['enrolled_count']),
    ], 409);
}

$filesStmt = $pdo->prepare("SELECT storage_provider, storage_key FROM uploaded_files WHERE file_type = 'course_certificate' AND reference_id = :id");
$filesStmt->execute([':id' => $id]);
$files = $filesStmt->fetchAll();

$stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
$stmt->execute([':id' => $id]);

// Only reached once the course row is actually gone, so every certificate ever uploaded
// for it (including superseded history) is cleaned up too.
$pdo->prepare("DELETE FROM uploaded_files WHERE file_type = 'course_certificate' AND reference_id = :id")->execute([':id' => $id]);
foreach ($files as $f) {
    storage_delete($f['storage_provider'], $f['storage_key']);
}

send_json(['success' => true, 'message' => 'Course deleted']);
