<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "config.php";

$username = $_SESSION["username"];
$success_message = "";
$error_message = "";

// ดึงข้อมูลผู้ใช้
$user_data = [];
$sql = "SELECT id, username, email, name, role, pumble_user_id, location_type FROM users WHERE username = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $db_username, $email, $name, $role, $pumble_user_id, $location_type);
    if (mysqli_stmt_fetch($stmt)) {
        $user_data = [
            'id' => $id,
            'username' => $db_username,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'pumble_user_id' => $pumble_user_id,
            'location_type' => $location_type
        ];
    }
    mysqli_stmt_close($stmt);
}

// ประมวลผลการเปลี่ยนรหัสผ่าน
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = trim($_POST["current_password"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    // ตรวจสอบว่ากรอกครบทุกช่อง
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    }
    // ตรวจสอบว่ารหัสผ่านใหม่ตรงกัน
    elseif ($new_password !== $confirm_password) {
        $error_message = "รหัสผ่านใหม่ไม่ตรงกัน";
    }
    // ตรวจสอบความยาวรหัสผ่าน
    elseif (strlen($new_password) < 6) {
        $error_message = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";
    }
    else {
        // ตรวจสอบรหัสผ่านเก่า
        $sql = "SELECT password FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $hashed_password);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            // ตรวจสอบว่ารหัสผ่านเก่าถูกต้อง
            if (password_verify($current_password, $hashed_password)) {
                // อัพเดทรหัสผ่านใหม่
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE username = ?";
                
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "ss", $new_hashed_password, $username);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $success_message = "เปลี่ยนรหัสผ่านสำเร็จ!";
                    } else {
                        $error_message = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
                    }
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                $error_message = "รหัสผ่านเก่าไม่ถูกต้อง";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ผู้ใช้ - ULG Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title {
            font-size: 1.8em;
            color: #333;
            font-weight: 600;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .profile-card, .password-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Profile Info */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .info-value {
            font-size: 1.1em;
            color: #333;
            font-weight: 600;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Password Change Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Password Requirements */
        .password-hint {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            body {
                padding: 15px;
            }

            .header {
                padding: 20px;
            }

            .page-title {
                font-size: 1.4em;
                width: 100%;
            }

            .back-btn {
                width: 100%;
                text-align: center;
            }

            .profile-card, .password-card {
                padding: 20px;
            }

            .section-title {
                font-size: 1.3em;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="page-title">👤 โปรไฟล์ผู้ใช้</h1>
            <a href="dashboard.php" class="back-btn">← กลับสู่หน้าหลัก</a>
        </div>

        <div class="main-content">
            <!-- Profile Information Card -->
            <div class="profile-card">
                <h2 class="section-title">
                    📋 ข้อมูลส่วนตัว
                </h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">ชื่อผู้ใช้</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">ชื่อ-นามสกุล</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['name'] ?? '-'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">อีเมล</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['email'] ?? '-'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">บทบาท</div>
                        <div class="info-value">
                            <span class="role-badge"><?php echo strtoupper(htmlspecialchars($user_data['role'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">สถานที่</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['location_type'] ?? '-'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Pumble User ID</div>
                        <div class="info-value" style="font-size: 0.9em; word-break: break-all;">
                            <?php echo htmlspecialchars($user_data['pumble_user_id'] ?? '-'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="password-card">
                <h2 class="section-title">
                    🔐 เปลี่ยนรหัสผ่าน
                </h2>

                <?php if (!empty($success_message)): ?>
                    <div class="message success-message">
                        ✓ <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="message error-message">
                        ✗ <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">รหัสผ่านปัจจุบัน *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">รหัสผ่านใหม่ *</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-hint">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่านใหม่ *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" name="change_password" class="submit-btn">
                        เปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>