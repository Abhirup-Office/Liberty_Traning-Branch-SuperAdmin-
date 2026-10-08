/**
 * dashboard.js — read-only overview of the student's own non-financial profile.
 */

async function loadDashboard() {
    try {
        const res = await StudentApi.getProfile();
        const s = res.data.student;

        el('studentName').textContent = `${s.first_name} ${s.last_name}`.trim();
        el('studentDisplayId').textContent = s.display_id || s.student_code;
        el('studentCourse').textContent = `${s.course_name} · ${s.branch_name}`;

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

loadDashboard();
