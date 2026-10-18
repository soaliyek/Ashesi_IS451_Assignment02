<?php
session_start();
header("Content-Type: application/json");

// Ensure user is authenticated
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$requestedRole = $input['role'] ?? '';

// Strict Negative Test Enforcement: 
// The server completely refuses any request to change roles, regardless of who is asking.
echo json_encode([
    'ok' => false,
    'message' => 'Access Denied: User roles cannot be modified.'
]);
exit;