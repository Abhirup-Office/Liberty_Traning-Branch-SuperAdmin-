<?php
/**
 * Student Portal — Dashboard: a compact overview. Full detail lives on the dedicated
 * pages (My Profile, My Course, Certificate).
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';
$student = require_student_login();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Student Portal</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/student_layout_top.php'; ?>

            <p id="pageError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2" role="alert"></p>

            <!-- ============ PROFILE SUMMARY ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <div id="avatarContainer" class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center font-bold text-xl text-gray-600 shrink-0"></div>
                    <div class="min-w-0">
                        <p class="font-extrabold text-xl truncate" id="studentName">Loading…</p>
                        <p class="text-xs text-gray-500">Student ID <span class="font-mono font-semibold text-gray-700" id="studentDisplayId"></span></p>
                        <p class="text-xs text-gray-500" id="studentCourse"></p>
                    </div>
                </div>
            </section>

            <!-- ============ QUICK LINKS ============ -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="certificate.php" class="bg-white border border-gray-200 rounded-xl p-4 text-center shadow-sm hover:border-red-300 hover:shadow-md transition">
                    <p class="text-sm font-semibold">Certificate</p>
                </a>
                <a href="profile.php" class="bg-white border border-gray-200 rounded-xl p-4 text-center shadow-sm hover:border-red-300 hover:shadow-md transition">
                    <p class="text-sm font-semibold">My Profile</p>
                </a>
            </section>

<?php require __DIR__ . '/../templates/student_layout_bottom.php'; ?>
<script src="js/toast.js"></script>
<script src="js/student-api.js"></script>
<script src="js/student-shared.js"></script>
<script src="js/dashboard.js"></script>
</body>
</html>
