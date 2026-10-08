/**
 * toast.js — lightweight toast notifications, styled to match the existing
 * Tailwind design (white card, colored left border, same font/shadow as
 * the rest of the dashboard). Injected purely via JS so no HTML changes
 * are needed beyond an optional #toastContainer anchor (auto-created if absent).
 */

function getToastContainer() {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'fixed top-4 right-4 z-[100] flex flex-col gap-2 w-80 max-w-[90vw]';
        document.body.appendChild(container);
    }
    return container;
}

const TOAST_STYLES = {
    success: { border: 'border-green-500', icon: '✅', text: 'text-green-700' },
    error: { border: 'border-red-500', icon: '⚠️', text: 'text-red-700' },
    info: { border: 'border-gray-400', icon: 'ℹ️', text: 'text-gray-700' },
};

function showToast(message, type = 'success', duration = 3500) {
    const style = TOAST_STYLES[type] || TOAST_STYLES.info;
    const container = getToastContainer();

    const toast = document.createElement('div');
    toast.className = `bg-white border-l-4 ${style.border} rounded-lg shadow-lg px-4 py-3 flex items-start gap-2 text-sm animate-[fadeIn_0.2s_ease-out]`;
    // escapeHtml() comes from student-shared.js, loaded after this file but always before
    // showToast() is actually called at runtime. Messages often embed user-controlled data,
    // so this is escaped the same as every other rendered value rather than trusted as plain text.
    toast.innerHTML = `
        <span>${style.icon}</span>
        <span class="${style.text} font-medium leading-snug">${escapeHtml(message)}</span>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
