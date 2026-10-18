<?php
session_start();
require("../config/database.php");

header("Content-Type: application/json");

// Ensure user is authenticated
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$targetStudentID = trim($input['studentID'] ?? '');
$courseCode = trim($input['courseCode'] ?? '');
$newGrade = trim($input['grade'] ?? '');

if (empty($targetStudentID) || empty($courseCode) || $newGrade === '') {
    echo json_encode(['ok' => false, 'message' => 'Student ID, course code, and grade are required.']);
    exit;
}

$role = $_SESSION["role"];
$userID = $_SESSION["userID"];

// Negative Test Enforcement (T4): Students cannot edit grades
if ($role === 'STUDENT') {
    echo json_encode(['ok' => false, 'message' => 'Access Denied: Students are not permitted to modify grades.']);
    exit;
}

try {
    if ($role === 'LECTURER') {
        // Retrieve the lecturerID
        $stmt = $connection->prepare("SELECT lecturerID FROM Lecturer WHERE userID = ?");
        $stmt->execute([$userID]);
        $res = $stmt->get_result()->fetch_assoc();
        $lecturerID = $res['lecturerID'] ?? '';

        // Verify that this lecturer is assigned to teach the requested course
        $assignStmt = $connection->prepare("
            SELECT 1 FROM Assignment 
            WHERE lecturerID = ? AND courseCode = ?
        ");
        $assignStmt->execute([$lecturerID, $courseCode]);
        if ($assignStmt->get_result()->num_rows === 0) {
            echo json_encode(['ok' => false, 'message' => 'Access Denied: You are not assigned to teach this course.']);
            exit;
        }

        // Perform the grade update in the Enrollment table
        $updateStmt = $connection->prepare("
            UPDATE Enrollment 
            SET grade = ? 
            WHERE studentID = ? AND courseCode = ?
        ");
        $updateStmt->execute([$newGrade, $targetStudentID, $courseCode]);

        echo json_encode(['ok' => true, 'message' => 'Grade updated successfully!']);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid role context.']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;