<?php
/**
 * Starts the PHP session with hardened cookie settings.
 * Loaded with require_once by every entry point (config.php, auth_guard.php),
 * so it must only contain executable setup, no declarations.
 */

declare(strict_types=1);

const SESSION_LIFETIME_SECONDS = 28800; // 8 hours

/**
 * True when this request actually arrived over HTTPS. Checked rather than assumed so the
 * same code runs unchanged in local HTTP development and in an HTTPS production deployment —
 * no hardcoded domain or environment flag to keep in sync between the two.
 *   - $_SERVER['HTTPS']: set directly by Apache/PHP's own built-in web server when it
 *     terminates TLS itself.
 *   - X-Forwarded-Proto: set when a reverse proxy (nginx, a load balancer, a PaaS) terminates
 *     TLS in front of PHP. This header can be forged by a client, so it must only be trusted
 *     when the proxy in front of PHP is configured to always set/overwrite it itself — true of
 *     every standard reverse-proxy TLS setup, but worth keeping in mind if that ever changes.
 */
function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME_SECONDS);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Secure only when the request is actually HTTPS — forcing it unconditionally would
        // stop the browser from ever sending the cookie back over today's local HTTP setup.
        'secure' => is_https_request(),
    ]);
    session_start();
}
