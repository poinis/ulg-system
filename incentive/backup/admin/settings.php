<?php
// admin/settings.php - System Settings
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $settings = [
            'max_base_incentive' => (int) $_POST['max_base_incentive'],
            'target_points' => (int) $_POST['target_points'],
            'trophy_bonus_per_person' => (int) $_POST['trophy_bonus_per_person'],
            'budget_cap_per_person' => (int) $_POST['budget_cap_per_person']
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("UPDATE incentive_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $stmt->bind_param("sis", $value, $userId, $key);
            $stmt->execute();
        }
        
        $message = 'บันทึกการตั้งค่าเรียบร้อย';
        $messageType = 'success';
    }
    
    if (isset($_POST['add_branch'])) {
        $branchCode = strtoupper(trim($_POST['branch_code']));
        $branchName = trim($_POST['branch_name']);
        
        if ($branchCode && $branchName) {
            $stmt = $conn->prepare("INSERT INTO incentive_branches (branch_code, branch_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE branch_name = VALUES(branch_name)");
            $stmt->bind_param("ss", $branchCode, $branchName);
            if ($stmt->execute()) {
                $message = 'เพิ่มสาขาเรียบร้อย';
                $messageType = 'success';
            }
        }
    }
    
    if (isset($_POST['toggle_branch'])) {
        $branchId = (int) $_POST['branch_id'];
        $isActive = (int) $_POST['is_active'];
        $stmt = $conn->prepare("UPDATE incentive_branches SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $isActive, $branchId);
        $stmt->execute();
        $message = $isActive ? 'เปิดใช้งานสาขาแล้ว' : 'ปิดใช้งานสาขาแล้ว';
        $messageType = 'success';
    }
}

// Get current settings
$maxIncentive = getSetting($conn, 'max_base_incentive', 2500);
$targetPoints = getSetting($conn, 'target_points', 100);
$trophyBonus = getSetting($conn, 'trophy_bonus_per_person', 500);
$budgetCap = getSetting($conn, 'budget_cap_per_person', 2200);

// Get all branches
$branches = $conn->query("SELECT * FROM incentive_branches ORDER BY is_active DESC, branch_name")->fetch_all(MYSQLI_ASSOC);

// Get pending count
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f7fa; min-height: 100vh; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: #fff; padding: 20px 0; z-index: 100;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 20px; font-weight: 600; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.6); margin-top: 4px; }
        .nav-menu { list-style: none; }
        .nav-menu a {
            display: flex; align-items: center; gap: 12px; padding: 14px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1); color: #fff; border-left-color: #667eea;
        }
        .nav-menu a i { width: 20px; text-align: center; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge.pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        
        .main-content { margin-left: 260px; padding: 24px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .top-bar h1 { font-size: 24px; color: #1a1a2e; }
        
        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background: rgba(46, 213, 115, 0.1); color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.3); }
        .alert.error { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); }
        
        /* Card */
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eee;
        }
        .card-header h3 { font-size: 18px; color: #1a1a2e; }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group .hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .btn-sm {
            padding: 8px 14px;
            font-size: 13px;
        }
        
        /* Branch Table */
        .branch-table {
            width: 100%;
            border-collapse: collapse;
        }
        .branch-table th, .branch-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .branch-table th {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            background: #f9f9f9;
        }
        .branch-table tr:hover { background: #f9f9f9; }
        .branch-table .inactive { opacity: 0.5; }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.active { background: rgba(46, 213, 115, 0.1); color: #2ed573; }
        .status-badge.inactive { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }
        
        /* Add Branch Form */
        .add-branch-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        .add-branch-form .form-group { flex: 1; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .add-branch-form { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🎯 Incentive</h2>
            <p>Admin Panel</p>
        </div>
        <ul class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="approve.php">
                <i class="fas fa-clipboard-check"></i> ตรวจสอบงาน
                <?php if ($pendingCount > 0): ?>
                <span class="badge pending" style="margin-left: auto;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php"><i class="fas fa-calculator"></i> คำนวณเงิน</a>
            <a href="trophy.php"><i class="fas fa-trophy"></i> Trophy Bonus</a>
            <a href="settings.php" class="active"><i class="fas fa-cog"></i> ตั้งค่า</a>
            <a href="export.php"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../checklist.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> กลับหน้า Checklist
            </a>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-cog"></i> ตั้งค่าระบบ</h1>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Incentive Settings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h"></i> ตั้งค่า Incentive</h3>
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Base Incentive สูงสุด (บาท)</label>
                        <input type="number" name="max_base_incentive" value="<?= $maxIncentive ?>" min="0" step="100">
                        <div class="hint">เงิน Incentive สูงสุดเมื่อทำครบ 100%</div>
                    </div>
                    <div class="form-group">
                        <label>เป้าหมายคะแนน/เดือน</label>
                        <input type="number" name="target_points" value="<?= $targetPoints ?>" min="1">
                        <div class="hint">คะแนนที่ต้องทำให้ครบเพื่อได้ 100%</div>
                    </div>
                    <div class="form-group">
                        <label>Trophy Bonus ต่อคน (บาท)</label>
                        <input type="number" name="trophy_bonus_per_person" value="<?= $trophyBonus ?>" min="0" step="100">
                        <div class="hint">โบนัสต่อคนเมื่อได้รางวัล Trophy</div>
                    </div>
                    <div class="form-group">
                        <label>Budget Cap ต่อคน (บาท)</label>
                        <input type="number" name="budget_cap_per_person" value="<?= $budgetCap ?>" min="0" step="100">
                        <div class="hint">เพดาน Base Incentive สูงสุด (ไม่รวม Trophy)</div>
                    </div>
                </div>
                <div style="margin-top: 24px;">
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Branch Management -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-store"></i> จัดการสาขา</h3>
            </div>
            
            <!-- Add Branch -->
            <form method="POST" class="add-branch-form" style="margin-bottom: 24px;">
                <div class="form-group">
                    <label>รหัสสาขา</label>
                    <input type="text" name="branch_code" placeholder="เช่น SIAM" required maxlength="20" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label>ชื่อสาขา</label>
                    <input type="text" name="branch_name" placeholder="เช่น Siam Paragon" required>
                </div>
                <button type="submit" name="add_branch" class="btn btn-success" style="height: fit-content;">
                    <i class="fas fa-plus"></i> เพิ่มสาขา
                </button>
            </form>
            
            <!-- Branch List -->
            <table class="branch-table">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อสาขา</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                    <tr class="<?= $branch['is_active'] ? '' : 'inactive' ?>">
                        <td><strong><?= htmlspecialchars($branch['branch_code']) ?></strong></td>
                        <td><?= htmlspecialchars($branch['branch_name']) ?></td>
                        <td>
                            <span class="status-badge <?= $branch['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $branch['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน' ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $branch['is_active'] ? 0 : 1 ?>">
                                <button type="submit" name="toggle_branch" 
                                        class="btn btn-sm <?= $branch['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                    <?php if ($branch['is_active']): ?>
                                    <i class="fas fa-ban"></i> ปิดใช้งาน
                                    <?php else: ?>
                                    <i class="fas fa-check"></i> เปิดใช้งาน
                                    <?php endif; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
