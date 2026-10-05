<?php
/**
 * One-time migration: extends the existing courses table for Course Management.
 * Additive only: existing rows keep their data, global courses keep branch_id NULL,
 * and every existing course is marked Active. Safe to re-run.
 * Run from the project root:  php migrations/002_course_management.php
 */

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=localhost;dbname=liberty_tms;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

function column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'courses' AND COLUMN_NAME = :c"
    );
    $stmt->execute([':c' => $column]);
    return (bool) $stmt->fetchColumn();
}

function index_exists(PDO $pdo, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'courses' AND INDEX_NAME = :i"
    );
    $stmt->execute([':i' => $index]);
    return (bool) $stmt->fetchColumn();
}

function foreign_key_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = 'courses'
           AND CONSTRAINT_NAME = :n AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->execute([':n' => $name]);
    return (bool) $stmt->fetchColumn();
}

$columns = [
    'branch_id' => "ADD COLUMN branch_id INT NULL AFTER id",
    'course_code' => "ADD COLUMN course_code VARCHAR(20) NULL AFTER course_name",
    'description' => "ADD COLUMN description TEXT NULL AFTER course_code",
    'start_date' => "ADD COLUMN start_date DATE NULL",
    'end_date' => "ADD COLUMN end_date DATE NULL",
    'status' => "ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'",
    'created_at' => "ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
];

$added = [];
foreach ($columns as $name => $ddl) {
    if (!column_exists($pdo, $name)) {
        $pdo->exec("ALTER TABLE courses $ddl");
        $added[] = $name;
    }
}
echo $added ? 'Added columns: ' . implode(', ', $added) . "\n" : "Columns already present\n";

if (!index_exists($pdo, 'uq_courses_code')) {
    $pdo->exec("ALTER TABLE courses ADD UNIQUE KEY uq_courses_code (course_code)");
    echo "Added unique index uq_courses_code\n";
}
if (!index_exists($pdo, 'uq_courses_branch_name')) {
    $pdo->exec("ALTER TABLE courses ADD UNIQUE KEY uq_courses_branch_name (branch_id, course_name)");
    echo "Added unique index uq_courses_branch_name\n";
}
if (!index_exists($pdo, 'idx_courses_branch_status')) {
    $pdo->exec("ALTER TABLE courses ADD KEY idx_courses_branch_status (branch_id, status)");
    echo "Added index idx_courses_branch_status\n";
}
if (!foreign_key_exists($pdo, 'fk_courses_branch')) {
    $pdo->exec("ALTER TABLE courses ADD CONSTRAINT fk_courses_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT");
    echo "Added foreign key fk_courses_branch\n";
}

echo "Migration complete\n";
