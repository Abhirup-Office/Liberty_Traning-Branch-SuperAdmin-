/**
 * student-shared.js — helpers used by every student-portal page.
 * Loaded before the page script. Mirrors the admin side's public/js/shared.js in spirit,
 * but deliberately has no payment-recording or student-editing capability — this file
 * only ever calls read-only or own-data-only student_*.php endpoints.
 */

const el = (id) => document.getElementById(id);

const currency = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0 });

const statusBadgeClass = {
    'Paid in Full': 'bg-green-100 text-green-700',
    'Partial': 'bg-orange-100 text-orange-700',
    'Pending': 'bg-red-100 text-red-700',
};

const ICON_EYE = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.01 9.963 7.178.07.207.07.432 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.01-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
const ICON_EYE_SLASH = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.065 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.5a10.52 10.52 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243" /></svg>`;

/** Wires every [data-toggle-password] button to show/hide the input it names. Safe to call more than once. */
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

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatDate(value, options = { year: 'numeric', month: 'short', day: 'numeric' }) {
    return value ? new Date(value).toLocaleDateString('en-IN', options) : '—';
}

function showPageError(message) {
    const box = el('pageError');
    if (!box) return;
    box.textContent = message;
    box.classList.remove('hidden');
}

function renderSkeletonRows(tbody, cols, rows = 5) {
    const cell = '<td class="px-4 py-3"><div class="h-4 bg-gray-200 rounded w-24"></div></td>';
    tbody.innerHTML = Array.from({ length: rows }).map(() => `<tr class="animate-pulse">${Array.from({ length: cols }).map(() => cell).join('')}</tr>`).join('');
}

function renderEmptyRow(tbody, cols, message) {
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-gray-400 py-8">${escapeHtml(message)}</td></tr>`;
}

function renderErrorRow(tbody, cols, message) {
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-red-600 py-8">${escapeHtml(message)}</td></tr>`;
}

/** "Showing x–y of z", "Page p of n", Prev/Next — same contract as the admin side's version. */
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

/** Mobile drawer: the sidebar slides in below md; the overlay closes it. */
function initStudentNav() {
    const sidebar = el('studentSidebar');
    const overlay = el('studentNavOverlay');
    const toggle = el('studentNavToggle');
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

function bindLogout() {
    async function doLogout() {
        try {
            const res = await StudentApi.logout();
            window.location.href = res.redirect;
        } catch (err) {
            window.location.href = 'login.php';
        }
    }
    ['logoutBtn', 'logoutBtnMobile'].forEach((id) => {
        const btn = el(id);
        if (btn) btn.addEventListener('click', doLogout);
    });
}


initStudentNav();
bindLogout();
initPasswordToggles();
