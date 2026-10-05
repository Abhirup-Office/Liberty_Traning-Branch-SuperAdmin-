<?php
/**
 * Database connection (PDO) + shared API bootstrap.
 * Included at the top of every endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

/** Reads and decodes a JSON request body into an associative array. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
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

function send_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}
