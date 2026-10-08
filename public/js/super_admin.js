/**
 * super_admin.js — Super Admin Overview logic.
 * Global KPIs, clickable campus cards, the all-branch directory (with a branch
 * filter), and the recent fee-collections feed.
 */

const state = {
    branches: [],
    students: [],
    activeBranch: 'all',
};

const modeIcon = { UPI: '📲', Cash: '💵', Bank: '🏦' };

async function init() {
    renderSkeletonRows(el('allStudentsBody'), 10);
    try {
        const meta = await Api.getMeta();
        state.branches = meta.data.branches;
        el('gkpiBranches').textContent = state.branches.length;
        populateBranchFilter();
        renderCampusPerformance(meta.data.performance);

        await Promise.all([loadKpis(), loadStudents(), loadTransactions()]);
    } catch (err) {
        console.error(err);
        showToast('Failed to load super admin console: ' + err.message, 'error');
        renderErrorRow(el('allStudentsBody'), 10, err.message, init);
    }
}

function populateBranchFilter() {
    const select = el('branchFilter');
    state.branches.forEach((b) => {
        select.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`);
    });
    select.addEventListener('change', () => {
        state.activeBranch = select.value;
        loadStudents();
    });
}

async function loadKpis() {
    const res = await Api.getKpis('all');
    el('gkpiStudents').textContent = res.data.enrolled;
    el('gkpiCollected').textContent = currency(res.data.collected);
    el('gkpiPending').textContent = currency(res.data.pending);
}

async function loadStudents() {
    const tbody = el('allStudentsBody');
    renderSkeletonRows(tbody, 10);
    try {
        // Recently enrolled: newest first, latest 10. The full list lives on students.php.
        const res = await Api.getStudents({ branch: state.activeBranch, per_page: 10, page: 1 });
        state.students = res.data;
        renderAllStudents(state.students);
    } catch (err) {
        renderErrorRow(tbody, 10, err.message, loadStudents);
    }
}

document.addEventListener('student:changed', () => Promise.all([loadKpis(), loadStudents()]));

async function loadTransactions() {
    const container = el('transactionFeed');
    try {
        const res = await Api.getTransactions({ limit: 8 });
        renderTransactionFeed(res.data);
    } catch (err) {
        container.innerHTML = `<p class="text-sm text-red-600 text-center py-6">${escapeHtml(err.message)}</p>`;
    }
}

function renderCampusPerformance(performance) {
    const container = el('campusPerformance');
    container.innerHTML = performance.map((p) => {
        const collected = Number(p.collected);
        const pending = Number(p.pending);
        const total = collected + pending;
        const pct = total > 0 ? Math.round((collected / total) * 100) : 0;

        return `
        <button type="button" data-branch-open="${escapeHtml(p.id)}" aria-label="Open ${escapeHtml(p.name)} branch dashboard"
                class="block w-full text-left bg-white border border-gray-200 rounded-xl p-5 shadow-sm cursor-pointer transition hover:shadow-md hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 active:scale-[0.99]">
            <p class="font-bold text-lg">${escapeHtml(p.name)}</p>
            <p class="text-xs text-gray-500 mb-3">Manager: ${escapeHtml(p.manager_name)}</p>
            <p class="text-sm text-gray-600 mb-3">${escapeHtml(p.student_count)} students enrolled</p>
            <div class="flex justify-between text-xs mb-1">
                <span class="text-green-600 font-semibold">${currency(collected)} collected</span>
                <span class="text-orange-500 font-semibold">${currency(pending)} pending</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                <div class="bg-red-600 h-2.5 rounded-full" style="width:${pct}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">${pct}% collected</p>
        </button>`;
    }).join('');

    container.querySelectorAll('[data-branch-open]').forEach((btn) => {
        btn.addEventListener('click', () => openBranchDashboard(Number(btn.dataset.branchOpen)));
    });
}

function renderAllStudents(students) {
    const tbody = el('allStudentsBody');
    if (students.length === 0) {
        renderEmptyRow(tbody, 10, 'No students have been enrolled yet.');
        return;
    }

    tbody.innerHTML = students.map((s) => `
        <tr class="hover:bg-gray-50 cursor-pointer" data-student-row="${escapeHtml(s.id)}">
            <td class="px-4 py-3">
                <p class="font-semibold">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</p>
                <p class="text-xs text-gray-500">${escapeHtml(s.phone)}</p>
                <p class="text-xs text-gray-400 font-mono">${escapeHtml(s.display_id || s.student_code)}</p>
            </td>
            <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${escapeHtml(s.branch_name)}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(s.course_name)}</td>
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${new Date(s.created_at).toLocaleDateString('en-IN')}</td>
            <td class="px-4 py-3 text-right">${currency(s.total_fee)}</td>
            <td class="px-4 py-3 text-right text-green-600">${currency(s.amount_paid)}</td>
            <td class="px-4 py-3 text-right text-orange-500">${currency(s.balance_due)}</td>
            <td class="px-4 py-3">${addressCell(s.address)}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status] || ''}">${escapeHtml(s.status)}</span>
            </td>
            <td class="px-4 py-3">${studentActionButtons(s)}</td>
        </tr>
    `).join('');

    bindStudentActions(tbody, students);
    bindAddressButtons(tbody);

    tbody.querySelectorAll('tr[data-student-row]').forEach((row) => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('button, a, select, input')) return;
            openStudentProfile(row.dataset.studentRow);
        });
    });
}

function renderTransactionFeed(transactions) {
    const container = el('transactionFeed');
    if (transactions.length === 0) {
        container.innerHTML = `<p class="text-sm text-gray-400 text-center py-6">No transactions yet.</p>`;
        return;
    }

    container.innerHTML = transactions.map((t) => `
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-lg shrink-0">${modeIcon[t.payment_mode] || '💳'}</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate">${escapeHtml(t.first_name)} ${escapeHtml(t.last_name)} <span class="text-gray-400 font-normal">· ${escapeHtml(t.branch_name)}</span></p>
                <p class="text-xs text-gray-500">${escapeHtml(t.receipt_no)} · ${new Date(t.transaction_date).toLocaleString('en-IN')}</p>
            </div>
            <p class="text-sm font-bold text-green-600 whitespace-nowrap">+${currency(t.amount)}</p>
        </div>
    `).join('');
}

init();
