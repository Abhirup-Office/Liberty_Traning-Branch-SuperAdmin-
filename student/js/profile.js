/**
 * profile.js — read-only My Profile. Every value is rendered as plain text.
 */

async function loadProfile() {
    try {
        const res = await StudentApi.getProfile();
        const s = res.data.student;

        el('studentName').textContent = `${s.first_name} ${s.last_name}`.trim();
        el('studentDisplayId').textContent = s.display_id || s.student_code;
        el('infoName').textContent = `${s.first_name} ${s.last_name}`.trim();
        el('infoPhone').textContent = s.phone;
        el('infoFather').textContent = s.father_name || 'Not provided';
        el('infoMother').textContent = s.mother_name || 'Not provided';
        el('infoDob').textContent = formatDate(s.date_of_birth);
        el('infoBranch').textContent = s.branch_name;
        el('infoCourse').textContent = `${s.course_name} (${s.duration})`;
        el('infoAddress').textContent = s.address || 'Not provided';

        const avatar = el('avatarContainer');
        if (s.photo_path) {
            avatar.innerHTML = `<img src="${studentPhotoUrl()}" alt="" class="w-16 h-16 rounded-full object-cover" />`;
        } else {
            avatar.textContent = ((s.first_name[0] || '') + (s.last_name[0] || '')).toUpperCase();
        }
    } catch (err) {
        showPageError(err.message);
    }
}

loadProfile();
