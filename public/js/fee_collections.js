/**
 * fee_collections.js — payment ledger. Totals come from get_kpis.php (all branches);
 * rows, filters and pagination come from get_transactions.php.
 */

const FEE_COLS = 6;
const feeState = { page: 1, perPage: 10 };
const feeModeIcon = { UPI: '📲', Cash: '💵', Bank: '🏦' };

function feeFilters() {
    return {
        branch: el('branchFilter').value || 'all',
        payment_mode: el('modeFilter').value,
        search: el('searchInput').value.trim(),
        page: feeState.page,
        per_page: feeState.perPage,
    };
}

async function loadFeeOptions() {
    try {
        const meta = await Api.getMeta();
        el('branchFilter').insertAdjacentHTML('beforeend',
            meta.data.branches.map((b) => `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`).join(''));
    } catch (err) {
        showToast('Could not load branch filter: ' + err.message, 'error');
    }
}

async function loadFeeTotals() {
    try {
        const res = await Api.getKpis('all');
        el('kpiCollected').textContent = currency(res.data.collected);
        el('kpiPending').textContent = currency(res.data.pending);
    } catch (err) {
        el('kpiCollected').textContent = '—';
        el('kpiPending').textContent = '—';
        showToast('Could not load fee totals: ' + err.message, 'error');
    }
}

async function loadFeeRows() {
    const tbody = el('transactionsBody');
    renderSkeletonRows(tbody, FEE_COLS, 6);
    try {
        const res = await Api.getTransactions(feeFilters());
        el('kpiTransactions').textContent = res.pagination.total;
        renderFeeRows(res.data);
        renderPagination({
            pagination: res.pagination,
            infoId: 'paginationInfo',
            pageId: 'paginationPage',
            prevId: 'prevPageBtn',
            nextId: 'nextPageBtn',
            onChange: (page) => {
                feeState.page = page;
                loadFeeRows();
            },
        });
    } catch (err) {
        el('kpiTransactions').textContent = '—';
        renderErrorRow(tbody, FEE_COLS, err.message, loadFeeRows);
    }
}

function renderFeeRows(transactions) {
    const tbody = el('transactionsBody');
    if (transactions.length === 0) {
        renderEmptyRow(tbody, FEE_COLS, 'No payments match these filters.');
        return;
    }

    tbody.innerHTML = transactions.map((t) => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">${escapeHtml(t.receipt_no)}</td>
            <td class="px-4 py-3 font-semibold">${escapeHtml(t.first_name)} ${escapeHtml(t.last_name)}</td>
            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${escapeHtml(t.branch_name)}</span></td>
            <td class="px-4 py-3 text-center"><span title="${escapeHtml(t.payment_mode)}">${feeModeIcon[t.payment_mode] || '💳'} ${escapeHtml(t.payment_mode)}</span></td>
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${new Date(t.transaction_date).toLocaleString('en-IN')}</td>
            <td class="px-4 py-3 text-right font-bold text-green-600">+${currency(t.amount)}</td>
        </tr>
    `).join('');
}

function resetFeeAndLoad() {
    feeState.page = 1;
    loadFeeRows();
}

el('branchFilter').addEventListener('change', resetFeeAndLoad);
el('modeFilter').addEventListener('change', resetFeeAndLoad);
el('searchInput').addEventListener('input', debounce(resetFeeAndLoad, 350));

loadFeeOptions();
loadFeeTotals();
loadFeeRows();
