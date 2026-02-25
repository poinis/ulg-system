<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once "config.php";

$email = "";
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    
    // ตรวจสอบว่ามี email นี้ในระบบหรือไม่
    $sql = "SELECT id, username, email FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $user_id, $username, $user_email);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            // สร้าง token สำหรับรีเซ็ตรหัสผ่าน
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));
            
            // บันทึก token ลงฐานข้อมูล
            $sql_token = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
            if ($stmt_token = mysqli_prepare($conn, $sql_token)) {
                mysqli_stmt_bind_param($stmt_token, "issss", $user_id, $token, $expires, $token, $expires);
                mysqli_stmt_execute($stmt_token);
                mysqli_stmt_close($stmt_token);
                
                // สร้าง reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                
                // ส่งอีเมล
                $to = $user_email;
                $subject = "รีเซ็ตรหัสผ่าน - ULG Portal";
                $email_message = "สวัสดีคุณ " . $username . ",\n\n";
                $email_message .= "คุณได้ทำการขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ\n\n";
                $email_message .= "กรุณาคลิกลิงก์ด้านล่างเพื่อตั้งรหัสผ่านใหม่:\n";
                $email_message .= $reset_link . "\n\n";
                $email_message .= "ลิงก์นี้จะหมดอายุภายใน 1 ชั่วโมง\n\n";
                $email_message .= "หากคุณไม่ได้ทำการขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยอีเมลนี้\n\n";
                $email_message .= "ขอบคุณครับ\ULG Portal Team";
                
                $headers = "From: noreply@prontodenim.com\r\n";
                $headers .= "Reply-To: online@prontoddenim.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                if (mail($to, $subject, $email_message, $headers)) {
                    $message = "ระบบได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบอีเมล";
                } else {
                    $error = "เกิดข้อผิดพลาดในการส่งอีเมล กรุณาลองใหม่อีกครั้ง";
                }
            }
        } else {
            // ไม่พบอีเมลในระบบ แต่ไม่แจ้งให้ผู้ใช้รู้เพื่อความปลอดภัย
            $message = "หากอีเมลนี้มีอยู่ในระบบ เราจะส่งลิงก์รีเซ็ตรหัสผ่านไปให้";
        }
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ลืมรหัสผ่าน | ULG Portal</title>
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
      .description {
        text-align: center;
        color: #666;
        margin-bottom: 20px;
        font-size: 0.95em;
      }
      label {
        margin-top: 18px;
        font-weight: 500;
        color: #52616b;
      }
      input[type="email"] {
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
      .success {
        color: #5cb85c;
        background: #e8f5e9;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        text-align: center;
      }
      .back-to-login {
        text-align: center;
        margin-top: 15px;
      }
      .back-to-login a {
        color: #375a7f;
        text-decoration: none;
        font-size: 0.95em;
      }
      .back-to-login a:hover {
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
        <h2>ลืมรหัสผ่าน</h2>
        <div class="description">
            กรุณากรอกอีเมลที่ลงทะเบียนไว้<br>เราจะส่งลิงก์สำหรับรีเซ็ตรหัสผ่านให้คุณ
        </div>
        
        <?php if($error != ''): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($message != ''): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="forgot_password.php">
            <label>อีเมล</label>
            <input type="email" name="email" required autocomplete="email" placeholder="your@email.com">

            <input type="submit" value="ส่งลิงก์รีเซ็ตรหัสผ่าน">
        </form>
        
        <div class="back-to-login">
            <a href="index.php">← กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>