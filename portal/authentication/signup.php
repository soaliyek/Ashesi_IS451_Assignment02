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
    <title>Sign Up</title>
</head>
<body>
    

    <div class="smallBody">
        <div id="image">

        </div>

        <form id="signup" action="../api/register.php" method="post" class="form">
            <div id="part1" class="part">
                <div class="form_title">
                    <h1>Registration</h1>
                </div>
                <div class="form_data">
                    <input type="text" name="signupCode" id="signupCode" class="entry" placeholder="GJE57398" maxlength="100" autocomplete="off" required>
                </div>
                <div id="remarks" role="status" aria-live="polite">
                    
                </div>
                <div class="additonals">
                    <p><a href="signin.php">Login Instead</a></p>
                </div>
                <div class="button">
                    <input type="submit" value="Register" id="registerButton">
                </div>
            </div>
        </form>
    </div>
    <script src="auth.js" defer></script>
</body>
</html>