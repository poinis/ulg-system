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

// ถ้าไม่ใช่ admin ให้กลับไปหน้า dashboard
if ($role !== 'admin') {
    header("location: dashboard_content.php");
    exit;
}

$error = "";
$success = "";

// ประมวลผลการเพิ่มผู้ใช้
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST["username"]);
    $new_name = trim($_POST["name"]);
    $new_email = trim($_POST["email"]);
    $new_password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $new_role = trim($_POST["role"]);
    
    // ตรวจสอบข้อมูล
    if (empty($new_username) || empty($new_name) || empty($new_email) || empty($new_password) || empty($new_role)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif (strlen($new_password) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } elseif ($new_password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
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
            
            // เข้ารหัสรหัสผ่าน
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // เพิ่มผู้ใช้ใหม่
            $sql_insert = "INSERT INTO users (username, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "sssss", $new_username, $new_name, $new_email, $hashed_password, $new_role);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $success = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว";
                // ล้างฟอร์ม
                $new_username = $new_name = $new_email = $new_role = "";
            } else {
                $error = "เกิดข้อผิดพลาดในการเพิ่มผู้ใช้งาน";
            }
            mysqli_stmt_close($stmt_insert);
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
<head>
    <title>เพิ่มผู้ใช้งาน | Content Portal</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(120deg, #74ebd5 0%, #ACB6E5 100%);
        margin: 0;
        padding: 20px;
      }
      .container {
        max-width: 600px;
        margin: 20px auto;
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
        display: block;
        margin-top: 18px;
        font-weight: 500;
        color: #52616b;
      }
      input[type="text"], 
      input[type="email"], 
      input[type="password"],
      select {
        width: 100%;
        padding: 12px 10px;
        margin-top: 6px;
        margin-bottom: 18px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1em;
        box-sizing: border-box;
      }
      select {
        cursor: pointer;
        background-color: white;
      }
      input[type="submit"] {
        background-color: #2ecc71;
        color: white;
        font-size: 1.08em;
        padding: 12px 0;
        width: 100%;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        margin-top: 10px;
        margin-bottom: 10px;
      }
      input[type="submit"]:hover {
        background-color: #27ae60;
      }
      .error {
        color: #e74c3c;
        background: #fbeee0;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        text-align: center;
      }
      .success {
        color: #2ecc71;
        background: #e8f5e9;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        text-align: center;
      }
      .back-link {
        text-align: center;
        margin-top: 20px;
      }
      .back-link a {
        color: #375a7f;
        text-decoration: none;
        font-size: 0.95em;
      }
      .back-link a:hover {
        text-decoration: underline;
      }
      .password-requirements {
        font-size: 0.85em;
        color: #666;
        margin-top: -10px;
        margin-bottom: 15px;
      }
      .role-info {
        font-size: 0.85em;
        color: #666;
        margin-top: -10px;
        margin-bottom: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
      }
      .role-info strong {
        display: block;
        margin-bottom: 5px;
      }
      @media screen and (max-width: 480px) {
        .container {
          padding: 18px 8px 18px 8px;
          margin-top: 10px;
        }
        h2 {
          font-size: 1.4em;
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

            <label>รหัสผ่าน *</label>
            <input type="password" name="password" required autocomplete="new-password" minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร">
            <div class="password-requirements">* รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>

            <label>ยืนยันรหัสผ่าน *</label>
            <input type="password" name="confirm_password" required autocomplete="new-password" minlength="6" placeholder="กรอกรหัสผ่านอีกครั้ง">

            <label>บทบาท (Role) *</label>
            <select name="role" required>
                <option value="">-- เลือกบทบาท --</option>
                <option value="admin" <?php echo (isset($new_role) && $new_role == 'admin') ? 'selected' : ''; ?>>Admin (ผู้ดูแลระบบ)</option>
                <option value="marketing" <?php echo (isset($new_role) && $new_role == 'marketing') ? 'selected' : ''; ?>>Marketing</option>
                <option value="brand" <?php echo (isset($new_role) && $new_role == 'brand') ? 'selected' : ''; ?>>Brand</option>
                <option value="approve" <?php echo (isset($new_role) && $new_role == 'approve') ? 'selected' : ''; ?>>Approve (ผู้อนุมัติ)</option>
            </select>
            
            <div class="role-info">
                <strong>คำอธิบายบทบาท:</strong>
                • <strong>Admin:</strong> สิทธิ์เต็ม จัดการทุกอย่าง<br>
                • <strong>Marketing:</strong> บรีฟงาน ดูแลคอนเทนต์<br>
                • <strong>Brand:</strong> ดูงานของแบรนด์ตัวเอง<br>
                • <strong>Approve:</strong> อนุมัติงาน
            </div>

            <input type="submit" value="✓ เพิ่มผู้ใช้งาน">
        </form>
        
        <div class="back-link">
            <a href="dashboard_content.php">← กลับไปหน้า Dashboard</a>
        </div>
    </div>
</body>
</html>