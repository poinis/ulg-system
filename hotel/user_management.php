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

// ตรวจสอบสิทธิ์ (เฉพาะ admin และ manager)
if (!in_array($role, ['admin', 'manager'])) {
    header("location: dashboard.php");
    exit;
}

// ดึงรายการผู้ใช้ทั้งหมด
$users = [];
$sql = "SELECT u.*, d.name as department_name 
        FROM users u
        LEFT JOIN departments d ON u.department = d.name
        ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// ดึงรายการแผนก
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}

$success = '';
$error = '';

// จัดการการลบผู้ใช้
if (isset($_GET['delete']) && $role == 'admin') {
    $delete_id = intval($_GET['delete']);
    
    // ไม่ให้ลบตัวเอง
    if ($delete_id == $user_id) {
        $error = 'ไม่สามารถลบบัญชีของตัวเองได้';
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = 'ลบผู้ใช้สำเร็จ';
            header("refresh:1;url=user_management.php");
        } else {
            $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ - User Management</title>
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
            max-width: 1600px;
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
            max-width: 1600px;
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
        
        .nav a:hover {
            background: #5568d3;
        }
        
        .nav a.secondary {
            background: #95a5a6;
        }
        
        .container {
            max-width: 1600px;
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
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-admin { background: #dc3545; color: white; }
        .badge-manager { background: #ffc107; color: #333; }
        .badge-staff { background: #17a2b8; color: white; }
        .badge-maintenance { background: #ff9800; color: white; }
        .badge-housekeeping { background: #9c27b0; color: white; }
        .badge-frontdesk { background: #28a745; color: white; }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
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
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .user-meta {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .pumble-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 5px;
        }
        
        .pumble-indicator.active {
            background: #28a745;
        }
        
        .pumble-indicator.inactive {
            background: #dc3545;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.85em;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👥 จัดการผู้ใช้</h1>
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong><br>
                <small><?php echo htmlspecialchars($role); ?></small>
            </div>
        </div>
    </div>
    
    <div class="nav">
        <div class="nav-content">
            <a href="user_add.php">➕ เพิ่มผู้ใช้ใหม่</a>
            <a href="user_roles.php">🔐 จัดการ Role</a>
            <a href="pumble_settings.php">🔔 ตั้งค่า Pumble</a>
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
        
        <!-- สถิติ -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo count($users); ?></h3>
                <p>👥 ผู้ใช้ทั้งหมด</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($users, fn($u) => $u['role'] == 'admin')); ?></h3>
                <p>🔑 Admin</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($users, fn($u) => $u['role'] == 'manager')); ?></h3>
                <p>👔 Manager</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($users, fn($u) => !empty($u['pumble_webhook']))); ?></h3>
                <p>🔔 มี Pumble</p>
            </div>
        </div>
        
        <!-- ตารางผู้ใช้ -->
        <div class="card">
            <h2>📋 รายชื่อผู้ใช้ทั้งหมด</h2>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ผู้ใช้</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>แผนก</th>
                            <th>อีเมล</th>
                            <th>เบอร์โทร</th>
                            <th style="text-align: center;">Pumble</th>
                            <th>วันที่สร้าง</th>
                            <th style="text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(mb_substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($user['username']); ?></code>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php 
                                        $role_thai = [
                                            'admin' => '🔑 Admin',
                                            'manager' => '👔 Manager',
                                            'staff' => '👤 Staff',
                                            'maintenance' => '🔧 Maintenance',
                                            'housekeeping' => '🧹 Housekeeping',
                                            'frontdesk' => '🏨 Front Desk'
                                        ];
                                        echo $role_thai[$user['role']] ?? $user['role'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($user['pumble_webhook'])): ?>
                                        <span class="pumble-indicator active" title="มี Pumble Webhook"></span>
                                        <small style="color: #28a745;">✓</small>
                                    <?php else: ?>
                                        <span class="pumble-indicator inactive" title="ไม่มี Pumble Webhook"></span>
                                        <small style="color: #dc3545;">✗</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo getThaiDate($user['created_at'], 'short'); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-warning" title="แก้ไข">
                                            ✏️
                                        </a>
                                        <?php if ($role == 'admin' && $user['id'] != $user_id): ?>
                                            <a href="user_management.php?delete=<?php echo $user['id']; ?>" 
                                               class="btn btn-danger" 
                                               onclick="return confirm('ยืนยันการลบผู้ใช้ <?php echo htmlspecialchars($user['name']); ?> ?')"
                                               title="ลบ">
                                                🗑️
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>