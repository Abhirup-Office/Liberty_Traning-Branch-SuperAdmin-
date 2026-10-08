<?php
/**
 * Adds brute-force lockout tracking to super_admins and branch_admins — the same two
 * columns, same semantics, already used by students (failed_login_count, locked_until;
 * see migration 006_student_login.php and api/student_login.php). Idempotent — safe to re-run.
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

$pdo = connect_database();

foreach (['super_admins', 'branch_admins'] as $table) {
    $hasCount = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'failed_login_count'"
    )->fetchColumn();
    if ((int) $hasCount === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash");
        echo "Added $table.failed_login_count\n";
    } else {
        echo "$table.failed_login_count already exists, skipping.\n";
    }

    $hasLock = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'locked_until'"
    )->fetchColumn();
    if ((int) $hasLock === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN locked_until TIMESTAMP NULL DEFAULT NULL AFTER failed_login_count");
        echo "Added $table.locked_until\n";
    } else {
        echo "$table.locked_until already exists, skipping.\n";
    }
}

echo "Migration 010 complete.\n";
