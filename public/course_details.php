<?php
/**
 * Course details: course information, enrollment statistics, and the enrolled students.
 * A course outside the admin's branch returns "not found" (same as a missing course).
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';
require_once __DIR__ . '/../api/course_access.php';
require_once __DIR__ . '/../api/db.php';

$admin = require_login(['super_admin', 'branch_admin']);
clear_view_branch_id();

$courseId = (int) ($_GET['id'] ?? 0);
if ($courseId <= 0) {
    render_course_not_found();
}
$pdo = connect_database();
if (fetch_course_for_admin($pdo, $admin, $courseId) === null) {
    render_course_not_found();
}

$pageTitle = 'Course Details';
$activeNav = 'courses';
$headerActions = '<a href="course_form.php?id=' . $courseId . '" class="text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Edit Course</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Course Details</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>

            <p id="pageError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2" role="alert"></p>

            <!-- ============ COURSE INFORMATION ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">Course <span id="courseCode" class="font-mono font-semibold text-gray-700">—</span></p>
                        <h2 class="font-extrabold text-xl" id="courseName">Loading…</h2>
                    </div>
                    <span id="courseStatus" class="self-start px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">—</span>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-5 text-sm">
                    <div><dt class="text-xs text-gray-500">Branch</dt><dd class="font-semibold" id="courseBranch">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Duration</dt><dd class="font-semibold" id="courseDuration">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Default Fee</dt><dd class="font-semibold" id="courseFee">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Created</dt><dd class="font-semibold" id="courseCreated">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Start Date</dt><dd class="font-semibold" id="courseStart">—</dd></div>
                    <div><dt class="text-xs text-gray-500">End Date</dt><dd class="font-semibold" id="courseEnd">—</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Description</dt><dd class="text-gray-700 whitespace-pre-line" id="courseDescription">—</dd></div>
                </dl>
                <div class="mt-5 pt-4 border-t border-gray-200">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="toggleStatusBtn" class="hidden text-sm font-semibold border rounded-lg px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"></button>
                        <button type="button" id="deleteCourseBtn" class="text-sm font-semibold text-red-600 border border-red-200 rounded-lg px-3 py-2 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Delete course</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">A course with enrolled students cannot be deleted; deactivate it instead.</p>
                </div>
            </section>

            <!-- ============ ENROLLMENT STATISTICS ============ -->
            <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total Students</p>
                    <p class="text-3xl font-extrabold mt-1" id="enrTotal">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Paid in Full</p>
                    <p class="text-3xl font-extrabold mt-1 text-green-600" id="enrPaid">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Partial</p>
                    <p class="text-3xl font-extrabold mt-1 text-orange-500" id="enrPartial">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Overdue</p>
                    <p class="text-3xl font-extrabold mt-1 text-red-600" id="enrOverdue">—</p>
                </div>
            </section>

            <!-- ============ ENROLLED STUDENTS ============ -->
            <section id="students" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden scroll-mt-24">
                <div class="p-4 border-b border-gray-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <h2 class="font-bold">Enrolled Students</h2>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input id="searchInput" type="text" placeholder="Search name, phone or student code..."
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 sm:w-64" />
                        <select id="statusFilter" aria-label="Filter by payment status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">All Status</option>
                            <option value="Paid in Full">Paid in Full</option>
                            <option value="Partial">Partial</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-3">Student ID</th>
                                <th class="text-left px-4 py-3">Student Name</th>
                                <th class="text-left px-4 py-3">Student Code</th>
                                <th class="text-left px-4 py-3">Phone</th>
                                <th class="text-center px-4 py-3">Status</th>
                                <th class="text-left px-4 py-3">Enrolled</th>
                                <th class="text-center px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsBody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3 border-t border-gray-200 text-sm">
                    <p class="text-gray-500" id="paginationInfo">Loading...</p>
                    <div class="flex items-center gap-2">
                        <button id="prevPageBtn" class="border border-gray-300 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                        <span class="text-gray-500" id="paginationPage">Page 1 of 1</span>
                        <button id="nextPageBtn" class="border border-gray-300 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                    </div>
                </div>
            </section>

<?php require __DIR__ . '/../templates/super_layout_bottom.php'; ?>
<?php require __DIR__ . '/../templates/student_profile_modal.php'; ?>
<script>
    const COURSE_ID = <?= json_encode($courseId) ?>;
</script>
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/course_details.js"></script>
</body>
</html>
