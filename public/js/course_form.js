/**
 * course_form.js — create and edit a course. Validation is repeated on the server (api/save_course.php).
 */

const courseForm = el('courseForm');
const saveBtn = el('saveCourseBtn');
const saveLabel = saveBtn.textContent;

function showPageError(message) {
    const box = el('pageError');
    box.textContent = message;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function clearErrors() {
    el('pageError').classList.add('hidden');
    courseForm.querySelectorAll('[data-error-for]').forEach((p) => {
        p.classList.add('hidden');
        p.textContent = '';
    });
}

function showFieldError(field, message) {
    const p = courseForm.querySelector(`[data-error-for="${field}"]`);
    if (p) {
        p.textContent = message;
        p.classList.remove('hidden');
    }
}

async function loadBranchChoices() {
    const select = el('courseBranch');
    try {
        const meta = await Api.getMeta();
        if (IS_BRANCH_ADMIN) {
            const own = meta.data.branches.find((b) => b.id === SESSION_BRANCH_ID);
            select.innerHTML = own
                ? `<option value="${escapeHtml(own.id)}">${escapeHtml(own.name)}</option>`
                : '<option value="">Your branch</option>';
            select.value = String(SESSION_BRANCH_ID);
        } else {
            select.insertAdjacentHTML('beforeend',
                meta.data.branches.map((b) => `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`).join(''));
        }
    } catch (err) {
        showPageError('Could not load branches: ' + err.message);
    }
}

function fillForm(course) {
    courseForm.elements.course_name.value = course.course_name;
    courseForm.elements.course_code.value = course.course_code || '';
    courseForm.elements.duration.value = course.duration;
    courseForm.elements.description.value = course.description || '';
    courseForm.elements.total_fee.value = course.total_fee;
    courseForm.elements.start_date.value = course.start_date || '';
    courseForm.elements.end_date.value = course.end_date || '';
    courseForm.elements.status.value = course.status;
    if (!IS_BRANCH_ADMIN) {
        courseForm.elements.branch_id.value = course.branch_id ?? '';
    }
}

async function loadCourseForEdit() {
    try {
        const res = await Api.getCourse(COURSE_ID);
        fillForm(res.data.course);
        el('formLoading').classList.add('hidden');
        courseForm.classList.remove('hidden');
    } catch (err) {
        el('formLoading').classList.add('hidden');
        showPageError(err.message);
    }
}

function readValues() {
    const f = courseForm.elements;
    return {
        id: COURSE_ID,
        course_name: f.course_name.value.trim(),
        course_code: f.course_code.value.trim().toUpperCase(),
        description: f.description.value.trim(),
        duration: f.duration.value.trim(),
        total_fee: f.total_fee.value === '' ? 0 : Number(f.total_fee.value),
        start_date: f.start_date.value,
        end_date: f.end_date.value,
        status: f.status.value,
        branch_id: IS_BRANCH_ADMIN ? undefined : (f.branch_id.value ? Number(f.branch_id.value) : null),
    };
}

function validate(values) {
    let ok = true;
    const rules = [
        ['course_name', values.course_name.length >= 3, 'Course name must be at least 3 characters.'],
        ['course_code', COURSE_ID !== null || /^[A-Z0-9-]{2,20}$/.test(values.course_code), 'Use 2 to 20 letters, digits or hyphens.'],
        ['duration', values.duration.length > 0, 'Duration is required.'],
        ['total_fee', values.total_fee >= 0, 'Fee cannot be negative.'],
        ['branch_id', IS_BRANCH_ADMIN || !!values.branch_id, 'Select a branch.'],
    ];
    rules.forEach(([field, passes, message]) => {
        if (!passes) {
            showFieldError(field, message);
            ok = false;
        }
    });
    if (values.start_date && values.end_date && values.end_date < values.start_date) {
        showFieldError('end_date', 'End date cannot be before the start date.');
        ok = false;
    }
    return ok;
}

async function handleSubmit(e) {
    e.preventDefault();
    if (saveBtn.disabled) return;
    clearErrors();

    const values = readValues();
    if (!validate(values)) return;

    if (values.branch_id === undefined) delete values.branch_id;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving…';
    try {
        await Api.saveCourse(values);
        sessionStorage.setItem('flashMessage', COURSE_ID ? 'Course updated successfully.' : 'Course created successfully.');
        window.location.href = 'courses.php';
    } catch (err) {
        showPageError(err.message);
        saveBtn.disabled = false;
        saveBtn.textContent = saveLabel;
    }
}

courseForm.addEventListener('submit', handleSubmit);

loadBranchChoices().then(() => {
    if (COURSE_ID !== null) {
        loadCourseForEdit();
    } else {
        el('courseBranch').value = IS_BRANCH_ADMIN ? String(SESSION_BRANCH_ID) : '';
    }
});
