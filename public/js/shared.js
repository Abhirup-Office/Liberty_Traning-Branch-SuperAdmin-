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

const ICON_EYE = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.01 9.963 7.178.07.207.07.432 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.01-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
const ICON_EYE_SLASH = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.065 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.5a10.52 10.52 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243" /></svg>`;

/**
 * Wires every [data-toggle-password] button to show/hide the password input it names via
 * data-toggle-password="<input id>". Safe to call more than once (each button is only bound
 * once); called once at the bottom of this file for whatever toggle buttons already exist in
 * the page's HTML, including ones inside a modal that's hidden at load time.
 */
function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        if (btn.dataset.toggleBound) return;
        btn.dataset.toggleBound = '1';
        const input = document.getElementById(btn.dataset.togglePassword);
        if (!input) return;
        btn.innerHTML = ICON_EYE;
        btn.setAttribute('aria-label', 'Show password');
        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? ICON_EYE_SLASH : ICON_EYE;
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
}

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

/**
 * The reminder-specific status (distinct from the payment status badge above): driven by
 * the next installment date rather than how much has been paid, so the reminder wording and
 * badge can say "Overdue" / "Due Today" even for a student whose payment status is "Partial".
 */
const reminderStatusBadgeClass = {
    'Fully Paid': 'bg-green-100 text-green-700',
    'Overdue': 'bg-red-100 text-red-700',
    'Due Today': 'bg-orange-100 text-orange-700',
    'Pending': 'bg-gray-100 text-gray-600',
};

function reminderStatusOf(s) {
    if (!needsReminder(s)) return 'Fully Paid';
    if (!s.next_installment_date) return 'Pending';
    const today = new Date().toISOString().slice(0, 10);
    const due = String(s.next_installment_date).slice(0, 10);
    if (due < today) return 'Overdue';
    if (due === today) return 'Due Today';
    return 'Pending';
}

function studentActionButtons(s) {
    const name = escapeHtml(`${s.first_name} ${s.last_name}`);
    const reminderStatus = reminderStatusOf(s);
    const statusBadge = `<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap ${reminderStatusBadgeClass[reminderStatus]}">${reminderStatus}</span>`;
    const reminderButton = needsReminder(s) ? `
            <button type="button" data-remind-id="${escapeHtml(s.id)}" title="Send WhatsApp reminder" aria-label="Send WhatsApp reminder to ${name}"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 border border-green-200 rounded-lg px-2.5 py-1.5 hover:bg-green-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500">
                ${ICON_WHATSAPP}<span>Send Reminder</span>
            </button>` : '';
    return `
        <div class="flex flex-col items-center justify-center gap-1 whitespace-nowrap">
            <div class="flex items-center justify-center gap-1.5">
                <button type="button" data-profile-id="${escapeHtml(s.id)}" title="View profile" aria-label="View profile of ${name}"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    ${ICON_PROFILE}<span>View Profile</span>
                </button>${reminderButton}
            </div>
            ${statusBadge}
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

function formatDob(dob) {
    if (!dob) return '<span class="text-gray-400 font-normal">Not provided</span>';
    return new Date(dob).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
}

function paymentRowHtml(p) {
    const meta = [
        escapeHtml(p.payment_mode),
        new Date(p.transaction_date).toLocaleDateString('en-IN'),
        p.recorded_by_name ? `by ${escapeHtml(p.recorded_by_name)}` : '',
    ].filter(Boolean).join(' · ');
    const extra = [
        p.reference_no ? `Ref ${escapeHtml(p.reference_no)}` : '',
        p.notes ? escapeHtml(p.notes) : '',
    ].filter(Boolean).join(' — ');

    return `
        <li class="py-2 border-b border-gray-100 last:border-0">
            <div class="flex items-center justify-between text-sm">
                <span class="font-mono text-xs text-gray-500">${escapeHtml(p.receipt_no)}</span>
                <span class="text-gray-500 text-xs">${meta}</span>
                <span class="font-semibold text-green-600">+${currency(p.amount)}</span>
            </div>
            ${extra ? `<p class="text-xs text-gray-400 mt-0.5">${extra}</p>` : ''}
        </li>`;
}

function installmentStatusHtml(installment) {
    if (!installment || !installment.next_installment_date) return '';
    const dueDate = new Date(installment.next_installment_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
    if (installment.is_overdue) {
        return `
            <div class="bg-red-50 border border-red-100 rounded-lg px-3 py-2 flex items-center justify-between text-sm">
                <span class="text-red-700 font-semibold">Installment overdue — was due ${dueDate}</span>
                <span class="text-red-600 text-xs font-semibold">${Math.abs(installment.days_remaining)} day(s) overdue</span>
            </div>`;
    }
    return `
        <div class="bg-orange-50 border border-orange-100 rounded-lg px-3 py-2 flex items-center justify-between text-sm">
            <span class="text-orange-700 font-semibold">Next installment due ${dueDate}</span>
            <span class="text-orange-600 text-xs font-semibold">${installment.days_remaining} day(s) remaining</span>
        </div>`;
}

function renderStudentProfile({ student: s, payments, last_payment: lastPayment, installment }) {
    const payRows = payments.length
        ? payments.map(paymentRowHtml).join('')
        : '<li class="text-sm text-gray-400 py-2">No payments recorded yet.</li>';

    const initials = escapeHtml((s.first_name[0] || '') + (s.last_name[0] || ''));
    const avatar = s.photo_path
        ? `<img src="${studentPhotoUrl(s.id)}" alt="" class="w-12 h-12 rounded-full object-cover bg-gray-100 shrink-0" />`
        : `<div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-600 shrink-0">${initials}</div>`;

    return `
        <div class="flex items-center gap-3">
            ${avatar}
            <div class="min-w-0">
                <p class="font-bold text-lg truncate">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</p>
                <p class="text-xs text-gray-500">Student ID <span class="font-mono font-semibold text-gray-700">${escapeHtml(s.display_id || s.student_code)}</span></p>
            </div>
            <span class="ml-auto px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status] || ''}">${escapeHtml(s.status)}</span>
        </div>

        <div data-photo-section class="flex items-center gap-2 text-xs">
            <label class="cursor-pointer font-semibold text-red-600 hover:underline">
                ${s.photo_path ? 'Change photo' : 'Add photo'}
                <input type="file" accept="image/jpeg,image/png,image/webp" data-photo-input class="hidden" />
            </label>
            <span class="text-gray-400">JPEG, PNG or WEBP, up to 2 MB</span>
            <span data-photo-status class="text-gray-500"></span>
        </div>

        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-gray-500">Branch</dt><dd class="font-semibold">${escapeHtml(s.branch_name)}</dd></div>
            <div><dt class="text-xs text-gray-500">Course</dt><dd class="font-semibold">${escapeHtml(s.course_name)} <span class="text-xs text-gray-400">(${escapeHtml(s.duration)})</span></dd></div>
            <div><dt class="text-xs text-gray-500">Phone</dt><dd class="font-semibold">${escapeHtml(s.phone)}</dd></div>
            <div><dt class="text-xs text-gray-500">Enrolled</dt><dd class="font-semibold">${new Date(s.created_at).toLocaleDateString('en-IN')}</dd></div>
            <div><dt class="text-xs text-gray-500">Father's Name</dt><dd class="font-semibold">${s.father_name ? escapeHtml(s.father_name) : '<span class="text-gray-400 font-normal">Not provided</span>'}</dd></div>
            <div><dt class="text-xs text-gray-500">Mother's Name</dt><dd class="font-semibold">${s.mother_name ? escapeHtml(s.mother_name) : '<span class="text-gray-400 font-normal">Not provided</span>'}</dd></div>
            <div><dt class="text-xs text-gray-500">Date of Birth</dt><dd class="font-semibold">${formatDob(s.date_of_birth)}</dd></div>
            <div><dt class="text-xs text-gray-500">Student Code</dt><dd class="font-mono text-xs text-gray-500">${escapeHtml(s.student_code)}</dd></div>
            <div class="col-span-2"><dt class="text-xs text-gray-500">Address</dt><dd class="font-semibold whitespace-pre-line">${s.address ? escapeHtml(s.address) : '<span class="text-gray-400 font-normal">Not provided</span>'}</dd></div>
        </dl>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Course Fee</p><p class="font-bold">${currency(s.total_fee)}</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Paid</p><p class="font-bold text-green-600">${currency(s.amount_paid)}</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Balance</p><p class="font-bold text-orange-500">${currency(s.balance_due)}</p></div>
        </div>
        ${lastPayment ? `
        <div class="text-sm">
            <span class="text-gray-500">Last payment:</span>
            <span class="font-semibold">${currency(lastPayment.amount)}</span>
            <span class="text-gray-500">on ${new Date(lastPayment.transaction_date).toLocaleDateString('en-IN')}</span>
        </div>` : ''}
        ${installmentStatusHtml(installment)}

        <div data-portal-section class="flex items-center justify-between gap-2 text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
            <span class="text-gray-600">
                Student portal login:
                ${s.password_hash_set
                    ? '<span class="text-green-700 font-semibold">Activated</span>'
                    : '<span class="text-gray-500 font-semibold">Not activated</span>'}
            </span>
            <button type="button" data-reset-password title="${s.date_of_birth ? '' : 'Set a date of birth first'}"
                    ${s.date_of_birth ? '' : 'disabled'}
                    class="font-semibold text-red-600 hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">
                ${s.password_hash_set ? 'Reset to DOB' : 'Activate login'}
            </button>
        </div>
        <p data-reset-result class="hidden text-xs bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg px-3 py-2"></p>

        <div>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold">Recent payments</p>
                <button type="button" data-history-toggle class="text-xs font-semibold text-red-600 hover:underline">View full payment history</button>
            </div>
            <ul>${payRows}</ul>
            <div data-history-section class="hidden mt-3 border-t border-gray-200 pt-3">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="text-gray-500 uppercase">
                            <tr>
                                <th class="text-left py-1 pr-2">Receipt</th>
                                <th class="text-left py-1 pr-2">Date</th>
                                <th class="text-left py-1 pr-2">Mode</th>
                                <th class="text-left py-1 pr-2">Recorded By</th>
                                <th class="text-right py-1">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between pt-2 text-xs">
                    <p class="text-gray-500" id="historyInfo">Loading...</p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="historyPrevBtn" class="border border-gray-300 rounded px-2 py-1 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                        <span class="text-gray-500" id="historyPage">Page 1 of 1</span>
                        <button type="button" id="historyNextBtn" class="border border-gray-300 rounded px-2 py-1 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                    </div>
                </div>
            </div>
        </div>`;
}

function bindResetPassword(studentId) {
    const btn = document.querySelector('#profileBody [data-reset-password]');
    const result = document.querySelector('#profileBody [data-reset-result]');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        if (btn.disabled) return;
        const verb = btn.textContent.trim().toLowerCase().includes('activate') ? 'activate the login for' : 'reset the login password for';
        if (!confirm(`Are you sure you want to ${verb} this student? Their current password will stop working.`)) return;

        btn.disabled = true;
        try {
            const res = await Api.resetStudentPassword(studentId);
            // Shown inline and left on screen — a full profile reload would wipe this
            // message before the admin has a chance to copy it down.
            result.textContent = `New password: ${res.data.new_password} — share this with the student. It will not be shown again.`;
            result.classList.remove('hidden');
            showToast('Student login password reset.', 'success');
            document.dispatchEvent(new CustomEvent('student:changed'));

            const statusLine = document.querySelector('#profileBody [data-portal-section] span');
            if (statusLine) statusLine.innerHTML = 'Student portal login: <span class="text-green-700 font-semibold">Activated</span>';
            btn.textContent = 'Reset to DOB';
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });
}

function bindPhotoUpload(studentId) {
    const input = document.querySelector('#profileBody [data-photo-input]');
    const status = document.querySelector('#profileBody [data-photo-status]');
    if (!input) return;

    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            status.textContent = 'File is larger than 2 MB.';
            status.className = 'text-red-600';
            return;
        }
        status.textContent = 'Uploading…';
        status.className = 'text-gray-500';
        try {
            await Api.uploadStudentPhoto(studentId, file);
            showToast('Photo updated.', 'success');
            document.dispatchEvent(new CustomEvent('student:changed'));
            await openStudentProfile(studentId);
        } catch (err) {
            status.textContent = err.message;
            status.className = 'text-red-600';
        }
    });
}

/**
 * Quick Fee Counter: the payment form shown inside the profile while money is still owed.
 * Covers both payment options the same way — a full payment is just an amount equal to the
 * whole pending balance; an installment is a smaller amount with an optional next due date.
 */
function paymentFormHtml(s) {
    const outstanding = outstandingOf(s);
    if (outstanding <= 0) {
        return '<p class="text-sm font-semibold text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">Fully paid. No payment is due.</p>';
    }
    const today = new Date().toISOString().slice(0, 10);
    return `
        <form data-pay-form class="border-t border-gray-200 pt-4 space-y-3" novalidate>
            <p class="text-sm font-bold">Record payment <span class="text-xs font-normal text-gray-500">(pending ${currency(outstanding)})</span></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payAmount">Amount (₹)</label>
                    <input id="payAmount" name="amount" type="number" min="1" max="${outstanding}" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                    <button type="button" data-pay-full class="text-xs text-red-600 hover:underline mt-1">Pay full pending amount</button>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payMode">Payment mode</label>
                    <select id="payMode" name="mode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank">Bank</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payReference">Reference / Txn No. (optional)</label>
                    <input id="payReference" name="reference_no" type="text" maxlength="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600" for="payNextDate">Next installment date (optional)</label>
                    <input id="payNextDate" name="next_installment_date" type="date" min="${today}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600" for="payNotes">Notes (optional)</label>
                    <textarea id="payNotes" name="notes" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
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
    const payFullBtn = form.querySelector('[data-pay-full]');

    payFullBtn?.addEventListener('click', () => {
        form.elements.amount.value = outstandingOf(s);
    });

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
            await Api.recordPayment({
                student_id: studentId,
                amount,
                payment_mode: form.elements.mode.value,
                reference_no: form.elements.reference_no.value.trim(),
                notes: form.elements.notes.value.trim(),
                next_installment_date: form.elements.next_installment_date.value,
            });
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

/** "View full payment history": paginated, loaded only when expanded — never on every profile open. */
function bindPaymentHistory(studentId) {
    const toggle = document.querySelector('#profileBody [data-history-toggle]');
    const section = document.querySelector('#profileBody [data-history-section]');
    if (!toggle || !section) return;

    const historyState = { page: 1, perPage: 10 };

    async function loadHistory() {
        const tbody = el('historyBody');
        renderSkeletonRows(tbody, 5, 3);
        try {
            const res = await Api.getPaymentHistory(studentId, { page: historyState.page, per_page: historyState.perPage });
            if (res.data.length === 0) {
                renderEmptyRow(tbody, 5, 'No payments recorded yet.');
            } else {
                tbody.innerHTML = res.data.map((p) => `
                    <tr>
                        <td class="py-1 pr-2 font-mono text-gray-500">${escapeHtml(p.receipt_no)}</td>
                        <td class="py-1 pr-2 text-gray-600 whitespace-nowrap">${new Date(p.transaction_date).toLocaleDateString('en-IN')}</td>
                        <td class="py-1 pr-2 text-gray-600">${escapeHtml(p.payment_mode)}</td>
                        <td class="py-1 pr-2 text-gray-600">${p.recorded_by_name ? escapeHtml(p.recorded_by_name) : '—'}</td>
                        <td class="py-1 text-right font-semibold text-green-600">+${currency(p.amount)}</td>
                    </tr>`).join('');
            }
            renderPagination({
                pagination: res.pagination,
                infoId: 'historyInfo',
                pageId: 'historyPage',
                prevId: 'historyPrevBtn',
                nextId: 'historyNextBtn',
                onChange: (page) => {
                    historyState.page = page;
                    loadHistory();
                },
            });
        } catch (err) {
            renderErrorRow(tbody, 5, err.message, loadHistory);
        }
    }

    toggle.addEventListener('click', () => {
        const isHidden = section.classList.contains('hidden');
        section.classList.toggle('hidden');
        toggle.textContent = isHidden ? 'Hide full payment history' : 'View full payment history';
        if (isHidden && !section.dataset.loaded) {
            section.dataset.loaded = '1';
            loadHistory();
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
        bindPhotoUpload(studentId);
        bindPaymentHistory(studentId);
        bindResetPassword(studentId);
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

const ICON_CERTIFICATE = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;

/** Certificate column cell for one student row — separate from studentActionButtons(). */
function certificateCellHtml(s) {
    const hasCert = !!s.has_certificate;
    const name = escapeHtml(`${s.first_name} ${s.last_name}`);
    const label = hasCert ? 'Uploaded' : 'Upload';
    const title = hasCert ? 'Manage Certificate' : 'Upload Certificate';
    return `
        <button type="button" data-cert-id="${escapeHtml(s.id)}" title="${title}" aria-label="${title} for ${name}"
                class="inline-flex items-center gap-1 text-xs font-semibold border rounded-lg px-2 py-1.5 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 ${
                    hasCert ? 'text-green-700 border-green-200 hover:bg-green-50' : 'text-gray-500 border-gray-300 hover:bg-gray-50'
                }">
            ${ICON_CERTIFICATE}<span>${label}</span>
        </button>`;
}

/** Wires the buttons rendered by certificateCellHtml(). */
function bindCertificateButtons(container) {
    container.querySelectorAll('[data-cert-id]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            openCertificateModal(Number(btn.dataset.certId));
        });
    });
}

/**
 * Individual student certificate modal: upload / replace / delete, with a circular progress
 * indicator for the upload (most relevant for larger PDF files). Entirely separate from the
 * existing global/sample course certificate modal elsewhere in the app.
 */
const certState = { studentId: null };

function closeCertModal() {
    el('certModal')?.classList.add('hidden');
}

async function openCertificateModal(studentId) {
    certState.studentId = studentId;
    const modal = el('certModal');
    const body = el('certModalBody');
    if (!modal || !body) return;
    body.innerHTML = '<p class="text-sm text-gray-400">Loading...</p>';
    modal.classList.remove('hidden');
    el('certCloseBtn')?.focus();

    try {
        const res = await Api.getStudentCertificate(studentId);
        renderCertModalBody(res.data);
    } catch (err) {
        body.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(err.message)}</p>`;
    }
}

function certFileSizeLabel(bytes) {
    if (!bytes) return '';
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function renderCertModalBody(data) {
    const cert = data.certificate;
    const body = el('certModalBody');
    const nameVal = cert ? cert.certificate_name : data.student_name;
    const durationVal = cert ? cert.course_duration : data.course_duration;

    const previewHtml = !cert
        ? '<p class="text-sm text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-4 py-6 text-center">No certificate uploaded</p>'
        : cert.mime_type === 'application/pdf'
            ? `<div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-3 text-sm">
                   <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                   <span class="min-w-0 truncate text-gray-700">${escapeHtml(cert.original_filename || 'certificate.pdf')}</span>
                   <span class="text-xs text-gray-400 shrink-0">${certFileSizeLabel(cert.file_size_bytes)}</span>
                   <a href="${studentCertificateFileUrl(data.student_id)}" target="_blank" rel="noopener" class="ml-auto text-red-600 font-semibold hover:underline shrink-0">View</a>
               </div>`
            : `<img src="${studentCertificateFileUrl(data.student_id)}?t=${Date.now()}" alt="Certificate preview" class="w-full max-h-64 object-contain rounded-lg border border-gray-200 bg-gray-50" />`;

    const statusHtml = cert
        ? '<span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700"><svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Uploaded</span>'
        : '<span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /></svg> Not Uploaded</span>';

    body.innerHTML = `
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-gray-500">Student</dt><dd class="font-semibold">${escapeHtml(data.student_name)}</dd></div>
            <div><dt class="text-xs text-gray-500">Course</dt><dd class="font-semibold">${escapeHtml(data.course_name)}</dd></div>
            <div class="col-span-2"><dt class="text-xs text-gray-500">Certificate</dt><dd>${statusHtml}</dd></div>
        </dl>
        <div>${previewHtml}</div>
        <form id="certForm" class="space-y-3 border-t border-gray-200 pt-3" novalidate>
            <div>
                <label class="text-xs font-semibold text-gray-600">Student Name (on certificate)</label>
                <input name="certificate_name" value="${escapeHtml(nameVal)}" maxlength="150" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Course Duration</label>
                <input name="course_duration" value="${escapeHtml(durationVal)}" maxlength="50" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">${cert ? 'Replace File (optional)' : 'Certificate File *'}</label>
                <input type="file" name="certificate" accept=".png,image/png,.pdf,application/pdf" class="w-full text-sm mt-1" />
                <p class="text-xs text-gray-400 mt-1">PNG up to 5 MB, or PDF up to 25 MB.</p>
            </div>
            <div id="certProgressWrap" class="hidden items-center gap-3">
                <svg width="40" height="40" viewBox="0 0 40 40" class="shrink-0">
                    <circle cx="20" cy="20" r="16" fill="none" stroke="#e5e7eb" stroke-width="4" />
                    <circle id="certProgressCircle" cx="20" cy="20" r="16" fill="none" stroke="#dc2626" stroke-width="4"
                            stroke-dasharray="100.53" stroke-dashoffset="100.53" stroke-linecap="round" transform="rotate(-90 20 20)" />
                </svg>
                <span id="certProgressLabel" class="text-sm text-gray-600">Uploading… 0%</span>
            </div>
            <p id="certFormError" role="alert" class="hidden text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2"></p>
            <div class="flex items-center justify-between gap-2 pt-1">
                ${cert ? '<button type="button" id="certDeleteBtn" class="text-xs font-semibold text-red-600 border border-red-200 rounded-lg px-3 py-2 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Delete Certificate</button>' : '<span></span>'}
                <button type="submit" id="certSaveBtn" class="bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold text-sm rounded-lg px-4 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                    ${cert ? 'Save / Replace' : 'Upload Certificate'}
                </button>
            </div>
        </form>`;

    bindCertForm(data.student_id, !!cert);
}

function bindCertForm(studentId, hasCert) {
    const form = el('certForm');
    if (!form) return;
    const saveBtn = el('certSaveBtn');
    const errorBox = el('certFormError');
    const progressWrap = el('certProgressWrap');
    const progressCircle = el('certProgressCircle');
    const progressLabel = el('certProgressLabel');
    const CIRC = 2 * Math.PI * 16;

    const MAX_PNG_BYTES = 5 * 1024 * 1024;
    const MAX_PDF_BYTES = 25 * 1024 * 1024;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (saveBtn.disabled) return;
        errorBox.classList.add('hidden');

        const name = form.elements.certificate_name.value.trim();
        const duration = form.elements.course_duration.value.trim();
        const file = form.elements.certificate.files[0] || null;

        if (!name || !duration) {
            errorBox.textContent = 'Student name and course duration are required.';
            errorBox.classList.remove('hidden');
            return;
        }
        if (!file && !hasCert) {
            errorBox.textContent = 'A certificate file (PNG or PDF) is required for the first upload.';
            errorBox.classList.remove('hidden');
            return;
        }
        if (file) {
            const isPng = file.type === 'image/png';
            const isPdf = file.type === 'application/pdf';
            if (!isPng && !isPdf) {
                errorBox.textContent = 'Only PNG or PDF files are allowed.';
                errorBox.classList.remove('hidden');
                return;
            }
            const maxBytes = isPng ? MAX_PNG_BYTES : MAX_PDF_BYTES;
            if (file.size > maxBytes) {
                errorBox.textContent = `${isPng ? 'PNG' : 'PDF'} files must be under ${isPng ? '5 MB' : '25 MB'}.`;
                errorBox.classList.remove('hidden');
                return;
            }
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
        if (file) {
            progressWrap.classList.remove('hidden');
            progressWrap.classList.add('flex');
            progressCircle.setAttribute('stroke-dashoffset', String(CIRC));
            progressLabel.textContent = 'Uploading… 0%';
        }

        try {
            await Api.saveStudentCertificate(studentId, name, duration, file, (pct) => {
                progressCircle.setAttribute('stroke-dashoffset', String(CIRC * (1 - pct / 100)));
                progressLabel.textContent = pct >= 100 ? '✓ Upload complete' : `Uploading… ${pct}%`;
            });
            showToast('Certificate saved.', 'success');
            document.dispatchEvent(new CustomEvent('student:changed'));
            await openCertificateModal(studentId);
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.classList.remove('hidden');
            saveBtn.disabled = false;
            saveBtn.textContent = hasCert ? 'Save / Replace' : 'Upload Certificate';
            progressWrap.classList.add('hidden');
        }
    });

    const deleteBtn = el('certDeleteBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to delete this certificate?')) return;
            deleteBtn.disabled = true;
            try {
                await Api.deleteStudentCertificate(studentId);
                showToast('Certificate deleted.', 'success');
                document.dispatchEvent(new CustomEvent('student:changed'));
                await openCertificateModal(studentId);
            } catch (err) {
                showToast(err.message, 'error');
                deleteBtn.disabled = false;
            }
        });
    }
}

function initCertificateModal() {
    const modal = el('certModal');
    if (!modal) return;
    el('certCloseBtn').addEventListener('click', closeCertModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeCertModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeCertModal();
    });
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
initPasswordToggles();
initCertificateModal();
