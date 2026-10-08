<?php
/**
 * Adds uploaded_files: the audit/metadata table for every student photo and course
 * certificate upload (storage backend, object key/path, mime type, size, who uploaded it,
 * when, and which student/course it belongs to). Idempotent — safe to re-run.
 *
 * Existing students.photo_path / courses.certificate_path are left in place as a fast
 * "current file" cache (unchanged reads everywhere else keep working); this table is the
 * new source of truth for streaming files and for certificate history.
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

$pdo = connect_database();

$exists = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uploaded_files'"
)->fetchColumn();

if ((int) $exists === 0) {
    $pdo->exec(
        "CREATE TABLE uploaded_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_type ENUM('student_photo','course_certificate') NOT NULL,
            reference_id INT NOT NULL,
            storage_provider ENUM('local','s3') NOT NULL DEFAULT 'local',
            storage_key VARCHAR(500) NOT NULL,
            url VARCHAR(1000) NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size_bytes INT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            uploaded_by_role ENUM('super_admin','branch_admin') NULL,
            uploaded_by_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_uploaded_files_lookup (file_type, reference_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "Created uploaded_files table.\n";

    // Backfill the two existing bare-filename columns as historical records so every
    // photo/certificate a student or admin can already see today also has a metadata row.
    $students = $pdo->query("SELECT id, photo_path FROM students WHERE photo_path IS NOT NULL AND photo_path <> ''")->fetchAll();
    $photoDir = __DIR__ . '/../uploads/students';
    foreach ($students as $s) {
        $path = $photoDir . '/' . basename($s['photo_path']);
        $size = is_file($path) ? filesize($path) : 0;
        $mime = is_file($path) ? ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream') : 'application/octet-stream';
        $ins = $pdo->prepare(
            "INSERT INTO uploaded_files (file_type, reference_id, storage_provider, storage_key, mime_type, file_size_bytes, is_active)
             VALUES ('student_photo', :ref, 'local', :key, :mime, :size, 1)"
        );
        $ins->execute([':ref' => $s['id'], ':key' => $s['photo_path'], ':mime' => $mime, ':size' => $size ?: 0]);
    }
    if ($students) {
        echo 'Backfilled ' . count($students) . " student photo record(s).\n";
    }

    $courses = $pdo->query("SELECT id, certificate_path FROM courses WHERE certificate_path IS NOT NULL AND certificate_path <> ''")->fetchAll();
    $certDir = __DIR__ . '/../uploads/certificates';
    foreach ($courses as $c) {
        $path = $certDir . '/' . basename($c['certificate_path']);
        $size = is_file($path) ? filesize($path) : 0;
        $mime = is_file($path) ? ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream') : 'application/octet-stream';
        $ins = $pdo->prepare(
            "INSERT INTO uploaded_files (file_type, reference_id, storage_provider, storage_key, mime_type, file_size_bytes, is_active)
             VALUES ('course_certificate', :ref, 'local', :key, :mime, :size, 1)"
        );
        $ins->execute([':ref' => $c['id'], ':key' => $c['certificate_path'], ':mime' => $mime, ':size' => $size ?: 0]);
    }
    if ($courses) {
        echo 'Backfilled ' . count($courses) . " course certificate record(s).\n";
    }
} else {
    echo "uploaded_files already exists, skipping.\n";
}

echo "Migration 008 complete.\n";
