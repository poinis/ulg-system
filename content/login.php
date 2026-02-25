<?php
session_start();
require_once "config.php";

$username = $password = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // ตรวจสอบ username และ password แบบ plain text
    $sql = "SELECT id, username FROM users WHERE username = ? AND password = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $username, $password);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id, $username);
            mysqli_stmt_fetch($stmt);

            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $id;
            $_SESSION["username"] = $username;

            header("location: user_dashboard.php");
            exit;
        } else {
            $error = "Username or password not correct.";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta charset="utf-8">
    <style>
      body { font-family:Tahoma; }
      form { max-width:300px; margin:auto; }
      .error { color: red; }
    </style>
</head>
<body>
    <form method="POST" action="login.php">
        <h2>Login</h2>
        <div class="error"><?php echo $error; ?></div>
        <div>
            <label>Username</label><br>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password</label><br>
            <input type="password" name="password" required>
        </div><br>
        <input type="submit" value="Login">
    </form>
</body>
</html>
