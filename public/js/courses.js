/**
 * courses.js — Course list. Filtering, pagination and statistics come from api/get_courses.php.
 */

const COURSE_COLS = IS_BRANCH_ADMIN ? 8 : 9;
const courseState = { page: 1, perPage: 10 };
const statusPillClass = {
    Active: 'bg-green-100 text-green-700',
    Inactive: 'bg-gray-200 text-gray-600',
};

function courseFilters() {
    const branchSelect = el('branchFilter');
    return {
        branch: branchSelect ? branchSelect.value : 'all',
        status: el('statusFilter').value,
        search: el('searchInput').value.trim(),
        page: courseState.page,
        per_page: courseState.perPage,
    };
}

async function loadBranchOptions() {
    if (IS_BRANCH_ADMIN) return;
    try {
        const meta = await Api.getMeta();
        el('branchFilter').insertAdjacentHTML('beforeend',
            meta.data.branches.map((b) => `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`).join(''));
    } catch (err) {
        showToast('Could not load branch filter: ' + err.message, 'error');
    }
}

async function loadCourses() {
    const tbody = el('coursesBody');
    renderSkeletonRows(tbody, COURSE_COLS, 5);
    try {
        const res = await Api.getCourses(courseFilters());
        el('statTotal').textContent = res.stats.total_courses;
        el('statActive').textContent = res.stats.active_courses;
        el('statInactive').textContent = res.stats.inactive_courses;
        el('statEnrollments').textContent = res.stats.total_enrollments;
        renderCourseRows(res.data);
        renderPagination({
            pagination: res.pagination,
            infoId: 'paginationInfo',
            pageId: 'paginationPage',
            prevId: 'prevPageBtn',
            nextId: 'nextPageBtn',
            onChange: (page) => {
                courseState.page = page;
                loadCourses();
            },
        });
    } catch (err) {
        renderErrorRow(tbody, COURSE_COLS, err.message, loadCourses);
    }
}

function renderCourseRows(courses) {
    const tbody = el('coursesBody');
    if (courses.length === 0) {
        renderEmptyRow(tbody, COURSE_COLS, 'No courses match these filters.');
        return;
    }

    tbody.innerHTML = courses.map((c) => {
        const toggleLabel = c.status === 'Active' ? 'Deactivate' : 'Activate';
        const toggleTo = c.status === 'Active' ? 'Inactive' : 'Active';
        return `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500">${escapeHtml(c.id)}</td>
            <td class="px-4 py-3">
                <a href="course_details.php?id=${escapeHtml(c.id)}" class="font-semibold text-gray-900 hover:text-red-600">${escapeHtml(c.course_name)}</a>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">${escapeHtml(c.course_code || '—')}</td>
            ${IS_BRANCH_ADMIN ? '' : `<td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${escapeHtml(c.branch_name || 'Global')}</span></td>`}
            <td class="px-4 py-3 text-gray-600">${escapeHtml(c.duration)}</td>
            <td class="px-4 py-3 text-right font-semibold">${escapeHtml(c.enrolled_count)}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold ${statusPillClass[c.status] || ''}">${escapeHtml(c.status)}</span></td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${new Date(c.created_at).toLocaleDateString('en-IN')}</td>
            <td class="px-4 py-3">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <a href="course_details.php?id=${escapeHtml(c.id)}" class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">View</a>
                    <a href="course_form.php?id=${escapeHtml(c.id)}" class="text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Edit</a>
                    <a href="course_details.php?id=${escapeHtml(c.id)}#students" class="text-xs font-semibold text-red-600 border border-red-200 rounded-lg px-2.5 py-1.5 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Students</a>
                    <button type="button" data-toggle-id="${escapeHtml(c.id)}" data-toggle-to="${toggleTo}"
                            class="text-xs font-semibold ${c.status === 'Active' ? 'text-orange-600 border-orange-200 hover:bg-orange-50' : 'text-green-700 border-green-200 hover:bg-green-50'} border rounded-lg px-2.5 py-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">${toggleLabel}</button>
                    <button type="button" data-delete-id="${escapeHtml(c.id)}" title="Delete course"
                            class="text-xs font-semibold text-red-600 border border-red-200 rounded-lg px-2.5 py-1.5 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Delete</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-toggle-id]').forEach((btn) => {
        btn.addEventListener('click', () => toggleCourseStatus(Number(btn.dataset.toggleId), btn.dataset.toggleTo));
    });
    tbody.querySelectorAll('[data-delete-id]').forEach((btn) => {
        btn.addEventListener('click', () => removeCourse(Number(btn.dataset.deleteId)));
    });
}

async function removeCourse(id) {
    if (!confirm('Are you sure you want to delete this course?')) return;
    try {
        await Api.deleteCourse(id);
        showToast('Course deleted.', 'success');
        await loadCourses();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function toggleCourseStatus(id, status) {
    const verb = status === 'Active' ? 'activate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${verb} this course?`)) return;
    try {
        await Api.setCourseStatus(id, status);
        showToast(`Course ${status === 'Active' ? 'activated' : 'deactivated'}.`, 'success');
        await loadCourses();
    } catch (err) {
        showToast('Could not change status: ' + err.message, 'error');
    }
}

function resetAndLoad() {
    courseState.page = 1;
    loadCourses();
}

el('searchInput').addEventListener('input', debounce(resetAndLoad, 350));
el('statusFilter').addEventListener('change', resetAndLoad);
el('branchFilter')?.addEventListener('change', resetAndLoad);

const flash = sessionStorage.getItem('flashMessage');
if (flash) {
    sessionStorage.removeItem('flashMessage');
    showToast(flash, 'success');
}

loadBranchOptions();
loadCourses();
