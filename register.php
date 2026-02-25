<?php
// register.php - Branch Registration (Mobile-first)
session_start();
require_once "config.php";

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $branchName = trim($_POST['branch_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    // Validation
    if (empty($username) || empty($password) || empty($branchName) || empty($department)) {
        $message = 'กรุณากรอกข้อมูลให้ครบ';
        $messageType = 'error';
    } elseif (strlen($username) < 3) {
        $message = 'Username ต้องมีอย่างน้อย 3 ตัวอักษร';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'รหัสผ่านไม่ตรงกัน';
        $messageType = 'error';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'รูปแบบอีเมลไม่ถูกต้อง';
        $messageType = 'error';
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $message = 'Username นี้มีผู้ใช้งานแล้ว';
            $messageType = 'error';
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert with role='pending' (รอการอนุมัติ)
            $role = 'pending';
            $locationType = 'branch';
            $credits = 1000;
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, name, role, location_type, branch_name, department, credits, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssssssi", $username, $hashedPassword, $email, $department, $role, $locationType, $branchName, $department, $credits);
            
            if ($stmt->execute()) {
                $message = 'สมัครสำเร็จ! กรุณารอการอนุมัติจาก Admin';
                $messageType = 'success';
                // Clear form
                $username = $email = $branchName = $department = '';
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สมัครสาขา | PRONTO & CO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px 30px;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 36px;
            color: #fff;
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.3);
        }
        
        .logo h1 {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #888;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .alert.success {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .alert.error {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group label i {
            margin-right: 6px;
            width: 16px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 16px 20px;
            padding-left: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e94560;
            background: rgba(233, 69, 96, 0.05);
        }
        
        .form-group input::placeholder {
            color: #666;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
            transition: color 0.3s;
        }
        
        .form-group input:focus + i,
        .input-wrapper:focus-within i {
            color: #e94560;
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }
        
        .password-toggle:hover {
            color: #e94560;
        }
        
        .btn-register {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(233, 69, 96, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #888;
            font-size: 14px;
        }
        
        .login-link a {
            color: #e94560;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .info-box i {
            color: #fbbf24;
            font-size: 20px;
            margin-top: 2px;
        }
        
        .info-box p {
            color: #fbbf24;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .required {
            color: #e94560;
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .register-container {
                padding: 30px 20px;
                border-radius: 20px;
            }
            
            .logo-icon {
                width: 70px;
                height: 70px;
                font-size: 30px;
            }
            
            .logo h1 {
                font-size: 22px;
            }
            
            .form-group input {
                padding: 14px 18px;
                padding-left: 45px;
                font-size: 16px;
            }
            
            .btn-register {
                padding: 16px;
                font-size: 16px;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-container {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-store"></i>
            </div>
            <h1>สมัครสาขา</h1>
            <p>PRONTO & CO Portal</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($messageType !== 'success'): ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p>หลังจากสมัครแล้ว กรุณารอการอนุมัติจาก Admin ก่อนเข้าใช้งานระบบ</p>
        </div>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="text" name="username" placeholder="ชื่อผู้ใช้งาน" value="<?= htmlspecialchars($username ?? '') ?>" required>
                    <i class="fas fa-user"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> รหัสผ่าน <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                    <i class="fas fa-lock"></i>
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> ยืนยันรหัสผ่าน <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                    <i class="fas fa-lock"></i>
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> อีเมล</label>
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="example@email.com" value="<?= htmlspecialchars($email ?? '') ?>">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-store"></i> ชื่อร้าน (Department) <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="text" name="department" placeholder="เช่น Pronto Denim, Public House" value="<?= htmlspecialchars($department ?? '') ?>" required>
                    <i class="fas fa-store"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> ชื่อสาขา (Branch) <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="text" name="branch_name" placeholder="เช่น Central World, Siam Paragon" value="<?= htmlspecialchars($branchName ?? '') ?>" required>
                    <i class="fas fa-map-marker-alt"></i>
                </div>
            </div>
            
            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i>
                สมัครสมาชิก
            </button>
        </form>
        <?php endif; ?>
        
        <div class="login-link">
            มีบัญชีอยู่แล้ว? <a href="index.php">เข้าสู่ระบบ</a>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>