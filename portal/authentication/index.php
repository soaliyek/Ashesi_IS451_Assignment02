<?php
    session_start();

    if (isset($_SESSION["auth"]) && $_SESSION["auth"] === true) {
        header("Location: ../public/dashboard.php");
        exit;
    }

    header("Location: signin.php");
    exit();
?>