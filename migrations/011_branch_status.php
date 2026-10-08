<?php
/**
 * Adds branches.status for soft-delete/deactivation. Branches are never hard-deleted:
 * students.branch_id and branch_admins.branch_id both cascade on delete, so a real DELETE
 * would silently wipe every student (and their transactions, uploaded files, etc.) and the
 * branch's manager account in that branch — unacceptable for historical records. Deactivating
 * instead just hides the branch from active lists and blocks its manager's login; all
 * student/course/payment history stays intact and queryable.
 * Idempotent — safe to re-run.
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

$pdo = connect_database();

$has = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'status'"
)->fetchColumn();

if ((int) $has === 0) {
    $pdo->exec("ALTER TABLE branches ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER location");
    echo "Added branches.status.\n";
} else {
    echo "branches.status already exists, skipping.\n";
}

echo "Migration 011 complete.\n";
