<?php
require_once "config.php";
require_once "config_pumble.php";
session_start();

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

// ดึงการตั้งค่า Webhook แผนก
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}

// ดึง Pumble logs ล่าสุด
$logs = [];
$sql = "SELECT l.*, u.name as user_name 
        FROM pumble_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 20";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
}

$success = '';
$error = '';

// อัพเดท webhook แผนก
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_department'])) {
    $dept_id = intval($_POST['dept_id']);
    $webhook_url = cleanInput($_POST['webhook_url']);
    
    $sql = "UPDATE departments SET pumble_webhook = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $webhook_url, $dept_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = 'อัพเดท Webhook แผนกสำเร็จ';
    } else {
        $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// ทดสอบส่งการแจ้งเตือน
if (isset($_POST['test_notification'])) {
    $test_user_id = intval($_POST['test_user_id']);
    $test_message = "🔔 ทดสอบการแจ้งเตือน Pumble\n⏰ เวลา: " . date('Y-m-d H:i:s') . "\nทดสอบโดย: {$name}";
    
    if (sendPumbleToUser($conn, $test_user_id, $test_message)) {
        $success = 'ส่งการแจ้งเตือนทดสอบสำเร็จ';
    } else {
        $error = 'ส่งการแจ้งเตือนไม่สำเร็จ ตรวจสอบ Webhook URL';
    }
}

// ดึงรายชื่อผู้ใช้ที่มี webhook
$users_with_webhook = [];
$sql = "SELECT id, name, role, pumble_webhook FROM users WHERE pumble_webhook IS NOT NULL AND pumble_webhook != '' ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users_with_webhook[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า Pumble Notifications</title>
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
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        
        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav a {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .nav a.secondary {
            background: #95a5a6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 30px;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .webhook-form {
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .webhook-form h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
        }
        
        .status-success {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-failed {
            color: #dc3545;
            font-weight: 600;
        }
        
        .info-box {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .info-box h3 {
            color: #2196f3;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🔔 ตั้งค่า Pumble Notifications</h1>
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong><br>
                <small><?php echo htmlspecialchars($role); ?></small>
            </div>
        </div>
    </div>
    
    <div class="nav">
        <div class="nav-content">
            <a href="user_management.php">👥 จัดการผู้ใช้</a>
            <a href="user_add.php">➕ เพิ่มผู้ใช้</a>
            <a href="dashboard.php" class="secondary">🏠 หน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- คำแนะนำ -->
        <div class="info-box">
            <h3>💡 วิธีตั้งค่า Pumble Webhook</h3>
            <ul>
                <li>เข้าไปที่ Pumble Workspace ของคุณ</li>
                <li>ไปที่ <strong>Settings → Apps & Integrations</strong></li>
                <li>เลือก <strong>Incoming Webhook</strong> และคลิก <strong>Add to Pumble</strong></li>
                <li>เลือกช่อง (Channel) ที่ต้องการรับการแจ้งเตือน</li>
                <li>คัดลอก <strong>Webhook URL</strong> มาวางในช่องด้านล่าง</li>
                <li>สามารถสร้าง Webhook แยกสำหรับแต่ละแผนก หรือแต่ละบุคคลได้</li>
            </ul>
        </div>
        
        <!-- ตั้งค่า Webhook แผนก -->
        <div class="card">
            <h2>🏢 Webhook แผนก</h2>
            
            <?php foreach ($departments as $dept): ?>
                <div class="webhook-form">
                    <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                        <div class="form-group">
                            <label>Webhook URL</label>
                            <input type="url" name="webhook_url" 
                                   value="<?php echo htmlspecialchars($dept['pumble_webhook'] ?? ''); ?>" 
                                   placeholder="https://api.pumble.com/workspaces/YOUR_WORKSPACE/webhooks/...">
                        </div>
                        <button type="submit" name="update_department" class="btn btn-primary">
                            💾 บันทึก
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ทดสอบการแจ้งเตือน -->
        <div class="card">
            <h2>🧪 ทดสอบการแจ้งเตือน</h2>
            
            <?php if (empty($users_with_webhook)): ?>
                <p style="color: #6c757d; text-align: center; padding: 20px;">
                    ยังไม่มีผู้ใช้ที่ตั้งค่า Webhook<br>
                    <a href="user_management.php">ไปตั้งค่าใน จัดการผู้ใช้</a>
                </p>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>เลือกผู้ใช้สำหรับทดสอบ</label>
                        <select name="test_user_id" class="form-group input" style="width: 100%; padding: 10px;">
                            <?php foreach ($users_with_webhook as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['name']); ?> (<?php echo $u['role']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="test_notification" class="btn btn-success">
                        📨 ส่งการแจ้งเตือนทดสอบ
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- ประวัติการส่งการแจ้งเตือน -->
        <div class="card">
            <h2>📜 ประวัติการส่งการแจ้งเตือน (20 ล่าสุด)</h2>
            
            <?php if (empty($logs)): ?>
                <p style="text-align: center; color: #6c757d; padding: 20px;">ยังไม่มีประวัติการส่งการแจ้งเตือน</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>เวลา</th>
                            <th>ผู้รับ</th>
                            <th>ข้อความ</th>
                            <th style="text-align: center;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['user_name'] ?? 'ทั่วไป'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(mb_substr($log['message'], 0, 80)); ?>
                                    <?php echo mb_strlen($log['message']) > 80 ? '...' : ''; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($log['status'] == 'success'): ?>
                                        <span class="status-success">✓ สำเร็จ</span>
                                    <?php else: ?>
                                        <span class="status-failed">✗ ล้มเหลว</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>