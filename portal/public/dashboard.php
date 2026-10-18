<?php
session_start();

// Strict session security guard
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    header("Location: ../authentication/signin.php");
    exit;
}

require("../config/database.php");

// Fetch the specific institutional ID (studentID or lecturerID) based on role
$specificID = "";
if ($_SESSION["role"] === "STUDENT") {
    $stmt =$connection->prepare("SELECT studentID FROM Student WHERE userID = ?");
    $stmt->execute([$_SESSION["userID"]]);
    $res =$stmt->get_result()->fetch_assoc();
    $specificID =$res['studentID'] ?? '';
} else if ($_SESSION["role"] === "LECTURER") {
    $stmt =$connection->prepare("SELECT lecturerID FROM Lecturer WHERE userID = ?");
    $stmt->execute([$_SESSION["userID"]]);
    $res =$stmt->get_result()->fetch_assoc();
    $specificID =$res['lecturerID'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Portal - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f49; }
        header { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        #profile { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        nav button { padding: 8px 15px; margin-right: 5px; cursor: pointer; }
        .subdivision { margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .row { display: flex; gap: 15px; margin-bottom: 10px; align-items: center; }
    </style>
</head>
<body>
    <header>
        <div id="profile">
            <div id="profileDetails">
                <p><strong>Name:</strong> <span id="name"><?php echo htmlspecialchars($_SESSION["firstName"] . " " . $_SESSION["lastName"]); ?></span></p>
                <p><strong>ID:</strong> <span id="id"><?php echo htmlspecialchars($specificID); ?></span></p>
                
                <!-- Role modification test form (Server will deny this!) -->
                <form id="roleForm" style="display:inline;" onsubmit="handleRoleChange(event)">
                    <label>Role:</label>
                    <input type="text" id="roleInput" value="<?php echo htmlspecialchars($_SESSION["role"]); ?>">
                    <button type="submit">Set Role</button>
                </form>
                <p id="roleFeedback" style="color: red; font-size: 0.9em;"></p>
            </div>
            <div id="logout">
                <p><a href="../api/logout.php">Logout</a></p>
            </div>
        </div>
        <nav>
            <button type="button" id="gradesSwitch" onclick="doSwitch('grades')">Grades</button>
            <button type="button" id="editSwitch" onclick="doSwitch('edit')">Edit Grades</button>
            <button type="button" id="courseSwitch" onclick="doSwitch('course')">Course Roster</button>
        </nav>
    </header>

    <!-- SUBDIVISION 1: Grades -->
    <div id="grades" class="subdivision">
        <h3>My Grades</h3>
        <div id="gradesContent">Loading grades...</div>
    </div>

    <!-- SUBDIVISION 2: Edit Grades -->
    <div id="editGrades" class="subdivision" style="display: none;">
        <h3>Edit / Query Student Grades</h3>
        <div class="row">
            <input type="text" id="queryStudentID" placeholder="Enter Student ID">
            <button type="button" onclick="queryStudentGrades()">Query</button>
        </div>
        <div id="editGradesContainer" style="margin-top: 15px;">
            <!-- Dynamic grade inputs populated here -->
        </div>
    </div>

    <!-- SUBDIVISION 3: Course Roster -->
    <div id="courseRoster" class="subdivision" style="display: none;">
        <h3>Assigned Course Roster</h3>
        <div id="rosterContent">Loading roster...</div>
    </div>

    <script>
        // Fix tab switcher (fixed mismatch with 'grades' argument)
        function doSwitch(arg){
            const gradesPart = document.getElementById("grades");
            const editPart = document.getElementById("editGrades");
            const coursePart = document.getElementById("courseRoster");

            gradesPart.style.display = (arg === "grades") ? "block" : "none";
            editPart.style.display = (arg === "edit") ? "block" : "none";
            coursePart.style.display = (arg === "course") ? "block" : "none";

            if (arg === "grades") loadMyGrades();
            if (arg === "course") loadCourseRoster();
        }

        // Negative Test Check: Attempting to change role should be rejected by server API
        async function handleRoleChange(event) {
            event.preventDefault();
            const newRole = document.getElementById('roleInput').value.trim();
            const feedback = document.getElementById('roleFeedback');

            const res = await fetch('../api/update_role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: newRole })
            });
            const data = await res.json();
            feedback.textContent = data.message;
        }

        // Placeholder fetch functions for dashboard data
        async function loadMyGrades() {
            const container = document.getElementById('gradesContent');
            const res = await fetch('../api/get_grades.php');
            const data = await res.json();
            
            if (!data.ok) {
                container.innerHTML = `<p style="color:red;">${data.message}</p>`;
                return;
            }

            if (data.grades.length === 0) {
                container.innerHTML = "<p>No grades found or you are a lecturer.</p>";
                return;
            }

            let html = "";
            data.grades.forEach(g => {
                html += `<div class="row"><p><strong>${g.courseCode}</strong></p><p>${g.courseTitle}</p><p>Grade: <strong>${g.grade}</strong></p></div>`;
            });
            container.innerHTML = html;
        }

        async function queryStudentGrades() {
            const studentID = document.getElementById('queryStudentID').value.trim();
            const container = document.getElementById('editGradesContainer');
            
            const res = await fetch(`../api/query_grades.php?studentID=${encodeURIComponent(studentID)}`);
            const data = await res.json();

            if (!data.ok) {
                container.innerHTML = `<p style="color:red;">${data.message}</p>`;
                return;
            }

            let html = "";
            data.records.forEach(r => {
                html += `
                    <div class="row" style="gap: 20px;">
                        <span>${r.courseCode} - ${r.courseTitle}</span>
                        <input type="text" id="grade_${r.courseCode}_${studentID}" value="${r.grade}">
                        <button type="button" onclick="updateGrade('${studentID}', '${r.courseCode}')">Set Grade</button>
                    </div>`;
            });
            container.innerHTML = html;
        }

        // globally accessible for inline onclick
        window.updateGrade = async function(studentID, courseCode) {
            const inputField = document.getElementById(`grade_${courseCode}_${studentID}`);
            if (!inputField) {
                alert("Input field not found!");
                return;
            }

            const newGrade = inputField.value.trim();
            const res = await fetch('../api/update_grade.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ studentID, courseCode, grade: newGrade })
            });
            const data = await res.json();
            alert(data.message);
        }
        /*
        async function updateGrade(studentID, courseCode) {
            const newGrade = document.getElementById(`grade_${courseCode}_${studentID}`).value.trim();
            const res = await fetch('../api/update_grade.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ studentID, courseCode, grade: newGrade })
            });
            const data = await res.json();
            alert(data.message);
        }
        */

        async function loadCourseRoster() {
            const container = document.getElementById('rosterContent');
            const res = await fetch('../api/get_roster.php');
            const data = await res.json();

            if (!data.ok) {
                container.innerHTML = `<p style="color:red;">${data.message}</p>`;
                return;
            }

            let html = "";
            data.roster.forEach(r => {
                html += `<div class="row"><p><strong>${r.studentID}</strong></p><p>${r.firstName} ${r.lastName}</p><p>Grade: ${r.grade}</p></div>`;
            });
            container.innerHTML = html;
        }

        // Load default tab on start
        loadMyGrades();
    </script>
</body>
</html>