<?php
/**
 * Super Admin Overview.
 * Reachable only by accounts in the super_admins table.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = require_login(['super_admin']);
clear_view_branch_id();

$pageTitle = 'Super Admin Console';
$activeNav = 'overview';
$headerActions = '
    <button class="text-sm font-semibold border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50">Export Report</button>
    <a href="all_branches.php?new=1" class="text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">+ New Branch</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Super Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>
            <!-- ============ TOP KPI ROW ============ -->
            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <a href="all_branches.php" class="block bg-white border border-gray-200 rounded-xl p-5 shadow-sm cursor-pointer transition hover:shadow-md hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    <p class="text-sm text-gray-500">Total Branches</p>
                    <p class="text-3xl font-extrabold mt-1" id="gkpiBranches">0</p>
                </a>
                <a href="students.php" class="block bg-white border border-gray-200 rounded-xl p-5 shadow-sm cursor-pointer transition hover:shadow-md hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    <p class="text-sm text-gray-500">Total Active Students</p>
                    <p class="text-3xl font-extrabold mt-1" id="gkpiStudents">0</p>
                </a>
                <a href="fee_collections.php" class="block bg-white border border-gray-200 rounded-xl p-5 shadow-sm cursor-pointer transition hover:shadow-md hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    <p class="text-sm text-gray-500">Total Fees Collected</p>
                    <p class="text-3xl font-extrabold mt-1 text-green-600" id="gkpiCollected">₹0</p>
                </a>
                <a href="students.php" class="block bg-white border border-gray-200 rounded-xl p-5 shadow-sm cursor-pointer transition hover:shadow-md hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    <p class="text-sm text-gray-500">Total Fees Pending</p>
                    <p class="text-3xl font-extrabold mt-1 text-orange-500" id="gkpiPending">₹0</p>
                </a>
            </section>

            <!-- ============ CAMPUS PERFORMANCE ============ -->
            <section>
                <h2 class="font-bold mb-3">Campus Performance</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4" id="campusPerformance"></div>
            </section>

            <!-- ============ ALL-BRANCH STUDENT DIRECTORY ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Recently Enrolled Students</h2>
                        <p class="text-xs text-gray-500">Latest 10 enrollments, newest first</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select id="branchFilter" aria-label="Filter by branch" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="all">All Branches</option>
                        </select>
                        <a href="students.php" class="text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 text-center whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">View All Students</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-3">Student Info</th>
                                <th class="text-left px-4 py-3">Branch</th>
                                <th class="text-left px-4 py-3">Course</th>
                                <th class="text-left px-4 py-3">Enrolled</th>
                                <th class="text-right px-4 py-3">Course Fee</th>
                                <th class="text-right px-4 py-3">Paid</th>
                                <th class="text-right px-4 py-3">Pending</th>
                                <th class="text-left px-4 py-3">Address</th>
                                <th class="text-center px-4 py-3">Payment Status</th>
                                <th class="text-center px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="allStudentsBody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </section>

            <!-- ============ RECENT FEE COLLECTIONS ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <h2 class="font-bold mb-4">Recent Fee Collections</h2>
                <div id="transactionFeed" class="space-y-4"></div>
            </section>
<?php require __DIR__ . '/../templates/super_layout_bottom.php'; ?>
<?php require __DIR__ . '/../templates/student_profile_modal.php'; ?>
<?php require __DIR__ . '/../templates/address_modal.php'; ?>
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/super_admin.js"></script>
</body>
</html>
