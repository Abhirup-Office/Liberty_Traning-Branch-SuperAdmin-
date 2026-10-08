<?php
/**
 * Starts the student-portal session under its own cookie name (LF_STUDENT_SESSION),
 * completely separate from the admin session (PHPSESSID, started by api/session.php).
 * Without this, an admin and a student signed in on the same browser would share one
 * session cookie and overwrite each other's login. session_name() only has an effect
 * before the session starts, so this must run before anything else touches the session.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('LF_STUDENT_SESSION');
}

// Same hardened cookie settings as the admin session; session.php only starts a session
// if one isn't already active, so calling it here is safe and avoids duplicating the setup.
require_once __DIR__ . '/session.php';
