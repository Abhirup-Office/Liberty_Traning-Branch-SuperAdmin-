/**
 * all_branches.js — All Branches page: campus table with search, Open Branch,
 * and the Create Branch modal (which also creates the Branch Manager account).
 */

const COLS = 9;
const branchState = { rows: [] };

// ---------------- Table ----------------

async function loadBranches() {
    const tbody = el('branchesBody');
    renderSkeletonRows(tbody, COLS, 3);
    try {
        // The management table needs every branch regardless of status (so a deactivated one
        // can still be found and reactivated), unlike api/get_meta.php's branch list, which is
        // Active-only and feeds dropdowns elsewhere.
        const res = await Api.getAllBranches();
        branchState.rows = res.data.branches.map((b) => {
            const collected = Number(b.collected);
            const pending = Number(b.pending);
            const total = collected + pending;
            return {
                id: b.id,
                name: b.name,
                manager: b.manager_name,
                location: b.location,
                status: b.status,
                students: Number(b.student_count),
                collected,
                pending,
                pct: total > 0 ? Math.round((collected / total) * 100) : 0,
            };
        });
        renderBranches();
    } catch (err) {
        renderErrorRow(tbody, COLS, err.message, loadBranches);
    }
}

function renderBranches() {
    const tbody = el('branchesBody');
    const q = el('searchInput').value.trim().toLowerCase();
    const rows = branchState.rows.filter((r) =>
        !q || [r.name, r.manager, r.location].some((v) => String(v).toLowerCase().includes(q))
    );

    if (rows.length === 0) {
        renderEmptyRow(tbody, COLS, branchState.rows.length === 0 ? 'No branches found.' : 'No branches match your search.');
        return;
    }

    tbody.innerHTML = rows.map((r) => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-semibold">${escapeHtml(r.name)}</td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(r.manager)}</td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(r.location)}</td>
            <td class="px-4 py-3 text-right">${r.students}</td>
            <td class="px-4 py-3 text-right text-green-600">${currency(r.collected)}</td>
            <td class="px-4 py-3 text-right text-orange-500">${currency(r.pending)}</td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-24 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-red-600 h-2 rounded-full" style="width:${r.pct}%"></div>
                    </div>
                    <span class="text-xs text-gray-500">${r.pct}%</span>
                </div>
            </td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 rounded-full text-xs font-semibold ${r.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'}">${escapeHtml(r.status)}</span>
            </td>
            <td class="px-4 py-3 text-center">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <button type="button" data-manager-id="${r.id}" data-manager-name="${escapeHtml(r.name)}"
                            class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-2 whitespace-nowrap hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">View Manager</button>
                    <button type="button" data-reassign-id="${r.id}" data-reassign-name="${escapeHtml(r.name)}" data-reassign-manager="${escapeHtml(r.manager)}"
                            class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-2 whitespace-nowrap hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Change Admin</button>
                    <button type="button" data-open-id="${r.id}"
                            class="text-xs font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Open Branch</button>
                    ${r.status === 'Active'
                        ? `<button type="button" data-deactivate-id="${r.id}" data-deactivate-name="${escapeHtml(r.name)}"
                                class="text-xs font-semibold text-red-600 border border-red-200 rounded-lg px-2.5 py-2 whitespace-nowrap hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Delete Branch</button>`
                        : `<button type="button" data-activate-id="${r.id}" data-activate-name="${escapeHtml(r.name)}"
                                class="text-xs font-semibold text-green-700 border border-green-200 rounded-lg px-2.5 py-2 whitespace-nowrap hover:bg-green-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500">Reactivate</button>`}
                </div>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('[data-open-id]').forEach((btn) => {
        btn.addEventListener('click', () => openBranchDashboard(Number(btn.dataset.openId)));
    });
    tbody.querySelectorAll('[data-manager-id]').forEach((btn) => {
        btn.addEventListener('click', () => openManagerModal(Number(btn.dataset.managerId), btn.dataset.managerName));
    });
    tbody.querySelectorAll('[data-reassign-id]').forEach((btn) => {
        btn.addEventListener('click', () => openReassignModal(Number(btn.dataset.reassignId), btn.dataset.reassignName, btn.dataset.reassignManager));
    });
    tbody.querySelectorAll('[data-deactivate-id]').forEach((btn) => {
        btn.addEventListener('click', () => changeBranchStatus(Number(btn.dataset.deactivateId), btn.dataset.deactivateName, 'Inactive'));
    });
    tbody.querySelectorAll('[data-activate-id]').forEach((btn) => {
        btn.addEventListener('click', () => changeBranchStatus(Number(btn.dataset.activateId), btn.dataset.activateName, 'Active'));
    });
}

// ---------------- Delete / reactivate branch ----------------

async function changeBranchStatus(id, name, status) {
    const verb = status === 'Inactive' ? 'delete' : 'reactivate';
    const warning = status === 'Inactive'
        ? `Are you sure you want to delete "${name}"? This deactivates the branch — it will disappear from active branch lists and its manager will no longer be able to log in. Existing students, courses and payment history are kept and are not affected.`
        : `Are you sure you want to reactivate "${name}"? It will reappear in active branch lists and its manager will be able to log in again.`;
    if (!confirm(warning)) return;
    try {
        await Api.setBranchStatus(id, status);
        showToast(`Branch ${verb}d.`, 'success');
        await loadBranches();
    } catch (err) {
        showToast('Could not change branch status: ' + err.message, 'error');
    }
}

// ---------------- Branch Manager modal ----------------

function closeManagerModal() {
    el('managerModal').classList.add('hidden');
}

async function openManagerModal(branchId, branchName) {
    const modal = el('managerModal');
    const body = el('managerBody');
    el('managerTitle').textContent = `Branch Manager · ${branchName}`;
    body.innerHTML = '<p class="text-sm text-gray-400">Loading...</p>';
    modal.classList.remove('hidden');
    el('managerCloseBtn').focus();

    try {
        const res = await Api.getBranchManagers(branchId);
        const { branch, managers } = res.data;
        if (managers.length === 0) {
            body.innerHTML = '<p class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">No Branch Manager Assigned</p>';
            return;
        }
        body.innerHTML = managers.map((m) => `
            <dl class="grid grid-cols-2 gap-3 text-sm border border-gray-200 rounded-lg p-4">
                <div class="col-span-2"><dt class="text-xs text-gray-500">Manager Name</dt><dd class="font-bold text-base">${escapeHtml(m.name)}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-gray-500">Email</dt><dd class="font-semibold break-all">${escapeHtml(m.email)}</dd></div>
                <div><dt class="text-xs text-gray-500">Branch</dt><dd class="font-semibold">${escapeHtml(branch.name)}</dd></div>
                <div><dt class="text-xs text-gray-500">Location</dt><dd class="font-semibold">${escapeHtml(branch.location)}</dd></div>
                <div><dt class="text-xs text-gray-500">Account Created</dt><dd class="font-semibold">${new Date(m.created_at).toLocaleDateString('en-IN')}</dd></div>
                <div><dt class="text-xs text-gray-500">Account ID</dt><dd class="font-semibold">${escapeHtml(m.id)}</dd></div>
            </dl>`).join('');
    } catch (err) {
        body.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(err.message)}</p>`;
    }
}

el('managerCloseBtn').addEventListener('click', closeManagerModal);
el('managerModal').addEventListener('click', (e) => {
    if (e.target === el('managerModal')) closeManagerModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeManagerModal();
});

// ---------------- Create Branch modal ----------------

const branchForm = el('createBranchForm');
const submitBtn = el('submitBranchBtn');
const submitLabel = submitBtn.textContent;

function openBranchModal() {
    el('createBranchModal').classList.remove('hidden');
    el('branchName').focus();
}

function closeBranchModal() {
    if (submitBtn.disabled) return; // do not dismiss while a save is in flight
    el('createBranchModal').classList.add('hidden');
    resetBranchForm();
}

function resetBranchForm() {
    branchForm.reset();
    clearBranchErrors();
}

function clearBranchErrors() {
    el('createBranchError').classList.add('hidden');
    branchForm.querySelectorAll('[data-error-for]').forEach((p) => {
        p.classList.add('hidden');
        p.textContent = '';
    });
    branchForm.querySelectorAll('input').forEach((i) => i.removeAttribute('aria-invalid'));
}

function showFieldError(field, message) {
    const input = branchForm.elements[field];
    const p = branchForm.querySelector(`[data-error-for="${field}"]`);
    if (input) input.setAttribute('aria-invalid', 'true');
    if (p) {
        p.textContent = message;
        p.classList.remove('hidden');
    }
}

function validateBranchForm(values) {
    let valid = true;
    const checks = [
        ['name', values.name.length > 0 && values.name.length <= 100, 'Branch name is required.'],
        ['location', values.location.length > 0 && values.location.length <= 150, 'Location is required.'],
        ['manager_name', values.manager_name.length > 0 && values.manager_name.length <= 100, 'Manager name is required.'],
        ['manager_email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.manager_email) && values.manager_email.length <= 100, 'Enter a valid email address.'],
        ['manager_password', values.manager_password.length >= 10 && values.manager_password.length <= 72, 'Password must be 10 to 72 characters.'],
    ];
    checks.forEach(([field, ok, message]) => {
        if (!ok) {
            showFieldError(field, message);
            valid = false;
        }
    });
    return valid;
}

function setSaving(isSaving) {
    submitBtn.disabled = isSaving;
    submitBtn.textContent = isSaving ? 'Creating…' : submitLabel;
    branchForm.setAttribute('aria-busy', String(isSaving));
}

async function handleCreateBranch(e) {
    e.preventDefault();
    if (submitBtn.disabled) return;
    clearBranchErrors();

    const values = {
        name: branchForm.elements.name.value.trim(),
        location: branchForm.elements.location.value.trim(),
        manager_name: branchForm.elements.manager_name.value.trim(),
        manager_email: branchForm.elements.manager_email.value.trim(),
        manager_password: branchForm.elements.manager_password.value,
    };

    if (!validateBranchForm(values)) return;

    setSaving(true);
    try {
        const res = await Api.createBranch(values);
        el('createBranchModal').classList.add('hidden');
        resetBranchForm();
        showToast(`Branch "${res.data.name}" created. Manager login: ${res.data.manager_email}`, 'success');
        await loadBranches();
    } catch (err) {
        const errorBox = el('createBranchError');
        errorBox.textContent = err.message;
        errorBox.classList.remove('hidden');
    } finally {
        setSaving(false);
    }
}

el('createBranchBtn').addEventListener('click', openBranchModal);
el('closeBranchModalBtn').addEventListener('click', closeBranchModal);
el('cancelBranchBtn').addEventListener('click', closeBranchModal);
el('createBranchModal').addEventListener('click', (e) => {
    if (e.target === el('createBranchModal')) closeBranchModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !el('createBranchModal').classList.contains('hidden')) closeBranchModal();
});
branchForm.addEventListener('submit', handleCreateBranch);

el('searchInput').addEventListener('input', debounce(renderBranches, 200));

// The Overview's "+ New Branch" link lands here with ?new=1.
if (new URLSearchParams(window.location.search).get('new') === '1') {
    openBranchModal();
}

// ---------------- Change Branch Admin modal ----------------

const reassignForm = el('reassignForm');
const reassignSubmitBtn = el('reassignSubmitBtn');
let reassignBranchId = null;

function closeReassignModal() {
    if (reassignSubmitBtn.disabled) return;
    el('reassignModal').classList.add('hidden');
    clearReassignErrors();
    reassignForm.reset();
}

function clearReassignErrors() {
    el('reassignError').classList.add('hidden');
    reassignForm.querySelectorAll('[data-error-for]').forEach((p) => {
        p.classList.add('hidden');
        p.textContent = '';
    });
    reassignForm.querySelectorAll('input').forEach((i) => i.removeAttribute('aria-invalid'));
}

function showReassignFieldError(field, message) {
    const input = reassignForm.elements[field];
    const p = reassignForm.querySelector(`[data-error-for="${field}"]`);
    if (input) input.setAttribute('aria-invalid', 'true');
    if (p) {
        p.textContent = message;
        p.classList.remove('hidden');
    }
}

function openReassignModal(branchId, branchName, currentManager) {
    reassignBranchId = branchId;
    el('reassignBranchName').textContent = branchName;
    el('reassignCurrentManager').textContent = currentManager || 'No Branch Manager Assigned';
    clearReassignErrors();
    reassignForm.reset();
    el('reassignModal').classList.remove('hidden');
    el('reassignManagerName').focus();
}

function validateReassignForm(values) {
    let valid = true;
    const checks = [
        ['manager_name', values.manager_name.length > 0 && values.manager_name.length <= 100, 'Manager name is required.'],
        ['manager_email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.manager_email) && values.manager_email.length <= 100, 'Enter a valid email address.'],
        ['manager_password', values.manager_password.length >= 10 && values.manager_password.length <= 72, 'Password must be 10 to 72 characters.'],
    ];
    checks.forEach(([field, ok, message]) => {
        if (!ok) {
            showReassignFieldError(field, message);
            valid = false;
        }
    });
    return valid;
}

async function handleReassign(e) {
    e.preventDefault();
    if (reassignSubmitBtn.disabled) return;
    clearReassignErrors();

    const values = {
        branch_id: reassignBranchId,
        manager_name: reassignForm.elements.manager_name.value.trim(),
        manager_email: reassignForm.elements.manager_email.value.trim(),
        manager_password: reassignForm.elements.manager_password.value,
    };

    if (!validateReassignForm(values)) return;

    reassignSubmitBtn.disabled = true;
    reassignSubmitBtn.textContent = 'Saving…';
    try {
        const res = await Api.changeBranchAdmin(values);
        el('reassignModal').classList.add('hidden');
        reassignForm.reset();
        showToast(`Branch manager updated. New login: ${res.data.manager_email}`, 'success');
        await loadBranches();
    } catch (err) {
        const errorBox = el('reassignError');
        errorBox.textContent = err.message;
        errorBox.classList.remove('hidden');
    } finally {
        reassignSubmitBtn.disabled = false;
        reassignSubmitBtn.textContent = 'Save';
    }
}

el('reassignCloseBtn').addEventListener('click', closeReassignModal);
el('reassignCancelBtn').addEventListener('click', closeReassignModal);
el('reassignModal').addEventListener('click', (e) => {
    if (e.target === el('reassignModal')) closeReassignModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !el('reassignModal').classList.contains('hidden')) closeReassignModal();
});
reassignForm.addEventListener('submit', handleReassign);

loadBranches();
