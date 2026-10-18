<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Helper functions for Base64URL encoding/decoding
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

// Read incoming JSON request body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!$action) {
    echo json_encode(['ok' => false, 'message' => 'No action specified.']);
    exit;
}

// QUERIES (MySQLi Prepared Statements)
$findUserAndCredentialQuery = $connection->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.email, u.role, c.credentialID, c.publicKey, c.signCount
    FROM User u
    JOIN Credential c ON u.userID = c.userID
    WHERE u.email = ?
");

$updateSignCountQuery = $connection->prepare("
    UPDATE Credential
    SET signCount = ?
    WHERE credentialID = ?
");

// START KEY LOGIN
//----------------------------
if ($action === 'start') {
    $email = trim($input['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['ok' => false, 'message' => 'Email is required.']);
        exit;
    }

    $findUserAndCredentialQuery->execute([$email]);
    $result = $findUserAndCredentialQuery->get_result();
    $record = $result->fetch_assoc();

    if (!$record) {
        echo json_encode(['ok' => false, 'message' => 'No account or passkey found for this email.']);
        exit;
    }

    // Generate a secure 32-byte cryptographic challenge
    $challengeBytes = random_bytes(32);
    
    // Store session state for the 'finish' step
    $_SESSION['login_challenge'] = $challengeBytes;
    $_SESSION['login_userID'] = $record['userID'];
    $_SESSION['login_credentialID'] = $record['credentialID'];
    $_SESSION['login_signCount'] = $record['signCount'];

    echo json_encode([
        'ok' => true,
        'options' => [
            'challenge' => base64UrlEncode($challengeBytes),
            'credentialID' => $record['credentialID']
        ]
    ]);
    exit;
}

// FINISH LOGIN
//----------------------------
if ($action === 'finish') {
    // Validate session data exists
    if (!isset($_SESSION['login_challenge'], $_SESSION['login_userID'], $_SESSION['login_credentialID'])) {
        echo json_encode(['ok' => false, 'message' => 'Login session expired or invalid. Please restart.']);
        exit;
    }

    $userID = $_SESSION['login_userID'];
    $expectedCredentialID = $_SESSION['login_credentialID'];
    $expectedChallenge = $_SESSION['login_challenge'];
    $lastSignCount = $_SESSION['login_signCount'];

    $assertionData = $input['assertion'] ?? null;
    if (!$assertionData) {
        echo json_encode(['ok' => false, 'message' => 'No assertion data provided.']);
        exit;
    }

    $clientDataJSON = base64UrlDecode($assertionData['clientDataJSON']);
    $clientData = json_decode($clientDataJSON, true);

    // Verify challenge match
    $clientChallenge = base64UrlDecode($clientData['challenge'] ?? '');
    if (!hash_equals($expectedChallenge, $clientChallenge)) {
        echo json_encode(['ok' => false, 'message' => 'Challenge mismatch. Security validation failed.']);
        exit;
    }

    // Verify credential ID matches what we expected for this user
    if (($assertionData['id'] ?? '') !== $expectedCredentialID) {
        echo json_encode(['ok' => false, 'message' => 'Unknown credential ID provided.']);
        exit;
    }

    try {
        // 1. Increment signCount security counter
        $newSignCount = $lastSignCount + 1;
        $updateSignCountQuery->execute([$newSignCount, $expectedCredentialID]);

        // 2. Fetch full user details and role for the active session (RBAC support)
        $userQuery = $connection->prepare("
            SELECT userID, firstName, lastName, email, role 
            FROM User 
            WHERE userID = ?
        ");
        $userQuery->execute([$userID]);
        $userResult = $userQuery->get_result();
        $userData = $userResult->fetch_assoc();

        // 3. Establish active session state for the dashboard
        $_SESSION['auth'] = true;
        $_SESSION['userID'] = $userData['userID'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['firstName'] = $userData['firstName'];
        $_SESSION['lastName'] = $userData['lastName'];

        // Clear temporary login challenge session variables
        unset($_SESSION['login_challenge'], $_SESSION['login_userID'], $_SESSION['login_credentialID'], $_SESSION['login_signCount']);

        echo json_encode([
            'ok' => true,
            'confirmation' => [
                'verdict' => 'success'
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Database error during login: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Invalid action endpoint.']);
?>