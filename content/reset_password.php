<?php
require_once "config.php";

$token = "";
$valid_token = false;
$user_id = 0;
$message = "";
$error = "";

// ตรวจสอบ token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $sql = "SELECT pr.user_id, pr.expires_at, u.username 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW()";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $user_id, $expires_at, $username);
            mysqli_stmt_fetch($stmt);
            $valid_token = true;
        } else {
            $error = "ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว";
        }
        mysqli_stmt_close($stmt);
    }
}

// ประมวลผลการเปลี่ยนรหัสผ่าน
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    if (strlen($new_password) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } elseif ($new_password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        // เข้ารหัสรหัสผ่านใหม่ด้วย password_hash
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // อัพเดทรหัสผ่านใหม่
        $sql_update = "UPDATE users SET password = ? WHERE id = ?";
        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "si", $hashed_password, $user_id);
            
            if (mysqli_stmt_execute($stmt_update)) {
                // ลบ token ที่ใช้แล้ว
                $sql_delete = "DELETE FROM password_resets WHERE user_id = ?";
                if ($stmt_delete = mysqli_prepare($conn, $sql_delete)) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
                    mysqli_stmt_execute($stmt_delete);
                    mysqli_stmt_close($stmt_delete);
                }
                
                $message = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบได้เลย";
                $valid_token = false; // ป้องกันการส่งฟอร์มซ้ำ
            } else {
                $error = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
<head>
    <title>รีเซ็ตรหัสผ่าน | Content Portal</title>
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
      input[type="password"] {
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
      .password-requirements {
        font-size: 0.85em;
        color: #666;
        margin-top: -10px;
        margin-bottom: 15px;
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
        <h2>รีเซ็ตรหัสผ่าน</h2>
        
        <?php if($error != ''): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($message != ''): ?>
            <div class="success"><?php echo $message; ?></div>

        <?php elseif($valid_token): ?>
            <div class="description">
                สวัสดีคุณ <?php echo htmlspecialchars($username); ?><br>
                กรุณาตั้งรหัสผ่านใหม่ของคุณ
            </div>
            
            <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                <label>รหัสผ่านใหม่</label>
                <input type="password" name="new_password" required autocomplete="new-password" minlength="6">
                <div class="password-requirements">* รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>

                <label>ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" required autocomplete="new-password" minlength="6">

                <input type="submit" value="เปลี่ยนรหัสผ่าน">
            </form>
        <?php else: ?>
            <div class="description">
                ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว
            </div>
            <div class="back-to-login">
                <a href="forgot_password.php">← ขอลิงก์รีเซ็ตใหม่</a>
            </div>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="index.php">← กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>