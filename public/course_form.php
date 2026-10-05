<?php
/**
 * Create or edit a course. Pass ?id=<course id> to edit.
 * Editing a course outside the admin's branch is refused here and again by api/save_course.php.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';
require_once __DIR__ . '/../api/course_access.php';
require_once __DIR__ . '/../api/db.php';

$admin = require_login(['super_admin', 'branch_admin']);
clear_view_branch_id();
$isBranchAdmin = $admin['role'] === 'branch_admin';

$courseId = isset($_GET['id']) && $_GET['id'] !== '' ? (int) $_GET['id'] : null;
if ($courseId !== null && $courseId > 0) {
    $pdo = connect_database();
    if (fetch_course_for_admin($pdo, $admin, $courseId) === null) {
        render_course_not_found();
    }
}
$isEdit = $courseId !== null && $courseId > 0;

$pageTitle = $isEdit ? 'Edit Course' : 'Create Course';
$activeNav = 'courses';
$headerActions = '<a href="courses.php" class="text-sm font-semibold border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">← Back to Courses</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — <?= htmlspecialchars($pageTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>

            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6 max-w-3xl">
                <h2 class="font-bold text-lg"><?= htmlspecialchars($pageTitle) ?></h2>
                <p class="text-xs text-gray-500 mb-5">Fields marked * are required.<?= $isEdit ? ' The course code cannot be changed after creation.' : '' ?></p>

                <p id="pageError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2 mb-4" role="alert"></p>
                <div id="formLoading" class="<?= $isEdit ? '' : 'hidden' ?> text-sm text-gray-400 mb-4">Loading course...</div>

                <form id="courseForm" novalidate class="space-y-4 <?= $isEdit ? 'hidden' : '' ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="courseName" class="text-xs font-semibold text-gray-600">Course Name *</label>
                            <input id="courseName" name="course_name" type="text" maxlength="120" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="course_name"></p>
                        </div>
                        <div>
                            <label for="courseCode" class="text-xs font-semibold text-gray-600">Course Code *</label>
                            <input id="courseCode" name="course_code" type="text" maxlength="20" <?= $isEdit ? 'readonly' : 'required' ?> placeholder="e.g. DCA-01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 uppercase focus:outline-none focus:ring-2 focus:ring-red-500 <?= $isEdit ? 'bg-gray-100 text-gray-500' : '' ?>" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="course_code"></p>
                        </div>
                        <div>
                            <label for="courseDuration" class="text-xs font-semibold text-gray-600">Duration *</label>
                            <input id="courseDuration" name="duration" type="text" maxlength="50" required placeholder="e.g. 6 Months" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="duration"></p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="courseDescription" class="text-xs font-semibold text-gray-600">Description</label>
                            <textarea id="courseDescription" name="description" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="description"></p>
                        </div>
                        <div>
                            <label for="courseFee" class="text-xs font-semibold text-gray-600">Course Fee (₹)</label>
                            <input id="courseFee" name="total_fee" type="number" min="0" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="total_fee"></p>
                        </div>
                        <div>
                            <label for="courseBranch" class="text-xs font-semibold text-gray-600">Branch *</label>
                            <select id="courseBranch" name="branch_id" <?= $isBranchAdmin ? 'disabled' : 'required' ?> class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500 <?= $isBranchAdmin ? 'bg-gray-100 text-gray-500' : '' ?>">
                                <option value="">Select a branch</option>
                            </select>
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="branch_id"></p>
                        </div>
                        <div>
                            <label for="courseStart" class="text-xs font-semibold text-gray-600">Start Date</label>
                            <input id="courseStart" name="start_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="start_date"></p>
                        </div>
                        <div>
                            <label for="courseEnd" class="text-xs font-semibold text-gray-600">End Date</label>
                            <input id="courseEnd" name="end_date" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <p class="hidden text-xs text-red-600 mt-1" data-error-for="end_date"></p>
                        </div>
                        <div>
                            <label for="courseStatus" class="text-xs font-semibold text-gray-600">Status *</label>
                            <select id="courseStatus" name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <a href="courses.php" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</a>
                        <button type="submit" id="saveCourseBtn" class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            <?= $isEdit ? 'Save Changes' : 'Create Course' ?>
                        </button>
                    </div>
                </form>
            </section>

<?php require __DIR__ . '/../templates/super_layout_bottom.php'; ?>
<script>
    const COURSE_ID = <?= json_encode($isEdit ? $courseId : null) ?>;
    const IS_BRANCH_ADMIN = <?= json_encode($isBranchAdmin) ?>;
    const SESSION_BRANCH_ID = <?= json_encode($isBranchAdmin ? $admin['branch_id'] : null) ?>;
</script>
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/course_form.js"></script>
</body>
</html>
