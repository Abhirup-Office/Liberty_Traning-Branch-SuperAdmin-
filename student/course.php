<?php
/**
 * Student Portal — My Course. Read-only: enrollment/course details come from the admin side.
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';
$student = require_student_login();

$pageTitle = 'My Course';
$activeNav = 'course';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — My Course</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/student_layout_top.php'; ?>

            <p id="pageError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2" role="alert"></p>

            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6 max-w-2xl">
                <h2 class="font-extrabold text-xl" id="courseName">Loading…</h2>
                <p class="text-xs text-gray-500 mb-5" id="courseBranch"></p>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-xs text-gray-500">Duration</dt><dd class="font-semibold" id="courseDuration">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Enrolled On</dt><dd class="font-semibold" id="courseEnrolled">—</dd></div>
                </dl>
            </section>

<?php require __DIR__ . '/../templates/student_layout_bottom.php'; ?>
<script src="js/toast.js"></script>
<script src="js/student-api.js"></script>
<script src="js/student-shared.js"></script>
<script src="js/course.js"></script>
</body>
</html>
