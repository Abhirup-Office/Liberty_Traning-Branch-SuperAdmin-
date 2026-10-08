/**
 * certificate.js — shows the student's own individual certificate, if one has been issued.
 */

async function loadMyCertificate() {
    const content = el('myCertificateContent');
    try {
        const res = await StudentApi.getMyCertificate();
        const cert = res.data.certificate;

        if (!cert) {
            el('myCertificateCourseLabel').textContent = '';
            content.innerHTML = '<p class="text-sm text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-4 py-6 text-center">No certificate has been issued to you yet.</p>';
            return;
        }

        el('myCertificateCourseLabel').textContent = cert.course_name;
        const url = studentMyCertificateFileUrl();
        const isPdf = cert.mime_type === 'application/pdf';
        // Only one download format is ever offered, matching whatever was actually uploaded —
        // this project has no PNG<->PDF conversion capability, so a second format is never
        // fabricated (see api/student_certificate_file.php's docblock).
        const downloadLabel = isPdf ? 'Download PDF' : 'Download PNG';

        content.innerHTML = `
            <p class="text-sm font-semibold mb-3">${escapeHtml(cert.certificate_name)} — ${escapeHtml(cert.course_duration)}</p>
            ${isPdf
                ? `<iframe src="${url}" class="w-full rounded-lg border border-gray-200" style="height: 60vh;" title="My certificate"></iframe>`
                : `<img src="${url}" alt="Your certificate" class="w-full max-w-2xl rounded-lg border border-gray-200" />`}
            <div class="flex flex-wrap gap-2 mt-3">
                <a href="${url}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-700 border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                    View
                </a>
                <a href="${url}" download title="${downloadLabel}" aria-label="${downloadLabel}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 border border-red-200 rounded-lg px-3 py-1.5 hover:bg-red-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    ${downloadLabel}
                </a>
            </div>`;
    } catch (err) {
        content.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(err.message)}</p>`;
    }
}

loadMyCertificate();
