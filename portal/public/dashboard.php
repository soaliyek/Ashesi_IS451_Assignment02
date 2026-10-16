<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <header>
        <div id="profile">
            <div id="profileImage">

            </div>
            <div id="profuileDetails">
                <p id="name">Serva Devante</p>
                <p id="id">43262027</p>
                <div id="role">
                    <input type="text" name="" id="" value="STUDENT">
                    <button type="button">Set Role</button>
                </div>
            </div>
            <div id="logout">
                <button type="button">Logout</button>
            </div>
        </div>
        <nav>
            <button type="button" id="gradesSwitch" onclick="doSwitch('grade')">Grades</button>
            <button type="button" id="editSwitch" onclick="doSwitch('edit')">Edit Grades</button>
            <button type="button" id="courseSwitch" onclick="doSwitch('course')">Course Roster</button>
        </nav>
    </header>

    <div id="grades">
        <div id="courseGrade">
            <p>CS323</p>
            <p>Software Engineering</p>
            <p>B+</p>
        </div>
    </div>

    <div id="editGrades" style="display: none;" onclick="doSwitch('edit')">
        <!-- For each grade display, it should be input and button to set the grade -->
        <div id="studentID">
            <input type="text" name="" id="" placeholder="Student ID">
            <button type="button">Query</button>
        </div>
        <div>
            <p>CS323</p>
            <p>Software Engineering</p>
            <div>
                <input type="text" name="" id="" value="B+">
                <button type="button">Set Grade</button>
            </div>
        </div>
    </div>

    <div id="courseRoster" style="display: none;" onclick="doSwitch('course')">
        <div>
            <p>32562027</p>
            <p>John Krispky</p>
            <p>A</p>
        </div>
    </div>

    <script>
        function doSwitch(arg){
            const gradesSwitch = document.getElementById("gradesSwitch");
            const editSwitch = document.getElementById("editSwitch");
            const courseSwitch = document.getElementById("courseSwitch");

            const gradesPart = document.getElementById("grades");
            const editPart = document.getElementById("editGrades");
            const coursePart = document.getElementById("courseRoster");

            if (arg === "grades") {
                gradesPart.style.display = "block";
                editPart.style.display = "none";
                coursePart.style.display = "none";
            } else if (arg === "edit") {
                gradesPart.style.display = "none";
                editPart.style.display = "block";
                coursePart.style.display = "none";
            } else if (arg === "course") {
                gradesPart.style.display = "none";
                editPart.style.display = "none";
                coursePart.style.display = "block";
            }
        }
    </script>
</body>
</html>