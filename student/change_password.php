<?php
/**
 * Student Portal — Change Password.
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';
$student = require_student_login();

$pageTitle = 'Change Password';
$activeNav = 'password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Change Password</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/student_layout_top.php'; ?>

            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6 max-w-md">
                <h2 class="font-bold mb-1">Change Password</h2>
                <p class="text-xs text-gray-500 mb-4">Use this once you've signed in with your date-of-birth password, to set one only you know.</p>
                <form id="changePasswordForm" novalidate class="space-y-4">
                    <div>
                        <label for="currentPassword" class="text-xs font-semibold text-gray-600">Current Password</label>
                        <div class="relative mt-1">
                            <input id="currentPassword" name="current_password" type="password" required autocomplete="current-password" class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <button type="button" data-toggle-password="currentPassword" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none"></button>
                        </div>
                    </div>
                    <div>
                        <label for="newPassword" class="text-xs font-semibold text-gray-600">New Password</label>
                        <div class="relative mt-1">
                            <input id="newPassword" name="new_password" type="password" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <button type="button" data-toggle-password="newPassword" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none"></button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">At least 8 characters.</p>
                    </div>
                    <div>
                        <label for="confirmPassword" class="text-xs font-semibold text-gray-600">Confirm New Password</label>
                        <div class="relative mt-1">
                            <input id="confirmPassword" name="confirm_password" type="password" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <button type="button" data-toggle-password="confirmPassword" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none"></button>
                        </div>
                    </div>
                    <p id="passwordError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>
                    <p id="passwordSuccess" role="status" class="hidden text-sm text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2"></p>
                    <button type="submit" id="changePasswordBtn" class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-semibold text-sm rounded-lg py-2.5">Change Password</button>
                </form>
            </section>

<?php require __DIR__ . '/../templates/student_layout_bottom.php'; ?>
<script src="js/toast.js"></script>
<script src="js/student-api.js"></script>
<script src="js/student-shared.js"></script>
<script src="js/change_password.js"></script>
</body>
</html>
