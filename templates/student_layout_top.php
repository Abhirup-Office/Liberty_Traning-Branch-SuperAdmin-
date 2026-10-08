<?php
/**
 * Student Portal layout — opening half (sidebar + header + <main>).
 * Mirrors templates/super_layout_top.php's markup/classes exactly (same design
 * language), with student nav items and $student instead of $admin.
 * Required variables: $student, $pageTitle, $activeNav.
 * Close with templates/student_layout_bottom.php.
 */

if (!isset($student, $pageTitle, $activeNav)) {
    http_response_code(403);
    exit;
}

$initials = strtoupper(substr($student['name'], 0, 1) . substr(strrchr($student['name'], ' ') ?: '', 1, 1));

$navItems = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php'],
    'profile' => ['label' => 'My Profile', 'href' => 'profile.php'],
    'course' => ['label' => 'My Course', 'href' => 'course.php'],
    'certificate' => ['label' => 'Certificate', 'href' => 'certificate.php'],
    'password' => ['label' => 'Change Password', 'href' => 'change_password.php'],
];
?>
<link rel="stylesheet" href="../public/css/brand.css" />
<div class="flex min-h-screen app-shell">

    <!-- Mobile overlay (closes the drawer on tap) -->
    <div id="studentNavOverlay" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden"></div>

    <!-- ============ SIDEBAR — drawer on mobile, fixed column on md+ ============ -->
    <aside id="studentSidebar" class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-white border-r border-gray-200 shrink-0 -translate-x-full transition-transform duration-200 md:translate-x-0 md:sticky md:top-0 md:h-screen md:self-start md:z-30">
        <div class="flex items-center gap-3 px-5 py-5 border-b border-gray-200">
            <div class="min-w-0">
                <img src="../public/assets/liberty-logo.jpg" alt="Liberty Foundation" class="w-full max-w-[200px] h-auto" />
                <p class="text-xs text-gray-500">Student Portal</p>
            </div>
        </div>
        <nav class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-1 text-sm font-medium">
            <?php foreach ($navItems as $key => $item): ?>
                <?php $isActive = $key === $activeNav; ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg <?= $isActive ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-100' ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="px-3 py-4 border-t border-gray-200">
            <div class="flex items-center gap-2 px-2 mb-3">
                <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold"><?= htmlspecialchars($initials ?: 'S') ?></div>
                <div class="leading-tight min-w-0">
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($student['name']) ?></p>
                    <p class="text-xs text-gray-400">Student</p>
                </div>
            </div>
            <button id="logoutBtn" class="w-full text-sm font-medium text-gray-500 hover:text-red-600 border border-gray-200 rounded-lg px-3 py-1.5">Logout</button>
        </div>
    </aside>

    <!-- ============ MAIN ============ -->
    <div class="flex-1 min-w-0">
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button id="studentNavToggle" type="button" aria-label="Open navigation"
                            class="md:hidden border border-gray-300 rounded-lg px-2.5 py-2 text-gray-600 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="font-extrabold text-xl"><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>
        </header>

        <main class="px-4 sm:px-6 py-6 space-y-6">
