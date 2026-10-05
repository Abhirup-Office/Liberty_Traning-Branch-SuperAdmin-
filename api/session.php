<?php
/**
 * Starts the PHP session with hardened cookie settings.
 * Loaded with require_once by every entry point (config.php, auth_guard.php),
 * so it must only contain executable setup, no declarations.
 */

declare(strict_types=1);

const SESSION_LIFETIME_SECONDS = 28800; // 8 hours

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECONDS);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
