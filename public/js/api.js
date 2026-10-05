/**
 * api.js — thin fetch() wrappers around the PHP backend.
 * All functions return the parsed JSON body and throw an Error on failure.
 */

const API_BASE = '../api';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function toQuery(params = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, value);
        }
    });
    return query.toString();
}

async function apiRequest(path, options = {}) {
    const res = await fetch(`${API_BASE}/${path}`, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken(),
            ...(options.headers || {}),
        },
    });

    if (res.status === 401) {
        window.location.href = 'login.php';
        throw new Error('Your session has expired. Please sign in again.');
    }

    const body = await res.json().catch(() => ({ success: false, message: 'Invalid server response' }));
    if (!res.ok || !body.success) {
        throw new Error(body.message || `Request failed (${res.status})`);
    }
    return body;
}

const Api = {
    /** filters: { branch, status, course, search, page, per_page } — empty values are omitted. */
    getStudents(filters = {}) {
        return apiRequest(`get_students.php?${toQuery(filters)}`);
    },
    getKpis(branchId = 'all') {
        return apiRequest(`get_kpis.php?${toQuery({ branch: branchId })}`);
    },
    getMeta() {
        return apiRequest('get_meta.php');
    },
    /** params: { branch, payment_mode, search, page, per_page, limit } */
    getTransactions(params = {}) {
        return apiRequest(`get_transactions.php?${toQuery(params)}`);
    },
    getCourses(params = {}) {
        return apiRequest(`get_courses.php?${toQuery(params)}`);
    },
    getCourse(id) {
        return apiRequest(`get_course.php?${toQuery({ id })}`);
    },
    saveCourse(payload) {
        return apiRequest('save_course.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    setCourseStatus(id, status) {
        return apiRequest('set_course_status.php', {
            method: 'POST',
            body: JSON.stringify({ id, status }),
        });
    },
    updateStudent(payload) {
        return apiRequest('update_student.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    requestReminder(studentId) {
        return apiRequest('send_reminder.php', {
            method: 'POST',
            body: JSON.stringify({ student_id: studentId }),
        });
    },
    deleteCourse(id) {
        return apiRequest('delete_course.php', {
            method: 'POST',
            body: JSON.stringify({ id }),
        });
    },
    getBranchManagers(branchId) {
        return apiRequest(`get_branch_managers.php?${toQuery({ branch_id: branchId })}`);
    },
    getStudent(id) {
        return apiRequest(`get_student.php?${toQuery({ id })}`);
    },
    createBranch(payload) {
        return apiRequest('create_branch.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    setViewBranch(branchId) {
        return apiRequest('set_view_branch.php', {
            method: 'POST',
            body: JSON.stringify({ branch_id: branchId }),
        });
    },
    addStudent(payload) {
        return apiRequest('add_student.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    deleteStudent(id) {
        return apiRequest('delete_student.php', {
            method: 'POST',
            body: JSON.stringify({ id }),
        });
    },
    recordPayment(payload) {
        return apiRequest('record_payment.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
};
