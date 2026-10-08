<?php
/**
 * GET /api/student_photo.php
 * Streams the logged-in student's own photo. Takes no id parameter at all — same
 * IDOR-proof pattern as student_profile.php. This is the student-portal counterpart to
 * the admin-only api/get_student_photo.php (a student session cannot pass that endpoint's
 * require_api_login(), since it checks for admin session keys that simply don't exist in
 * the separate student session).
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
require __DIR__ . '/storage.php';
$student = require_student_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->prepare('SELECT photo_path FROM students WHERE id = :id');
$stmt->execute([':id' => $student['id']]);
$photoPath = $stmt->fetchColumn();

if (empty($photoPath)) {
    send_json(['success' => false, 'message' => 'Photo not found'], 404);
}

$current = storage_current($pdo, 'student_photo', $student['id']);
if (!$current) {
    send_json(['success' => false, 'message' => 'Photo not found'], 404);
}
storage_stream($current['storage_provider'], $current['storage_key'], $current['mime_type']);
