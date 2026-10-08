<?php
/**
 * POST /api/upload_student_certificate.php
 * (multipart/form-data: student_id, certificate_name, course_duration, certificate?)
 *
 * Creates or replaces one student's INDIVIDUAL certificate record — entirely separate from
 * the existing global/sample certificate on the course (courses.certificate_path /
 * api/upload_course_certificate.php), which this never touches.
 *
 * `certificate` (the file) is optional on this endpoint:
 *   - First time for this student: a file is required (there is no template-rendering
 *     engine in this project to generate pixels/pages from text alone, and none is added
 *     here — see the certificate feature's final report for why).
 *   - If a certificate already exists: omitting the file just updates certificate_name /
 *     course_duration (e.g. fixing a typo) and keeps the existing file untouched. Providing
 *     a file replaces it — the old file is deleted only after the new one is safely stored.
 *
 * Validation: PNG up to 5 MB, PDF up to 25 MB, verified from the file's actual bytes (magic
 * number / getimagesize), never from the client-supplied extension or Content-Type.
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

const MAX_CERT_PNG_BYTES = 5 * 1024 * 1024;   // 5 MB
const MAX_CERT_PDF_BYTES = 25 * 1024 * 1024;  // 25 MB
const ALLOWED_CERT_MIMES = [
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];

$studentId = (int) ($_POST['student_id'] ?? 0);
$certificateName = trim((string) ($_POST['certificate_name'] ?? ''));
$courseDuration = trim((string) ($_POST['course_duration'] ?? ''));

if ($studentId <= 0) {
    send_json(['success' => false, 'message' => 'Invalid student id'], 422);
}
if ($certificateName === '' || mb_strlen($certificateName) > 150) {
    send_json(['success' => false, 'message' => 'Certificate name is required (max 150 characters)'], 422);
}
if ($courseDuration === '' || mb_strlen($courseDuration) > 50) {
    send_json(['success' => false, 'message' => 'Course duration is required (max 50 characters)'], 422);
}

$branchClause = $admin['role'] === 'branch_admin' ? ' AND s.branch_id = :branch_id' : '';
$params = [':id' => $studentId];
if ($branchClause) {
    $params[':branch_id'] = $admin['branch_id'];
}
$stmt = $pdo->prepare("SELECT s.id, s.course_id FROM students s WHERE s.id = :id$branchClause");
$stmt->execute($params);
$student = $stmt->fetch();
if (!$student) {
    send_json(['success' => false, 'message' => 'Student not found'], 404);
}

$existingStmt = $pdo->prepare('SELECT storage_provider, storage_key FROM student_certificates WHERE student_id = :id');
$existingStmt->execute([':id' => $studentId]);
$existing = $existingStmt->fetch();

$hasFile = isset($_FILES['certificate']) && $_FILES['certificate']['error'] !== UPLOAD_ERR_NO_FILE;

if (!$hasFile && !$existing) {
    send_json(['success' => false, 'message' => 'A certificate file (PNG or PDF) is required for the first upload'], 422);
}

$stored = null;
$detectedMime = null;
$fileSize = null;

if ($hasFile) {
    $file = $_FILES['certificate'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        send_json(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ')'], 422);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        send_json(['success' => false, 'message' => 'Invalid upload'], 422);
    }
    if ($file['size'] <= 0) {
        send_json(['success' => false, 'message' => 'The uploaded file is empty'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    if (!isset(ALLOWED_CERT_MIMES[$detectedMime])) {
        send_json(['success' => false, 'message' => 'Only PNG or PDF files are allowed'], 422);
    }
    if ($detectedMime === 'image/png') {
        // Confirms the bytes genuinely decode as an image, not just a PNG-signed blob.
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || $imageInfo['mime'] !== 'image/png') {
            send_json(['success' => false, 'message' => 'The PNG file is not valid'], 422);
        }
        if ($file['size'] > MAX_CERT_PNG_BYTES) {
            send_json(['success' => false, 'message' => 'PNG certificates must be under 5 MB'], 422);
        }
    } else {
        if ($file['size'] > MAX_CERT_PDF_BYTES) {
            send_json(['success' => false, 'message' => 'PDF certificates must be under 25 MB'], 422);
        }
    }

    $extension = ALLOWED_CERT_MIMES[$detectedMime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    try {
        $stored = storage_put($file['tmp_name'], 'student_certificates', $filename);
    } catch (\Throwable $e) {
        send_json(['success' => false, 'message' => 'Could not save the certificate'], 500);
    }
    $fileSize = (int) $file['size'];
}

try {
    if ($existing && $stored) {
        // Replacing the file: update everything, including the new storage reference.
        $stmt = $pdo->prepare(
            'UPDATE student_certificates
             SET certificate_name = :name, course_duration = :duration, course_id = :course_id,
                 storage_provider = :provider, storage_key = :key, mime_type = :mime,
                 file_size_bytes = :size, original_filename = :original,
                 uploaded_by_role = :role, uploaded_by_id = :uid
             WHERE student_id = :student_id'
        );
        $stmt->execute([
            ':name' => $certificateName,
            ':duration' => $courseDuration,
            ':course_id' => $student['course_id'],
            ':provider' => $stored['provider'],
            ':key' => $stored['key'],
            ':mime' => $detectedMime,
            ':size' => $fileSize,
            ':original' => isset($file) ? mb_substr(basename((string) $file['name']), 0, 255) : null,
            ':role' => $admin['role'],
            ':uid' => $admin['id'],
            ':student_id' => $studentId,
        ]);
        // Only remove the old file after the new row is safely committed.
        storage_delete($existing['storage_provider'], $existing['storage_key']);
    } elseif ($existing) {
        // Metadata-only update: keep the existing file untouched.
        $stmt = $pdo->prepare(
            'UPDATE student_certificates SET certificate_name = :name, course_duration = :duration WHERE student_id = :student_id'
        );
        $stmt->execute([':name' => $certificateName, ':duration' => $courseDuration, ':student_id' => $studentId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO student_certificates
                (student_id, course_id, certificate_name, course_duration, storage_provider, storage_key,
                 mime_type, file_size_bytes, original_filename, uploaded_by_role, uploaded_by_id)
             VALUES
                (:student_id, :course_id, :name, :duration, :provider, :key, :mime, :size, :original, :role, :uid)'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $student['course_id'],
            ':name' => $certificateName,
            ':duration' => $courseDuration,
            ':provider' => $stored['provider'],
            ':key' => $stored['key'],
            ':mime' => $detectedMime,
            ':size' => $fileSize,
            ':original' => isset($file) ? mb_substr(basename((string) $file['name']), 0, 255) : null,
            ':role' => $admin['role'],
            ':uid' => $admin['id'],
        ]);
    }
} catch (\Throwable $e) {
    // The new file (if any) is already on disk/S3 at this point; leaving it is far less
    // harmful than losing track of it, and a retry will simply overwrite it next time.
    send_json(['success' => false, 'message' => 'Could not save the certificate record'], 500);
}

send_json(['success' => true, 'message' => $hasFile ? 'Certificate saved.' : 'Certificate details updated.']);
