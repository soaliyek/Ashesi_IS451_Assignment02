<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Ensure user is authenticated
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$role = $_SESSION["role"];
$userID = $_SESSION["userID"];

// Access Control: Students cannot view course rosters
if ($role === 'STUDENT') {
    echo json_encode(['ok' => false, 'message' => 'Access Denied: Students cannot view course rosters.']);
    exit;
}

try {
    if ($role === 'LECTURER') {
        // 1. Retrieve the lecturerID
        $stmt = $connection->prepare("SELECT lecturerID FROM Lecturer WHERE userID = ?");
        $stmt->execute([$userID]);
        $res = $stmt->get_result()->fetch_assoc();
        $lecturerID = $res['lecturerID'] ?? '';

        // 2. Fetch students and grades for courses assigned to this lecturer
        $rosterStmt = $connection->prepare("
            SELECT s.studentID, u.firstName, u.lastName, e.courseCode, e.grade
            FROM Assignment a
            JOIN Enrollment e ON a.courseCode = e.courseCode
            JOIN Student s ON e.studentID = s.studentID
            JOIN User u ON s.userID = u.userID
            WHERE a.lecturerID = ?
        ");
        $rosterStmt->execute([$lecturerID]);
        $result = $rosterStmt->get_result();

        $roster = [];
        while ($row = $result->fetch_assoc()) {
            $roster[] = $row;
        }

        echo json_encode(['ok' => true, 'roster' => $roster]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid role context.']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;