<?php
/**
 * GET /api/get_course_certificate.php?id=<course id>
 * Admin preview/download of a course's sample certificate. Same branch scope as every
 * other course endpoint.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid course id'], 422);
}

// fileId is optional: when present (and belongs to this course), a specific historical
// certificate record is streamed instead of the current one — used by the certificate
// history list on the course page.
$fileId = (int) ($_GET['file_id'] ?? 0);

$course = fetch_course_for_admin($pdo, $admin, $id);
if (!$course || empty($course['certificate_path'])) {
    send_json(['success' => false, 'message' => 'Certificate not found'], 404);
}

if ($fileId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, storage_provider, storage_key, mime_type
         FROM uploaded_files WHERE id = :fid AND file_type = 'course_certificate' AND reference_id = :cid"
    );
    $stmt->execute([':fid' => $fileId, ':cid' => $id]);
    $record = $stmt->fetch();
} else {
    $record = storage_current($pdo, 'course_certificate', $id);
}

if (!$record) {
    send_json(['success' => false, 'message' => 'Certificate not found'], 404);
}
storage_stream($record['storage_provider'], $record['storage_key'], $record['mime_type']);
