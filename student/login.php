<?php
/**
 * Student Portal — login.
 * Entirely separate from the admin login: separate session cookie (LF_STUDENT_SESSION),
 * separate credential table columns (students.password_hash), separate guard functions.
 * A student session here can never satisfy an admin page's require_login(), and vice versa.
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';

if (current_student()) {
    header('Location: dashboard.php');
    exit;
}
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

<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="flex items-center gap-3 justify-center mb-6">
            <img src="../public/assets/liberty-logo.jpg" alt="Liberty Foundation" class="w-56 h-auto" />
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h1 class="text-xl font-extrabold">Student Portal</h1>
            <p class="text-sm text-gray-500 mt-1 mb-6">Sign in with your phone number and date of birth.</p>

            <form id="loginForm" class="space-y-4" novalidate>
                <div>
                    <label for="phone" class="text-xs font-semibold text-gray-600">Phone Number</label>
                    <input id="phone" name="phone" type="tel" required maxlength="20" autocomplete="username"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="10-digit number" />
                </div>
                <div>
                    <label for="password" class="text-xs font-semibold text-gray-600">Password</label>
                    <div class="relative mt-1">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               class="w-full border border-gray-300 rounded-lg pl-3 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="DD/MM/YYYY on first login" />
                        <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none"></button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">First time signing in? Your password is your date of birth, e.g. 15/08/2005.</p>
                </div>

                <p id="loginError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>

                <button type="submit" id="loginBtn" class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-bold rounded-lg py-2.5 text-sm">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-400 text-center mt-6">Forgot your password? Ask your branch office to reset it.</p>
        <p class="text-xs text-center mt-2"><a href="../public/login.php" class="text-gray-400 hover:text-red-600">Staff login →</a></p>
    </div>
</div>

<footer class="border-t border-gray-200">
    <div class="px-4 py-4 flex flex-col sm:flex-row items-center justify-center sm:justify-between max-w-sm mx-auto gap-1.5 text-xs text-gray-400">
        <p>&copy; <?= date('Y') ?> Liberty Training.</p>
        <p>By <span class="font-semibold text-red-600">Liberty Innovation</span></p>
    </div>
</footer>

<script src="js/toast.js"></script>
<script src="js/student-api.js"></script>
<script src="js/login.js"></script>
</body>
</html>
