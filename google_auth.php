<?php
session_start();
header('Content-Type: application/json');

// This endpoint is referenced by optional Google Sign-In UI.
// If you are not using Google authentication, the register/login flow should still work.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

echo json_encode([
    'success' => false,
    'message' => 'Google authentication is not configured on this server yet.',
    'debug' => [
        'received' => is_array($payload) ? array_keys($payload) : null,
    ]
]);

