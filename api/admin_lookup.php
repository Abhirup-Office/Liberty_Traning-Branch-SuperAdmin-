<?php
/**
 * Resolves a (recorded_by_role, recorded_by_id) pair from a transaction into a display name.
 * Admins live in two separate tables by design (super_admins / branch_admins), so this is a
 * plain lookup, not a foreign key — exactly the same pattern already used elsewhere (e.g.
 * api/get_branch_managers.php) for the same reason.
 * Loaded with require_once; contains only function declarations.
 */

declare(strict_types=1);

/** Adds a `recorded_by_name` column to a row that has recorded_by_role/recorded_by_id. */
function resolve_recorded_by(PDO $pdo, array $row): array
{
    $row['recorded_by_name'] = null;
    if ($row['recorded_by_role'] === 'super_admin' && $row['recorded_by_id']) {
        $stmt = $pdo->prepare('SELECT name FROM super_admins WHERE id = :id');
        $stmt->execute([':id' => $row['recorded_by_id']]);
        $row['recorded_by_name'] = $stmt->fetchColumn() ?: null;
    } elseif ($row['recorded_by_role'] === 'branch_admin' && $row['recorded_by_id']) {
        $stmt = $pdo->prepare('SELECT name FROM branch_admins WHERE id = :id');
        $stmt->execute([':id' => $row['recorded_by_id']]);
        $row['recorded_by_name'] = $stmt->fetchColumn() ?: null;
    }
    return $row;
}

/** Applies resolve_recorded_by() to every row in a list. */
function resolve_recorded_by_many(PDO $pdo, array $rows): array
{
    return array_map(fn (array $row) => resolve_recorded_by($pdo, $row), $rows);
}
