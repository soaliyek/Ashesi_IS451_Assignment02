<?php

    session_start();

    if (isset($_SESSION["auth"]) && $_SESSION["auth"] === true) {
        
        // Redirect based on role
        switch($_SESSION["role"]) {
            case "STUDENT":
                header("Location: student/courses.php");
                exit();
            case "LECTURER":
                header("Location: lecturer/courses.php");
                exit();
        }
    }

    header("Location: authentication/usersignup.php");
    exit();
?>