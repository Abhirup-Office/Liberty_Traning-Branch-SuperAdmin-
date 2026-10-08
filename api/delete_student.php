<?php
/**
 * POST /api/delete_student.php
 * Body (JSON): { id }
 * Hard-deletes a student (transactions cascade via FK).
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/storage.php';
$admin = require_api_login();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_json_body();
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

// A branch_admin may only delete students that belong to their own branch,
// regardless of which id they send — verify ownership before deleting.
if ($admin['role'] === 'branch_admin') {
    $ownerStmt = $pdo->prepare("SELECT branch_id FROM students WHERE id = :id");
    $ownerStmt->execute([':id' => $id]);
    $owner = $ownerStmt->fetch();
    if (!$owner || (int) $owner['branch_id'] !== $admin['branch_id']) {
        send_json(['success' => false, 'message' => 'Student not found'], 404);
    }
}

// Fetch the photo filename and every uploaded_files record first, so they can be removed
// from storage after the row is gone.
$photoStmt = $pdo->prepare("SELECT photo_path FROM students WHERE id = :id");
$photoStmt->execute([':id' => $id]);
$photoPath = $photoStmt->fetchColumn();

$filesStmt = $pdo->prepare("SELECT storage_provider, storage_key FROM uploaded_files WHERE file_type = 'student_photo' AND reference_id = :id");
$filesStmt->execute([':id' => $id]);
$files = $filesStmt->fetchAll();

$certStmt = $pdo->prepare('SELECT storage_provider, storage_key FROM student_certificates WHERE student_id = :id');
$certStmt->execute([':id' => $id]);
$certificate = $certStmt->fetch();

$stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() === 0) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

// student_certificates.student_id cascades on delete, so the row is already gone; only the
// physical file needs explicit cleanup here, same as the photo below.
$pdo->prepare("DELETE FROM uploaded_files WHERE file_type = 'student_photo' AND reference_id = :id")->execute([':id' => $id]);
foreach ($files as $f) {
    storage_delete($f['storage_provider'], $f['storage_key']);
}
if ($certificate) {
    storage_delete($certificate['storage_provider'], $certificate['storage_key']);
}
if (!empty($photoPath)) {
    $file = __DIR__ . '/../uploads/students/' . basename($photoPath);
    if (is_file($file)) {
        @unlink($file);
    }
}

send_json(['success' => true, 'message' => 'Student deleted']);
