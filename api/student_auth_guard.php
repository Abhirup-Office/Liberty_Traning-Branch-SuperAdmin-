<?php
/**
 * Session guard for the student portal. Mirrors api/auth_guard.php's shape
 * (current_*() / require_*_login()) but reads $_SESSION['student_id'], never
 * $_SESSION['admin_id'] — the two are different sessions (different cookie names)
 * and a student can never present admin credentials or vice versa.
 *
 * csrf_token()/require_csrf() are reused as-is from auth_guard.php rather than
 * duplicated: they only read/write $_SESSION, and by the time auth_guard.php is
 * loaded here the student session (LF_STUDENT_SESSION) is already active, so its
 * own session.php include is a no-op — no session-name collision occurs.
 */

declare(strict_types=1);

require_once __DIR__ . '/student_session.php';
require_once __DIR__ . '/auth_guard.php'; // reused only for csrf_token()/require_csrf()

const STUDENT_IDLE_TIMEOUT_SECONDS = 7200; // 2 hours without activity ends the session
const STUDENT_LOGIN_MAX_ATTEMPTS = 5;
const STUDENT_LOGIN_LOCKOUT_SECONDS = 900; // 15 minutes

/** Returns the logged-in student's session data, or null if not logged in or idle-expired. */
function current_student(): ?array
{
    if (!isset($_SESSION['student_id'])) {
        return null;
    }

    $now = time();
    if (isset($_SESSION['student_last_activity']) && $now - (int) $_SESSION['student_last_activity'] > STUDENT_IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $_SESSION['student_last_activity'] = $now;

    return [
        'id' => (int) $_SESSION['student_id'],
        'name' => $_SESSION['student_name'] ?? 'Student',
    ];
}

/** Page guard: redirects to the student login page if not authenticated. */
function require_student_login(): array
{
    $student = current_student();
    if (!$student) {
        header('Location: login.php');
        exit;
    }
    return $student;
}

/** API guard: 401 JSON if not authenticated, since the caller is fetch()/XHR. */
function require_student_api_login(): array
{
    $student = current_student();
    if (!$student) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    return $student;
}
