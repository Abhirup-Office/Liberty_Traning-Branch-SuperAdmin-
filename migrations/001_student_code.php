<?php
/**
 * One-time migration: adds students.student_code (CHAR(8), UNIQUE, NOT NULL).
 * Safe to re-run: each step checks the current state first.
 * Run from the project root:  php migrations/001_student_code.php
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_code.php';

$pdo = new PDO(
    'mysql:host=localhost;dbname=liberty_tms;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$columnExists = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_code'"
)->fetchColumn();

if (!$columnExists) {
    $pdo->exec("ALTER TABLE students ADD COLUMN student_code CHAR(8) NULL AFTER id");
    echo "Added column students.student_code\n";
}

$missing = $pdo->query("SELECT id FROM students WHERE student_code IS NULL OR student_code = ''")->fetchAll(PDO::FETCH_COLUMN);
$update = $pdo->prepare("UPDATE students SET student_code = :code WHERE id = :id AND (student_code IS NULL OR student_code = '')");
foreach ($missing as $id) {
    $code = generate_unique_student_code($pdo);
    $update->execute([':code' => $code, ':id' => $id]);
}
echo 'Assigned codes to ' . count($missing) . " existing student(s)\n";

$uniqueExists = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'students' AND INDEX_NAME = 'uq_students_student_code'"
)->fetchColumn();

if (!$uniqueExists) {
    $pdo->exec("ALTER TABLE students MODIFY student_code CHAR(8) NOT NULL");
    $pdo->exec("ALTER TABLE students ADD UNIQUE KEY uq_students_student_code (student_code)");
    echo "Set NOT NULL and added UNIQUE index uq_students_student_code\n";
}

echo "Migration complete\n";
