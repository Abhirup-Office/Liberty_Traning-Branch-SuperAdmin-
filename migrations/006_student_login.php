<?php
/**
 * One-time migration for Phase 3 (Student Authentication):
 *   - students.phone becomes UNIQUE — it is the login username, so it must identify exactly
 *     one student. Verified beforehand: no two existing students currently share a phone.
 *   - password_hash / password_changed_at / last_login_at / failed_login_count / locked_until
 *     added to students. No password is stored anywhere in plaintext; password_hash holds
 *     only the output of PHP's password_hash().
 *   - No password is backfilled here: of the 16 students that existed when this migration
 *     was written, NONE had a date_of_birth on file (it was optional in Phase 1), so there
 *     is nothing valid to hash yet. An admin sets DOB via the existing edit-student flow,
 *     then uses the new "Reset login password to DOB" action — login activates at that point.
 * Additive only. No existing row or column is changed in a breaking way.
 * Safe to re-run. Run from the project root:  php migrations/006_student_login.php
 */

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=localhost;dbname=liberty_tms;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (bool) $stmt->fetchColumn();
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = 'liberty_tms' AND TABLE_NAME = :t AND INDEX_NAME = :i"
    );
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (bool) $stmt->fetchColumn();
}

$dupes = $pdo->query(
    "SELECT phone, COUNT(*) c FROM students GROUP BY phone HAVING c > 1"
)->fetchAll();
if ($dupes) {
    echo "ABORTED: duplicate phone numbers exist, cannot make students.phone UNIQUE:\n";
    foreach ($dupes as $row) {
        echo "  {$row['phone']} ({$row['c']} students)\n";
    }
    echo "Resolve the duplicates, then re-run this migration.\n";
    exit(1);
}

if (!index_exists($pdo, 'students', 'uq_students_phone')) {
    $pdo->exec("ALTER TABLE students ADD UNIQUE KEY uq_students_phone (phone)");
    echo "Added unique index uq_students_phone\n";
} else {
    echo "uq_students_phone already present\n";
}

$columns = [
    'password_hash' => "ADD COLUMN password_hash VARCHAR(255) NULL AFTER photo_path",
    'password_changed_at' => "ADD COLUMN password_changed_at TIMESTAMP NULL AFTER password_hash",
    'last_login_at' => "ADD COLUMN last_login_at TIMESTAMP NULL AFTER password_changed_at",
    'failed_login_count' => "ADD COLUMN failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at",
    'locked_until' => "ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_login_count",
];
$added = [];
foreach ($columns as $name => $ddl) {
    if (!column_exists($pdo, 'students', $name)) {
        $pdo->exec("ALTER TABLE students $ddl");
        $added[] = $name;
    }
}
echo $added ? 'Added students columns: ' . implode(', ', $added) . "\n" : "students login columns already present\n";

$noDob = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE date_of_birth IS NULL")->fetchColumn();
if ($noDob > 0) {
    echo "NOTE: $noDob student(s) have no date_of_birth, so their portal login cannot be activated yet.\n";
}

echo "Migration complete\n";
