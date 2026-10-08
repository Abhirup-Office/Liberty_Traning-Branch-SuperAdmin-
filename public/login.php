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
<?php
// This page is also reachable at the clean URL /login (see the root .htaccess rewrite),
// which the browser treats as living in the site root for relative-path purposes — so
// assets="..."/js="..." would otherwise 404 under that URL. $_SERVER['SCRIPT_NAME'] always
// reflects the real executed file (/public/login.php) regardless of which URL triggered it,
// so this resolves correctly whether the site is deployed at the domain root or, as in local
// development, under a subfolder.
?>
<base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>/" />
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
                <img src="assets/liberty-logo.jpg" alt="Liberty Foundation" class="w-56 xl:w-64 h-auto" />
            </div>
            <span class="text-xs font-semibold bg-white border border-gray-200 text-gray-500 rounded-full px-3 py-1">Admin Gateway v2.4</span>
        </div>

        <div class="max-w-md">
            <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight">
                Empowering Careers Across <span class="text-red-600">India</span>
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
            <img src="assets/liberty-logo.jpg" alt="Liberty Foundation" class="lg:hidden w-48 sm:w-56 h-auto mb-6" />

            <!-- ============ ADMIN / STUDENT SELECTOR ============ -->
            <div id="loginRoleTabs" class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 mb-5" role="tablist" aria-label="Sign in as">
                <button type="button" id="adminTabBtn" role="tab" aria-selected="true" aria-controls="adminLoginPanel"
                        class="px-5 py-1.5 rounded-md text-sm font-semibold transition bg-white text-red-600 shadow-sm">Admin</button>
                <button type="button" id="studentTabBtn" role="tab" aria-selected="false" aria-controls="studentLoginPanel"
                        class="px-5 py-1.5 rounded-md text-sm font-semibold transition text-gray-500 hover:text-gray-700">Student</button>
            </div>

            <!-- ============ ADMIN LOGIN PANEL ============ -->
            <div id="adminLoginPanel">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-100 rounded-full px-3 py-1 mb-4">
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i> Admin Authorization
                </span>
                <h2 class="text-2xl font-extrabold">Sign In to Admin Portal</h2>
                <p class="text-sm text-gray-500 mt-1 mb-6">Enter your authorized credentials to access the dashboard.</p>

                <div class="flex items-center gap-3 my-6">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs font-semibold text-gray-400"> SIGN IN WITH EMAIL</span>
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

            <!-- ============ STUDENT LOGIN PANEL ============ -->
            <div id="studentLoginPanel" class="hidden">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-100 rounded-full px-3 py-1 mb-4">
                    <i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i> Student Access
                </span>
                <h2 class="text-2xl font-extrabold">Sign In to Student Portal</h2>
                <p class="text-sm text-gray-500 mt-1 mb-6">Sign in with your phone number and date of birth.</p>

                <form id="studentLoginForm" class="space-y-4" novalidate>
                    <div>
                        <label for="studentPhone" class="text-xs font-semibold text-gray-600">Phone Number</label>
                        <div class="relative mt-1">
                            <i data-lucide="phone" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input id="studentPhone" name="phone" type="tel" required maxlength="20" autocomplete="username" placeholder="10-digit number"
                                class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                        </div>
                    </div>
                    <div>
                        <label for="studentPassword" class="text-xs font-semibold text-gray-600">Password</label>
                        <div class="relative mt-1">
                            <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input id="studentPassword" name="password" type="password" required autocomplete="current-password" placeholder="DD/MM/YYYY on first login"
                                class="w-full border border-gray-300 rounded-lg pl-9 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                            <button type="button" id="studentTogglePasswordBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">First time signing in? Your password is your date of birth, e.g. 15/08/2005.</p>
                    </div>

                    <p id="studentLoginError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>

                    <button type="submit" id="studentLoginBtn"
                        class="w-full flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-bold rounded-xl py-3 text-sm transition">
                        Sign In
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>

                <div class="mt-6 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs text-gray-500 leading-relaxed">
                    Forgot your password? Ask your branch office to reset it.
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="border-t border-gray-200">
    <div class="px-4 py-4 flex flex-col sm:flex-row items-center justify-center sm:justify-between max-w-4xl mx-auto gap-1.5 text-xs text-gray-400">
        <p>&copy; <?= date('Y') ?> Liberty Training. All rights reserved.</p>
        <p>Design, developed &amp; maintained by <span class="font-semibold text-red-600">Liberty Innovation</span></p>
    </div>
</footer>

<script src="js/auth.js"></script>
</body>
</html>
