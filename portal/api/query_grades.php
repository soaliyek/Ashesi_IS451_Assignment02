<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Ensure user is authenticated
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$targetStudentID = trim($_GET['studentID'] ?? '');
if (empty($targetStudentID)) {
    echo json_encode(['ok' => false, 'message' => 'Student ID is required.']);
    exit;
}

$role = $_SESSION["role"];
$userID = $_SESSION["userID"];

try {
    if ($role === 'STUDENT') {
        // Retrieve the logged-in student's institutional studentID
        $stmt = $connection->prepare("SELECT studentID FROM Student WHERE userID = ?");
        $stmt->execute([$userID]);
        $res = $stmt->get_result()->fetch_assoc();
        $myStudentID = $res['studentID'] ?? '';

        // Negative Test Enforcement (T3): Students cannot query another student's records
        if ($targetStudentID !== $myStudentID) {
            echo json_encode(['ok' => false, 'message' => 'Access Denied: You can only query your own grades.']);
            exit;
        }

        // Fetch all enrolled courses and grades for this student
        $gradesStmt = $connection->prepare("
            SELECT c.courseCode, c.courseTitle, e.grade
            FROM Enrollment e
            JOIN Course c ON e.courseCode = c.courseCode
            WHERE e.studentID = ?
        ");
        $gradesStmt->execute([$targetStudentID]);
        $result = $gradesStmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }

        echo json_encode(['ok' => true, 'records' => $records]);
        exit;

    } else if ($role === 'LECTURER') {
        // Retrieve the logged-in lecturer's lecturerID
        $stmt = $connection->prepare("SELECT lecturerID FROM Lecturer WHERE userID = ?");
        $stmt->execute([$userID]);
        $res = $stmt->get_result()->fetch_assoc();
        $lecturerID = $res['lecturerID'] ?? '';

        // Fetch student's grades restricted ONLY to courses assigned to this lecturer
        $gradesStmt = $connection->prepare("
            SELECT c.courseCode, c.courseTitle, e.grade
            FROM Enrollment e
            JOIN Course c ON e.courseCode = c.courseCode
            JOIN Assignment a ON e.courseCode = a.courseCode
            WHERE e.studentID = ? AND a.lecturerID = ?
        ");
        $gradesStmt->execute([$targetStudentID, $lecturerID]);
        $result = $gradesStmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }

        echo json_encode(['ok' => true, 'records' => $records]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid role context.']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;