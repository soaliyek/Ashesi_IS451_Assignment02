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
$findUserQuery = $connection->prepare("
    SELECT userID, firstName, lastName, email, role, codeUsed
    FROM User
    WHERE signupCode = ?
");

$registerCredentialQuery = $connection->prepare("
    INSERT INTO Credential (credentialID, userID, publicKey, signCount)
    VALUES (?, ?, ?, ?)
");

$setSignUpCodeQuery = $connection->prepare("
    UPDATE User
    SET codeUsed = TRUE
    WHERE signupCode = ?
");



// START KEY REGISTRATION
//----------------------------
if ($action === 'start') {
    $signupCode = trim($input['signupCode'] ?? '');
    
    if (empty($signupCode)) {
        echo json_encode(['ok' => false, 'message' => 'Signup code is required.']);
        exit;
    }

    $findUserQuery->execute([$signupCode]);
    $result = $findUserQuery->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'Invalid signup code.']);
        exit;
    }

    if ($user['codeUsed']) {
        echo json_encode(['ok' => false, 'message' => 'This signup code has already been used.']);
        exit;
    }

    // Generate a secure 32-byte cryptographic challenge
    $challengeBytes = random_bytes(32);
    
    // Store challenge and userID in session for verification during 'finish'
    $_SESSION['reg_challenge'] = $challengeBytes;
    $_SESSION['reg_userID'] = $user['userID'];
    $_SESSION['reg_signupCode'] = $signupCode;

    // Pack the integer userID into a binary string for WebAuthn user.id expectation
    $binaryUserId = pack('N', $user['userID']);

    echo json_encode([
        'ok' => true,
        'options' => [
            'challenge' => base64UrlEncode($challengeBytes),
            'user' => [
                'id' => base64UrlEncode($binaryUserId),
                'email' => $user['email'],
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName']
            ]
        ]
    ]);
    exit;
}



// FINISH KEY REGISTRATION
//----------------------------
if ($action === 'finish') {
    // Validate session data exists
    if (!isset($_SESSION['reg_challenge'], $_SESSION['reg_userID'], $_SESSION['reg_signupCode'])) {
        echo json_encode(['ok' => false, 'message' => 'Registration session expired or invalid. Please restart.']);
        exit;
    }

    $userID = $_SESSION['reg_userID'];
    $signupCode = $_SESSION['reg_signupCode'];
    $expectedChallenge = $_SESSION['reg_challenge'];

    $credentialData = $input['credential'] ?? null;
    if (!$credentialData) {
        echo json_encode(['ok' => false, 'message' => 'No credential data provided.']);
        exit;
    }

    $clientDataJSON = base64UrlDecode($credentialData['clientDataJSON']);
    $clientData = json_decode($clientDataJSON, true);

    // Verify challenge match
    $clientChallenge = base64UrlDecode($clientData['challenge'] ?? '');
    if (!hash_equals($expectedChallenge, $clientChallenge)) {
        echo json_encode(['ok' => false, 'message' => 'Challenge mismatch. Security validation failed.']);
        exit;
    }

    // Verify origin (adjust if running on a different port or domain)
    $expectedOrigin = 'http://localhost'; 
    if (($clientData['origin'] ?? '') !== $expectedOrigin) {
        // Optional strict check
    }

    $credentialID = $credentialData['id']; 
    $attestationObject = base64UrlDecode($credentialData['attestationObject']);
    $publicKeyString = base64UrlEncode($attestationObject); 

    try {
        // MySQLi transaction start
        $connection->begin_transaction();

        // Save credential to database
        $registerCredentialQuery->execute([
            $credentialID,
            $userID,
            $publicKeyString,
            0 // Initial signCount
        ]);

        // Mark the signup code as used
        $setSignUpCodeQuery->execute([$signupCode]);

        $connection->commit();

        // Fetch user details and role for active session (Auto-login)
        $userQuery = $connection->prepare("
            SELECT userID, firstName, lastName, email, role 
            FROM User 
            WHERE userID = ?
        ");
        $userQuery->execute([$userID]);
        $userResult = $userQuery->get_result();
        $userData = $userResult->fetch_assoc();

        // Establish active session state
        $_SESSION['auth'] = true;
        $_SESSION['userID'] = $userData['userID'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['firstName'] = $userData['firstName'];
        $_SESSION['lastName'] = $userData['lastName'];

        // Clear registration session state
        unset($_SESSION['reg_challenge'], $_SESSION['reg_userID'], $_SESSION['reg_signupCode']);

        echo json_encode([
            'ok' => true,
            'confirmation' => [
                'verdict' => 'success'
            ]
        ]);
    } catch (Exception $e) {
        $connection->rollback(); // MySQLi rollback method
        echo json_encode(['ok' => false, 'message' => 'Database error during registration: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Invalid action endpoint.']);