<?php
/**
 * GET /api/google_callback.php?code=...&state=...
 * Handles Google's redirect back after consent: exchanges the auth code for
 * an access token, reads the verified user profile, and matches it against
 * BOTH the super_admins and branch_admins tables (super_admins first) to
 * decide which session role to start.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/google_config.php';
require __DIR__ . '/config.php'; // provides $pdo (also starts the session)
require __DIR__ . '/auth_guard.php';

/** Sends the browser back to the login page with an error code in the query string. */
function fail(string $errorCode): void
{
    header('Location: ../public/login.php?error=' . urlencode($errorCode));
    exit;
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$code || !$state || !isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    fail('invalid_request');
}
unset($_SESSION['oauth_state']);

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

$token = $client->fetchAccessTokenWithAuthCode($code);
if (isset($token['error'])) {
    fail('google_auth_failed');
}
$client->setAccessToken($token);

// verifyIdToken() validates the signature/audience and returns the claims.
$payload = $client->verifyIdToken();
if (!$payload) {
    fail('google_auth_failed');
}

$googleId = $payload['sub'];
$email = $payload['email'] ?? null;
$name = $payload['name'] ?? ($email ? explode('@', $email)[0] : 'Admin');
$emailVerified = $payload['email_verified'] ?? false;

if (!$email || !$emailVerified) {
    fail('unverified_email');
}

// --- Step 1: Super Admins ---
$stmt = $pdo->prepare("SELECT id, name, email FROM super_admins WHERE email = :email");
$stmt->execute([':email' => $email]);
$superAdmin = $stmt->fetch();

if ($superAdmin) {
    $pdo->prepare("UPDATE super_admins SET google_id = :google_id WHERE id = :id")
        ->execute([':google_id' => $googleId, ':id' => $superAdmin['id']]);

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id'] = (int) $superAdmin['id'];
    $_SESSION['admin_name'] = $superAdmin['name'] ?: $name;
    $_SESSION['role'] = 'super_admin';

    header('Location: ../public/' . dashboard_for_role('super_admin'));
    exit;
}

// --- Step 2: Branch Admins ---
$stmt = $pdo->prepare("SELECT id, branch_id, name, email FROM branch_admins WHERE email = :email");
$stmt->execute([':email' => $email]);
$branchAdmin = $stmt->fetch();

if ($branchAdmin) {
    $pdo->prepare("UPDATE branch_admins SET google_id = :google_id WHERE id = :id")
        ->execute([':google_id' => $googleId, ':id' => $branchAdmin['id']]);

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id'] = (int) $branchAdmin['id'];
    $_SESSION['admin_name'] = $branchAdmin['name'] ?: $name;
    $_SESSION['role'] = 'branch_admin';
    $_SESSION['branch_id'] = (int) $branchAdmin['branch_id'];

    header('Location: ../public/' . dashboard_for_role('branch_admin'));
    exit;
}

// --- Step 3: Neither table matched — invite-only portal, no auto-registration. ---
fail('unauthorized_email');
