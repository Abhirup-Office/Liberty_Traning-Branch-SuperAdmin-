/**
 * change_password.js
 */

const passwordForm = el('changePasswordForm');
const passwordErrorBox = el('passwordError');
const passwordSuccessBox = el('passwordSuccess');
const changePasswordBtn = el('changePasswordBtn');

passwordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (changePasswordBtn.disabled) return;
    passwordErrorBox.classList.add('hidden');
    passwordSuccessBox.classList.add('hidden');

    const currentPassword = passwordForm.elements.current_password.value;
    const newPassword = passwordForm.elements.new_password.value;
    const confirmPassword = passwordForm.elements.confirm_password.value;

    if (newPassword.length < 8) {
        passwordErrorBox.textContent = 'New password must be at least 8 characters.';
        passwordErrorBox.classList.remove('hidden');
        return;
    }
    if (newPassword !== confirmPassword) {
        passwordErrorBox.textContent = 'New password and confirmation do not match.';
        passwordErrorBox.classList.remove('hidden');
        return;
    }

    changePasswordBtn.disabled = true;
    changePasswordBtn.textContent = 'Saving…';
    try {
        await StudentApi.changePassword({ current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword });
        passwordSuccessBox.textContent = 'Password changed successfully.';
        passwordSuccessBox.classList.remove('hidden');
        passwordForm.reset();
        showToast('Password changed.', 'success');
    } catch (err) {
        passwordErrorBox.textContent = err.message;
        passwordErrorBox.classList.remove('hidden');
    } finally {
        changePasswordBtn.disabled = false;
        changePasswordBtn.textContent = 'Change Password';
    }
});
