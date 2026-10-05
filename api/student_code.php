<?php
/**
 * Generates unique 8-character alphanumeric student reference codes.
 * The code is random: it carries no branch, database ID, name, phone or date.
 * Loaded with require_once; contains only function declarations.
 */

declare(strict_types=1);

const STUDENT_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
const STUDENT_CODE_LENGTH = 8;
const STUDENT_CODE_MAX_ATTEMPTS = 10;

function random_student_code(): string
{
    $alphabet = STUDENT_CODE_ALPHABET;
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < STUDENT_CODE_LENGTH; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

/**
 * Returns a code not yet used in students.student_code.
 * The UNIQUE index is the final guard; callers must still handle a duplicate-key error on insert.
 */
function generate_unique_student_code(PDO $pdo): string
{
    $stmt = $pdo->prepare("SELECT 1 FROM students WHERE student_code = :code LIMIT 1");
    for ($attempt = 0; $attempt < STUDENT_CODE_MAX_ATTEMPTS; $attempt++) {
        $code = random_student_code();
        $stmt->execute([':code' => $code]);
        if ($stmt->fetchColumn() === false) {
            return $code;
        }
    }
    throw new RuntimeException('Could not allocate a unique student code');
}

function is_duplicate_key_error(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}
