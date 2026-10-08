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

/**
 * Multipart upload variant: unlike apiRequest(), the Content-Type header is left for the
 * browser to set (it must include the multipart boundary), only the CSRF header is added.
 */
async function apiUpload(path, formData) {
    const res = await fetch(`${API_BASE}/${path}`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken() },
        body: formData,
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

/**
 * Multipart upload with an upload-progress callback — fetch() has no reliable cross-browser
 * way to report upload progress, so this uses XMLHttpRequest instead, only for the one place
 * that needs a progress indicator (the certificate upload modal, for larger PDF files).
 */
function apiUploadWithProgress(path, formData, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${API_BASE}/${path}`);
        xhr.setRequestHeader('X-CSRF-Token', csrfToken());

        if (onProgress) {
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
            });
        }

        xhr.addEventListener('load', () => {
            if (xhr.status === 401) {
                window.location.href = 'login.php';
                reject(new Error('Your session has expired. Please sign in again.'));
                return;
            }
            let body;
            try {
                body = JSON.parse(xhr.responseText);
            } catch (e) {
                body = { success: false, message: 'Invalid server response' };
            }
            if (xhr.status >= 200 && xhr.status < 300 && body.success) {
                resolve(body);
            } else {
                reject(new Error(body.message || `Request failed (${xhr.status})`));
            }
        });
        xhr.addEventListener('error', () => reject(new Error('Could not reach the server. Please try again.')));
        xhr.send(formData);
    });
}

/** URL for <img src>; the browser's session cookie authenticates the request, same as any GET. */
function studentPhotoUrl(studentId) {
    return `${API_BASE}/get_student_photo.php?${toQuery({ id: studentId })}`;
}

function courseCertificateUrl(courseId) {
    return `${API_BASE}/get_course_certificate.php?${toQuery({ id: courseId })}`;
}

function studentCertificateFileUrl(studentId) {
    return `${API_BASE}/get_student_certificate_file.php?${toQuery({ student_id: studentId })}`;
}

const Api = {
    uploadStudentPhoto(studentId, file) {
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('photo', file);
        return apiUpload('upload_student_photo.php', formData);
    },
    uploadCourseCertificate(courseId, file) {
        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('certificate', file);
        return apiUpload('upload_course_certificate.php', formData);
    },
    getStudentCertificate(studentId) {
        return apiRequest(`get_student_certificate.php?${toQuery({ student_id: studentId })}`);
    },
    /** onProgress(percent) is called repeatedly while the file uploads; file may be null (metadata-only edit). */
    saveStudentCertificate(studentId, certificateName, courseDuration, file, onProgress) {
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('certificate_name', certificateName);
        formData.append('course_duration', courseDuration);
        if (file) formData.append('certificate', file);
        return apiUploadWithProgress('upload_student_certificate.php', formData, onProgress);
    },
    deleteStudentCertificate(studentId) {
        return apiRequest('delete_student_certificate.php', {
            method: 'POST',
            body: JSON.stringify({ student_id: studentId }),
        });
    },
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
    resetStudentPassword(id) {
        return apiRequest('reset_student_password.php', {
            method: 'POST',
            body: JSON.stringify({ id }),
        });
    },
    getPaymentHistory(studentId, params = {}) {
        return apiRequest(`get_payment_history.php?${toQuery({ student_id: studentId, ...params })}`);
    },
    createBranch(payload) {
        return apiRequest('create_branch.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    getAllBranches() {
        return apiRequest('get_all_branches.php');
    },
    setBranchStatus(id, status) {
        return apiRequest('set_branch_status.php', {
            method: 'POST',
            body: JSON.stringify({ id, status }),
        });
    },
    changeBranchAdmin(payload) {
        return apiRequest('change_branch_admin.php', {
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
