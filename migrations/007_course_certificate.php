<?php
/**
 * One-time migration for Phase 4 (Student Portal):
 *   - courses.certificate_path: the sample/template certificate for that course, uploaded
 *     by an admin, viewable by any student enrolled in that course. Same secure-storage
 *     pattern as student photos: only a generated filename is stored, the file itself
 *     lives under uploads/ (blocked from direct web access by uploads/.htaccess) and is
 *     served only through an authenticated endpoint.
 * Additive only, nullable. Safe to re-run. Run from the project root:
 *   php migrations/007_course_certificate.php
 */

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=localhost;dbname=liberty_tms;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$exists = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'certificate_path'"
)->fetchColumn();

if ($exists) {
    echo "Column courses.certificate_path already present\n";
} else {
    $pdo->exec("ALTER TABLE courses ADD COLUMN certificate_path VARCHAR(255) NULL AFTER status");
    echo "Added column courses.certificate_path\n";
}
echo "Migration complete\n";
