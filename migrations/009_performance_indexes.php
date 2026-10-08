<?php
/**
 * Phase 7 index audit. Idempotent — safe to re-run.
 *
 * Finding: students.idx_students_phone(phone) duplicates uq_students_phone(phone) exactly
 * (same single column, same order) — the UNIQUE constraint already provides everything a
 * plain index would (equality lookups, the login-by-phone query), so the extra index is pure
 * write overhead (every insert/update maintains two identical B-trees) with no read benefit.
 * Dropped here.
 *
 * Everything else audited (students.branch_id/course_id, courses.branch_id,
 * transactions.student_id+transaction_date, uploaded_files.file_type+reference_id) already
 * has an appropriate index from earlier migrations — see the Phase 7 report for the full
 * table-by-table review. No other indexes were added or removed.
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

$pdo = connect_database();

$hasRedundant = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_phone'"
)->fetchColumn();

if ((int) $hasRedundant > 0) {
    $pdo->exec('ALTER TABLE students DROP INDEX idx_students_phone');
    echo "Dropped redundant index students.idx_students_phone (duplicate of uq_students_phone).\n";
} else {
    echo "idx_students_phone already removed, skipping.\n";
}

echo "Migration 009 complete.\n";
