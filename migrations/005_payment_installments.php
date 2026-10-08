<?php
/**
 * One-time migration for Phase 2 (Payment + Installment Management):
 *   - transactions: reference_no, notes, next_installment_date (the plan as it stood at
 *     the moment of that payment), recorded_by_role + recorded_by_id (which admin
 *     recorded it — plain columns, not a FK, because admins live in two separate tables
 *     by design: super_admins and branch_admins).
 *   - students.next_installment_date: the CURRENT due date, mirrored from the latest
 *     payment, so the dashboard can show it without scanning transaction history.
 *   - Indexes for paginated per-student payment history and for finding upcoming/overdue
 *     installments efficiently at scale.
 * Every new column is nullable; no existing row needs a value. Additive only.
 * Safe to re-run. Run from the project root:  php migrations/005_payment_installments.php
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

// --- transactions ---------------------------------------------------------------
$txColumns = [
    'reference_no' => "ADD COLUMN reference_no VARCHAR(50) NULL AFTER payment_mode",
    'notes' => "ADD COLUMN notes VARCHAR(500) NULL AFTER reference_no",
    'next_installment_date' => "ADD COLUMN next_installment_date DATE NULL AFTER notes",
    'recorded_by_role' => "ADD COLUMN recorded_by_role ENUM('super_admin','branch_admin') NULL AFTER next_installment_date",
    'recorded_by_id' => "ADD COLUMN recorded_by_id INT NULL AFTER recorded_by_role",
];
$added = [];
foreach ($txColumns as $name => $ddl) {
    if (!column_exists($pdo, 'transactions', $name)) {
        $pdo->exec("ALTER TABLE transactions $ddl");
        $added[] = $name;
    }
}
echo $added ? 'Added transactions columns: ' . implode(', ', $added) . "\n" : "transactions columns already present\n";

if (!index_exists($pdo, 'transactions', 'idx_transactions_student_date')) {
    $pdo->exec("ALTER TABLE transactions ADD KEY idx_transactions_student_date (student_id, transaction_date)");
    echo "Added index idx_transactions_student_date\n";
}

// --- students ---------------------------------------------------------------------
if (!column_exists($pdo, 'students', 'next_installment_date')) {
    $pdo->exec("ALTER TABLE students ADD COLUMN next_installment_date DATE NULL AFTER status");
    echo "Added students.next_installment_date\n";
} else {
    echo "students.next_installment_date already present\n";
}

if (!index_exists($pdo, 'students', 'idx_students_next_installment')) {
    $pdo->exec("ALTER TABLE students ADD KEY idx_students_next_installment (next_installment_date)");
    echo "Added index idx_students_next_installment\n";
}

echo "Migration complete\n";
