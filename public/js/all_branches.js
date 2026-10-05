/**
 * all_branches.js — All Branches page: campus table with search, Open Branch,
 * and the Create Branch modal (which also creates the Branch Manager account).
 */

const COLS = 8;
const branchState = { rows: [] };

// ---------------- Table ----------------

async function loadBranches() {
    const tbody = el('branchesBody');
    renderSkeletonRows(tbody, COLS, 3);
    try {
        const meta = await Api.getMeta();
        const perfById = new Map(meta.data.performance.map((p) => [p.id, p]));
        branchState.rows = meta.data.branches.map((b) => {
            const perf = perfById.get(b.id) || { student_count: 0, collected: 0, pending: 0 };
            const collected = Number(perf.collected);
            const pending = Number(perf.pending);
            const total = collected + pending;
            return {
                id: b.id,
                name: b.name,
                manager: b.manager_name,
                location: b.location,
                students: Number(perf.student_count),
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
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <button type="button" data-manager-id="${r.id}" data-manager-name="${escapeHtml(r.name)}"
                            class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-2 whitespace-nowrap hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">View Manager</button>
                    <button type="button" data-open-id="${r.id}"
                            class="text-xs font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg px-3 py-2 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Open Branch</button>
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

loadBranches();
