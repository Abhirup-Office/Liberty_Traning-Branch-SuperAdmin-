<?php
/**
 * Admin Portal Login page.
 * If an admin session already exists, skip straight to their dashboard
 * instead of showing the form again.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = current_admin();
if ($admin) {
    header('Location: ' . dashboard_for_role($admin['role']));
    exit;
}

// Google OAuth failures land back here as ?error=<code> (see api/google_callback.php).
const OAUTH_ERROR_MESSAGES = [
    'unauthorized_email' => 'This Google account is not registered as a Liberty Training admin. Contact tech@libertytraining.in for access.',
    'unverified_email' => 'Your Google account email could not be verified.',
    'google_auth_failed' => 'Could not authenticate with Google. Please try again.',
    'invalid_request' => 'Your login request expired or was invalid. Please try again.',
];
$oauthErrorCode = $_GET['error'] ?? '';
$oauthErrorMessage = OAUTH_ERROR_MESSAGES[$oauthErrorCode] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Liberty Training — Admin Portal Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<style>
    body{font-family:'Inter',sans-serif;}
    .dot-pattern{
        background-image: radial-gradient(circle, #d1d5db 1px, transparent 1px);
        background-size: 22px 22px;
    }
</style>
</head>
<body class="bg-white text-gray-800">

<div class="min-h-screen flex flex-col lg:flex-row">

    <!-- ============ LEFT PANEL — BRANDING ============ -->
    <div class="hidden lg:flex lg:w-1/2 bg-gray-50 dot-pattern relative flex-col justify-between px-12 xl:px-16 py-12">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-600 text-white flex items-center justify-center font-extrabold">LT</div>
                <span class="font-extrabold text-lg">LIBERTY TRAINING</span>
            </div>
            <span class="text-xs font-semibold bg-white border border-gray-200 text-gray-500 rounded-full px-3 py-1">Admin Gateway v2.4</span>
        </div>

        <div class="max-w-md">
            <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight">
                Empowering Careers Across <span class="text-red-600">West Bengal</span>
            </h1>
            <p class="text-gray-500 mt-4 text-sm leading-relaxed">
                Comprehensive vocational, technical &amp; professional training across Kolkata, Bolpur, and Bankura institutes.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-8">
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="w-9 h-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center mb-3">
                        <i data-lucide="network" class="w-5 h-5"></i>
                    </div>
                    <p class="font-bold text-sm">3 Campuses</p>
                    <p class="text-xs text-gray-500 mt-1">Centralized Branch Management</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mb-3">
                        <i data-lucide="book-text" class="w-5 h-5"></i>
                    </div>
                    <p class="font-bold text-sm">Fee Systems</p>
                    <p class="text-xs text-gray-500 mt-1">Instant Batch Ledger Reconciliation</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="w-9 h-9 rounded-lg bg-green-100 text-green-600 flex items-center justify-center mb-3">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                    <p class="font-bold text-sm">12k+ Trainees</p>
                    <p class="text-xs text-gray-500 mt-1">Unified Student Registry</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="shield-check" class="w-5 h-5 text-green-600"></i>
                <span class="text-sm font-semibold">ISO 9001:2015 Certified Portal</span>
            </div>
            <div class="hidden xl:flex gap-2">
                <span class="text-xs bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">Kolkata</span>
                <span class="text-xs bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">Bolpur</span>
                <span class="text-xs bg-gray-100 text-gray-600 rounded-full px-2.5 py-1">Bankura</span>
            </div>
        </div>
    </div>

    <!-- ============ RIGHT PANEL — LOGIN FORM ============ -->
    <div class="w-full lg:w-1/2 flex flex-col px-6 sm:px-12 xl:px-20 py-10 justify-center relative">
        <p class="absolute top-6 right-6 text-xs font-semibold text-gray-400">PORTAL ID: #LTI-2025</p>

        <div class="w-full max-w-sm mx-auto">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-100 rounded-full px-3 py-1 mb-4">
                <i data-lucide="lock" class="w-3.5 h-3.5"></i> Admin Authorization
            </span>
            <h2 class="text-2xl font-extrabold">Sign In to Admin Portal</h2>
            <p class="text-sm text-gray-500 mt-1 mb-6">Enter your authorized credentials to access the dashboard.</p>

            <?php if ($oauthErrorMessage): ?>
            <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2 mb-4"><?= htmlspecialchars($oauthErrorMessage) ?></p>
            <?php endif; ?>

            <button id="googleLoginBtn" type="button"
                class="w-full flex items-center justify-center gap-3 border border-gray-300 rounded-xl py-3 font-semibold text-sm hover:bg-gray-50 transition">
                <svg class="w-5 h-5" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.8 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.5 0 10.4-1.9 14.2-5.1l-6.6-5.4C29.6 35.3 26.9 36 24 36c-5.3 0-9.7-3.1-11.3-7.9l-6.6 5C9.6 39.6 16.2 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.3 5.7l6.6 5.4C41.1 36.1 44 30.6 44 24c0-1.3-.1-2.7-.4-3.5z"/>
                </svg>
                Continue with Google Workplace
            </button>

            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-xs font-semibold text-gray-400">OR SIGN IN WITH EMAIL</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <form id="loginForm" class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Email</label>
                    <div class="relative mt-1">
                        <i data-lucide="mail" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input id="email" name="email" type="email" required placeholder="admin@libertytraining.in"
                            class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Password</label>
                    <div class="relative mt-1">
                        <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input id="password" name="password" type="password" required placeholder="••••••••"
                            class="w-full border border-gray-300 rounded-lg pl-9 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                        <button type="button" id="togglePasswordBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                        Remember me
                    </label>
                    <a href="#" class="text-red-600 font-medium hover:underline">Forgot password?</a>
                </div>

                <p id="loginError" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>

                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl py-3 text-sm transition">
                    Login to Dashboard
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <div class="mt-6 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs text-gray-500 leading-relaxed">
                Access restricted to authorized Branch Admins &amp; Super Administrators. For credential issuance, reach out to
                <a href="mailto:tech@libertytraining.in" class="text-red-600 font-medium">tech@libertytraining.in</a>.
            </div>
        </div>
    </div>
</div>

<script src="js/auth.js"></script>
</body>
</html>
