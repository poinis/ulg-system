<?php
// admin/reset_password.php - Reset User Password (Superadmin Only)
session_start();

// เช็ค login เหมือนระบบเดิม
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

// เฉพาะ superadmin เท่านั้น (กำหนด username ที่เป็น superadmin)
$superadmins = ['admin', 'oat', 'it', 'may']; // แก้ไขได้ตามต้องการ
$currentUsername = $_SESSION["username"] ?? '';

if (!in_array(strtolower($currentUsername), $superadmins)) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location='../dashboard.php';</script>";
    exit;
}

// ดึงชื่อ user ปัจจุบัน
$user_name = '';
$sql = "SELECT name FROM users WHERE username = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $currentUsername);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_name);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

$message = '';
$messageType = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = (int) $_POST['user_id'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (strlen($newPassword) < 4) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'รหัสผ่านไม่ตรงกัน';
        $messageType = 'error';
    } else {
        // Hash password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            // Get username for log
            $stmt2 = $conn->prepare("SELECT username, name FROM users WHERE id = ?");
            $stmt2->bind_param("i", $userId);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $user = $result->fetch_assoc();
            
            $message = "รีเซ็ตรหัสผ่านสำเร็จสำหรับ: {$user['name']} ({$user['username']})";
            $messageType = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Get all users
$users = $conn->query("SELECT id, username, name, email, role FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ULG Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d1a;
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .header {
            background: #1a1a2e;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #2a2a4a;
        }
        .welcome-text {
            font-size: 1.5em;
            color: #ffffff;
            font-weight: 600;
        }
        .welcome-text span { color: #a78bfa; }
        
        .button-group { display: flex; gap: 10px; align-items: center; }
        .btn-nav {
            background: #2a2a4a;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #3a3a5a;
        }
        .btn-nav:hover {
            background: #3a3a5a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.3);
        }
        
        /* Main Content */
        .main-content {
            background: #1a1a2e;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid #2a2a4a;
        }
        .section-title {
            font-size: 1.8em;
            color: #ffffff;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #7c3aed;
        }
        
        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.3); }
        .alert.error { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); }
        
        /* Search */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 1px solid #3a3a5a;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            background: #252542;
            color: #fff;
        }
        .search-box input::placeholder { color: #888; }
        .search-box input:focus { outline: none; border-color: #7c3aed; }
        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
        }
        
        /* User Table */
        .table-wrapper { overflow-x: auto; }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th, .user-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #2a2a4a;
        }
        .user-table th {
            font-weight: 600;
            color: #a0a0b0;
            font-size: 13px;
            text-transform: uppercase;
            background: #252542;
        }
        .user-table tr:hover { background: #252542; }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-badge.admin { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); }
        .role-badge.owner { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #000; }
        .role-badge.approve { background: linear-gradient(135deg, #059669 0%, #34d399 100%); }
        .role-badge.area { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }
        .role-badge.brand { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
        .role-badge.marketing { background: linear-gradient(135deg, #f97316 0%, #fb923c 100%); }
        
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: #1a1a2e;
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
            border: 1px solid #3a3a5a;
        }
        .modal h3 {
            margin-bottom: 20px;
            color: #fff;
            font-size: 1.4em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal .form-group { margin-bottom: 18px; }
        .modal .form-group label {
            display: block;
            font-size: 14px;
            color: #a0a0b0;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .modal .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #3a3a5a;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            background: #252542;
            color: #fff;
        }
        .modal .form-group input:focus { outline: none; border-color: #7c3aed; }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .modal-actions .btn { flex: 1; justify-content: center; padding: 14px; }
        .btn-cancel { background: #3a3a5a; color: #fff; }
        
        .user-info-display {
            background: #252542;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #3a3a5a;
        }
        .user-info-display p { margin: 6px 0; font-size: 14px; color: #a0a0b0; }
        .user-info-display strong { color: #fff; }
        
        .warning-text {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            padding: 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #a0a0b0;
            font-size: 14px;
        }
        .checkbox-group input { width: auto; }
        
        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .header { padding: 20px; flex-direction: column; text-align: center; }
            .button-group { width: 100%; flex-direction: column; }
            .btn-nav { width: 100%; text-align: center; }
            .main-content { padding: 20px; }
            .section-title { font-size: 1.4em; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="welcome-text">
                🔐 Reset Password <span>Superadmin</span>
            </div>
            <div class="button-group">
                <a href="../dashboard.php" class="btn-nav">🏠 Dashboard</a>
                <a href="../logout.php" class="btn-nav">ออกจากระบบ</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="section-title">👥 จัดการรหัสผ่านผู้ใช้</h2>
            
            <?php if ($message): ?>
            <div class="alert <?= $messageType ?>">
                <?= $messageType === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="ค้นหาชื่อ, username, email..." onkeyup="filterUsers()">
            </div>
            
            <div class="table-wrapper">
                <table class="user-table" id="userTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                            <td>
                                <span class="role-badge <?= strtolower($user['role']) ?>">
                                    <?= strtoupper(htmlspecialchars($user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['name']) ?>')">
                                    🔑 Reset
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal">
            <h3>🔑 Reset Password</h3>
            
            <div class="user-info-display">
                <p><strong>ชื่อ:</strong> <span id="modalUserName"></span></p>
                <p><strong>Username:</strong> <span id="modalUsername"></span></p>
            </div>
            
            <div class="warning-text">
                ⚠️ การรีเซ็ตรหัสผ่านจะมีผลทันที ผู้ใช้จะต้องใช้รหัสผ่านใหม่ในการเข้าสู่ระบบ
            </div>
            
            <form method="POST" id="resetForm">
                <input type="hidden" name="user_id" id="resetUserId">
                
                <div class="form-group">
                    <label>รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" id="newPassword" required minlength="4" 
                           placeholder="อย่างน้อย 4 ตัวอักษร">
                </div>
                
                <div class="form-group">
                    <label>ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" id="confirmPassword" required minlength="4"
                           placeholder="กรอกรหัสผ่านอีกครั้ง">
                </div>
                
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" id="showPassword" onchange="togglePassword()"> 
                        แสดงรหัสผ่าน
                    </label>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeResetModal()">
                        ยกเลิก
                    </button>
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        💾 รีเซ็ตรหัสผ่าน
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openResetModal(userId, username, name) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('modalUserName').textContent = name;
            document.getElementById('modalUsername').textContent = username;
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            document.getElementById('showPassword').checked = false;
            document.getElementById('resetModal').classList.add('show');
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('show');
        }
        
        function togglePassword() {
            const show = document.getElementById('showPassword').checked;
            document.getElementById('newPassword').type = show ? 'text' : 'password';
            document.getElementById('confirmPassword').type = show ? 'text' : 'password';
        }
        
        function filterUsers() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#userTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('รหัสผ่านไม่ตรงกัน');
                return false;
            }
            
            if (newPass.length < 4) {
                e.preventDefault();
                alert('รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร');
                return false;
            }
            
            return confirm('ยืนยันการรีเซ็ตรหัสผ่าน?');
        });
        
        // Close modal on escape or click outside
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeResetModal(); });
        document.getElementById('resetModal').addEventListener('click', e => { if (e.target.id === 'resetModal') closeResetModal(); });
    </script>
</body>
</html>