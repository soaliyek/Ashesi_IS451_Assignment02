<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit();
}


// ======================| COLLECT REQUEST PROPERTIES
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";



// QUERIES
//-----------------------
// Find the user with this signupCode
$findUserQuery = $connection->prepare("
    SELECT userID, firstName, lastName, email, codeUsed
    FROM User
    WHERE signupCode = ?
");

// Save the new Credential
$registerCredentialQuery = $connection->prepare("
    INSERT INTO Credential (credentialID, userID, publicKey, signCount)
    VALUES (?, ?, ?, ?)
");

// Set signUpCodeUsed to True
$setSignUpCodeQuery = $connection->prepare("
    UPDATE User
    SET codeUsed = TRUE
    WHERE signupCode = ?
");
//-------------------------











// ======================| VALIDATION
if ($email === "" || $password === "") {
    echo json_encode(["status" => "error", "message" => "Please fill all fields."]);
    exit();
}

if (!str_ends_with($email, "@ashesi.edu.gh")) {
    echo json_encode(["status" => "error", "message" => "Invalid Ashesi email."]);
    exit();
}



// ======================| FIND USER
$query = $connection->prepare("
    SELECT userId, fName, lName, email, passwordHash, role
    FROM Users
    WHERE email = ?
");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No account matches this email."]);
    exit();
}

$user = $result->fetch_assoc();


// ======================| VERIFY PASSWORD
if (!password_verify($password, $user["passwordHash"])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password."]);
    exit();
}


// ======================| LOGIN SUCCESS: CREATE SESSION
$_SESSION["auth"] = true;
$_SESSION["userId"] = $user["userId"];
$_SESSION["fName"] = $user["fName"];
$_SESSION["lName"] = $user["lName"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $user["role"];

if ($user["role"] == "student") {

    $studentQuery = $connection->prepare("
        SELECT studentId
        FROM Students
        WHERE userId = ?
    ");
    $studentQuery->bind_param("i", $user["userId"]);
    $studentQuery->execute();
    $myResult = $studentQuery->get_result();
    $student = $myResult->fetch_assoc();
    $_SESSION["studentId"] = $student["studentId"];

} else {

    $facultyQuery = $connection->prepare("
        SELECT facultyId
        FROM Faculty
        WHERE userId = ?
    ");
    $facultyQuery->bind_param("i", $user["userId"]);
    $facultyQuery->execute();
    $myResult = $facultyQuery->get_result();
    $faculty = $myResult->fetch_assoc();
    $_SESSION["facultyId"] = $faculty["facultyId"];
}



// ======================| SEND JSON RESPONSE
echo json_encode([
    "status" => "success",
    "message" => "Login successful!",
    "role" => $user["role"]
]);
exit();
?>