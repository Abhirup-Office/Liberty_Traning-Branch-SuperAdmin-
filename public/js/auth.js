/**
 * auth.js — Login page logic.
 * Handles the Admin/Student tab switch, the Google OAuth redirect, the Admin
 * email/password form, the Student phone/password form, and both password toggles.
 * The Admin and Student forms each post to their own existing, unmodified endpoint
 * (api/login.php and api/student_login.php) exactly as they always have — this file only
 * adds the tab switch and a second form, it does not touch either authentication flow.
 */

if (window.lucide) lucide.createIcons();

const API_BASE = '../api';

// ---------------- Admin / Student tab switch ----------------
// Guarded like the Google button below: these elements only exist on this page, but keeping
// the same defensive pattern means this file degrades gracefully if the markup ever changes.

const adminTabBtn = document.getElementById('adminTabBtn');
const studentTabBtn = document.getElementById('studentTabBtn');
const adminLoginPanel = document.getElementById('adminLoginPanel');
const studentLoginPanel = document.getElementById('studentLoginPanel');

function setActiveTab(role) {
    const isAdmin = role === 'admin';
    if (adminLoginPanel) adminLoginPanel.classList.toggle('hidden', !isAdmin);
    if (studentLoginPanel) studentLoginPanel.classList.toggle('hidden', isAdmin);
    if (adminTabBtn) {
        adminTabBtn.setAttribute('aria-selected', String(isAdmin));
        adminTabBtn.className = `px-5 py-1.5 rounded-md text-sm font-semibold transition ${
            isAdmin ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'
        }`;
    }
    if (studentTabBtn) {
        studentTabBtn.setAttribute('aria-selected', String(!isAdmin));
        studentTabBtn.className = `px-5 py-1.5 rounded-md text-sm font-semibold transition ${
            !isAdmin ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'
        }`;
    }
}

if (adminTabBtn && studentTabBtn) {
    adminTabBtn.addEventListener('click', () => setActiveTab('admin'));
    studentTabBtn.addEventListener('click', () => setActiveTab('student'));
}

// ---------------- Google OAuth ----------------
// Guarded: this page currently has no "Continue with Google" button in its markup, so without
// this check, the lookup below would throw on page load and silently stop every later script
// in this file from running — including the email/password submit handler further down.
const googleLoginBtn = document.getElementById('googleLoginBtn');
if (googleLoginBtn) {
    googleLoginBtn.addEventListener('click', () => {
        window.location.href = `${API_BASE}/google_auth.php`;
    });
}

// ---------------- Password visibility toggle ----------------

const passwordInput = document.getElementById('password');
const toggleBtn = document.getElementById('togglePasswordBtn');

toggleBtn.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    toggleBtn.innerHTML = isHidden
        ? '<i data-lucide="eye-off" class="w-4 h-4"></i>'
        : '<i data-lucide="eye" class="w-4 h-4"></i>';
    if (window.lucide) lucide.createIcons();
});

// ---------------- Email/password login ----------------

const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    loginError.classList.add('hidden');

    const email = document.getElementById('email').value.trim();
    const password = passwordInput.value;
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
        const res = await fetch(`${API_BASE}/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
        });
        const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response' }));

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            loginError.textContent = data.message || 'Invalid credentials';
            loginError.classList.remove('hidden');
        }
    } catch (err) {
        loginError.textContent = 'Could not reach the server. Please try again.';
        loginError.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
    }
});

// ---------------- Student password visibility toggle ----------------

const studentPasswordInput = document.getElementById('studentPassword');
const studentToggleBtn = document.getElementById('studentTogglePasswordBtn');

if (studentPasswordInput && studentToggleBtn) {
    studentToggleBtn.addEventListener('click', () => {
        const isHidden = studentPasswordInput.type === 'password';
        studentPasswordInput.type = isHidden ? 'text' : 'password';
        studentToggleBtn.innerHTML = isHidden
            ? '<i data-lucide="eye-off" class="w-4 h-4"></i>'
            : '<i data-lucide="eye" class="w-4 h-4"></i>';
        if (window.lucide) lucide.createIcons();
    });
}

// ---------------- Student phone/password login ----------------
// Posts to the existing api/student_login.php exactly as student/js/login.js already does —
// same endpoint, same request shape, same server-side session/lockout logic, unmodified.

const studentLoginForm = document.getElementById('studentLoginForm');
const studentLoginError = document.getElementById('studentLoginError');

if (studentLoginForm) {
    studentLoginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        studentLoginError.classList.add('hidden');

        const phone = document.getElementById('studentPhone').value.trim();
        const password = studentPasswordInput.value;
        const submitBtn = document.getElementById('studentLoginBtn');
        submitBtn.disabled = true;

        try {
            const res = await fetch(`${API_BASE}/student_login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone, password }),
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response' }));

            if (data.success) {
                // api/student_login.php's redirect is relative to student/ (its own normal
                // location); this page lives in public/, so the student directory is prefixed.
                window.location.href = `../student/${data.redirect}`;
            } else {
                studentLoginError.textContent = data.message || 'Invalid phone number or password';
                studentLoginError.classList.remove('hidden');
            }
        } catch (err) {
            studentLoginError.textContent = 'Could not reach the server. Please try again.';
            studentLoginError.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
        }
    });
}
