<?php
// admin/payroll.php - Payroll Calculator
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    header('Location: ../login.php');
    exit;
}

$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$payrollData = calculatePayroll($conn, $yearMonth);

// Get settings
$targetPoints = (int) getSetting($conn, 'target_points', 100);
$maxIncentive = (float) getSetting($conn, 'max_base_incentive', 2500);
$budgetCap = (float) getSetting($conn, 'budget_cap_per_person', 2200);

// Calculate totals
$totalBasePayout = 0;
$totalTrophyPayout = 0;
$totalPayout = 0;
$totalPending = 0;

foreach ($payrollData as $p) {
    $totalBasePayout += $p['base_incentive'];
    $totalTrophyPayout += $p['trophy_bonus'];
    $totalPayout += $p['total_payout'];
    $totalPending += $p['pending_count'];
}

// Get pending count for badge
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำนวณ Incentive | Admin</title>
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
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .summary-card.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .summary-card .label { font-size: 14px; color: rgba(0,0,0,0.5); margin-bottom: 8px; }
        .summary-card.highlight .label { color: rgba(255,255,255,0.8); }
        .summary-card .value { font-size: 32px; font-weight: 700; color: #1a1a2e; }
        .summary-card.highlight .value { color: #fff; }
        .summary-card .sub { font-size: 13px; color: #999; margin-top: 4px; }
        .summary-card.highlight .sub { color: rgba(255,255,255,0.7); }
        
        /* Filter */
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filter-bar input, .filter-bar select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        .filter-bar button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        
        /* Warning */
        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #856404;
        }
        .warning-box i { font-size: 24px; color: #ffc107; }
        
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
        }
        .card-header h3 { font-size: 18px; color: #1a1a2e; }
        
        /* Payroll Table */
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payroll-table th, .payroll-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .payroll-table th {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            background: #f9f9f9;
        }
        .payroll-table tr:hover { background: #f9f9f9; }
        .payroll-table .points {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        .payroll-table .money {
            font-size: 18px;
            font-weight: 600;
            color: #2ed573;
        }
        .progress-mini {
            width: 80px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }
        .progress-mini-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
        }
        .trophy-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .trophy-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .trophy-badge.most_views { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }
        .trophy-badge.most_review_growth { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .trophy-badge.hq_choice { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        
        .total-row {
            background: #f5f7fa !important;
            font-weight: 600;
        }
        .total-row .money {
            font-size: 22px;
            color: #1a1a2e;
        }
        
        /* Export Button */
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #2ed573;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }
        .export-btn:hover { background: #26b863; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .summary-grid { grid-template-columns: 1fr; }
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
            <a href="payroll.php" class="active"><i class="fas fa-calculator"></i> คำนวณเงิน</a>
            <a href="trophy.php"><i class="fas fa-trophy"></i> Trophy Bonus</a>
            <a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a>
            <a href="export.php"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../checklist.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> กลับหน้า Checklist
            </a>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-calculator"></i> คำนวณ Incentive</h1>
            <a href="export.php?month=<?= $yearMonth ?>" class="export-btn">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
        
        <!-- Filter -->
        <form class="filter-bar" method="GET">
            <label><i class="fas fa-calendar"></i> เลือกเดือน:</label>
            <input type="month" name="month" value="<?= $yearMonth ?>">
            <button type="submit"><i class="fas fa-sync"></i> คำนวณใหม่</button>
        </form>
        
        <?php if ($totalPending > 0): ?>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>มีงานรอตรวจสอบ <?= $totalPending ?> รายการ</strong>
                <br>กรุณาตรวจสอบให้เสร็จก่อนส่งข้อมูล Payroll
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Base Incentive รวม</div>
                <div class="value">฿<?= number_format($totalBasePayout, 0) ?></div>
                <div class="sub">จากเป้า <?= $targetPoints ?> คะแนน = ฿<?= number_format($maxIncentive, 0) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Trophy Bonus รวม</div>
                <div class="value">฿<?= number_format($totalTrophyPayout, 0) ?></div>
                <div class="sub">โบนัสพิเศษจากรางวัล</div>
            </div>
            <div class="summary-card highlight">
                <div class="label">รวมจ่ายทั้งหมด</div>
                <div class="value">฿<?= number_format($totalPayout, 0) ?></div>
                <div class="sub"><?= count($payrollData) ?> สาขา</div>
            </div>
            <div class="summary-card">
                <div class="label">Budget Cap / คน</div>
                <div class="value">฿<?= number_format($budgetCap, 0) ?></div>
                <div class="sub">เพดานไม่รวม Trophy</div>
            </div>
        </div>
        
        <!-- Payroll Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-table"></i> รายละเอียดตามสาขา - <?= thaiDate($yearMonth . '-01', 'full') ?></h3>
            </div>
            <table class="payroll-table">
                <thead>
                    <tr>
                        <th>สาขา</th>
                        <th>คะแนนรวม</th>
                        <th>% ของเป้า</th>
                        <th>Base Incentive</th>
                        <th>Trophy Bonus</th>
                        <th>รวมจ่าย/คน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payrollData as $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['branch_name']) ?></strong>
                            <br><small style="color: #999;"><?= $p['branch_code'] ?></small>
                        </td>
                        <td>
                            <span class="points"><?= $p['total_points'] ?></span>
                            <span style="color: #999;">/ <?= $targetPoints ?></span>
                        </td>
                        <td>
                            <strong><?= $p['payout_percent'] ?>%</strong>
                            <div class="progress-mini">
                                <div class="progress-mini-bar" style="width: <?= $p['payout_percent'] ?>%"></div>
                            </div>
                        </td>
                        <td class="money">฿<?= number_format($p['base_incentive'], 0) ?></td>
                        <td>
                            <?php if ($p['trophy_bonus'] > 0): ?>
                            <span class="money">฿<?= number_format($p['trophy_bonus'], 0) ?></span>
                            <div class="trophy-badges" style="margin-top: 4px;">
                                <?php foreach ($p['trophy_list'] as $trophy): ?>
                                <span class="trophy-badge <?= $trophy ?>">
                                    <?php
                                    $trophyNames = [
                                        'most_views' => '🔥 Views',
                                        'most_review_growth' => '⭐ Review',
                                        'hq_choice' => '🎨 HQ Choice'
                                    ];
                                    echo $trophyNames[$trophy] ?? $trophy;
                                    ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="money" style="font-size: 20px;">฿<?= number_format($p['total_payout'], 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td><strong>รวมทั้งหมด</strong></td>
                        <td></td>
                        <td></td>
                        <td class="money">฿<?= number_format($totalBasePayout, 0) ?></td>
                        <td class="money">฿<?= number_format($totalTrophyPayout, 0) ?></td>
                        <td class="money">฿<?= number_format($totalPayout, 0) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Formula Info -->
        <div class="card" style="background: #f9f9f9;">
            <h4 style="margin-bottom: 12px;"><i class="fas fa-info-circle"></i> สูตรคำนวณ</h4>
            <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                <li><strong>Base Incentive</strong> = (คะแนนรวมสาขา / <?= $targetPoints ?>) × ฿<?= number_format($maxIncentive, 0) ?> (สูงสุด 100%)</li>
                <li><strong>Trophy Bonus</strong> = รางวัลพิเศษ คนละ ฿500 ต่อรางวัล</li>
                <li><strong>Budget Cap</strong> = Base Incentive ไม่เกิน ฿<?= number_format($budgetCap, 0) ?>/คน (Trophy ไม่รวม)</li>
            </ul>
        </div>
    </div>
</body>
</html>
