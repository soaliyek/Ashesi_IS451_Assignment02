<?php
    session_start();

    if (isset($_SESSION["auth"]) && $_SESSION["auth"] === true) {
        header("Location: portal/public/dashboard.php");
        exit;
    }

    header("Location: portal/authentication/signin.php");
    exit();
?>