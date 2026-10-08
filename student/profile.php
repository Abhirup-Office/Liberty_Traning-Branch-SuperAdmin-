<?php
/**
 * Student Portal — My Profile.
 * Read-only. Every field is displayed as plain text; the page has no form controls at all,
 * and the server rejects any write attempt from a student session regardless.
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';
$student = require_student_login();

$pageTitle = 'My Profile';
$activeNav = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — My Profile</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/student_layout_top.php'; ?>

            <p id="pageError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2" role="alert"></p>

            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6 max-w-2xl">
                <div class="flex items-center gap-4 mb-5">
                    <div id="avatarContainer" class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center font-bold text-xl text-gray-600 shrink-0"></div>
                    <div class="min-w-0">
                        <p class="font-extrabold text-lg truncate" id="studentName">Loading…</p>
                        <p class="text-xs text-gray-500">Student ID <span class="font-mono font-semibold text-gray-700" id="studentDisplayId"></span></p>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Address</dt><dd class="font-semibold whitespace-pre-line" id="infoAddress">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Full Name</dt><dd class="font-semibold" id="infoName">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Phone (login username)</dt><dd class="font-semibold" id="infoPhone">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Father's Name</dt><dd class="font-semibold" id="infoFather">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Mother's Name</dt><dd class="font-semibold" id="infoMother">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Date of Birth</dt><dd class="font-semibold" id="infoDob">—</dd></div>
                    <div><dt class="text-xs text-gray-500">Branch</dt><dd class="font-semibold" id="infoBranch">—</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Course</dt><dd class="font-semibold" id="infoCourse">—</dd></div>
                </dl>
                <p class="text-xs text-gray-400 mt-2">Your details are maintained by your branch office. Contact them to request a change.</p>
            </section>

<?php require __DIR__ . '/../templates/student_layout_bottom.php'; ?>
<script src="js/toast.js"></script>
<script src="js/student-api.js"></script>
<script src="js/student-shared.js"></script>
<script src="js/profile.js"></script>
</body>
</html>
