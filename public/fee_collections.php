<?php
/**
 * Fee Collections — payment ledger with totals, search, filters and pagination.
 * Super Admin only.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = require_login(['super_admin']);
clear_view_branch_id();

$pageTitle = 'Fee Collections';
$activeNav = 'fees';
$headerActions = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Fee Collections</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>

            <!-- ============ KPI ROW ============ -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total Collected</p>
                    <p class="text-3xl font-extrabold mt-1 text-green-600" id="kpiCollected">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Total Pending</p>
                    <p class="text-3xl font-extrabold mt-1 text-orange-500" id="kpiPending">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Transactions (matching filters)</p>
                    <p class="text-3xl font-extrabold mt-1" id="kpiTransactions">—</p>
                </div>
            </section>

            <!-- ============ TOOLBAR ============ -->
            <section class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-col lg:flex-row lg:items-center gap-3">
                <input id="searchInput" type="text" placeholder="Search by student name or receipt no..."
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:flex">
                    <select id="branchFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="all">All Branches</option>
                    </select>
                    <select id="modeFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                        <option value="">All Modes</option>
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank">Bank</option>
                    </select>
                </div>
            </section>

            <!-- ============ TRANSACTIONS TABLE ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold">Payment Ledger</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-3">Receipt</th>
                                <th class="text-left px-4 py-3">Student</th>
                                <th class="text-left px-4 py-3">Branch</th>
                                <th class="text-center px-4 py-3">Mode</th>
                                <th class="text-left px-4 py-3">Date</th>
                                <th class="text-right px-4 py-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsBody" class="divide-y divide-gray-100"></tbody>
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
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/fee_collections.js"></script>
</body>
</html>
