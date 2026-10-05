<?php
/**
 * One-time migration: adds students.address (TEXT NULL). Existing students keep NULL.
 * Safe to re-run. Run from the project root:  php migrations/003_student_address.php
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
     WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'students' AND COLUMN_NAME = 'address'"
)->fetchColumn();

if ($exists) {
    echo "Column students.address already present\n";
} else {
    $pdo->exec("ALTER TABLE students ADD COLUMN address TEXT NULL AFTER phone");
    echo "Added column students.address (existing rows are NULL)\n";
}
echo "Migration complete\n";
