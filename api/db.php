<?php
/**
 * Database connection factory. Free of HTTP side effects so both JSON endpoints
 * (via config.php) and HTML pages can share it.
 */

declare(strict_types=1);

// PHP's default timezone otherwise falls back to the server's OS/php.ini setting, which can
// silently disagree with MySQL's (also OS-derived) "today" — observed in testing as PHP and
// MySQL reporting different calendar dates at the same instant. Every "is this overdue / due
// today" comparison in this app (Student Portal installment countdown, WhatsApp reminder
// status) depends on PHP's and MySQL's notion of "today" agreeing, so it is pinned here to
// the business's own timezone rather than left to whatever the OS happens to be set to.
date_default_timezone_set('Asia/Kolkata');

const DB_HOST = 'localhost';
const DB_NAME = 'liberty_tms';
const DB_USER = 'root';
const DB_PASS = '';

/** Returns a PDO connection. Throws PDOException on failure; callers decide how to respond. */
function connect_database(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
