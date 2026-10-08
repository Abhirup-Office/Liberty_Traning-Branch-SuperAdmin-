<?php
/**
 * Branch Admin Dashboard.
 * Reachable by branch_admin (locked to their own branch) and super_admin
 * (free to browse any branch).
 */

declare(strict_types=1);
require __DIR__ . '/../api/auth_check.php';

$admin = require_login(['branch_admin', 'super_admin']);
$initials = strtoupper(substr($admin['name'], 0, 1) . substr(strrchr($admin['name'], ' ') ?: '', 1, 1));
$roleLabel = $admin['role'] === 'super_admin' ? 'Super Admin' : 'Branch Admin';

// Branch the page is scoped to: a Branch Admin's own branch, or the branch a
// Super Admin chose to view. Null means a Super Admin browsing all campuses.
$viewBranchId = current_view_branch_id($admin);
$scopedBranchId = $admin['role'] === 'branch_admin' ? $admin['branch_id'] : $viewBranchId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Branch Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">

<?php if ($viewBranchId !== null): ?>
<!-- ============ SUPER ADMIN VIEWING CONTEXT ============ -->
<div class="bg-red-50 border-b border-red-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm">
        <p class="text-red-700">Viewing branch as Super Admin: <span id="viewBranchName" class="font-semibold">—</span></p>
        <button id="backToOverviewBtn" type="button" class="font-semibold text-red-600 hover:underline text-left">← Back to Super Admin Overview</button>
    </div>
</div>
<?php endif; ?>

<?php
$pageTitle = 'Dashboard';
$activeNav = $admin['role'] === 'super_admin' ? 'branch_view' : 'dashboard';
$headerActions = '';
require __DIR__ . '/../templates/super_layout_top.php';
?>

    <!-- ============ CAMPUS SWITCHER ============ -->
    <section>
        <h2 class="text-sm font-semibold text-gray-500 mb-2">Select Campus</h2>
        <p class="text-xs text-gray-500 mb-2" id="campusIndicator">Kolkata HQ Campus</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" id="campusSwitcher"></div>
    </section>

    <!-- ============ KPI SUMMARY ============ -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Total Enrolled</p>
            <p class="text-3xl font-extrabold mt-1" id="kpiEnrolled">0</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Fees Collected</p>
            <p class="text-3xl font-extrabold mt-1 text-green-600" id="kpiCollected">₹0</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Fees Pending</p>
            <p class="text-3xl font-extrabold mt-1 text-orange-500" id="kpiPending">₹0</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <p class="text-sm text-red-700">Overdue Alert</p>
                <p class="text-3xl font-extrabold mt-1 text-red-600" id="kpiOverdue">0</p>
            </div>
            <button class="mt-3 text-xs font-semibold bg-red-600 text-white rounded-lg px-3 py-2 hover:bg-red-700 self-start">Send WhatsApp Reminders</button>
        </div>
    </section>

    <!-- ============ ACTION TOOLBAR ============ -->
    <section class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center gap-3">
        <button id="addStudentBtn" class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm rounded-lg px-4 py-2.5 whitespace-nowrap">+ Add New Student</button>
        <input id="searchInput" type="text" placeholder="Search by name or phone..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        <select id="courseFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            <option value="">All Courses</option>
        </select>
        <select id="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            <option value="">All Payment Status</option>
            <option value="Paid in Full">Paid in Full</option>
            <option value="Partial">Partial</option>
            <option value="Pending">Pending</option>
        </select>
    </section>

    <!-- ============ MAIN SPLIT LAYOUT ============ -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Student Directory -->
        <div id="studentDirectory" class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden scroll-mt-24">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold">Student Directory</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-3">Student</th>
                            <th class="text-left px-4 py-3">Course</th>
                            <th class="text-left px-4 py-3">Address</th>
                            <th class="text-right px-4 py-3">Total Fee</th>
                            <th class="text-right px-4 py-3">Paid</th>
                            <th class="text-right px-4 py-3">Balance</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Last Payment</th>
                            <th class="text-center px-4 py-3">Certificate</th>
                            <th class="text-center px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 text-sm">
                <p class="text-gray-500" id="paginationInfo">Showing 0 of 0</p>
                <div class="flex items-center gap-2">
                    <button id="prevPageBtn" class="border border-gray-300 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                    <span class="text-gray-500" id="paginationPage">Page 1 of 1</span>
                    <button id="nextPageBtn" class="border border-gray-300 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>

        <!-- Quick Fee Counter -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 h-fit sticky top-20">
            <h2 class="font-bold mb-3">Quick Fee Counter</h2>
            <div id="feeCounterEmpty" class="text-sm text-gray-400 py-8 text-center">
                Select a student from the table to record a payment.
            </div>
            <div id="feeCounterPanel" class="hidden space-y-4">
                <div>
                    <p class="text-xs text-gray-500">Student</p>
                    <p class="font-semibold" id="fcStudentName">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Balance Due</p>
                    <p class="font-bold text-orange-500 text-xl" id="fcBalance">₹0</p>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Amount</label>
                    <input id="fcAmount" type="number" min="1" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" placeholder="Enter amount" />
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Payment Mode</label>
                    <div class="grid grid-cols-3 gap-2" id="fcPaymentModes">
                        <button data-mode="Cash" class="payment-mode-btn border border-gray-300 rounded-lg py-2 text-xs font-semibold">Cash</button>
                        <button data-mode="UPI" class="payment-mode-btn border border-gray-300 rounded-lg py-2 text-xs font-semibold">UPI</button>
                        <button data-mode="Bank" class="payment-mode-btn border border-gray-300 rounded-lg py-2 text-xs font-semibold">Bank</button>
                    </div>
                </div>
                <button id="recordPaymentBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold text-sm rounded-lg py-2.5">Record Payment</button>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/../templates/super_layout_bottom.php'; ?>

<!-- ============ ADD STUDENT MODAL ============ -->
<div id="addStudentModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-lg">Add New Student</h3>
            <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form id="addStudentForm" class="px-5 py-4 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Full Name *</label>
                    <input name="full_name" required type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" placeholder="e.g. Rohan Verma" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Phone Number *</label>
                    <input name="phone" required type="tel" pattern="[0-9]{10}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" placeholder="10-digit number" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Father's Name</label>
                    <input name="father_name" type="text" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Mother's Name</label>
                    <input name="mother_name" type="text" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Date of Birth</label>
                    <input name="date_of_birth" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Address</label>
                    <textarea name="address" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" placeholder="House no., street, area, city, PIN"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Enrolled Course *</label>
                    <select name="course_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" id="modalCourseSelect"></select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Course Fee *</label>
                    <input name="total_fee" required type="number" min="1" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                    <p class="text-xs text-gray-400 mt-1">Fills in from the selected course — edit it to set a different fee for this student.</p>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Initial Payment</label>
                    <input name="amount_paid" type="number" min="0" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1" />
                </div>
            </div>
            <p id="modalError" class="hidden text-sm text-red-600"></p>
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-200">
                <button type="button" id="cancelModalBtn" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ EDIT STUDENT MODAL ============ -->
<div id="editStudentModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" role="dialog" aria-modal="true" aria-labelledby="editStudentTitle">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <div>
                <h3 class="font-bold text-lg" id="editStudentTitle">Edit Student</h3>
                <p class="text-xs text-gray-500 font-mono" id="editStudentCode"></p>
            </div>
            <button type="button" id="closeEditStudentBtn" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <form id="editStudentForm" novalidate class="px-5 py-4 space-y-4 overflow-y-auto">
            <div>
                <label for="editFullName" class="text-xs font-semibold text-gray-600">Full Name *</label>
                <input id="editFullName" name="full_name" type="text" required maxlength="161" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                <p class="hidden text-xs text-red-600 mt-1" data-error-for="full_name"></p>
            </div>
            <div>
                <label for="editPhone" class="text-xs font-semibold text-gray-600">Phone Number *</label>
                <input id="editPhone" name="phone" type="tel" required pattern="[0-9]{10}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="10-digit number" />
                <p class="hidden text-xs text-red-600 mt-1" data-error-for="phone"></p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="editFatherName" class="text-xs font-semibold text-gray-600">Father's Name</label>
                    <input id="editFatherName" name="father_name" type="text" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
                <div>
                    <label for="editMotherName" class="text-xs font-semibold text-gray-600">Mother's Name</label>
                    <input id="editMotherName" name="mother_name" type="text" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
            </div>
            <div>
                <label for="editDob" class="text-xs font-semibold text-gray-600">Date of Birth</label>
                <input id="editDob" name="date_of_birth" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                <p class="hidden text-xs text-red-600 mt-1" data-error-for="date_of_birth"></p>
            </div>
            <div>
                <label for="editAddress" class="text-xs font-semibold text-gray-600">Address</label>
                <textarea id="editAddress" name="address" rows="3" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="House no., street, area, city, PIN"></textarea>
                <p class="hidden text-xs text-red-600 mt-1" data-error-for="address"></p>
            </div>
            <p id="editStudentError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-200">
                <button type="button" id="cancelEditStudentBtn" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" id="saveEditStudentBtn" class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../templates/student_profile_modal.php'; ?>
<?php require __DIR__ . '/../templates/address_modal.php'; ?>
<?php require __DIR__ . '/../templates/student_certificate_modal.php'; ?>

<script>
    // Branch the page is locked to (Branch Admin's branch, or a Super Admin's viewing branch);
    // null means a Super Admin browsing all campuses. Always derived from the PHP session.
    const SESSION_ROLE = <?= json_encode($admin['role']) ?>;
    const SESSION_BRANCH_ID = <?= json_encode($scopedBranchId) ?>;
</script>
<script src="js/api.js"></script>
<script src="js/toast.js"></script>
<script src="js/shared.js"></script>
<script src="js/auth-session.js"></script>
<script src="js/main.js"></script>
</body>
</html>
