<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Ensure user is authenticated
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Security Check: Only Students should fetch their personal grades here
if ($_SESSION["role"] !== "STUDENT") {
    echo json_encode(['ok' => true, 'grades' => []]);
    exit;
}

try {
    // 1. Get the studentID corresponding to this user's userID
    $studentStmt = $connection->prepare("SELECT studentID FROM Student WHERE userID = ?");
    $studentStmt->execute([$_SESSION["userID"]]);
    $studentRes = $studentStmt->get_result()->fetch_assoc();

    if (!$studentRes) {
        echo json_encode(['ok' => false, 'message' => 'Student record not found.']);
        exit;
    }

    $studentID = $studentRes['studentID'];

    // 2. Fetch enrolled courses and grades for this student
    $gradesStmt = $connection->prepare("
        SELECT c.courseCode, c.courseTitle, e.grade
        FROM Enrollment e
        JOIN Course c ON e.courseCode = c.courseCode
        WHERE e.studentID = ?
    ");
    $gradesStmt->execute([$studentID]);
    $gradesResult = $gradesStmt->get_result();

    $grades = [];
    while ($row = $gradesResult->fetch_assoc()) {
        $grades[] = $row;
    }

    echo json_encode([
        'ok' => true,
        'grades' => $grades
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;