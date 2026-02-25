<?php
// admin/dashboard.php - Admin Dashboard
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check login & admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isAdmin($_SESSION)) {
    header('Location: ../checklist.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'Admin';

// Get current month stats
$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedBranch = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Get data
$branches = getBranches($conn);
$branchSummary = getMonthlyBranchSummary($conn, $yearMonth);

// Get pending count
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];

// Get today's submissions count
$todayResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE submission_date = CURDATE()");
$todayCount = $todayResult->fetch_assoc()['cnt'];

// Calculate total points this month
$totalPoints = 0;
foreach ($branchSummary as $b) {
    $totalPoints += $b['total_points'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Incentive System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 20px 0;
            z-index: 100;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        .sidebar-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }
        .nav-menu {
            list-style: none;
        }
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: #667eea;
        }
        .nav-menu a i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 24px;
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .top-bar h1 {
            font-size: 24px;
            color: #1a1a2e;
        }
        .top-bar .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .top-bar .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        .stat-card .icon.purple { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .stat-card .icon.orange { background: rgba(255, 159, 67, 0.1); color: #ff9f43; }
        .stat-card .icon.green { background: rgba(46, 213, 115, 0.1); color: #2ed573; }
        .stat-card .icon.blue { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .stat-card .label {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }
        
        /* Cards */
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
        }
        .card-header h3 {
            font-size: 18px;
            color: #1a1a2e;
        }
        
        /* Month Selector */
        .month-selector {
            display: flex;
            gap: 8px;
        }
        .month-selector input, .month-selector select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        .month-selector button {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
        }
        
        /* Branch Summary Table */
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
        }
        .branch-table tr:hover {
            background: #f9f9f9;
        }
        .progress-mini {
            width: 100px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-mini-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge.pending { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .badge.success { background: rgba(46, 213, 115, 0.1); color: #2ed573; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 24px;
            border: 2px dashed #ddd;
            border-radius: 12px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
        }
        .action-btn:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .action-btn i {
            font-size: 32px;
        }
        .action-btn span {
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
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
            <a href="dashboard.php" class="active">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="approve.php">
                <i class="fas fa-clipboard-check"></i> ตรวจสอบงาน
                <?php if ($pendingCount > 0): ?>
                <span class="badge pending" style="margin-left: auto;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php">
                <i class="fas fa-calculator"></i> คำนวณเงิน
            </a>
            <a href="trophy.php">
                <i class="fas fa-trophy"></i> Trophy Bonus
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i> ตั้งค่า
            </a>
            <a href="export.php">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="../checklist.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> กลับหน้า Checklist
            </a>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>สวัสดี, <?= htmlspecialchars($userName) ?></span>
                <div class="avatar"><?= mb_substr($userName, 0, 1) ?></div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon purple"><i class="fas fa-clipboard-check"></i></div>
                <div class="value"><?= $todayCount ?></div>
                <div class="label">งานส่งวันนี้</div>
            </div>
            <div class="stat-card">
                <div class="icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="value"><?= $pendingCount ?></div>
                <div class="label">รอตรวจสอบ</div>
            </div>
            <div class="stat-card">
                <div class="icon green"><i class="fas fa-star"></i></div>
                <div class="value"><?= number_format($totalPoints) ?></div>
                <div class="label">คะแนนรวมเดือนนี้</div>
            </div>
            <div class="stat-card">
                <div class="icon blue"><i class="fas fa-store"></i></div>
                <div class="value"><?= count($branchSummary) ?></div>
                <div class="label">สาขาทั้งหมด</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> ทางลัด</h3>
            </div>
            <div class="quick-actions">
                <a href="approve.php?status=pending" class="action-btn">
                    <i class="fas fa-check-double"></i>
                    <span>ตรวจงานที่รอ (<?= $pendingCount ?>)</span>
                </a>
                <a href="payroll.php?month=<?= $yearMonth ?>" class="action-btn">
                    <i class="fas fa-calculator"></i>
                    <span>คำนวณ Incentive</span>
                </a>
                <a href="trophy.php?month=<?= $yearMonth ?>" class="action-btn">
                    <i class="fas fa-award"></i>
                    <span>ให้รางวัล Trophy</span>
                </a>
            </div>
        </div>
        
        <!-- Branch Summary -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-store"></i> สรุปคะแนนตามสาขา</h3>
                <form class="month-selector" method="GET">
                    <input type="month" name="month" value="<?= $yearMonth ?>">
                    <button type="submit"><i class="fas fa-filter"></i> กรอง</button>
                </form>
            </div>
            <table class="branch-table">
                <thead>
                    <tr>
                        <th>สาขา</th>
                        <th>คะแนนรวม</th>
                        <th>Progress</th>
                        <th>รอตรวจ</th>
                        <th>ผู้ใช้งาน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branchSummary as $branch): 
                        $progress = min(($branch['total_points'] / 100) * 100, 100);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($branch['branch_name']) ?></strong>
                            <br><small style="color: #999;"><?= $branch['branch_code'] ?></small>
                        </td>
                        <td>
                            <strong style="font-size: 18px; color: #667eea;"><?= $branch['total_points'] ?></strong>
                            <span style="color: #999;">/ 100</span>
                        </td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar" style="width: <?= $progress ?>%"></div>
                            </div>
                            <small style="color: #999;"><?= number_format($progress, 1) ?>%</small>
                        </td>
                        <td>
                            <?php if ($branch['pending_count'] > 0): ?>
                            <span class="badge pending"><?= $branch['pending_count'] ?> รายการ</span>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $branch['active_users'] ?> คน</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($branchSummary)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999; padding: 40px;">
                            ยังไม่มีข้อมูลในเดือนนี้
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
