<?php
/**
 * GET /api/google_auth.php
 * Kicks off the Google OAuth 2.0 flow by redirecting the browser to Google's
 * consent screen. Requires `composer require google/apiclient` to be run in
 * the project root first (generates vendor/autoload.php).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/google_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');

// A random state token guards against CSRF on the callback.
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$client->setState($state);

header('Location: ' . $client->createAuthUrl());
exit;
