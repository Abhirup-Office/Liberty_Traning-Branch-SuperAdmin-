<?php
/**
 * Course authorization. Every course endpoint and page resolves courses through
 * fetch_course_for_admin(), so the branch rule is enforced in one place:
 *   - Super Admin: any course.
 *   - Branch Admin: only courses where courses.branch_id equals their session branch_id.
 * The branch comes from the session, never from the request.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';

/** Branch the admin is confined to, or null when all branches are allowed (Super Admin). */
function course_scope_branch(array $admin): ?int
{
    return $admin['role'] === 'branch_admin' ? $admin['branch_id'] : null;
}

/** Returns the course if this admin may access it, otherwise null (callers answer 404). */
function fetch_course_for_admin(PDO $pdo, array $admin, int $courseId): ?array
{
    $scope = course_scope_branch($admin);
    $sql = "SELECT c.id, c.branch_id, c.course_name, c.course_code, c.description, c.duration,
                   c.total_fee, c.start_date, c.end_date, c.status, c.certificate_path, c.created_at, c.updated_at,
                   b.name AS branch_name,
                   (SELECT COUNT(*) FROM students s WHERE s.course_id = c.id) AS enrolled_count
            FROM courses c
            LEFT JOIN branches b ON b.id = c.branch_id
            WHERE c.id = :id";
    $params = [':id' => $courseId];

    if ($scope !== null) {
        $sql .= ' AND c.branch_id = :branch_id';
        $params[':branch_id'] = $scope;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Shown by course pages when the course is missing or outside the admin's branch. */
function render_course_not_found(): void
{
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Course not found</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:4rem;">'
        . '<h2>Course not found</h2><p>This course does not exist or is not available to your account.</p>'
        . '<p><a href="courses.php">Back to courses</a></p></body></html>';
    exit;
}
