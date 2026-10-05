/**
 * shared.js — helpers used by every Super Admin page.
 * Loaded before the page script. Page scripts must not redeclare these names.
 */

const el = (id) => document.getElementById(id);

const currency = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0 });

const statusBadgeClass = {
    'Paid in Full': 'bg-green-100 text-green-700',
    'Partial': 'bg-orange-100 text-orange-700',
    'Pending': 'bg-red-100 text-red-700',
    'Overdue': 'bg-red-100 text-red-700',
};

/** Escapes text from the database before it is placed into innerHTML. */
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

const ICON_PROFILE = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`;

const ICON_WHATSAPP = `<img src="assets/whatsapp-icon.png" alt="" class="w-4 h-4 shrink-0" aria-hidden="true" />`;

/** Profile + WhatsApp reminder buttons for one student row. */
/** Outstanding = fee minus paid. Null, empty or invalid amounts count as zero. */
function outstandingOf(s) {
    const value = Number(s.total_fee || 0) - Number(s.amount_paid || 0);
    return Number.isFinite(value) ? Math.round(value * 100) / 100 : 0;
}

/** Only students who still owe money get a reminder. The server enforces the same rule. */
function needsReminder(s) {
    return outstandingOf(s) > 0;
}

function studentActionButtons(s) {
    const name = escapeHtml(`${s.first_name} ${s.last_name}`);
    const reminderButton = needsReminder(s) ? `
            <button type="button" data-remind-id="${escapeHtml(s.id)}" title="Send WhatsApp reminder" aria-label="Send WhatsApp reminder to ${name}"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 border border-green-200 rounded-lg px-2.5 py-1.5 hover:bg-green-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500">
                ${ICON_WHATSAPP}<span>Send Reminder</span>
            </button>` : '';
    return `
        <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
            <button type="button" data-profile-id="${escapeHtml(s.id)}" title="View profile" aria-label="View profile of ${name}"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                ${ICON_PROFILE}<span>View Profile</span>
            </button>${reminderButton}
        </div>`;
}

/** Wires the buttons rendered by studentActionButtons(). `students` is the rendered list. */
function bindStudentActions(container, students) {
    const byId = new Map(students.map((s) => [String(s.id), s]));
    container.querySelectorAll('[data-profile-id]').forEach((btn) => {
        btn.addEventListener('click', () => openStudentProfile(btn.dataset.profileId));
    });
    container.querySelectorAll('[data-remind-id]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const student = byId.get(btn.dataset.remindId);
            if (student) sendWhatsAppReminder(student);
        });
    });
}

/**
 * Asks the server to validate the outstanding balance and prepare the message, then opens
 * WhatsApp. No SMS/WhatsApp Business API is configured, so the reminder is a prefilled chat.
 */
async function sendWhatsAppReminder(student) {
    if (!needsReminder(student)) {
        showToast('Payment is already complete. No reminder is required.', 'info');
        return;
    }
    // Open the tab synchronously on the click so popup blockers allow it; navigate after approval.
    const tab = window.open('about:blank', '_blank');
    try {
        const res = await Api.requestReminder(student.id);
        const { phone, message } = res.data;
        const url = `https://wa.me/91${phone}?text=${encodeURIComponent(message)}`;
        if (tab) {
            tab.opener = null;
            tab.location.href = url;
        } else {
            window.location.href = url;
        }
        showToast(`WhatsApp reminder opened for ${student.first_name} ${student.last_name}.`, 'success');
    } catch (err) {
        if (tab) tab.close();
        showToast(err.message, 'info');
    }
}

function renderStudentProfile({ student: s, payments }) {
    const payRows = payments.length
        ? payments.map((p) => `
            <li class="flex items-center justify-between text-sm py-2 border-b border-gray-100 last:border-0">
                <span class="font-mono text-xs text-gray-500">${escapeHtml(p.receipt_no)}</span>
                <span class="text-gray-500 text-xs">${escapeHtml(p.payment_mode)} · ${new Date(p.transaction_date).toLocaleDateString('en-IN')}</span>
                <span class="font-semibold text-green-600">+${currency(p.amount)}</span>
            </li>`).join('')
        : '<li class="text-sm text-gray-400 py-2">No payments recorded yet.</li>';

    return `
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600">${escapeHtml((s.first_name[0] || '') + (s.last_name[0] || ''))}</div>
            <div class="min-w-0">
                <p class="font-bold text-lg truncate">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</p>
                <p class="text-xs text-gray-500">Student code <span class="font-mono font-semibold text-gray-700">${escapeHtml(s.student_code)}</span></p>
            </div>
            <span class="ml-auto px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status] || ''}">${escapeHtml(s.status)}</span>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-gray-500">Branch</dt><dd class="font-semibold">${escapeHtml(s.branch_name)}</dd></div>
            <div><dt class="text-xs text-gray-500">Course</dt><dd class="font-semibold">${escapeHtml(s.course_name)} <span class="text-xs text-gray-400">(${escapeHtml(s.duration)})</span></dd></div>
            <div><dt class="text-xs text-gray-500">Phone</dt><dd class="font-semibold">${escapeHtml(s.phone)}</dd></div>
            <div><dt class="text-xs text-gray-500">Enrolled</dt><dd class="font-semibold">${new Date(s.created_at).toLocaleDateString('en-IN')}</dd></div>
            <div class="col-span-2"><dt class="text-xs text-gray-500">Address</dt><dd class="font-semibold whitespace-pre-line">${s.address ? escapeHtml(s.address) : '<span class="text-gray-400 font-normal">Not provided</span>'}</dd></div>
        </dl>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Course Fee</p><p class="font-bold">${currency(s.total_fee)}</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Paid</p><p class="font-bold text-green-600">${currency(s.amount_paid)}</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Balance</p><p class="font-bold text-orange-500">${currency(s.balance_due)}</p></div>
        </div>
        <div>
            <p class="text-sm font-bold mb-1">Recent payments</p>
            <ul>${payRows}</ul>
        </div>`;
}

/** Quick Fee Counter: the payment form shown inside the profile while money is still owed. */
function paymentFormHtml(s) {
    const outstanding = outstandingOf(s);
    if (outstanding <= 0) {
        return '<p class="text-sm font-semibold text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">Fully paid. No payment is due.</p>';
    }
    return `
        <form data-pay-form class="border-t border-gray-200 pt-4 space-y-3" novalidate>
            <p class="text-sm font-bold">Record payment <span class="text-xs font-normal text-gray-500">(pending ${currency(outstanding)})</span></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payAmount">Amount (₹)</label>
                    <input id="payAmount" name="amount" type="number" min="1" max="${outstanding}" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payMode">Payment mode</label>
                    <select id="payMode" name="mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank">Bank</option>
                    </select>
                </div>
            </div>
            <p data-pay-error role="alert" class="hidden text-xs text-red-600"></p>
            <button type="submit" data-pay-submit class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-semibold text-sm rounded-lg py-2.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Record Payment</button>
        </form>`;
}

function bindPaymentForm(studentId, s) {
    const form = document.querySelector('#profileBody [data-pay-form]');
    if (!form) return;
    const errorBox = form.querySelector('[data-pay-error]');
    const submit = form.querySelector('[data-pay-submit]');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (submit.disabled) return;
        const amount = Number(form.elements.amount.value);
        const outstanding = outstandingOf(s);
        errorBox.classList.add('hidden');

        if (!(amount > 0)) {
            errorBox.textContent = 'Enter an amount greater than zero.';
            errorBox.classList.remove('hidden');
            return;
        }
        if (amount > outstanding) {
            errorBox.textContent = `Amount cannot exceed the pending ${currency(outstanding)}.`;
            errorBox.classList.remove('hidden');
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Saving…';
        try {
            await Api.recordPayment({ student_id: studentId, amount, payment_mode: form.elements.mode.value });
            showToast(`Payment of ${currency(amount)} recorded.`, 'success');
            document.dispatchEvent(new CustomEvent('student:changed'));
            await openStudentProfile(studentId);
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.classList.remove('hidden');
            submit.disabled = false;
            submit.textContent = 'Record Payment';
        }
    });
}

async function openStudentProfile(studentId) {
    const modal = el('profileModal');
    const body = el('profileBody');
    if (!modal) return;

    body.innerHTML = '<p class="text-sm text-gray-400">Loading...</p>';
    modal.classList.remove('hidden');
    el('profileCloseBtn').focus();

    try {
        const res = await Api.getStudent(studentId);
        const s = res.data.student;
        body.innerHTML = renderStudentProfile(res.data) + paymentFormHtml(s);
        bindPaymentForm(studentId, s);
    } catch (err) {
        body.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(err.message)}</p>`;
    }
}

/** Short address preview that opens the full address in a modal. Full text is escaped into an attribute. */
function addressCell(address) {
    if (!address) return '<span class="text-gray-400 text-xs">—</span>';
    const text = String(address);
    const preview = text.length > 30 ? text.slice(0, 30) + '…' : text;
    return `<button type="button" data-address-full="${escapeHtml(text)}" title="View full address"
                class="text-left text-xs text-gray-700 hover:text-red-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 rounded max-w-[12rem] block truncate">${escapeHtml(preview)}</button>`;
}

function openAddressModal(address) {
    const modal = el('addressModal');
    if (!modal) return;
    el('addressBody').textContent = address;
    modal.classList.remove('hidden');
    el('addressCloseBtn').focus();
}

function bindAddressButtons(container) {
    container.querySelectorAll('[data-address-full]').forEach((btn) => {
        btn.addEventListener('click', () => openAddressModal(btn.dataset.addressFull));
    });
}

function initAddressModal() {
    const modal = el('addressModal');
    if (!modal) return;
    const close = () => modal.classList.add('hidden');
    el('addressCloseBtn').addEventListener('click', close);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
}

function closeStudentProfile() {
    el('profileModal')?.classList.add('hidden');
}

function renderSkeletonRows(tbody, cols, rows = 5) {
    const cell = '<td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>';
    tbody.innerHTML = Array.from({ length: rows }).map(() => `
        <tr class="animate-pulse">${Array.from({ length: cols }).map(() => cell).join('')}</tr>
    `).join('');
}

function renderEmptyRow(tbody, cols, message) {
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-gray-400 py-8">${escapeHtml(message)}</td></tr>`;
}

function renderErrorRow(tbody, cols, message, retry) {
    tbody.innerHTML = `
        <tr><td colspan="${cols}" class="text-center py-8">
            <p class="text-red-600 text-sm">${escapeHtml(message)}</p>
            <button type="button" data-retry class="mt-2 text-xs font-semibold text-red-600 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50">Try again</button>
        </td></tr>`;
    const btn = tbody.querySelector('[data-retry]');
    if (btn && typeof retry === 'function') btn.addEventListener('click', retry);
}

/**
 * Renders "Showing x–y of z", "Page p of n", and wires Prev/Next.
 * Uses onclick assignment so re-rendering never stacks listeners.
 */
function renderPagination({ pagination, infoId, pageId, prevId, nextId, onChange }) {
    const { page, per_page: perPage, total, total_pages: totalPages } = pagination;
    const start = total === 0 ? 0 : (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);

    el(infoId).textContent = total === 0 ? 'No results' : `Showing ${start}-${end} of ${total}`;
    el(pageId).textContent = `Page ${page} of ${totalPages}`;

    const prev = el(prevId);
    const next = el(nextId);
    prev.disabled = page <= 1;
    next.disabled = page >= totalPages;
    prev.onclick = () => { if (page > 1) onChange(page - 1); };
    next.onclick = () => { if (page < totalPages) onChange(page + 1); };
}

function debounce(fn, ms = 350) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
}

/** Sets the viewing context to a branch, then opens its dashboard. Role is unchanged. */
async function openBranchDashboard(branchId) {
    try {
        await Api.setViewBranch(branchId);
        window.location.href = 'branch_dashboard.php';
    } catch (err) {
        showToast('Could not open branch: ' + err.message, 'error');
    }
}

/** Mobile drawer: the sidebar slides in below md; the overlay closes it. */
function initSuperNav() {
    const sidebar = el('superSidebar');
    const overlay = el('superNavOverlay');
    const toggle = el('superNavToggle');
    if (!sidebar || !overlay || !toggle) return;

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    };
    const close = () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };

    toggle.addEventListener('click', open);
    overlay.addEventListener('click', close);
}

function initProfileModal() {
    const modal = el('profileModal');
    if (!modal) return;
    el('profileCloseBtn').addEventListener('click', closeStudentProfile);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeStudentProfile();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeStudentProfile();
    });
}

initSuperNav();
initProfileModal();
initAddressModal();
