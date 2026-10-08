<?php
/**
 * POST /api/upload_student_photo.php  (multipart/form-data: student_id, photo)
 * Stores only a generated filename in students.photo_path — never the image itself and
 * never the client's original filename. The file is saved under uploads/students/, which
 * uploads/.htaccess blocks from direct web access; it can only be read back through
 * api/get_student_photo.php, which re-checks branch ownership before streaming it.
 *
 * The file's TYPE is verified from its actual bytes (getimagesize + finfo), not from the
 * client-supplied filename or Content-Type, so an executable renamed to .jpg is rejected.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/storage.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

const MAX_PHOTO_BYTES = 2 * 1024 * 1024; // 2 MB
const ALLOWED_PHOTO_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$studentId = (int) ($_POST['student_id'] ?? 0);
if ($studentId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}

// Same branch-ownership rule as every other student endpoint.
$branchClause = $admin['role'] === 'branch_admin' ? ' AND branch_id = :branch_id' : '';
$params = [':id' => $studentId];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt = $pdo->prepare("SELECT photo_path FROM students WHERE id = :id$branchClause");
$stmt->execute($params);
$existing = $stmt->fetch();
if ($existing === false) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    send_json(['success' => false, 'message' => 'No photo file was uploaded'], 422);
}
$file = $_FILES['photo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    send_json(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ')'], 422);
}
if (!is_uploaded_file($file['tmp_name'])) {
    send_json(['success' => false, 'message' => 'Invalid upload'], 422);
}
if ($file['size'] <= 0 || $file['size'] > MAX_PHOTO_BYTES) {
    send_json(['success' => false, 'message' => 'Photo must be a non-empty file under 2 MB'], 422);
}

// Real image check: getimagesize() parses actual pixel data, so a text/PHP file renamed
// to .jpg fails here even though the extension and Content-Type could lie.
$imageInfo = @getimagesize($file['tmp_name']);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);

if ($imageInfo === false || !isset(ALLOWED_PHOTO_MIMES[$detectedMime]) || $imageInfo['mime'] !== $detectedMime) {
    send_json(['success' => false, 'message' => 'Only JPEG, PNG or WEBP images are allowed'], 422);
}

// Filename is entirely server-generated — the client's original filename is never used,
// which also rules out path traversal.
$extension = ALLOWED_PHOTO_MIMES[$detectedMime];
$filename = bin2hex(random_bytes(16)) . '.' . $extension;

try {
    $stored = storage_put($file['tmp_name'], 'students', $filename);
} catch (\Throwable $e) {
    send_json(['success' => false, 'message' => 'Could not save the photo'], 500);
}

$updateStmt = $pdo->prepare("UPDATE students SET photo_path = :photo_path WHERE id = :id");
$updateStmt->execute([':photo_path' => $filename, ':id' => $studentId]);

$previous = storage_current($pdo, 'student_photo', $studentId);
storage_record_upload(
    $pdo, 'student_photo', $studentId, $stored['provider'], $stored['key'],
    $detectedMime, (int) $file['size'], $admin['role'], (int) $admin['id']
);

// A student only ever has one current photo, so the previous file is removed (not kept as
// history, unlike certificates); losing the new upload is worse than a stray old file, so
// deletion is best-effort and happens after the new row is safely recorded.
if ($previous) {
    storage_delete($previous['storage_provider'], $previous['storage_key']);
}
if (!empty($existing['photo_path'])) {
    $legacyOld = __DIR__ . '/../uploads/students/' . basename($existing['photo_path']);
    if (is_file($legacyOld)) {
        @unlink($legacyOld);
    }
}

send_json(['success' => true, 'data' => ['photo_path' => $filename]]);
