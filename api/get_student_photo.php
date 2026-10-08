<?php
/**
 * GET /api/get_student_photo.php?id=<student id>
 * Streams a student's photo after the same branch-ownership check as every other student
 * endpoint. This is the ONLY way to read a photo — uploads/.htaccess blocks direct access
 * to the file on disk, and only a bare generated filename is stored in the database.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND branch_id = :branch_id' : '';
$params = [':id' => $id];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt = $pdo->prepare("SELECT photo_path FROM students WHERE id = :id$branchClause");
$stmt->execute($params);
$row = $stmt->fetch();

if ($row === false || empty($row['photo_path'])) {
    send_json(['success' => false, 'message' => 'Photo not found'], 404);
}

$current = storage_current($pdo, 'student_photo', $id);
if (!$current) {
    send_json(['success' => false, 'message' => 'Photo not found'], 404);
}
storage_stream($current['storage_provider'], $current['storage_key'], $current['mime_type']);
