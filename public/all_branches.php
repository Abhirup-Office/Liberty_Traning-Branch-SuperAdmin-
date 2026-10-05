<?php
/**
 * All Branches — per-campus performance table, search, "Open Branch",
 * and Create Branch (with its Branch Manager account). Super Admin only.
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = require_login(['super_admin']);
clear_view_branch_id();

$pageTitle = 'All Branches';
$activeNav = 'branches';
$headerActions = '
    <button type="button" id="createBranchBtn" class="text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">+ Create Branch</button>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — All Branches</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/super_layout_top.php'; ?>

            <!-- ============ TOOLBAR ============ -->
            <section class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <input id="searchInput" type="text" placeholder="Search by branch, manager or location..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </section>

            <!-- ============ BRANCH TABLE ============ -->
            <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold">Branch Overview</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-4 py-3">Branch</th>
                                <th class="text-left px-4 py-3">Manager</th>
                                <th class="text-left px-4 py-3">Location</th>
                                <th class="text-right px-4 py-3">Students</th>
                                <th class="text-right px-4 py-3">Collected</th>
                                <th class="text-right px-4 py-3">Pending</th>
                                <th class="text-left px-4 py-3">Clearance</th>
                                <th class="text-center px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="branchesBody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </section>

<?php require __DIR__ . '/../templates/super_layout_bottom.php'; ?>

<!-- ============ CREATE BRANCH MODAL ============ -->
<div id="createBranchModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" aria-labelledby="createBranchTitle">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <div>
                <h3 class="font-bold text-lg" id="createBranchTitle">Create Branch</h3>
                <p class="text-xs text-gray-500">Fields marked * are required.</p>
            </div>
            <button type="button" id="closeBranchModalBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <form id="createBranchForm" novalidate class="px-5 py-4 space-y-4 overflow-y-auto">
            <fieldset class="space-y-4">
                <legend class="text-sm font-bold text-gray-700 mb-1">Branch details</legend>
                <div>
                    <label for="branchName" class="text-xs font-semibold text-gray-600">Branch Name *</label>
                    <input id="branchName" name="name" type="text" maxlength="100" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="e.g. Siliguri" />
                    <p class="hidden text-xs text-red-600 mt-1" data-error-for="name"></p>
                </div>
                <div>
                    <label for="branchLocation" class="text-xs font-semibold text-gray-600">Location (City, State) *</label>
                    <input id="branchLocation" name="location" type="text" maxlength="150" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="e.g. Siliguri, West Bengal" />
                    <p class="hidden text-xs text-red-600 mt-1" data-error-for="location"></p>
                </div>
            </fieldset>

            <fieldset class="space-y-4 pt-2 border-t border-gray-200">
                <legend class="text-sm font-bold text-gray-700 mb-1">Branch Manager access</legend>
                <p class="text-xs text-gray-500 -mt-3">The manager signs in with this email and password and sees only this branch.</p>
                <div>
                    <label for="managerName" class="text-xs font-semibold text-gray-600">Manager Name *</label>
                    <input id="managerName" name="manager_name" type="text" maxlength="100" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                    <p class="hidden text-xs text-red-600 mt-1" data-error-for="manager_name"></p>
                </div>
                <div>
                    <label for="managerEmail" class="text-xs font-semibold text-gray-600">Manager Email *</label>
                    <input id="managerEmail" name="manager_email" type="email" maxlength="100" required autocomplete="off" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                    <p class="hidden text-xs text-red-600 mt-1" data-error-for="manager_email"></p>
                </div>
                <div>
                    <label for="managerPassword" class="text-xs font-semibold text-gray-600">Initial Password *</label>
                    <input id="managerPassword" name="manager_password" type="password" minlength="10" maxlength="72" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                    <p class="text-xs text-gray-400 mt-1">At least 10 characters. Share it with the manager securely.</p>
                    <p class="hidden text-xs text-red-600 mt-1" data-error-for="manager_password"></p>
                </div>
            </fieldset>

            <p id="createBranchError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>

            <div class="flex justify-end gap-3 pt-2 border-t border-gray-200">
                <button type="button" id="cancelBranchBtn" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" id="submitBranchBtn" class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Create Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ BRANCH MANAGER MODAL ============ -->
<div id="managerModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" aria-labelledby="managerTitle">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-lg" id="managerTitle">Branch Manager</h3>
            <button type="button" id="managerCloseBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="px-5 py-4 space-y-3 overflow-y-auto" id="managerBody"></div>
    </div>
</div>

<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/all_branches.js"></script>
</body>
</html>
