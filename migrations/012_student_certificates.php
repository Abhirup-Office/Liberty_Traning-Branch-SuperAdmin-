<?php
/**
 * Individual student+course certificates — deliberately a separate table from both
 * `courses.certificate_path` and `uploaded_files` (file_type='course_certificate'), which
 * remain the existing GLOBAL/SAMPLE certificate shown to every student on a course. This
 * table is the NEW per-student record: one row per student, holding the actual uploaded
 * certificate file plus the editable name/duration text that should appear on it (so an
 * admin can correct a typo without re-uploading).
 *
 * student_id is UNIQUE: a student has at most one individual certificate, replaced in place
 * (no history) — unlike the global certificate, nothing in this request asked for keeping
 * old individual-certificate versions.
 *
 * Idempotent — safe to re-run.
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

$pdo = connect_database();

$exists = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_certificates'"
)->fetchColumn();

if ((int) $exists === 0) {
    $pdo->exec(
        "CREATE TABLE student_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            certificate_name VARCHAR(150) NOT NULL,
            course_duration VARCHAR(50) NOT NULL,
            storage_provider ENUM('local','s3') NOT NULL DEFAULT 'local',
            storage_key VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size_bytes INT UNSIGNED NOT NULL,
            original_filename VARCHAR(255) NULL,
            uploaded_by_role ENUM('super_admin','branch_admin') NOT NULL,
            uploaded_by_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_certificate (student_id),
            CONSTRAINT fk_sc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_sc_course FOREIGN KEY (course_id) REFERENCES courses(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "Created student_certificates table.\n";
} else {
    echo "student_certificates already exists, skipping.\n";
}

echo "Migration 012 complete.\n";
