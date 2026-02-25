<?php
// admin/trophy.php - Trophy Bonus Management
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Handle trophy award/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchId = (int) $_POST['branch_id'];
    $trophyType = $_POST['trophy_type'];
    $action = $_POST['action'];
    
    if ($action === 'award') {
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        awardTrophy($conn, $branchId, $yearMonth, $trophyType, $userId, $notes);
    } elseif ($action === 'remove') {
        removeTrophy($conn, $branchId, $yearMonth, $trophyType);
    }
    
    header('Location: trophy.php?month=' . $yearMonth);
    exit;
}

// Get data
$branches = getBranches($conn);
$branchSummary = getMonthlyBranchSummary($conn, $yearMonth);
$trophyWinners = getTrophyWinners($conn, $yearMonth);
$bonusPerPerson = (float) getSetting($conn, 'trophy_bonus_per_person', 500);

// Create trophy map for easy lookup
$trophyMap = [];
foreach ($trophyWinners as $tw) {
    $trophyMap[$tw['trophy_type']] = $tw;
}

// Trophy types
$trophyTypes = [
    'most_views' => [
        'name' => 'Most Views',
        'name_th' => 'ยอดวิวสูงสุด',
        'icon' => '🔥',
        'description' => 'สาขาที่คลิปมียอดวิวสูงสุด (Viral Hit)',
        'color' => '#ff6b6b'
    ],
    'most_review_growth' => [
        'name' => 'Most Review Growth',
        'name_th' => 'รีวิวเติบโตสูงสุด',
        'icon' => '⭐',
        'description' => 'สาขาที่มีรีวิว Google เพิ่มขึ้นมากที่สุด',
        'color' => '#667eea'
    ],
    'hq_choice' => [
        'name' => "HQ's Choice",
        'name_th' => 'HQ เลือก',
        'icon' => '🎨',
        'description' => 'คลิปที่โดนใจ HQ ที่สุด (ความคิดสร้างสรรค์ + ภาพลักษณ์แบรนด์)',
        'color' => '#ffc107'
    ]
];

// Get pending count
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trophy Bonus | Admin</title>
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
        .filter-bar input {
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
        
        /* Trophy Cards Grid */
        .trophy-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .trophy-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .trophy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .trophy-header {
            padding: 24px;
            text-align: center;
            color: #fff;
        }
        .trophy-header .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .trophy-header h3 {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .trophy-header p {
            font-size: 13px;
            opacity: 0.9;
        }
        .trophy-body {
            padding: 24px;
        }
        .trophy-prize {
            text-align: center;
            margin-bottom: 20px;
            padding: 16px;
            background: #f9f9f9;
            border-radius: 12px;
        }
        .trophy-prize .amount {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .trophy-prize .label {
            font-size: 13px;
            color: #999;
        }
        
        .winner-info {
            background: linear-gradient(135deg, rgba(46, 213, 115, 0.1), rgba(46, 213, 115, 0.05));
            border: 1px solid rgba(46, 213, 115, 0.3);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            margin-bottom: 16px;
        }
        .winner-info .winner-label {
            font-size: 12px;
            color: #2ed573;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .winner-info .winner-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .branch-select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .trophy-notes {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 12px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-award {
            background: linear-gradient(135deg, #2ed573 0%, #1abc9c 100%);
            color: #fff;
        }
        .btn-award:hover { opacity: 0.9; }
        .btn-remove {
            background: #ff6b6b;
            color: #fff;
        }
        .btn-remove:hover { background: #ee5a52; }
        
        /* Summary Card */
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        .card h3 {
            font-size: 18px;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        
        .summary-list {
            display: grid;
            gap: 12px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        .summary-item .info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .summary-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .summary-item .text h4 { font-size: 14px; color: #1a1a2e; }
        .summary-item .text p { font-size: 12px; color: #999; }
        .summary-item .bonus {
            font-size: 18px;
            font-weight: 700;
            color: #2ed573;
        }
        
        @media (max-width: 1200px) {
            .trophy-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
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
            <a href="trophy.php" class="active"><i class="fas fa-trophy"></i> Trophy Bonus</a>
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
            <h1><i class="fas fa-trophy"></i> Trophy Bonus</h1>
        </div>
        
        <!-- Filter -->
        <form class="filter-bar" method="GET">
            <label><i class="fas fa-calendar"></i> เลือกเดือน:</label>
            <input type="month" name="month" value="<?= $yearMonth ?>">
            <button type="submit"><i class="fas fa-filter"></i> เลือก</button>
        </form>
        
        <!-- Trophy Cards -->
        <div class="trophy-grid">
            <?php foreach ($trophyTypes as $type => $trophy): 
                $winner = $trophyMap[$type] ?? null;
            ?>
            <div class="trophy-card">
                <div class="trophy-header" style="background: linear-gradient(135deg, <?= $trophy['color'] ?>, <?= $trophy['color'] ?>99);">
                    <div class="icon"><?= $trophy['icon'] ?></div>
                    <h3><?= $trophy['name'] ?></h3>
                    <p><?= $trophy['name_th'] ?></p>
                </div>
                <div class="trophy-body">
                    <div class="trophy-prize">
                        <div class="amount">฿<?= number_format($bonusPerPerson, 0) ?></div>
                        <div class="label">ต่อคนในสาขา</div>
                    </div>
                    
                    <p style="font-size: 13px; color: #666; margin-bottom: 16px; text-align: center;">
                        <?= $trophy['description'] ?>
                    </p>
                    
                    <?php if ($winner): ?>
                    <div class="winner-info">
                        <div class="winner-label"><i class="fas fa-crown"></i> ผู้ชนะ</div>
                        <div class="winner-name"><?= htmlspecialchars($winner['branch_name']) ?></div>
                        <?php if ($winner['notes']): ?>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;"><?= htmlspecialchars($winner['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="branch_id" value="<?= $winner['branch_id'] ?>">
                        <input type="hidden" name="trophy_type" value="<?= $type ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn btn-remove" onclick="return confirm('ยืนยันการลบรางวัล?')">
                            <i class="fas fa-times"></i> ยกเลิกรางวัล
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="trophy_type" value="<?= $type ?>">
                        <input type="hidden" name="action" value="award">
                        <select name="branch_id" class="branch-select" required>
                            <option value="">-- เลือกสาขาที่ชนะ --</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="notes" class="trophy-notes" placeholder="หมายเหตุ (ถ้ามี)"></textarea>
                        <button type="submit" class="btn btn-award">
                            <i class="fas fa-award"></i> มอบรางวัล
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Summary -->
        <?php if (!empty($trophyWinners)): ?>
        <div class="card">
            <h3><i class="fas fa-list"></i> สรุปรางวัลเดือน <?= thaiDate($yearMonth . '-01', 'full') ?></h3>
            <div class="summary-list">
                <?php foreach ($trophyWinners as $tw): ?>
                <div class="summary-item">
                    <div class="info">
                        <div class="icon" style="background: <?= $trophyTypes[$tw['trophy_type']]['color'] ?>22; color: <?= $trophyTypes[$tw['trophy_type']]['color'] ?>;">
                            <?= $trophyTypes[$tw['trophy_type']]['icon'] ?>
                        </div>
                        <div class="text">
                            <h4><?= $trophyTypes[$tw['trophy_type']]['name'] ?></h4>
                            <p><?= htmlspecialchars($tw['branch_name']) ?></p>
                        </div>
                    </div>
                    <div class="bonus">+฿<?= number_format($tw['bonus_per_person'], 0) ?>/คน</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
