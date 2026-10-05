/**
 * students.js — all-branch student directory. Filtering and pagination run on the
 * server (get_students.php); this file only builds the query and renders the result.
 */

const STUDENT_COLS = 9;
const studentState = { page: 1, perPage: 10 };

function studentFilters() {
    return {
        branch: el('branchFilter').value || 'all',
        course: el('courseFilter').value,
        status: el('statusFilter').value,
        search: el('searchInput').value.trim(),
        page: studentState.page,
        per_page: studentState.perPage,
    };
}

async function loadStudentOptions() {
    try {
        const meta = await Api.getMeta();
        el('branchFilter').insertAdjacentHTML('beforeend',
            meta.data.branches.map((b) => `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`).join(''));
        el('courseFilter').insertAdjacentHTML('beforeend',
            meta.data.courses.map((c) => `<option value="${escapeHtml(c.id)}">${escapeHtml(c.course_name)}</option>`).join(''));
    } catch (err) {
        showToast('Could not load filter options: ' + err.message, 'error');
    }
}

async function loadStudentRows() {
    const tbody = el('studentsBody');
    renderSkeletonRows(tbody, STUDENT_COLS, 6);
    try {
        const res = await Api.getStudents(studentFilters());
        renderStudentRows(res.data);
        renderPagination({
            pagination: res.pagination,
            infoId: 'paginationInfo',
            pageId: 'paginationPage',
            prevId: 'prevPageBtn',
            nextId: 'nextPageBtn',
            onChange: (page) => {
                studentState.page = page;
                loadStudentRows();
            },
        });
    } catch (err) {
        renderErrorRow(tbody, STUDENT_COLS, err.message, loadStudentRows);
    }
}

function renderStudentRows(students) {
    const tbody = el('studentsBody');
    if (students.length === 0) {
        renderEmptyRow(tbody, STUDENT_COLS, 'No students match these filters.');
        return;
    }

    tbody.innerHTML = students.map((s) => `
        <tr class="hover:bg-gray-50 cursor-pointer" data-student-row="${escapeHtml(s.id)}">
            <td class="px-4 py-3">
                <p class="font-semibold">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</p>
                <p class="text-xs text-gray-500">${escapeHtml(s.phone)}</p>
                <p class="text-xs text-gray-400 font-mono">${escapeHtml(s.student_code)}</p>
            </td>
            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${escapeHtml(s.branch_name)}</span></td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(s.course_name)}</td>
            <td class="px-4 py-3">${addressCell(s.address)}</td>
            <td class="px-4 py-3 text-right">${currency(s.total_fee)}</td>
            <td class="px-4 py-3 text-right text-green-600">${currency(s.amount_paid)}</td>
            <td class="px-4 py-3 text-right text-orange-500">${currency(s.balance_due)}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status] || ''}">${escapeHtml(s.status)}</span></td>
            <td class="px-4 py-3">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    ${studentActionButtons(s)}
                    <button type="button" data-branch-open="${escapeHtml(s.branch_id)}"
                            class="text-xs font-semibold text-red-600 hover:underline whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded">Open Branch</button>
                </div>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('[data-branch-open]').forEach((btn) => {
        btn.addEventListener('click', () => openBranchDashboard(Number(btn.dataset.branchOpen)));
    });
    bindStudentActions(tbody, students);
    bindAddressButtons(tbody);

    tbody.querySelectorAll('tr[data-student-row]').forEach((row) => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('button, a, select, input')) return;
            openStudentProfile(row.dataset.studentRow);
        });
    });
}

document.addEventListener('student:changed', () => loadStudentRows());

function resetAndLoad() {
    studentState.page = 1;
    loadStudentRows();
}

el('branchFilter').addEventListener('change', resetAndLoad);
el('courseFilter').addEventListener('change', resetAndLoad);
el('statusFilter').addEventListener('change', resetAndLoad);
el('searchInput').addEventListener('input', debounce(resetAndLoad, 350));

loadStudentOptions().then(loadStudentRows);
