<?php
/**
 * Database connection + shared JSON API bootstrap for student-portal endpoints.
 * Mirrors api/config.php exactly, except it starts the student session (its own
 * cookie name) instead of the admin session. Included at the top of every
 * api/student_*.php endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/student_session.php';
require_once __DIR__ . '/json_helpers.php';
install_safe_error_handler();

// Same-origin only — see api/config.php's matching comment.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = connect_database();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
