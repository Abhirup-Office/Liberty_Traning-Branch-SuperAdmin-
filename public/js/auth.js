/**
 * auth.js — Admin Portal Login logic.
 * Handles the Google OAuth redirect, the email/password fallback form,
 * and the password visibility toggle.
 */

if (window.lucide) lucide.createIcons();

const API_BASE = '../api';

// ---------------- Google OAuth ----------------

document.getElementById('googleLoginBtn').addEventListener('click', () => {
    window.location.href = `${API_BASE}/google_auth.php`;
});

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
