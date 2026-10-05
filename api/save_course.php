<?php
/**
 * POST /api/save_course.php
 * Body (JSON): { id?, course_name, course_code?, description, duration, total_fee,
 *                start_date, end_date, status, branch_id? }
 * Creates a course (no id) or updates one (with id). Course code is set once and never changed.
 * Branch Admins always act on their own branch; a branch_id they send is ignored.
 * Super Admin chooses the branch on create, and may change it only while no students are enrolled.
 */

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/course_access.php';
require_once __DIR__ . '/student_code.php';
$admin = require_api_login(['super_admin', 'branch_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_csrf();

$input = read_json_body();
$id = isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null;
$isBranchAdmin = $admin['role'] === 'branch_admin';

$existing = null;
if ($id !== null) {
    $existing = fetch_course_for_admin($pdo, $admin, $id);
    if (!$existing) {
        send_json(['success' => false, 'message' => 'Course not found'], 404);
    }
}

$name = trim((string) ($input['course_name'] ?? ''));
$code = strtoupper(trim((string) ($input['course_code'] ?? '')));
$description = trim((string) ($input['description'] ?? ''));
$duration = trim((string) ($input['duration'] ?? ''));
$totalFee = $input['total_fee'] ?? 0;
$startDate = trim((string) ($input['start_date'] ?? ''));
$endDate = trim((string) ($input['end_date'] ?? ''));
$status = (string) ($input['status'] ?? 'Active');

if ($isBranchAdmin) {
    $branchId = $admin['branch_id'];
} else {
    $branchId = isset($input['branch_id']) && $input['branch_id'] !== '' ? (int) $input['branch_id'] : ($existing['branch_id'] ?? null);
}

$errors = [];
if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
    $errors[] = 'Course name must be 3 to 120 characters.';
}
if ($existing === null && !preg_match('/^[A-Z0-9-]{2,20}$/', $code)) {
    $errors[] = 'Course code is required: 2 to 20 letters, digits or hyphens.';
}
if (mb_strlen($description) > 2000) {
    $errors[] = 'Description must be at most 2000 characters.';
}
if ($duration === '' || mb_strlen($duration) > 50) {
    $errors[] = 'Duration is required (max 50 characters).';
}
if (!is_numeric($totalFee) || (float) $totalFee < 0 || (float) $totalFee > 99999999.99) {
    $errors[] = 'Default course fee must be a positive amount.';
}
foreach (['start_date' => $startDate, 'end_date' => $endDate] as $label => $value) {
    if ($value !== '' && !(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)))) {
        $errors[] = str_replace('_', ' ', ucfirst($label)) . ' must be a valid date.';
    }
}
if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
    $errors[] = 'End date cannot be before the start date.';
}
if (!in_array($status, ['Active', 'Inactive'], true)) {
    $errors[] = 'Status must be Active or Inactive.';
}
if (!$branchId) {
    $errors[] = 'Branch is required.';
} else {
    $branchCheck = $pdo->prepare("SELECT id FROM branches WHERE id = :id");
    $branchCheck->execute([':id' => $branchId]);
    if ($branchCheck->fetchColumn() === false) {
        $errors[] = 'Selected branch does not exist.';
    }
}
if ($errors) {
    send_json(['success' => false, 'message' => implode(' ', $errors)], 422);
}

// Duplicate checks: course name within a branch, and course code across the system.
$dupSql = "SELECT id FROM courses WHERE branch_id = :branch_id AND LOWER(course_name) = LOWER(:name)";
$dupParams = [':branch_id' => $branchId, ':name' => $name];
if ($id !== null) {
    $dupSql .= ' AND id <> :self_id';
    $dupParams[':self_id'] = $id;
}
$dupStmt = $pdo->prepare($dupSql . ' LIMIT 1');
$dupStmt->execute($dupParams);
if ($dupStmt->fetchColumn() !== false) {
    send_json(['success' => false, 'message' => 'A course with this name already exists in this branch.'], 409);
}

if ($existing === null) {
    $codeStmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code LIMIT 1");
    $codeStmt->execute([':code' => $code]);
    if ($codeStmt->fetchColumn() !== false) {
        send_json(['success' => false, 'message' => 'This course code is already in use.'], 409);
    }
}

if ($existing !== null && (int) $existing['branch_id'] !== (int) $branchId && (int) $existing['enrolled_count'] > 0) {
    send_json(['success' => false, 'message' => 'The branch cannot be changed while students are enrolled in this course.'], 422);
}

$values = [
    'branch_id' => $branchId,
    'course_name' => $name,
    'description' => $description !== '' ? $description : null,
    'duration' => $duration,
    'total_fee' => round((float) $totalFee, 2),
    'start_date' => $startDate !== '' ? $startDate : null,
    'end_date' => $endDate !== '' ? $endDate : null,
    'status' => $status,
];
$bind = [];
foreach ($values as $column => $value) {
    $bind[":$column"] = $value;
}

try {
    if ($existing === null) {
        $bind[':course_code'] = $code;
        $stmt = $pdo->prepare(
            "INSERT INTO courses (branch_id, course_name, course_code, description, duration, total_fee, start_date, end_date, status)
             VALUES (:branch_id, :course_name, :course_code, :description, :duration, :total_fee, :start_date, :end_date, :status)"
        );
        $stmt->execute($bind);
        $savedId = (int) $pdo->lastInsertId();
        $statusCode = 201;
    } else {
        // course_code is intentionally absent from the UPDATE: it is immutable after creation.
        $stmt = $pdo->prepare(
            "UPDATE courses SET branch_id = :branch_id, course_name = :course_name, description = :description,
                    duration = :duration, total_fee = :total_fee, start_date = :start_date, end_date = :end_date,
                    status = :status
             WHERE id = :id"
        );
        $bind[':id'] = $id;
        $stmt->execute($bind);
        $savedId = $id;
        $statusCode = 200;
    }
} catch (PDOException $e) {
    if (is_duplicate_key_error($e)) {
        send_json(['success' => false, 'message' => 'A course with this name or code already exists.'], 409);
    }
    send_json(['success' => false, 'message' => 'Could not save the course. Please try again.'], 500);
}

send_json(['success' => true, 'data' => fetch_course_for_admin($pdo, $admin, $savedId)], $statusCode);
