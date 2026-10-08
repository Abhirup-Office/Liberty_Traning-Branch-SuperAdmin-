<?php
/**
 * POST /api/upload_course_certificate.php  (multipart/form-data: course_id, certificate)
 * Admin-only, same branch scope as every other course endpoint (fetch_course_for_admin).
 * Stores only a generated filename in courses.certificate_path — never the file's original
 * name, and never the file itself in the database. The file lives under uploads/certificates/,
 * blocked from direct web access by uploads/.htaccess; it is readable only through
 * api/get_course_certificate.php (admin) or api/student_certificate.php (the enrolled
 * student), both of which re-check scope before streaming.
 *
 * File type is verified from its actual bytes, not the filename or declared Content-Type:
 * PDF via its magic-number signature, images via getimagesize()+finfo (the same check
 * used for student photos).
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

const MAX_CERTIFICATE_BYTES = 5 * 1024 * 1024; // 5 MB
const ALLOWED_CERTIFICATE_MIMES = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

$courseId = (int) ($_POST['course_id'] ?? 0);
if ($courseId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid course id'], 422);
}

$course = fetch_course_for_admin($pdo, $admin, $courseId);
if (!$course) {
    send_json(['success' => false, 'message' => 'Course not found'], 404);
}

if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] === UPLOAD_ERR_NO_FILE) {
    send_json(['success' => false, 'message' => 'No certificate file was uploaded'], 422);
}
$file = $_FILES['certificate'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    send_json(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ')'], 422);
}
if (!is_uploaded_file($file['tmp_name'])) {
    send_json(['success' => false, 'message' => 'Invalid upload'], 422);
}
if ($file['size'] <= 0 || $file['size'] > MAX_CERTIFICATE_BYTES) {
    send_json(['success' => false, 'message' => 'Certificate must be a non-empty file under 5 MB'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);

if (!isset(ALLOWED_CERTIFICATE_MIMES[$detectedMime])) {
    send_json(['success' => false, 'message' => 'Only PDF, JPEG or PNG files are allowed'], 422);
}
// For images, also confirm they are genuinely decodable image data (same check as student photos).
if ($detectedMime !== 'application/pdf') {
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || $imageInfo['mime'] !== $detectedMime) {
        send_json(['success' => false, 'message' => 'Only PDF, JPEG or PNG files are allowed'], 422);
    }
}

$extension = ALLOWED_CERTIFICATE_MIMES[$detectedMime];
$filename = bin2hex(random_bytes(16)) . '.' . $extension;

try {
    $stored = storage_put($file['tmp_name'], 'certificates', $filename);
} catch (\Throwable $e) {
    send_json(['success' => false, 'message' => 'Could not save the certificate'], 500);
}

// certificate_path is kept pointing at the CURRENT certificate for fast reads elsewhere
// (course lists, the student "has a certificate" check), but the old file and its
// uploaded_files row are deliberately NOT deleted — multiple certificate uploads are kept
// as history (uploaded_files, is_active=0 for superseded ones) rather than overwritten.
$updateStmt = $pdo->prepare('UPDATE courses SET certificate_path = :path WHERE id = :id');
$updateStmt->execute([':path' => $filename, ':id' => $courseId]);

storage_record_upload(
    $pdo, 'course_certificate', $courseId, $stored['provider'], $stored['key'],
    $detectedMime, (int) $file['size'], $admin['role'], (int) $admin['id']
);

send_json(['success' => true, 'data' => ['certificate_path' => $filename]]);
