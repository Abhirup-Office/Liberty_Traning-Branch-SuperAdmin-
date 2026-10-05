/**
 * auth-session.js — shared session logic for protected pages.
 * Wires up any Logout button (id="logoutBtn" / "logoutBtnMobile") to
 * destroy the server-side session and send the admin back to login.
 */

async function handleLogout() {
    try {
        const res = await fetch('../api/logout.php', { method: 'POST' });
        const data = await res.json().catch(() => ({ success: true, redirect: 'login.php' }));
        window.location.href = data.redirect || 'login.php';
    } catch (err) {
        // Even if the request fails, send the admin back to the login screen.
        window.location.href = 'login.php';
    }
}

['logoutBtn', 'logoutBtnMobile'].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn) btn.addEventListener('click', handleLogout);
});
