<?php
// admin/approve.php - Approve Pending Users
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

// Check superadmin
$superadmins = ['admin', 'oat', 'it', 'may'];
$currentUsername = strtolower($_SESSION["username"] ?? '');
if (!in_array($currentUsername, $superadmins)) {
    echo "<script>alert('เฉพาะ Admin เท่านั้น'); window.location='../dashboard.php';</script>";
    exit;
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($userId > 0) {
        if ($action === 'approve') {
            $newRole = $_POST['role'] ?? 'shop';
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role = 'pending'");
            $stmt->bind_param("si", $newRole, $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'อนุมัติสำเร็จ!';
                $messageType = 'success';
            }
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'pending'");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'ปฏิเสธและลบสำเร็จ!';
                $messageType = 'success';
            }
        }
    }
}

// Get pending users
$pendingUsers = $conn->query("SELECT * FROM users WHERE role = 'pending' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get recently approved (last 7 days)
$recentApproved = $conn->query("SELECT * FROM users WHERE role != 'pending' AND role != 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Available roles for approval
$roles = ['shop', 'brand', 'area', 'marketing', 'owner'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติผู้ใช้งาน | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #1a1a2e;
            min-height: 100vh;
            color: #fff;
            padding: 20px;
            padding-bottom: 100px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);
            padding: 20px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge {
            background: #e94560;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #e94560;
            color: #fff;
        }
        
        .btn-secondary {
            background: #252542;
            color: #fff;
        }
        
        .btn-success {
            background: #2ed573;
            color: #fff;
        }
        
        .btn-danger {
            background: #ff6b6b;
            color: #fff;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        
        .btn-sm {
            padding: 10px 16px;
            font-size: 13px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert.success {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .alert.error {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
        }
        
        .section-title {
            font-size: 1.1em;
            color: #fbbf24;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card {
            background: #16213e;
            border-radius: 16px;
            margin-bottom: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .card-header {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 12px 15px;
            border-radius: 10px;
        }
        
        .info-item label {
            display: block;
            font-size: 11px;
            color: #888;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .info-item value {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            word-break: break-all;
        }
        
        .card-actions {
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .role-select {
            padding: 10px 15px;
            background: #252542;
            border: 1px solid #3a3a5a;
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        
        .role-select:focus {
            outline: none;
            border-color: #e94560;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #3a3a5a;
        }
        
        .username {
            font-size: 18px;
            font-weight: 700;
            color: #e94560;
        }
        
        .timestamp {
            font-size: 12px;
            color: #888;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        
        .status-approved {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
        }
        
        /* Approved users list */
        .approved-list {
            margin-top: 30px;
        }
        
        .approved-item {
            background: #16213e;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .approved-item .user-name {
            font-weight: 600;
        }
        
        .approved-item .user-detail {
            font-size: 12px;
            color: #888;
        }
        
        .role-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #252542;
            color: #2ed573;
        }
        
        /* Mobile optimizations */
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.1em;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .card-actions .btn,
            .card-actions .role-select {
                width: 100%;
                justify-content: center;
            }
            
            .user-info {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-user-check"></i>
                อนุมัติผู้ใช้งาน
                <?php if (count($pendingUsers) > 0): ?>
                <span class="badge"><?= count($pendingUsers) ?></span>
                <?php endif; ?>
            </h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
                <a href="../dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i></a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <h3 class="section-title">
            <i class="fas fa-clock"></i>
            รอการอนุมัติ
        </h3>
        
        <?php if (empty($pendingUsers)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>ไม่มีผู้ใช้รอการอนุมัติ</h3>
                <p>ทุกคำขอได้รับการดำเนินการแล้ว</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($pendingUsers as $user): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                    <span class="status-badge status-pending">รอการอนุมัติ</span>
                </div>
                <span class="timestamp">
                    <i class="fas fa-clock"></i>
                    <?= $user['created_at'] ? date('d/m/Y H:i', strtotime($user['created_at'])) : '-' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="user-info">
                    <div class="info-item">
                        <label><i class="fas fa-store"></i> ชื่อร้าน</label>
                        <value><?= htmlspecialchars($user['department'] ?: '-') ?></value>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-map-marker-alt"></i> สาขา</label>
                        <value><?= htmlspecialchars($user['branch_name'] ?: '-') ?></value>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-envelope"></i> อีเมล</label>
                        <value><?= htmlspecialchars($user['email'] ?: '-') ?></value>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-user"></i> ชื่อ</label>
                        <value><?= htmlspecialchars($user['name'] ?: '-') ?></value>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;flex:1;">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <select name="role" class="role-select">
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                        <i class="fas fa-check"></i> อนุมัติ
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('ปฏิเสธและลบผู้ใช้นี้?')">
                        <i class="fas fa-times"></i> ปฏิเสธ
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($recentApproved)): ?>
        <div class="approved-list">
            <h3 class="section-title">
                <i class="fas fa-user-check"></i>
                อนุมัติล่าสุด (7 วัน)
            </h3>
            
            <?php foreach ($recentApproved as $user): ?>
            <div class="approved-item">
                <div>
                    <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                    <div class="user-detail">
                        <?= htmlspecialchars($user['department']) ?> - <?= htmlspecialchars($user['branch_name']) ?>
                    </div>
                </div>
                <div style="text-align:right">
                    <span class="role-badge"><?= ucfirst($user['role']) ?></span>
                    <div class="timestamp" style="margin-top:5px"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>