<?php
/**
 * Database connection (PDO) + shared API bootstrap.
 * Included at the top of every endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/json_helpers.php';
install_safe_error_handler();

// This API is only ever called from this same application's own frontend JS (relative
// paths, same origin) — no CORS headers are needed, and sending a wildcard one for every
// response was pure unused attack surface.
header('Content-Type: application/json; charset=utf-8');

// Preflight requests never need a DB connection or a body.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = connect_database();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

/** Derives a status label from fee totals. */
function derive_status(float $totalFee, float $amountPaid): string
{
    if ($amountPaid >= $totalFee) {
        return 'Paid in Full';
    }
    // Anything with a very small payment relative to the fee is flagged Overdue.
    if ($amountPaid < $totalFee * 0.3) {
        return 'Overdue';
    }
    return 'Partial';
}
