<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

// ตรวจสอบสิทธิ์
if (!in_array($role, ['admin', 'manager'])) {
    header("location: dashboard.php");
    exit;
}

$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$edit_user_id = $is_edit ? intval($_GET['id']) : 0;

$user = null;
if ($is_edit) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $edit_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user) {
        header("location: user_management.php");
        exit;
    }
}

// ดึงรายการแผนก
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = cleanInput($_POST['username']);
    $user_name = cleanInput($_POST['name']);
    $email = cleanInput($_POST['email']);
    $user_role = cleanInput($_POST['role']);
    $department = cleanInput($_POST['department']);
    $phone = cleanInput($_POST['phone']);
    $pumble_webhook = cleanInput($_POST['pumble_webhook']);
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    
    if (empty($username) || empty($user_name) || empty($user_role)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else {
        if ($is_edit) {
            // อัพเดทผู้ใช้
            if (!empty($password)) {
                // มีการเปลี่ยนรหัสผ่าน
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, role = ?, department = ?, 
                        phone = ?, pumble_webhook = ?, password = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssssi", 
                    $username, $user_name, $email, $user_role, $department, 
                    $phone, $pumble_webhook, $hashed_password, $edit_user_id);
            } else {
                // ไม่เปลี่ยนรหัสผ่าน
                $sql = "UPDATE users SET username = ?, name = ?, email = ?, role = ?, department = ?, 
                        phone = ?, pumble_webhook = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssssi", 
                    $username, $user_name, $email, $user_role, $department, 
                    $phone, $pumble_webhook, $edit_user_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $success = 'อัพเดทข้อมูลผู้ใช้สำเร็จ';
                header("refresh:1;url=user_management.php");
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            // เพิ่มผู้ใช้ใหม่
            if (empty($password)) {
                $error = 'กรุณากรอกรหัสผ่าน';
            } else {
                // ตรวจสอบว่า username ซ้ำหรือไม่
                $sql = "SELECT id FROM users WHERE username = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error = 'Username นี้มีอยู่ในระบบแล้ว';
                    mysqli_stmt_close($stmt);
                } else {
                    mysqli_stmt_close($stmt);
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password, name, email, role, department, phone, pumble_webhook) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssssssss", 
                        $username, $hashed_password, $user_name, $email, $user_role, 
                        $department, $phone, $pumble_webhook);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'เพิ่มผู้ใช้ใหม่สำเร็จ';
                        header("refresh:1;url=user_management.php");
                    } else {
                        $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
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
    <title><?php echo $is_edit ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่'; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .form-card h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 1.5em;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group label.required:after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.85em;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .info-box strong {
            color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?php echo $is_edit ? '✏️ แก้ไขผู้ใช้' : '➕ เพิ่มผู้ใช้ใหม่'; ?></h1>
            <a href="user_management.php" class="back-link">← กลับรายการ</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2><?php echo $is_edit ? 'แก้ไขข้อมูลผู้ใช้' : 'สร้างผู้ใช้ใหม่'; ?></h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>💡 คำแนะนำ:</strong> 
                <?php if ($is_edit): ?>
                    หากไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นช่องรหัสผ่านว่างไว้
                <?php else: ?>
                    กรอกข้อมูลผู้ใช้ใหม่ให้ครบถ้วน และตั้งรหัสผ่านเริ่มต้น
                <?php endif; ?>
                <br>
                <strong>Pumble Webhook:</strong> คัดลอก Webhook URL จาก Pumble Workspace Settings → Apps & Integrations → Incoming Webhook
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" 
                               value="<?php echo $user ? htmlspecialchars($user['name']) : ''; ?>" 
                               placeholder="เช่น จอห์น สมิธ" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Username</label>
                        <input type="text" name="username" 
                               value="<?php echo $user ? htmlspecialchars($user['username']) : ''; ?>" 
                               placeholder="เช่น john.smith" required 
                               <?php echo $is_edit ? 'readonly' : ''; ?>
                               style="<?php echo $is_edit ? 'background: #f0f0f0;' : ''; ?>">
                        <?php if ($is_edit): ?>
                            <small>ไม่สามารถแก้ไข Username ได้</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label <?php echo !$is_edit ? 'class="required"' : ''; ?>>รหัสผ่าน</label>
                        <input type="password" name="password" 
                               placeholder="<?php echo $is_edit ? 'เว้นว่างหากไม่ต้องการเปลี่ยน' : 'กรอกรหัสผ่าน'; ?>"
                               <?php echo !$is_edit ? 'required' : ''; ?>>
                        <?php if ($is_edit): ?>
                            <small>เว้นว่างหากไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Role / บทบาท</label>
                        <select name="role" required>
                            <option value="">-- เลือก Role --</option>
                            <option value="admin" <?php echo ($user && $user['role'] == 'admin') ? 'selected' : ''; ?>>🔑 Admin</option>
                            <option value="manager" <?php echo ($user && $user['role'] == 'manager') ? 'selected' : ''; ?>>👔 Manager</option>
                            <option value="staff" <?php echo ($user && $user['role'] == 'staff') ? 'selected' : ''; ?>>👤 Staff</option>
                            <option value="maintenance" <?php echo ($user && $user['role'] == 'maintenance') ? 'selected' : ''; ?>>🔧 Maintenance</option>
                            <option value="housekeeping" <?php echo ($user && $user['role'] == 'housekeeping') ? 'selected' : ''; ?>>🧹 Housekeeping</option>
                            <option value="frontdesk" <?php echo ($user && $user['role'] == 'frontdesk') ? 'selected' : ''; ?>>🏨 Front Desk</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>อีเมล</label>
                        <input type="email" name="email" 
                               value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>" 
                               placeholder="example@hotel.com">
                    </div>
                    
                    <div class="form-group">
                        <label>เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" 
                               value="<?php echo $user ? htmlspecialchars($user['phone']) : ''; ?>" 
                               placeholder="081-234-5678">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>แผนก</label>
                    <select name="department">
                        <option value="">-- เลือกแผนก --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['name']); ?>" 
                                <?php echo ($user && $user['department'] == $dept['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>🔔 Pumble Webhook URL</label>
                    <input type="url" name="pumble_webhook" 
                           value="<?php echo $user ? htmlspecialchars($user['pumble_webhook']) : ''; ?>" 
                           placeholder="https://api.pumble.com/workspaces/YOUR_WORKSPACE/webhooks/YOUR_WEBHOOK">
                    <small>URL สำหรับส่งการแจ้งเตือนไปยังผู้ใช้คนนี้โดยตรง (ไม่บังคับ)</small>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? '💾 บันทึกการแก้ไข' : '✅ สร้างผู้ใช้'; ?>
                    </button>
                    <a href="user_management.php" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>