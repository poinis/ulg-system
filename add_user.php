<?php
require_once "config.php";
session_start();

// ตรวจสอบว่าเป็น admin หรือไม่
if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];
$role = '';

// ดึง role ของผู้ใช้
$sql_role = "SELECT role FROM users WHERE username = ?";
$stmt_role = mysqli_prepare($conn, $sql_role);
mysqli_stmt_bind_param($stmt_role, "s", $username);
mysqli_stmt_execute($stmt_role);
mysqli_stmt_bind_result($stmt_role, $role);
mysqli_stmt_fetch($stmt_role);
mysqli_stmt_close($stmt_role);

// กำหนด roles ที่อนุญาตให้เข้าถึง
$allowed_roles = ['brand', 'admin'];

// ถ้าไม่ใช่ role ที่อนุญาต ให้กลับไปหน้า dashboard
if (!in_array($role, $allowed_roles)) {
    header("location: dashboard.php");
    exit;
}

$error = "";
$success = "";

// ฟังก์ชันส่งอีเมลแจ้งผู้ใช้ใหม่
function sendWelcomeEmail($email, $name, $username, $token) {
    // กำหนด URL ของระบบ (ปรับตาม server จริง)
    $base_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $reset_link = $base_url . "/reset_password.php?token=" . $token;
    
    $to = $email;
    $subject = "=?UTF-8?B?" . base64_encode("ยินดีต้อนรับสู่ระบบ Content Portal - ตั้งรหัสผ่านของคุณ") . "?=";
    
    $message = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: linear-gradient(135deg, #059669 0%, #34d399 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .info-box { background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #3b82f6; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
            .warning { color: #dc2626; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 ยินดีต้อนรับ!</h1>
                <p>บัญชีของคุณถูกสร้างเรียบร้อยแล้ว</p>
            </div>
            <div class='content'>
                <p>สวัสดีคุณ <strong>" . htmlspecialchars($name) . "</strong>,</p>
                
                <p>บัญชีผู้ใช้ของคุณในระบบ Content Portal ได้ถูกสร้างเรียบร้อยแล้ว</p>
                
                <div class='info-box'>
                    <strong>📋 ข้อมูลบัญชีของคุณ:</strong><br>
                    👤 Username: <strong>" . htmlspecialchars($username) . "</strong><br>
                    📧 Email: <strong>" . htmlspecialchars($email) . "</strong>
                </div>
                
                <p>กรุณาคลิกปุ่มด้านล่างเพื่อตั้งรหัสผ่านของคุณ:</p>
                
                <center>
                    <a href='" . $reset_link . "' class='button'>🔐 ตั้งรหัสผ่าน</a>
                </center>
                
                <p>หรือคัดลอกลิงก์นี้ไปวางในเบราว์เซอร์:</p>
                <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 5px; font-size: 12px;'>" . $reset_link . "</p>
                
                <p class='warning'>⚠️ ลิงก์นี้จะหมดอายุใน 24 ชั่วโมง</p>
                
                <div class='footer'>
                    <p>หากคุณไม่ได้ร้องขอบัญชีนี้ กรุณาติดต่อผู้ดูแลระบบ</p>
                    <p>© " . date('Y') . " Content Portal - ULG</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers สำหรับ HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Content Portal <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// ฟังก์ชันสร้าง token สำหรับตั้งรหัสผ่าน
function generatePasswordToken($conn, $user_id) {
    // สร้าง token แบบสุ่ม
    $token = bin2hex(random_bytes(32));
    
    // กำหนดเวลาหมดอายุ (24 ชั่วโมง)
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // ลบ token เก่าของ user นี้ (ถ้ามี)
    $sql_delete = "DELETE FROM password_resets WHERE user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
    mysqli_stmt_execute($stmt_delete);
    mysqli_stmt_close($stmt_delete);
    
    // บันทึก token ใหม่
    $sql_insert = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "iss", $user_id, $token, $expires_at);
    
    if (mysqli_stmt_execute($stmt_insert)) {
        mysqli_stmt_close($stmt_insert);
        return $token;
    }
    
    mysqli_stmt_close($stmt_insert);
    return false;
}

// ประมวลผลการเพิ่มผู้ใช้
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST["username"]);
    $new_name = trim($_POST["name"]);
    $new_email = trim($_POST["email"]);
    $new_role = trim($_POST["role"]);
    $send_email = isset($_POST["send_email"]) ? true : false;
    
    // ตรวจสอบข้อมูล
    if (empty($new_username) || empty($new_name) || empty($new_email) || empty($new_role)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // ตรวจสอบว่า username หรือ email ซ้ำหรือไม่
        $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ss", $new_username, $new_email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "Username หรือ Email นี้มีอยู่ในระบบแล้ว";
            mysqli_stmt_close($stmt_check);
        } else {
            mysqli_stmt_close($stmt_check);
            
            // สร้างรหัสผ่านชั่วคราว (random) - ผู้ใช้จะต้องตั้งใหม่ผ่านอีเมล
            $temp_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // เพิ่มผู้ใช้ใหม่
            $sql_insert = "INSERT INTO users (username, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "sssss", $new_username, $new_name, $new_email, $hashed_password, $new_role);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $new_user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_insert);
                
                // ส่งอีเมลถ้าเลือกตัวเลือกนี้
                if ($send_email) {
                    // สร้าง token สำหรับตั้งรหัสผ่าน
                    $token = generatePasswordToken($conn, $new_user_id);
                    
                    if ($token) {
                        // ส่งอีเมล
                        if (sendWelcomeEmail($new_email, $new_name, $new_username, $token)) {
                            $success = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว และส่งอีเมลแจ้งไปที่ " . htmlspecialchars($new_email);
                        } else {
                            $success = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว แต่ไม่สามารถส่งอีเมลได้ (กรุณาตรวจสอบการตั้งค่า mail server)";
                        }
                    } else {
                        $success = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว แต่เกิดข้อผิดพลาดในการสร้างลิงก์ตั้งรหัสผ่าน";
                    }
                } else {
                    $success = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว (ไม่ได้ส่งอีเมล)";
                }
                
                // ล้างฟอร์ม
                $new_username = $new_name = $new_email = $new_role = "";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มผู้ใช้งาน";
                mysqli_stmt_close($stmt_insert);
            }
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>เพิ่มผู้ใช้งาน | ULG Portal</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d1a;
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #1a1a2e;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            padding: 32px 24px 24px 24px;
            border-radius: 15px;
            border: 1px solid #2a2a4a;
        }

        h2 {
            text-align: center;
            margin-bottom: 28px;
            color: #ffffff;
            font-size: 1.6em;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: 500;
            color: #a0a0b0;
        }

        input[type="text"], 
        input[type="email"], 
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 14px;
            margin-top: 6px;
            margin-bottom: 18px;
            border: 1px solid #3a3a5a;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            background: #252542;
            color: #ffffff;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus, 
        input[type="email"]:focus, 
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }

        input[type="text"]::placeholder,
        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #6a6a7a;
        }

        select {
            cursor: pointer;
        }

        select option {
            background: #252542;
            color: #ffffff;
        }

        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #252542;
            border-radius: 8px;
            border: 1px solid #3a3a5a;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-container:hover {
            border-color: #7c3aed;
        }

        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: #7c3aed;
        }

        .checkbox-container .checkbox-label {
            color: #ffffff;
            font-weight: 500;
        }

        .checkbox-container .checkbox-desc {
            color: #a0a0b0;
            font-size: 0.85em;
            margin-top: 4px;
        }

        input[type="submit"] {
            background: linear-gradient(135deg, #059669 0%, #34d399 100%);
            color: white;
            font-size: 1.1em;
            padding: 14px 0;
            width: 100%;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
            margin-top: 15px;
            margin-bottom: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .error {
            color: #f87171;
            background: rgba(220, 38, 38, 0.15);
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .success {
            color: #34d399;
            background: rgba(5, 150, 105, 0.15);
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(5, 150, 105, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #a78bfa;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: #c4b5fd;
            text-decoration: underline;
        }

        .role-info {
            font-size: 0.85em;
            color: #a0a0b0;
            margin-top: -10px;
            margin-bottom: 15px;
            padding: 15px;
            background: #252542;
            border-radius: 8px;
            border: 1px solid #3a3a5a;
            line-height: 1.8;
        }

        .role-info strong {
            color: #ffffff;
        }

        .role-info .title {
            display: block;
            margin-bottom: 10px;
            color: #a78bfa;
            font-weight: 600;
        }

        .email-info {
            font-size: 0.85em;
            color: #a0a0b0;
            padding: 12px 15px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            margin-bottom: 15px;
        }

        .email-info strong {
            color: #3b82f6;
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 20px 15px;
                margin-top: 10px;
            }

            h2 {
                font-size: 1.4em;
            }

            input[type="text"], 
            input[type="email"], 
            input[type="password"],
            select {
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>👤 เพิ่มผู้ใช้งานใหม่</h2>
        
        <?php if($error != ''): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success != ''): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="add_user.php">
            <label>Username *</label>
            <input type="text" name="username" required autocomplete="username" value="<?php echo isset($new_username) ? htmlspecialchars($new_username) : ''; ?>" placeholder="ชื่อผู้ใช้สำหรับเข้าสู่ระบบ">

            <label>ชื่อ-นามสกุล *</label>
            <input type="text" name="name" required autocomplete="name" value="<?php echo isset($new_name) ? htmlspecialchars($new_name) : ''; ?>" placeholder="ชื่อเต็มของผู้ใช้">

            <label>อีเมล *</label>
            <input type="email" name="email" required autocomplete="email" value="<?php echo isset($new_email) ? htmlspecialchars($new_email) : ''; ?>" placeholder="email@example.com">

            <label>บทบาท (Role) *</label>
            <select name="role" required>
                <option value="">-- เลือกบทบาท --</option>
                <option value="admin" <?php echo (isset($new_role) && $new_role == 'admin') ? 'selected' : ''; ?>>Admin (ผู้ดูแลระบบ)</option>
                <option value="marketing" <?php echo (isset($new_role) && $new_role == 'marketing') ? 'selected' : ''; ?>>Marketing</option>
                <option value="brand" <?php echo (isset($new_role) && $new_role == 'brand') ? 'selected' : ''; ?>>Brand</option>
                <option value="approve" <?php echo (isset($new_role) && $new_role == 'approve') ? 'selected' : ''; ?>>Approve (ผู้อนุมัติ)</option>
                <option value="owner" <?php echo (isset($new_role) && $new_role == 'owner') ? 'selected' : ''; ?>>Owner (เจ้าของ)</option>
                <option value="owner" <?php echo (isset($new_role) && $new_role == 'area') ? 'selected' : ''; ?>>area </option>
            </select>
            
            <div class="role-info">
                <span class="title">📋 คำอธิบายบทบาท:</span>
                <strong>Admin:</strong> สิทธิ์เต็ม จัดการทุกอย่าง<br>
                <strong>Marketing:</strong> บรีฟงาน ดูแลคอนเทนต์<br>
                <strong>Brand:</strong> ดูงานของแบรนด์ตัวเอง<br>
                <strong>Approve:</strong> อนุมัติงาน<br>
                <strong>Owner:</strong> เจ้าของ ดู Sales Report ได้
            </div>

            <label class="checkbox-container">
                <input type="checkbox" name="send_email" value="1" checked>
                <div>
                    <div class="checkbox-label">📧 ส่งอีเมลแจ้งผู้ใช้ใหม่</div>
                    <div class="checkbox-desc">ระบบจะส่งอีเมลพร้อมลิงก์ให้ผู้ใช้ตั้งรหัสผ่านเอง</div>
                </div>
            </label>

            <div class="email-info">
                <strong>💡 หมายเหตุ:</strong> เมื่อเลือกส่งอีเมล ผู้ใช้จะได้รับลิงก์สำหรับตั้งรหัสผ่านด้วยตัวเอง (ลิงก์หมดอายุใน 24 ชั่วโมง)
            </div>

            <input type="submit" value="✓ เพิ่มผู้ใช้งาน">
        </form>
        
        <div class="back-link">
            <a href="dashboard.php">← กลับไปหน้า Dashboard</a>
        </div>
    </div>
</body>
</html>