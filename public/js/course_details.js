/**
 * course_details.js — course information, enrollment statistics, status toggle,
 * and the enrolled-student list (students are fetched with the existing get_students.php,
 * which already enforces branch scope).
 */

const ENROLL_COLS = 7;
const enrollState = { page: 1, perPage: 10 };
const enrollPillClass = {
    Active: 'bg-green-100 text-green-700',
    Inactive: 'bg-gray-200 text-gray-600',
};

function showError(message) {
    const box = el('pageError');
    box.textContent = message;
    box.classList.remove('hidden');
}

function renderCourse(course, enrollment) {
    el('courseName').textContent = course.course_name;
    el('courseCode').textContent = course.course_code || '—';
    el('courseBranch').textContent = course.branch_name || 'Global';
    el('courseDuration').textContent = course.duration;
    el('courseFee').textContent = currency(course.total_fee);
    el('courseCreated').textContent = new Date(course.created_at).toLocaleDateString('en-IN');
    el('courseStart').textContent = course.start_date ? new Date(course.start_date).toLocaleDateString('en-IN') : '—';
    el('courseEnd').textContent = course.end_date ? new Date(course.end_date).toLocaleDateString('en-IN') : '—';
    el('courseDescription').textContent = course.description || 'No description provided.';

    const pill = el('courseStatus');
    pill.textContent = course.status;
    pill.className = `self-start px-2.5 py-1 rounded-full text-xs font-semibold ${enrollPillClass[course.status] || ''}`;

    const toggle = el('toggleStatusBtn');
    const next = course.status === 'Active' ? 'Inactive' : 'Active';
    toggle.textContent = next === 'Inactive' ? 'Deactivate course' : 'Activate course';
    toggle.className = `text-sm font-semibold border rounded-lg px-3 py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 ${
        next === 'Inactive' ? 'text-orange-600 border-orange-200 hover:bg-orange-50' : 'text-green-700 border-green-200 hover:bg-green-50'}`;
    toggle.dataset.next = next;
    toggle.classList.remove('hidden');

    el('enrTotal').textContent = enrollment.total_students;
    el('enrPaid').textContent = enrollment.paid_in_full;
    el('enrPartial').textContent = enrollment.partial;
    el('enrOverdue').textContent = enrollment.overdue;

    const preview = el('certificatePreview');
    const uploadLabel = el('certificateUploadLabel');
    if (course.certificate_path) {
        const isPdf = course.certificate_path.toLowerCase().endsWith('.pdf');
        preview.innerHTML = isPdf
            ? `<a href="${courseCertificateUrl(course.id)}" target="_blank" rel="noopener" class="text-sm text-red-600 hover:underline">View uploaded PDF certificate →</a>`
            : `<img src="${courseCertificateUrl(course.id)}" alt="Sample certificate" class="max-w-xs rounded-lg border border-gray-200" />`;
        preview.classList.remove('hidden');
        uploadLabel.textContent = 'Replace certificate';
    } else {
        preview.classList.add('hidden');
        uploadLabel.textContent = 'Upload certificate';
    }
}

async function loadCourse() {
    try {
        const res = await Api.getCourse(COURSE_ID);
        renderCourse(res.data.course, res.data.enrollment);
    } catch (err) {
        showError(err.message);
    }
}

el('certificateInput').addEventListener('change', async () => {
    const input = el('certificateInput');
    const file = input.files[0];
    if (!file) return;
    const status = el('certificateStatus');
    if (file.size > 5 * 1024 * 1024) {
        status.textContent = 'File is larger than 5 MB.';
        status.className = 'text-xs mt-1 text-red-600';
        return;
    }
    status.textContent = 'Uploading…';
    status.className = 'text-xs mt-1 text-gray-500';
    try {
        await Api.uploadCourseCertificate(COURSE_ID, file);
        showToast('Certificate uploaded.', 'success');
        status.textContent = '';
        input.value = '';
        await loadCourse();
    } catch (err) {
        status.textContent = err.message;
        status.className = 'text-xs mt-1 text-red-600';
    }
});

el('deleteCourseBtn').addEventListener('click', async () => {
    if (!confirm('Are you sure you want to delete this course?')) return;
    try {
        await Api.deleteCourse(COURSE_ID);
        sessionStorage.setItem('flashMessage', 'Course deleted.');
        window.location.href = 'courses.php';
    } catch (err) {
        showToast(err.message, 'error');
    }
});

el('toggleStatusBtn').addEventListener('click', async () => {
    const next = el('toggleStatusBtn').dataset.next;
    if (!confirm(`Are you sure you want to ${next === 'Active' ? 'activate' : 'deactivate'} this course?`)) return;
    try {
        await Api.setCourseStatus(COURSE_ID, next);
        showToast(`Course ${next === 'Active' ? 'activated' : 'deactivated'}.`, 'success');
        await loadCourse();
    } catch (err) {
        showToast('Could not change status: ' + err.message, 'error');
    }
});

async function loadEnrolledStudents() {
    const tbody = el('studentsBody');
    renderSkeletonRows(tbody, ENROLL_COLS, 5);
    try {
        const res = await Api.getStudents({
            course: COURSE_ID,
            status: el('statusFilter').value,
            search: el('searchInput').value.trim(),
            page: enrollState.page,
            per_page: enrollState.perPage,
        });
        renderEnrolledRows(res.data);
        renderPagination({
            pagination: res.pagination,
            infoId: 'paginationInfo',
            pageId: 'paginationPage',
            prevId: 'prevPageBtn',
            nextId: 'nextPageBtn',
            onChange: (page) => {
                enrollState.page = page;
                loadEnrolledStudents();
            },
        });
    } catch (err) {
        renderErrorRow(tbody, ENROLL_COLS, err.message, loadEnrolledStudents);
    }
}

function renderEnrolledRows(students) {
    const tbody = el('studentsBody');
    if (students.length === 0) {
        renderEmptyRow(tbody, ENROLL_COLS, 'No students are enrolled in this course yet.');
        return;
    }

    tbody.innerHTML = students.map((s) => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500">${escapeHtml(s.id)}</td>
            <td class="px-4 py-3 font-semibold">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">${escapeHtml(s.student_code)}</td>
            <td class="px-4 py-3 text-gray-600">${escapeHtml(s.phone)}</td>
            <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-semibold ${statusBadgeClass[s.status] || ''}">${escapeHtml(s.status)}</span></td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${new Date(s.created_at).toLocaleDateString('en-IN')}</td>
            <td class="px-4 py-3">${studentActionButtons(s)}</td>
        </tr>
    `).join('');

    bindStudentActions(tbody, students);
}

function resetAndLoadStudents() {
    enrollState.page = 1;
    loadEnrolledStudents();
}

el('statusFilter').addEventListener('change', resetAndLoadStudents);
el('searchInput').addEventListener('input', debounce(resetAndLoadStudents, 350));

loadCourse();
loadEnrolledStudents();
