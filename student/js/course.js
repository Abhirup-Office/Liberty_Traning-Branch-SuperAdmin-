/**
 * course.js — My Course, read-only.
 */

async function loadCourse() {
    try {
        const res = await StudentApi.getProfile();
        const s = res.data.student;

        el('courseName').textContent = s.course_name;
        el('courseBranch').textContent = `${s.branch_name} campus`;
        el('courseDuration').textContent = s.duration;
        el('courseEnrolled').textContent = formatDate(s.created_at);
    } catch (err) {
        showPageError(err.message);
    }
}

loadCourse();
