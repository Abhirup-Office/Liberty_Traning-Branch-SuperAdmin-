<?php
/**
 * Students — all-branch student directory with search, filters and pagination.
 * Super Admin only.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = require_login(['super_admin']);
clear_view_branch_id();

$pageTitle = 'Students';
$activeNav = 'students';
$headerActions = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Students</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>

            <!-- ============ TOOLBAR ============ -->
            <section class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-col lg:flex-row lg:items-center gap-3">
                <input id="searchInput" type="text" placeholder="Search by name, phone, code or course..."
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:flex">
                    <select id="branchFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="all">All Branches</option>
                    </select>
                    <select id="courseFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="">All Courses</option>
                    </select>
                    <select id="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="">All Payment Status</option>
                        <option value="Paid in Full">Paid</option>
                        <option value="Partial">Partial</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </section>

            <!-- ============ STUDENT TABLE ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold">Student Directory</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-3">Student</th>
                                <th class="text-left px-4 py-3">Branch</th>
                                <th class="text-left px-4 py-3">Course</th>
                                <th class="text-left px-4 py-3">Address</th>
                                <th class="text-right px-4 py-3">Total Fee</th>
                                <th class="text-right px-4 py-3">Paid</th>
                                <th class="text-right px-4 py-3">Balance</th>
                                <th class="text-center px-4 py-3">Status</th>
                                <th class="text-center px-4 py-3">Certificate</th>
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
<?php require __DIR__ . '/../templates/address_modal.php'; ?>
<?php require __DIR__ . '/../templates/student_certificate_modal.php'; ?>
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/students.js"></script>
</body>
</html>
