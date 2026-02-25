<?php
// admin/index.php - Admin Tools (Superadmin Only)
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

// เฉพาะ Superadmin เท่านั้น
$superadmins = ['admin', 'oat', 'it', 'may'];
$currentUsername = $_SESSION["username"] ?? '';

if (!in_array(strtolower($currentUsername), $superadmins)) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='../dashboard.php';</script>";
    exit;
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['new_username']);
        $email = trim($_POST['new_email']);
        $name = trim($_POST['new_name']);
        $password = $_POST['new_password'];
        $role = $_POST['new_role'];
        $location_type = $_POST['new_location_type'] ?? 'headquarters';
        $branch_name = trim($_POST['new_branch_name'] ?? '');
        $department = trim($_POST['new_department'] ?? '');
        
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'Username นี้มีในระบบแล้ว';
            $messageType = 'error';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, name, password, role, location_type, branch_name, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $username, $email, $name, $hashedPassword, $role, $location_type, $branch_name, $department);
            if ($stmt->execute()) {
                $message = 'เพิ่มผู้ใช้สำเร็จ';
                $messageType = 'success';
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $userId = (int) $_POST['edit_user_id'];
        $username = trim($_POST['edit_username']);
        $email = trim($_POST['edit_email']);
        $name = trim($_POST['edit_name']);
        $role = $_POST['edit_role'];
        $location_type = $_POST['edit_location_type'] ?? 'headquarters';
        $branch_name = trim($_POST['edit_branch_name'] ?? '');
        $department = trim($_POST['edit_department'] ?? '');
        $pumble_user_id = trim($_POST['edit_pumble_user_id'] ?? '');
        $pumble_webhook_url = trim($_POST['edit_pumble_webhook_url'] ?? '');
        $newPassword = $_POST['edit_password'] ?? '';
        
        // Check duplicate username (exclude current user)
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $userId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'Username นี้มีในระบบแล้ว';
            $messageType = 'error';
        } else {
            if (!empty($newPassword)) {
                // Update with new password
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, name=?, password=?, role=?, location_type=?, branch_name=?, department=?, pumble_user_id=?, pumble_webhook_url=? WHERE id=?");
                $stmt->bind_param("ssssssssssi", $username, $email, $name, $hashedPassword, $role, $location_type, $branch_name, $department, $pumble_user_id, $pumble_webhook_url, $userId);
            } else {
                // Update without password
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, name=?, role=?, location_type=?, branch_name=?, department=?, pumble_user_id=?, pumble_webhook_url=? WHERE id=?");
                $stmt->bind_param("sssssssssi", $username, $email, $name, $role, $location_type, $branch_name, $department, $pumble_user_id, $pumble_webhook_url, $userId);
            }
            
            if ($stmt->execute()) {
                $message = 'อัปเดตข้อมูลผู้ใช้สำเร็จ';
                $messageType = 'success';
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $userId = (int) $_POST['delete_user_id'];
        
        // ป้องกันลบตัวเอง
        $checkSelf = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $checkSelf->bind_param("i", $userId);
        $checkSelf->execute();
        $targetUser = $checkSelf->get_result()->fetch_assoc();
        
        if ($targetUser && strtolower($targetUser['username']) === strtolower($currentUsername)) {
            $message = 'ไม่สามารถลบบัญชีตัวเองได้';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $message = 'ลบผู้ใช้สำเร็จ';
                $messageType = 'success';
            } else {
                $message = 'เกิดข้อผิดพลาด';
                $messageType = 'error';
            }
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

// Available roles
$availableRoles = ['admin', 'owner', 'approve', 'area', 'brand', 'marketing', 'shop'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools - ULG Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d1a;
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            background: #1a1a2e;
            padding: 20px 30px;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border: 1px solid #2a2a4a;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 1.5em; color: #fbbf24; }
        .btn-nav {
            background: #2a2a4a;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid #3a3a5a;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-nav:hover { background: #3a3a5a; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.3); }
        .alert.error { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); }
        
        .card {
            background: #1a1a2e;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #2a2a4a;
        }
        .card h2 {
            font-size: 1.3em;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f59e0b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #a0a0b0;
            font-size: 13px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 14px;
            background: #252542;
            border: 1px solid #3a3a5a;
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #f59e0b;
        }
        .form-group input::placeholder { color: #666; }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #1a1a2e; }
        .btn-success { background: #2ed573; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .btn-info { background: #3b82f6; color: #fff; }
        .btn-sm { padding: 8px 14px; font-size: 12px; }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        
        .table-wrapper { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table th, .table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #2a2a4a;
        }
        .table th {
            background: #252542;
            color: #a0a0b0;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .table tr:hover { background: rgba(255,255,255,0.03); }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        .role-badge.admin { background: rgba(124, 58, 237, 0.2); color: #a78bfa; }
        .role-badge.owner { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .role-badge.shop { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .role-badge.brand { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
        .role-badge.marketing { background: rgba(251, 146, 60, 0.2); color: #fb923c; }
        .role-badge.approve { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .role-badge.area { background: rgba(20, 184, 166, 0.2); color: #2dd4bf; }
        
        .action-btns { display: flex; gap: 6px; }
        
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: #1a1a2e;
            border-radius: 15px;
            padding: 30px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #3a3a5a;
        }
        .modal h3 {
            font-size: 1.3em;
            margin-bottom: 20px;
            color: #fbbf24;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .text-muted { color: #666; font-size: 12px; }
        .text-truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .header { flex-direction: column; text-align: center; }
            .form-grid { grid-template-columns: 1fr; }
            .table { font-size: 12px; }
            .action-btns { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Admin Tools</h1>
            <div>
                <a href="../dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
                <a href="permissions.php" class="btn-nav"><i class="far fa-check-square"></i> Permissions</a>
                <a href="../logout.php" class="btn-nav"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Add User Card -->
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="new_username" required placeholder="username">
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="new_password" required placeholder="รหัสผ่าน">
                    </div>
                    <div class="form-group">
                        <label>ชื่อแสดง *</label>
                        <input type="text" name="new_name" required placeholder="ชื่อ-นามสกุล">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="new_email" placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="new_role" required>
                            <?php foreach ($availableRoles as $r): ?>
                            <option value="<?= $r ?>"><?= strtoupper($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location Type</label>
                        <select name="new_location_type">
                            <option value="headquarters">Headquarters</option>
                            <option value="branch">Branch</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch Name</label>
                        <input type="text" name="new_branch_name" placeholder="ชื่อสาขา (ถ้ามี)">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="new_department" placeholder="แผนก">
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มผู้ใช้
                </button>
            </form>
        </div>
        
        <!-- Users List Card -->
        <div class="card">
            <h2><i class="fas fa-users"></i> รายการผู้ใช้ทั้งหมด (<?= count($users) ?> คน)</h2>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>ชื่อ</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Location</th>
                            <th>Branch</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td class="text-truncate"><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                            <td>
                                <span class="role-badge <?= strtolower($user['role']) ?>">
                                    <?= strtoupper($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['location_type'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['branch_name'] ?: '-') ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-info btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3><i class="fas fa-user-edit"></i> แก้ไขข้อมูลผู้ใช้</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="edit_username" id="edit_username" required>
                    </div>
                    <div class="form-group">
                        <label>Password ใหม่ <span class="text-muted">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                        <input type="password" name="edit_password" id="edit_password" placeholder="รหัสผ่านใหม่">
                    </div>
                    <div class="form-group">
                        <label>ชื่อแสดง *</label>
                        <input type="text" name="edit_name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="edit_email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="edit_role" id="edit_role" required>
                            <?php foreach ($availableRoles as $r): ?>
                            <option value="<?= $r ?>"><?= strtoupper($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location Type</label>
                        <select name="edit_location_type" id="edit_location_type">
                            <option value="headquarters">Headquarters</option>
                            <option value="branch">Branch</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch Name</label>
                        <input type="text" name="edit_branch_name" id="edit_branch_name">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="edit_department" id="edit_department">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>Pumble User ID</label>
                    <input type="text" name="edit_pumble_user_id" id="edit_pumble_user_id" placeholder="Pumble User ID">
                </div>
                <div class="form-group">
                    <label>Pumble Webhook URL</label>
                    <input type="text" name="edit_pumble_webhook_url" id="edit_pumble_webhook_url" placeholder="https://api.pumble.com/...">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #3a3a5a;" onclick="closeEditModal()">ยกเลิก</button>
                    <button type="submit" name="update_user" class="btn btn-success">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form (Hidden) -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="delete_user_id" id="delete_user_id">
        <input type="hidden" name="delete_user" value="1">
    </form>
    
    <script>
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role || 'shop';
            document.getElementById('edit_location_type').value = user.location_type || 'headquarters';
            document.getElementById('edit_branch_name').value = user.branch_name || '';
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('edit_pumble_user_id').value = user.pumble_user_id || '';
            document.getElementById('edit_pumble_webhook_url').value = user.pumble_webhook_url || '';
            document.getElementById('edit_password').value = '';
            
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function confirmDelete(userId, username) {
            if (confirm('ยืนยันลบผู้ใช้ "' + username + '"?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้!')) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>
</body>
</html>