<?php
/**
 * Generates the human-readable Student ID shown in the UI: LF/001/DCA.
 *   LF      = fixed prefix
 *   001     = sequence number from student_id_sequences (grows past 3 digits naturally)
 *   DCA     = the student's course code
 *
 * Uniqueness comes from student_id_sequences: each call INSERTs a new row and reads its
 * AUTO_INCREMENT id. InnoDB's auto-increment allocation is atomic across concurrent
 * connections, so two admins creating students at the same instant can never be handed
 * the same number — no app-level locking is needed. students.display_id's UNIQUE index
 * is the final guard.
 *
 * This is independent of students.id (the internal primary key) and independent of
 * student_code (the existing anonymous 8-character reference used internally for search
 * and reminders) — both keep working exactly as before.
 *
 * Loaded with require_once; contains only function declarations.
 */

declare(strict_types=1);

/** Allocates the next sequence number. Atomic by construction — see the file docblock. */
function next_student_sequence(PDO $pdo): int
{
    $pdo->exec('INSERT INTO student_id_sequences () VALUES ()');
    return (int) $pdo->lastInsertId();
}

function build_display_id(int $sequence, string $courseCode): string
{
    return 'LF/' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT) . '/' . strtoupper($courseCode);
}

/** Allocates a sequence number and builds the LF/NNN/CODE id. courseCode must not be empty. */
function generate_unique_display_id(PDO $pdo, string $courseCode): string
{
    return build_display_id(next_student_sequence($pdo), $courseCode);
}
