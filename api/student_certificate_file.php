<?php
/**
 * GET /api/student_certificate_file.php
 * Streams the LOGGED-IN student's own individual certificate. Takes no id parameter at all —
 * identity comes only from the session, so there is no id to swap to reach another student's
 * certificate; the request itself cannot express that.
 *
 * Only one download/view link is ever offered to the student, matching whichever file type
 * (PNG or PDF) was actually uploaded — this project has no PNG<->PDF conversion capability
 * (no Imagick/Ghostscript available), so a second format is never fabricated or offered.
 */

declare(strict_types=1);
require __DIR__ . '/student_config.php';
require __DIR__ . '/student_auth_guard.php';
require __DIR__ . '/storage.php';
$student = require_student_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$stmt = $pdo->prepare('SELECT storage_provider, storage_key, mime_type FROM student_certificates WHERE student_id = :id');
$stmt->execute([':id' => $student['id']]);
$cert = $stmt->fetch();
if (!$cert) {
    send_json(['success' => false, 'message' => 'No certificate has been issued to you yet'], 404);
}

storage_stream($cert['storage_provider'], $cert['storage_key'], $cert['mime_type']);
