<?php
/**
 * The student portal's default/initial password is the student's date of birth, written
 * the way a student would naturally write it: DD/MM/YYYY (e.g. "15/08/2005"). This is
 * never stored — only password_hash() of this string is ever written to the database.
 * Loaded with require_once; contains only a function declaration.
 */

declare(strict_types=1);

function dob_default_password(string $dateOfBirth): string
{
    return date('d/m/Y', strtotime($dateOfBirth));
}
