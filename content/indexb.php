<?php
require_once "config.php";
session_start();

$username = $password = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // ดึงข้อมูล user และ hashed password
    $sql = "SELECT id, username, password FROM users WHERE username = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password);
            mysqli_stmt_fetch($stmt);

            // ตรวจสอบรหัสผ่านด้วย password_verify
            if (password_verify($password, $hashed_password)) {
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $id;
                $_SESSION["username"] = $username;

                header("location: dashboard_content.php");
                exit;
            } else {
                $error = "Username หรือ Password ไม่ถูกต้อง";
            }
        } else {
            $error = "Username หรือ Password ไม่ถูกต้อง";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ระบบล็อกอิน | Content Portal</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(120deg, #74ebd5 0%, #ACB6E5 100%);
        margin: 0;
        padding: 0;
      }
      .container {
        max-width: 400px;
        margin: 60px auto 0 auto;
        background: white;
        box-shadow: 0 10px 24px rgba(106, 123, 165, 0.15);
        padding: 32px 24px 24px 24px;
        border-radius: 12px;
      }
      h2 {
        text-align: center;
        margin-bottom: 28px;
        color: #375a7f;
      }
      label {
        margin-top: 18px;
        font-weight: 500;
        color: #52616b;
      }
      input[type="text"], input[type="password"] {
        width: 100%;
        padding: 12px 10px;
        margin-top: 6px;
        margin-bottom: 18px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1em;
        box-sizing: border-box;
      }
      input[type="submit"] {
        background-color: #375a7f;
        color: white;
        font-size: 1.08em;
        padding: 12px 0;
        width: 100%;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(60,60,120,0.08);
        margin-top: 10px;
        margin-bottom: 10px;
      }
      input[type="submit"]:hover {
        background-color: #2c4365;
      }
      .error {
        color: #d9534f;
        background: #fbeee0;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        text-align: center;
      }
      .forgot-password {
        text-align: center;
        margin-top: 15px;
      }
      .forgot-password a {
        color: #375a7f;
        text-decoration: none;
        font-size: 0.95em;
      }
      .forgot-password a:hover {
        text-decoration: underline;
      }
      @media screen and (max-width: 480px) {
        .container {
          padding: 18px 8px 18px 8px;
          margin-top: 24px;
        }
        h2 {
          font-size: 1.4em;
        }
      }
    </style>
</head>
<body>
    <div class="container">
        <h2>เข้าสู่ระบบ Content Portal</h2>
        <?php if($error != ''): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <label>Username</label>
            <input type="text" name="username" required autocomplete="username">

            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">

            <input type="submit" value="เข้าสู่ระบบ">
        </form>
        
        <div class="forgot-password">
            <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
        </div>
    </div>
</body>
</html>