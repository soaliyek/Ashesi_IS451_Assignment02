<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

header("Content-Type: application/json");

// Return JSON for fetch()
echo json_encode([
    "status" => "success",
    "message" => "Logged out successfully"
]);
exit();

?>