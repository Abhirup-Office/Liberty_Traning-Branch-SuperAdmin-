<?php
/**
 * Student profile edits are not permitted. The Student Portal is read-only: name, phone,
 * parents' names, date of birth, address, photo, branch, course and enrollment are all set
 * by the branch office. Any write attempt from a student session is refused here, server-side,
 * regardless of what the request contains.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
require_student_api_login();

http_response_code(403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Students cannot edit profile information. Contact your branch office.']);
exit;
