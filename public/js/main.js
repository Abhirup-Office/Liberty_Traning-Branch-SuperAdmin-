/**
 * main.js — Branch Admin Dashboard logic.
 * Modular fetch/render functions, server-side filtering + pagination,
 * toast notifications, and a lightweight loading state for the table.
 */

const state = {
    branches: [],
    courses: [],
    students: [],
    activeBranchId: null,
    selectedStudent: null,
    selectedPaymentMode: null,
    pagination: { page: 1, per_page: 10, total: 0, total_pages: 1 },
};

const AVATAR_COLORS = ['bg-red-100 text-red-700', 'bg-blue-100 text-blue-700', 'bg-green-100 text-green-700', 'bg-purple-100 text-purple-700', 'bg-orange-100 text-orange-700'];

function initialsOf(first, last) {
    return `${(first || '?')[0]}${(last || '')[0] || ''}`.toUpperCase();
}

function avatarColorFor(id) {
    return AVATAR_COLORS[id % AVATAR_COLORS.length];
}

// ---------------- Bootstrap ----------------

async function init() {
    try {
        const meta = await Api.getMeta();
        state.branches = meta.data.branches;
        state.courses = meta.data.courses;

        // A branch_admin's branch comes from the PHP session (see branch_dashboard.php) —
        // never from anything the client could tamper with. The backend enforces this
        // independently on every request regardless of what we set here.
        state.activeBranchId = typeof SESSION_BRANCH_ID !== 'undefined' && SESSION_BRANCH_ID !== null
            ? SESSION_BRANCH_ID
            : state.branches[0]?.id ?? null;

        renderCampusSwitcher();
        populateCourseFilters();
        wireViewBanner();
        await Promise.all([fetchStudents(), updateKpis()]);
    } catch (err) {
        console.error(err);
        showToast('Failed to initialize dashboard: ' + err.message, 'error');
    }
}

function renderCampusSwitcher() {
    const container = el('campusSwitcher');
    container.innerHTML = state.branches.map((b) => `
        <button data-branch-id="${b.id}" class="campus-card text-left border rounded-xl p-4 transition ${
            b.id === state.activeBranchId
                ? 'border-red-600 bg-red-50 ring-1 ring-red-600'
                : 'border-gray-200 bg-white hover:border-red-300'
        }">
            <p class="font-bold">${escapeHtml(b.name)}</p>
            <p class="text-xs text-gray-500 mt-1">${escapeHtml(b.manager_name)} · ${escapeHtml(b.location)}</p>
        </button>
    `).join('');

    // Switching campuses is only allowed when no branch is in scope. A Branch Admin
    // or a Super Admin viewing a branch is locked to SESSION_BRANCH_ID.
    if (typeof SESSION_BRANCH_ID === 'undefined' || SESSION_BRANCH_ID === null) {
        container.querySelectorAll('.campus-card').forEach((btn) => {
            btn.addEventListener('click', async () => {
                state.activeBranchId = Number(btn.dataset.branchId);
                state.pagination.page = 1;
                const branch = state.branches.find((b) => b.id === state.activeBranchId);
                el('campusIndicator').textContent = `${branch.name} Campus`;
                renderCampusSwitcher();
                await Promise.all([fetchStudents(), updateKpis()]);
            });
        });
    }

    const activeBranch = state.branches.find((b) => b.id === state.activeBranchId);
    if (activeBranch) el('campusIndicator').textContent = `${activeBranch.name} Campus`;
}

/** Super Admin viewing a branch: name the branch in the banner and wire "Back to Overview". */
function wireViewBanner() {
    if (SESSION_ROLE !== 'super_admin' || SESSION_BRANCH_ID === null) return;

    const branch = state.branches.find((b) => b.id === SESSION_BRANCH_ID);
    el('viewBranchName').textContent = branch ? branch.name : `Branch #${SESSION_BRANCH_ID}`;

    el('backToOverviewBtn').addEventListener('click', async () => {
        try {
            await Api.setViewBranch(null);
            window.location.href = 'super_admin_dashboard.php';
        } catch (err) {
            showToast('Could not return to overview: ' + err.message, 'error');
        }
    });
}

function populateCourseFilters() {
    const courseFilter = el('courseFilter');
    const modalSelect = el('modalCourseSelect');
    const courseOptions = state.courses.map((c) => `<option value="${c.id}">${escapeHtml(c.course_name)}</option>`).join('');
    courseFilter.insertAdjacentHTML('beforeend', courseOptions);
    modalSelect.innerHTML = courseOptions;
    modalSelect.addEventListener('change', syncCourseFee);
    syncCourseFee();
}

// The course's current fee is shown read-only; the server applies the same fee.
function syncCourseFee() {
    const course = state.courses.find((c) => String(c.id) === el('modalCourseSelect').value);
    el('addStudentForm').elements.total_fee.value = course ? course.total_fee : '';
}

document.addEventListener('student:changed', () => Promise.all([fetchStudents(), updateKpis()]));

// ---------------- KPIs ----------------

async function updateKpis() {
    const res = await Api.getKpis(state.activeBranchId);
    const k = res.data;
    el('kpiEnrolled').textContent = k.enrolled;
    el('kpiCollected').textContent = currency(k.collected);
    el('kpiPending').textContent = currency(k.pending);
    el('kpiOverdue').textContent = k.overdue_count;
}

// ---------------- Student table (fetch + render) ----------------

function renderTableSkeleton() {
    const tbody = el('studentTableBody');
    tbody.innerHTML = Array.from({ length: 5 }).map(() => `
        <tr class="animate-pulse">
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-32 mb-1"></div><div class="h-3 bg-gray-100 rounded w-20"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-28"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-16 ml-auto"></div></td>
            <td class="px-4 py-3"><div class="h-5 bg-gray-200 rounded-full w-20 mx-auto"></div></td>
            <td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-4 mx-auto"></div></td>
        </tr>
    `).join('');
}

async function fetchStudents() {
    renderTableSkeleton();
    try {
        const res = await Api.getStudents({
            branch: state.activeBranchId,
            status: el('statusFilter').value,
            course: el('courseFilter').value,
            search: el('searchInput').value.trim(),
            page: state.pagination.page,
            per_page: state.pagination.per_page,
        });
        state.students = res.data;
        state.pagination = res.pagination;
        renderStudentTable();
        renderBranchPagination();
    } catch (err) {
        console.error(err);
        showToast('Failed to load students: ' + err.message, 'error');
    }
}

function formatLastPayment(value) {
    if (!value) return '<span class="text-gray-400">No payment yet</span>';
    return new Date(String(value).replace(' ', 'T')).toLocaleDateString('en-IN');
}

function renderStudentTable() {
    const tbody = el('studentTableBody');

    if (state.students.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-gray-400 py-8">No students found.</td></tr>`;
        return;
    }

    tbody.innerHTML = state.students.map((s) => `
        <tr class="hover:bg-gray-50 cursor-pointer" data-student-id="${s.id}">
            <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${avatarColorFor(s.id)}">${escapeHtml(initialsOf(s.first_name, s.last_name))}</div>
                    <div>
                        <p class="font-semibold">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(s.phone)}</p>
                        <p class="text-xs text-gray-400 font-mono">${escapeHtml(s.display_id || s.student_code)}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(s.course_name)}</td>
            <td class="px-4 py-3">${addressCell(s.address)}</td>
            <td class="px-4 py-3 text-right">${currency(s.total_fee)}</td>
            <td class="px-4 py-3 text-right text-green-600">${currency(s.amount_paid)}</td>
            <td class="px-4 py-3 text-right text-orange-500">${currency(s.balance_due)}</td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status]}">${escapeHtml(s.status)}</span>
            </td>
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${formatLastPayment(s.last_payment_date)}</td>
            <td class="px-4 py-3 text-center">${certificateCellHtml(s)}</td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                <button type="button" data-profile-id="${s.id}" title="View profile" aria-label="View profile of ${escapeHtml(s.first_name)}" class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-1.5 mr-1 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">View Profile</button>
                ${needsReminder(s)
                    ? `<button type="button" data-remind-id="${s.id}" title="Send WhatsApp reminder" aria-label="Send WhatsApp reminder to ${escapeHtml(s.first_name)}" class="text-xs font-semibold text-green-700 border border-green-200 rounded-lg px-2.5 py-1.5 mr-1 hover:bg-green-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500">Send Reminder</button>`
                    : `<span class="text-xs font-semibold text-gray-400 border border-gray-200 rounded-lg px-2.5 py-1.5 mr-1">Paid · No reminder</span>`}
                <button data-edit-id="${s.id}" title="Edit student" aria-label="Edit ${escapeHtml(s.first_name)}" class="text-gray-400 hover:text-red-600 mr-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button data-delete-id="${s.id}" title="Delete" class="text-gray-400 hover:text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('tr[data-student-id]').forEach((row) => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('[data-delete-id], [data-edit-id], [data-profile-id], [data-remind-id], [data-cert-id]')) return;
            selectStudent(Number(row.dataset.studentId));
        });
    });

    bindAddressButtons(tbody);
    bindCertificateButtons(tbody);

    tbody.querySelectorAll('[data-edit-id]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            openEditStudent(Number(btn.dataset.editId));
        });
    });

    tbody.querySelectorAll('[data-profile-id]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            openStudentProfile(btn.dataset.profileId);
        });
    });

    tbody.querySelectorAll('[data-remind-id]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const student = state.students.find((st) => String(st.id) === btn.dataset.remindId);
            if (student) sendWhatsAppReminder(student);
        });
    });

    tbody.querySelectorAll('[data-delete-id]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleDelete(Number(btn.dataset.deleteId));
        });
    });
}

function renderBranchPagination() {
    const { page, total, total_pages: totalPages, per_page: perPage } = state.pagination;
    const start = total === 0 ? 0 : (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);

    el('paginationInfo').textContent = `Showing ${start}-${end} of ${total}`;
    el('paginationPage').textContent = `Page ${page} of ${totalPages}`;
    el('prevPageBtn').disabled = page <= 1;
    el('nextPageBtn').disabled = page >= totalPages;
}

el('prevPageBtn').addEventListener('click', async () => {
    if (state.pagination.page > 1) {
        state.pagination.page -= 1;
        await fetchStudents();
    }
});

el('nextPageBtn').addEventListener('click', async () => {
    if (state.pagination.page < state.pagination.total_pages) {
        state.pagination.page += 1;
        await fetchStudents();
    }
});

// ---------------- Quick Fee Counter ----------------

function selectStudent(id) {
    state.selectedStudent = state.students.find((s) => s.id === id) || null;
    state.selectedPaymentMode = null;
    renderFeeCounter();
}

function renderFeeCounter() {
    const s = state.selectedStudent;
    if (!s) {
        el('feeCounterEmpty').classList.remove('hidden');
        el('feeCounterPanel').classList.add('hidden');
        return;
    }
    el('feeCounterEmpty').classList.add('hidden');
    el('feeCounterPanel').classList.remove('hidden');
    el('fcStudentName').textContent = `${s.first_name} ${s.last_name}`;
    el('fcBalance').textContent = currency(s.balance_due);
    el('fcAmount').value = '';
    document.querySelectorAll('.payment-mode-btn').forEach((btn) => {
        btn.classList.remove('bg-red-600', 'text-white', 'border-red-600');
    });
}

// Live "remaining due" preview as the amount is typed.
el('fcAmount').addEventListener('input', () => {
    const s = state.selectedStudent;
    if (!s) return;
    const entered = Number(el('fcAmount').value) || 0;
    const remaining = Math.max(0, Number(s.balance_due) - entered);
    el('fcBalance').textContent = currency(remaining);
});

document.querySelectorAll('.payment-mode-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        state.selectedPaymentMode = btn.dataset.mode;
        document.querySelectorAll('.payment-mode-btn').forEach((b) => {
            b.classList.remove('bg-red-600', 'text-white', 'border-red-600');
        });
        btn.classList.add('bg-red-600', 'text-white', 'border-red-600');
    });
});

async function handlePayment() {
    const s = state.selectedStudent;
    const amount = Number(el('fcAmount').value);

    if (!s) return;
    if (!amount || amount <= 0) {
        showToast('Enter a valid amount.', 'error');
        return;
    }
    if (!state.selectedPaymentMode) {
        showToast('Select a payment mode.', 'error');
        return;
    }
    if (amount > Number(s.balance_due)) {
        showToast('Amount exceeds the remaining balance.', 'error');
        return;
    }

    try {
        const res = await Api.recordPayment({
            student_id: s.id,
            amount,
            payment_mode: state.selectedPaymentMode,
        });
        showToast(`Payment of ${currency(amount)} recorded for ${res.data.student_name}. Receipt: ${res.data.receipt_no}`, 'success');
        await Promise.all([fetchStudents(), updateKpis()]);
        state.selectedStudent = state.students.find((st) => st.id === s.id) || null;
        renderFeeCounter();
    } catch (err) {
        showToast('Failed to record payment: ' + err.message, 'error');
    }
}

el('recordPaymentBtn').addEventListener('click', handlePayment);

// ---------------- Delete ----------------

async function handleDelete(id) {
    if (!confirm('Delete this student? This cannot be undone.')) return;
    try {
        await Api.deleteStudent(id);
        showToast('Student deleted.', 'success');
        if (state.selectedStudent?.id === id) {
            state.selectedStudent = null;
            renderFeeCounter();
        }
        await Promise.all([fetchStudents(), updateKpis()]);
    } catch (err) {
        showToast('Failed to delete student: ' + err.message, 'error');
    }
}

// ---------------- Add Student Modal ----------------

function openModal() {
    el('addStudentModal').classList.remove('hidden');
    el('modalError').classList.add('hidden');
}

function closeModal() {
    el('addStudentModal').classList.add('hidden');
    el('addStudentForm').reset();
}

el('addStudentBtn').addEventListener('click', openModal);
el('closeModalBtn').addEventListener('click', closeModal);
el('cancelModalBtn').addEventListener('click', closeModal);
el('addStudentModal').addEventListener('click', (e) => {
    if (e.target === el('addStudentModal')) closeModal();
});

el('addStudentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);

    const fullName = String(form.get('full_name')).trim();
    const [firstName, ...rest] = fullName.split(' ');
    const lastName = rest.join(' ');

    const payload = {
        branch_id: state.activeBranchId,
        course_id: Number(form.get('course_id')),
        first_name: firstName,
        last_name: lastName || '',
        phone: String(form.get('phone')).trim(),
        address: String(form.get('address') ?? '').trim(),
        father_name: String(form.get('father_name') ?? '').trim(),
        mother_name: String(form.get('mother_name') ?? '').trim(),
        date_of_birth: String(form.get('date_of_birth') ?? '').trim(),
        total_fee: Number(form.get('total_fee')),
        amount_paid: Number(form.get('amount_paid')) || 0,
    };

    if (payload.amount_paid > payload.total_fee) {
        const errorEl = el('modalError');
        errorEl.textContent = 'Initial payment cannot exceed total fee.';
        errorEl.classList.remove('hidden');
        return;
    }

    try {
        const res = await Api.addStudent(payload);
        closeModal();
        showToast(`${payload.first_name} ${payload.last_name} added. Student ID: ${res.data.display_id}`, 'success');
        state.pagination.page = 1;
        await Promise.all([fetchStudents(), updateKpis()]);
    } catch (err) {
        const errorEl = el('modalError');
        errorEl.textContent = err.message;
        errorEl.classList.remove('hidden');
    }
});

// ---------------- Edit Student ----------------

const editState = { id: null, saving: false };

function showEditError(message) {
    const box = el('editStudentError');
    box.textContent = message;
    box.classList.remove('hidden');
}

function closeEditStudent() {
    if (editState.saving) return;
    el('editStudentModal').classList.add('hidden');
}

async function openEditStudent(id) {
    const form = el('editStudentForm');
    editState.id = id;
    form.reset();
    el('editStudentError').classList.add('hidden');
    el('editStudentCode').textContent = '';
    el('editStudentModal').classList.remove('hidden');
    try {
        const res = await Api.getStudent(id);
        const s = res.data.student;
        form.elements.full_name.value = `${s.first_name} ${s.last_name}`.trim();
        form.elements.phone.value = s.phone;
        form.elements.address.value = s.address || '';
        form.elements.father_name.value = s.father_name || '';
        form.elements.mother_name.value = s.mother_name || '';
        form.elements.date_of_birth.value = s.date_of_birth || '';
        el('editStudentCode').textContent = `Student ID ${s.display_id || s.student_code}`;
    } catch (err) {
        showEditError(err.message);
    }
}

el('editStudentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (editState.saving) return;

    const form = e.target;
    const [firstName, ...rest] = form.elements.full_name.value.trim().split(/\s+/);
    const payload = {
        id: editState.id,
        first_name: firstName || '',
        last_name: rest.join(' '),
        phone: form.elements.phone.value.trim(),
        address: form.elements.address.value.trim(),
        father_name: form.elements.father_name.value.trim(),
        mother_name: form.elements.mother_name.value.trim(),
        date_of_birth: form.elements.date_of_birth.value.trim(),
    };

    if (!payload.first_name) {
        showEditError('Full name is required.');
        return;
    }
    if (!/^[0-9]{10}$/.test(payload.phone)) {
        showEditError('Phone number must be exactly 10 digits.');
        return;
    }

    const btn = el('saveEditStudentBtn');
    editState.saving = true;
    btn.disabled = true;
    btn.textContent = 'Saving…';
    try {
        await Api.updateStudent(payload);
        el('editStudentModal').classList.add('hidden');
        showToast('Student updated successfully.', 'success');
        await Promise.all([fetchStudents(), updateKpis()]);
        if (state.selectedStudent?.id === payload.id) {
            selectStudent(payload.id);
        }
    } catch (err) {
        showEditError(err.message);
    } finally {
        editState.saving = false;
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    }
});

el('closeEditStudentBtn').addEventListener('click', closeEditStudent);
el('cancelEditStudentBtn').addEventListener('click', closeEditStudent);
el('editStudentModal').addEventListener('click', (e) => {
    if (e.target === el('editStudentModal')) closeEditStudent();
});

// ---------------- Filters ----------------

let searchDebounceTimer = null;
function onFilterChange() {
    state.pagination.page = 1;
    fetchStudents();
}

el('searchInput').addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(onFilterChange, 350);
});
el('courseFilter').addEventListener('change', onFilterChange);
el('statusFilter').addEventListener('change', onFilterChange);

init();
