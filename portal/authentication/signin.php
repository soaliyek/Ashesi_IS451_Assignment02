<?php
    session_start();

    if (isset($_SESSION["auth"]) && $_SESSION["auth"] === true) {
        header("Location: ../public/dashboard.php");
        exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/css/signup.css">
    <link rel="stylesheet" href="auth.css">
    <title>Sign In</title>
</head>
<body>

    <div class="smallBody">
        <div id="image">

        </div>

        <!-- <form id="signin" action="../api/login.php" method="post"> -->
        <form id="signin" class="form">
            
            <div id="part1" class="part">
                <div class="form_title">
                    <h1>Welcome Back</h1>
                </div>
                <div class="form_data">
                    <input type="email" name="email" id="email" class="entry" placeholder="Email" maxlength="100" autocomplete="on" required>
                </div>
                <div id="remarks" role="status" aria-live="polite">
                    
                </div>
                <div class="additonals">
                    <p>Have You Been Already Registered? <a href="signup.php">Register Instead</a></p>
                </div>
                <div class="button">
                    <input type="submit" value="Sign In" id="loginButton">
                </div>
            </div>
        </form>
    </div>
    <script src="auth.js" defer></script>
</body>
</html>