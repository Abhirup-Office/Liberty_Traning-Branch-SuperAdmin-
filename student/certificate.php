<?php
/**
 * Student Portal — Certificate: the student's own individual certificate, issued to them for
 * their specific course. Read-only; students cannot upload or replace it themselves.
 */

declare(strict_types=1);
require __DIR__ . '/../api/student_auth_guard.php';
$student = require_student_login();

$pageTitle = 'Certificate';
$activeNav = 'certificate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>" />
<title>Liberty Training — Certificate</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 text-gray-800">
<?php require __DIR__ . '/../templates/student_layout_top.php'; ?>

            <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 sm:p-6">
                <h2 class="font-bold mb-1">My Certificate</h2>
                <p class="text-xs text-gray-500 mb-5" id="myCertificateCourseLabel">—</p>
                <div id="myCertificateContent"><p class="text-sm text-gray-400">Loading…</p></div>
            </section>

<?php
require __DIR__ . '/../templates/student_layout_bottom.php';
// Cache-busting: the version query string changes automatically whenever a script file is
// actually edited, so a browser that already cached an older copy (e.g. from before the "My
// Certificate" section existed) is forced to fetch the current one instead of silently
// running stale code that never updates these specific elements.
$assetVer = static fn (string $rel): string => (string) (@filemtime(__DIR__ . '/' . $rel) ?: time());
?>
<script src="js/toast.js?v=<?= $assetVer('js/toast.js') ?>"></script>
<script src="js/student-api.js?v=<?= $assetVer('js/student-api.js') ?>"></script>
<script src="js/student-shared.js?v=<?= $assetVer('js/student-shared.js') ?>"></script>
<script src="js/certificate.js?v=<?= $assetVer('js/certificate.js') ?>"></script>
</body>
</html>
