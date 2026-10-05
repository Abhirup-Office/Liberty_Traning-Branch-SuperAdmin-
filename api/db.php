<?php
/**
 * Database connection factory. Free of HTTP side effects so both JSON endpoints
 * (via config.php) and HTML pages can share it.
 */

declare(strict_types=1);

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
