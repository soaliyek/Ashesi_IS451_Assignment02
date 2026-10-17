<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function registrationRespond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function registrationBase64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function registrationBase64UrlDecode(string $data): string|false
{
    if ($data === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $data)) {
        return false;
    }

    $decoded = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    return $decoded === false ? false : $decoded;
}

function registrationReadCborLength(string $data, int &$offset, int $additional): int
{
    if ($additional < 24) {
        return $additional;
    }

    $byteCount = match ($additional) {
        24 => 1,
        25 => 2,
        26 => 4,
        27 => 8,
        default => throw new InvalidArgumentException('Invalid CBOR length.'),
    };

    if ($offset + $byteCount > strlen($data)) {
        throw new InvalidArgumentException('Truncated CBOR data.');
    }

    $bytes = substr($data, $offset, $byteCount);
    $offset += $byteCount;

    if ($byteCount === 8) {
        $parts = unpack('Nhigh/Nlow', $bytes);
        if ($parts['high'] !== 0) {
            throw new InvalidArgumentException('CBOR value is too large.');
        }
        return $parts['low'];
    }

    if ($byteCount === 4) {
        return unpack('N', $bytes)[1];
    }

    if ($byteCount === 2) {
        return unpack('n', $bytes)[1];
    }

    return ord($bytes);
}

function registrationReadCbor(string $data, int &$offset): mixed
{
    if ($offset >= strlen($data)) {
        throw new InvalidArgumentException('Truncated CBOR data.');
    }

    $initial = ord($data[$offset++]);
    $major = $initial >> 5;
    $additional = $initial & 0x1f;

    if ($major === 7) {
        return match ($additional) {
            20 => false,
            21 => true,
            22 => null,
            default => throw new InvalidArgumentException('Unsupported CBOR simple value.'),
        };
    }

    $length = registrationReadCborLength($data, $offset, $additional);
    if ($length > strlen($data) - $offset && ($major === 2 || $major === 3)) {
        throw new InvalidArgumentException('Truncated CBOR string.');
    }

    return match ($major) {
        0 => $length,
        1 => -1 - $length,
        2, 3 => registrationReadCborString($data, $offset, $length),
        4 => registrationReadCborArray($data, $offset, $length),
        5 => registrationReadCborMap($data, $offset, $length),
        default => throw new InvalidArgumentException('Unsupported CBOR type.'),
    };
}

function registrationReadCborString(string $data, int &$offset, int $length): string
{
    $value = substr($data, $offset, $length);
    $offset += $length;
    return $value;
}

function registrationReadCborArray(string $data, int &$offset, int $length): array
{
    if ($length > 128) {
        throw new InvalidArgumentException('CBOR array is too large.');
    }

    $values = [];
    for ($i = 0; $i < $length; $i++) {
        $values[] = registrationReadCbor($data, $offset);
    }
    return $values;
}

function registrationReadCborMap(string $data, int &$offset, int $length): array
{
    if ($length > 128) {
        throw new InvalidArgumentException('CBOR map is too large.');
    }

    $values = [];
    for ($i = 0; $i < $length; $i++) {
        $key = registrationReadCbor($data, $offset);
        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentException('Invalid CBOR map key.');
        }
        $values[$key] = registrationReadCbor($data, $offset);
    }
    return $values;
}

function registrationGetRequestData(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        registrationRespond(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }

    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        registrationRespond(415, ['ok' => false, 'message' => 'Expected a JSON request.']);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = $GLOBALS['isHttps'] ? 'https' : 'http';
    if ($host === '' || $origin !== $scheme . '://' . $host) {
        registrationRespond(403, ['ok' => false, 'message' => 'Request origin was not accepted.']);
    }

    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || strlen($rawBody) > 65536) {
        registrationRespond(413, ['ok' => false, 'message' => 'Request body is too large.']);
    }
    $data = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        registrationRespond(400, ['ok' => false, 'message' => 'Invalid JSON request.']);
    }
    return $data;
}

function registrationParseCredential(array $credential, string $expectedChallenge, string $expectedOrigin, string $rpId): array
{
    if (($credential['type'] ?? null) !== 'public-key'
        || !is_string($credential['rawId'] ?? null)
        || !is_string($credential['id'] ?? null)
        || !is_array($credential['response'] ?? null)
        || !is_string($credential['response']['clientDataJSON'] ?? null)
        || !is_string($credential['response']['attestationObject'] ?? null)
    ) {
        throw new InvalidArgumentException('The browser returned an invalid credential.');
    }

    $rawId = registrationBase64UrlDecode($credential['rawId']);
    $clientDataBytes = registrationBase64UrlDecode($credential['response']['clientDataJSON']);
    $attestationBytes = registrationBase64UrlDecode($credential['response']['attestationObject']);
    if ($rawId === false
        || strlen($rawId) > 1023
        || !hash_equals(registrationBase64UrlEncode($rawId), $credential['id'])
        || $clientDataBytes === false
        || $attestationBytes === false
    ) {
        throw new InvalidArgumentException('The browser returned malformed credential data.');
    }

    $clientData = json_decode($clientDataBytes, true);
    if (!is_array($clientData)
        || json_last_error() !== JSON_ERROR_NONE
        || ($clientData['type'] ?? null) !== 'webauthn.create'
        || !is_string($clientData['challenge'] ?? null)
        || !hash_equals($expectedChallenge, $clientData['challenge'])
        || ($clientData['origin'] ?? null) !== $expectedOrigin
        || (array_key_exists('crossOrigin', $clientData) && $clientData['crossOrigin'] !== false)
    ) {
        throw new InvalidArgumentException('The browser challenge or origin did not match.');
    }

    $offset = 0;
    $attestation = registrationReadCbor($attestationBytes, $offset);
    if (!is_array($attestation)
        || $offset !== strlen($attestationBytes)
        || ($attestation['fmt'] ?? null) !== 'none'
        || !is_string($attestation['authData'] ?? null)
        || ($attestation['attStmt'] ?? null) !== []
    ) {
        throw new InvalidArgumentException('Unsupported or malformed attestation data.');
    }

    $authData = $attestation['authData'];
    if (strlen($authData) < 55
        || !hash_equals(hash('sha256', $rpId, true), substr($authData, 0, 32))
    ) {
        throw new InvalidArgumentException('The authenticator data did not match this site.');
    }

    $flags = ord($authData[32]);
    if (($flags & 0x41) !== 0x41 || ($flags & 0x04) !== 0x04) {
        throw new InvalidArgumentException('The authenticator did not verify the user and create a resident credential.');
    }

    $credentialLength = unpack('n', substr($authData, 53, 2))[1];
    $credentialStart = 55;
    $credentialEnd = $credentialStart + $credentialLength;
    if ($credentialLength === 0
        || $credentialEnd >= strlen($authData)
        || !hash_equals($rawId, substr($authData, $credentialStart, $credentialLength))
    ) {
        throw new InvalidArgumentException('The credential identifier did not match authenticator data.');
    }

    $coseOffset = $credentialEnd;
    $coseKey = registrationReadCbor($authData, $coseOffset);
    if (!is_array($coseKey) || $coseOffset !== strlen($authData)
        || ($coseKey[1] ?? null) !== 2
        || ($coseKey[3] ?? null) !== -7
        || ($coseKey[-1] ?? null) !== 1
        || !is_string($coseKey[-2] ?? null)
        || strlen($coseKey[-2]) !== 32
        || !is_string($coseKey[-3] ?? null)
        || strlen($coseKey[-3]) !== 32
    ) {
        throw new InvalidArgumentException('Only valid ES256 P-256 passkeys are supported.');
    }

    $subjectPublicKeyInfo = hex2bin('3039301306072a8648ce3d020106082a8648ce3d030107032200')
        . "\x04" . $coseKey[-2] . $coseKey[-3];
    $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    $publicKeyDetails = $publicKey === false ? false : openssl_pkey_get_details($publicKey);
    if ($publicKeyDetails === false || $publicKeyDetails['type'] !== OPENSSL_KEYTYPE_EC) {
        throw new InvalidArgumentException('The passkey public key is invalid.');
    }

    $signCount = unpack('N', substr($authData, 33, 4))[1];
    return [
        'credential_id' => registrationBase64UrlEncode($rawId),
        'public_key' => registrationBase64UrlEncode(substr($authData, $credentialEnd)),
        'sign_count' => $signCount,
    ];
}

try {
    $request = registrationGetRequestData();
    $action = $request['action'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $rpId = strtolower((string) parse_url('//' . $host, PHP_URL_HOST));
    if ($rpId === '') {
        registrationRespond(400, ['ok' => false, 'message' => 'Unable to determine this site host.']);
    }
    $origin = ($isHttps ? 'https' : 'http') . '://' . $host;

    if ($action === 'begin') {
        $signupCode = trim((string) ($request['signupCode'] ?? ''));
        if ($signupCode === '' || strlen($signupCode) > 100) {
            registrationRespond(400, ['ok' => false, 'message' => 'Enter a valid signup code.']);
        }

        require __DIR__ . '/../config/database.php';
        $statement = $connection->prepare(
            'SELECT userID, firstName, lastName, email, role FROM `User` WHERE signupCode = ? AND codeUsed = FALSE LIMIT 1'
        );
        $statement->bind_param('s', $signupCode);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();
        $connection->close();

        if (!$user) {
            unset($_SESSION['pending_registration']);
            registrationRespond(400, ['ok' => false, 'message' => 'That signup code is invalid or has already been used.']);
        }

        $challenge = registrationBase64UrlEncode(random_bytes(32));
        $userHandle = registrationBase64UrlEncode(random_bytes(32));
        $_SESSION['pending_registration'] = [
            'user_id' => (int) $user['userID'],
            'signup_code' => $signupCode,
            'challenge' => $challenge,
            'user_handle' => $userHandle,
            'email' => $user['email'],
            'role' => $user['role'],
            'created_at' => time(),
            'origin' => $origin,
            'rp_id' => $rpId,
        ];

        registrationRespond(200, [
            'ok' => true,
            'options' => [
                'challenge' => $challenge,
                'rp' => ['name' => 'University Portal', 'id' => $rpId],
                'user' => [
                    'id' => $userHandle,
                    'name' => $user['email'],
                    'displayName' => trim($user['firstName'] . ' ' . $user['lastName']),
                ],
                'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
                'timeout' => 120000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'residentKey' => 'required',
                    'requireResidentKey' => true,
                    'userVerification' => 'required',
                ],
            ],
        ]);
    }

    if ($action === 'finish') {
        $pending = $_SESSION['pending_registration'] ?? null;
        if (!is_array($pending)
            || time() - (int) ($pending['created_at'] ?? 0) > 300
            || ($pending['origin'] ?? null) !== $origin
            || ($pending['rp_id'] ?? null) !== $rpId
        ) {
            unset($_SESSION['pending_registration']);
            registrationRespond(400, ['ok' => false, 'message' => 'Your registration has expired. Please try again.']);
        }

        $credential = $request['credential'] ?? null;
        if (!is_array($credential)) {
            registrationRespond(400, ['ok' => false, 'message' => 'The browser did not return a passkey.']);
        }

        $verifiedCredential = registrationParseCredential(
            $credential,
            $pending['challenge'],
            $pending['origin'],
            $pending['rp_id']
        );

        require __DIR__ . '/../config/database.php';
        $connection->begin_transaction();
        try {
            $statement = $connection->prepare(
                'UPDATE `User` SET codeUsed = TRUE WHERE userID = ? AND signupCode = ? AND codeUsed = FALSE'
            );
            $statement->bind_param('is', $pending['user_id'], $pending['signup_code']);
            $statement->execute();
            if ($statement->affected_rows !== 1) {
                $statement->close();
                throw new DomainException('That signup code has already been used.');
            }
            $statement->close();

            $statement = $connection->prepare(
                'INSERT INTO Credential (credentialID, userID, publicKey, signCount) VALUES (?, ?, ?, ?)'
            );
            $statement->bind_param(
                'sisi',
                $verifiedCredential['credential_id'],
                $pending['user_id'],
                $verifiedCredential['public_key'],
                $verifiedCredential['sign_count']
            );
            $statement->execute();
            $statement->close();
            $connection->commit();
            $connection->close();
        } catch (Throwable $error) {
            $connection->rollback();
            $connection->close();
            throw $error;
        }

        session_regenerate_id(true);
        $_SESSION = [
            'userID' => $pending['user_id'],
            'email' => $pending['email'],
            'role' => $pending['role'],
            'authenticated' => true,
        ];
        registrationRespond(200, ['ok' => true, 'message' => 'Passkey registered successfully.']);
    }

    registrationRespond(400, ['ok' => false, 'message' => 'Unknown registration action.']);
} catch (DomainException $error) {
    registrationRespond(409, ['ok' => false, 'message' => $error->getMessage()]);
} catch (InvalidArgumentException $error) {
    registrationRespond(400, ['ok' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Passkey registration failed: ' . $error->getMessage());
    registrationRespond(500, ['ok' => false, 'message' => 'Registration could not be completed. Please try again.']);
}
