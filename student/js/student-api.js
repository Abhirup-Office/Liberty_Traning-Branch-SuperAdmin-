/**
 * student-api.js — fetch() wrappers for the student-portal API only.
 * Deliberately has no function that calls an admin endpoint (add/edit/delete student,
 * record payment, etc.) — even if a student could somehow forge such a request, the
 * student session (cookie LF_STUDENT_SESSION) is not the admin session an admin endpoint
 * checks for, so it would be refused server-side regardless. This file just doesn't offer
 * the capability in the first place.
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

const StudentApi = {
    login(phone, password) {
        return apiRequest('student_login.php', {
            method: 'POST',
            body: JSON.stringify({ phone, password }),
        });
    },
    logout() {
        return apiRequest('student_logout.php', { method: 'POST' });
    },
    getProfile() {
        return apiRequest('student_profile.php');
    },
    changePassword(payload) {
        return apiRequest('student_change_password.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },
    getMyCertificate() {
        return apiRequest('student_my_certificate.php');
    },
};

/** Multipart upload: Content-Type is left for the browser to set (needs the boundary). */
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

function studentPhotoUrl() {
    return `${API_BASE}/student_photo.php`;
}

function studentCertificateUrl() {
    return `${API_BASE}/student_certificate.php`;
}

/** The student's own INDIVIDUAL certificate — separate from the sample certificate above. */
function studentMyCertificateFileUrl() {
    return `${API_BASE}/student_certificate_file.php`;
}
