<?php
// leaderboard.php - Branch Leaderboard
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userBranch = getUserBranch($conn, $userId);

$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get branch rankings
$branchSummary = getMonthlyBranchSummary($conn, $yearMonth);
$trophyWinners = getTrophyWinners($conn, $yearMonth);

// Create trophy map
$trophyMap = [];
foreach ($trophyWinners as $tw) {
    if (!isset($trophyMap[$tw['branch_id']])) {
        $trophyMap[$tw['branch_id']] = [];
    }
    $trophyMap[$tw['branch_id']][] = $tw['trophy_type'];
}

$targetPoints = (int) getSetting($conn, 'target_points', 100);
$maxIncentive = (float) getSetting($conn, 'max_base_incentive', 2500);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>อันดับสาขา | PRONTO</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .header h1 { font-size: 24px; font-weight: 600; }
        .header .avatar {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
        }
        
        /* Month Selector */
        .month-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .month-selector input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 12px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
        }
        .month-selector button {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            background: #667eea;
            color: #fff;
            font-family: inherit;
            cursor: pointer;
        }
        
        /* Top 3 Podium */
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        .podium-item {
            text-align: center;
            transition: transform 0.3s;
        }
        .podium-item:hover {
            transform: translateY(-5px);
        }
        .podium-item.first { order: 2; }
        .podium-item.second { order: 1; }
        .podium-item.third { order: 3; }
        
        .podium-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            position: relative;
        }
        .podium-item.first .podium-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ffd700, #ffaa00);
            font-size: 32px;
        }
        .podium-item.second .podium-avatar {
            background: linear-gradient(135deg, #c0c0c0, #a0a0a0);
        }
        .podium-item.third .podium-avatar {
            background: linear-gradient(135deg, #cd7f32, #a0522d);
        }
        
        .podium-rank {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 24px;
            height: 24px;
            background: #1a1a2e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
        .podium-item.first .podium-rank { background: #ffd700; color: #1a1a2e; }
        .podium-item.second .podium-rank { background: #c0c0c0; color: #1a1a2e; }
        .podium-item.third .podium-rank { background: #cd7f32; color: #fff; }
        
        .podium-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .podium-points {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }
        .podium-item.first .podium-points {
            font-size: 24px;
            color: #ffd700;
        }
        
        .podium-stand {
            width: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px 8px 0 0;
            margin-top: 10px;
        }
        .podium-item.first .podium-stand { height: 80px; width: 100px; }
        .podium-item.second .podium-stand { height: 60px; }
        .podium-item.third .podium-stand { height: 40px; }
        
        /* Trophy Section */
        .trophy-section {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .trophy-section h3 {
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .trophy-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .trophy-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
        }
        .trophy-icon {
            font-size: 28px;
        }
        .trophy-info {
            flex: 1;
        }
        .trophy-name {
            font-size: 14px;
            font-weight: 600;
        }
        .trophy-winner {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        .trophy-bonus {
            color: #2ed573;
            font-weight: 600;
        }
        
        /* Rankings List */
        .section-title {
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .ranking-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.08);
            border-radius: 14px;
            transition: all 0.3s;
        }
        .ranking-item.my-branch {
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
        }
        .ranking-item:hover {
            background: rgba(255,255,255,0.12);
        }
        
        .ranking-position {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        .ranking-item:nth-child(1) .ranking-position { background: linear-gradient(135deg, #ffd700, #ffaa00); color: #1a1a2e; }
        .ranking-item:nth-child(2) .ranking-position { background: linear-gradient(135deg, #c0c0c0, #a0a0a0); color: #1a1a2e; }
        .ranking-item:nth-child(3) .ranking-position { background: linear-gradient(135deg, #cd7f32, #a0522d); color: #fff; }
        
        .ranking-info {
            flex: 1;
        }
        .ranking-name {
            font-weight: 600;
            font-size: 15px;
        }
        .ranking-progress {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
        }
        .progress-bar {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }
        .progress-text {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            min-width: 35px;
        }
        
        .ranking-points {
            text-align: right;
        }
        .ranking-points .value {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        .ranking-points .label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        
        .trophy-badges {
            display: flex;
            gap: 4px;
            margin-top: 4px;
        }
        .mini-trophy {
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255,255,255,0.5);
        }
        
        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            padding-bottom: max(12px, env(safe-area-inset-bottom));
            display: flex;
            justify-content: space-around;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 4px;
            color: rgba(255,255,255,0.5); text-decoration: none; font-size: 12px;
        }
        .nav-item.active, .nav-item:hover { color: #667eea; }
        .nav-item i { font-size: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 อันดับสาขา</h1>
            <div class="avatar"><?= mb_substr($userName, 0, 1) ?></div>
        </div>
        
        <!-- Month Selector -->
        <form class="month-selector" method="GET">
            <input type="month" name="month" value="<?= $yearMonth ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        
        <?php if (count($branchSummary) >= 3): ?>
        <!-- Top 3 Podium -->
        <div class="podium">
            <?php for ($i = 0; $i < min(3, count($branchSummary)); $i++): 
                $branch = $branchSummary[$i];
                $position = $i + 1;
                $class = $position === 1 ? 'first' : ($position === 2 ? 'second' : 'third');
            ?>
            <div class="podium-item <?= $class ?>">
                <div class="podium-avatar">
                    <?= mb_substr($branch['branch_name'], 0, 1) ?>
                    <span class="podium-rank"><?= $position ?></span>
                </div>
                <div class="podium-name"><?= htmlspecialchars($branch['branch_name']) ?></div>
                <div class="podium-points"><?= $branch['total_points'] ?> pts</div>
                <div class="podium-stand"></div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($trophyWinners)): ?>
        <!-- Trophy Section -->
        <div class="trophy-section">
            <h3><i class="fas fa-award"></i> รางวัลประจำเดือน</h3>
            <div class="trophy-list">
                <?php 
                $trophyInfo = [
                    'most_views' => ['icon' => '🔥', 'name' => 'Most Views', 'name_th' => 'ยอดวิวสูงสุด'],
                    'most_review_growth' => ['icon' => '⭐', 'name' => 'Most Review Growth', 'name_th' => 'รีวิวเติบโตสูงสุด'],
                    'hq_choice' => ['icon' => '🎨', 'name' => "HQ's Choice", 'name_th' => 'HQ เลือก']
                ];
                foreach ($trophyWinners as $tw): 
                    $info = $trophyInfo[$tw['trophy_type']] ?? ['icon' => '🏆', 'name' => $tw['trophy_type'], 'name_th' => ''];
                ?>
                <div class="trophy-item">
                    <span class="trophy-icon"><?= $info['icon'] ?></span>
                    <div class="trophy-info">
                        <div class="trophy-name"><?= $info['name'] ?></div>
                        <div class="trophy-winner"><?= htmlspecialchars($tw['branch_name']) ?></div>
                    </div>
                    <span class="trophy-bonus">+฿<?= number_format($tw['bonus_per_person'], 0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Full Rankings -->
        <div class="section-title"><i class="fas fa-list-ol"></i> อันดับทั้งหมด</div>
        
        <?php if (empty($branchSummary)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>ยังไม่มีข้อมูล</h3>
            <p>ยังไม่มีการส่งงานในเดือนนี้</p>
        </div>
        <?php else: ?>
        <div class="ranking-list">
            <?php 
            $rank = 1;
            foreach ($branchSummary as $branch): 
                $progress = min(($branch['total_points'] / $targetPoints) * 100, 100);
                $isMyBranch = $userBranch && $userBranch['id'] == $branch['branch_id'];
                $branchTrophies = $trophyMap[$branch['branch_id']] ?? [];
            ?>
            <div class="ranking-item <?= $isMyBranch ? 'my-branch' : '' ?>">
                <div class="ranking-position"><?= $rank ?></div>
                <div class="ranking-info">
                    <div class="ranking-name">
                        <?= htmlspecialchars($branch['branch_name']) ?>
                        <?php if ($isMyBranch): ?>
                        <span style="font-size: 11px; color: #667eea;">(สาขาของคุณ)</span>
                        <?php endif; ?>
                    </div>
                    <div class="ranking-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <span class="progress-text"><?= number_format($progress, 0) ?>%</span>
                    </div>
                    <?php if (!empty($branchTrophies)): ?>
                    <div class="trophy-badges">
                        <?php foreach ($branchTrophies as $t): ?>
                        <span class="mini-trophy"><?= $trophyInfo[$t]['icon'] ?? '🏆' ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="ranking-points">
                    <div class="value"><?= $branch['total_points'] ?></div>
                    <div class="label">คะแนน</div>
                </div>
            </div>
            <?php 
                $rank++;
            endforeach; 
            ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Nav -->
    <div class="bottom-nav">
        <a href="checklist.php" class="nav-item">
            <i class="fas fa-clipboard-check"></i>
            <span>Checklist</span>
        </a>
        <a href="history.php" class="nav-item">
            <i class="fas fa-history"></i>
            <span>ประวัติ</span>
        </a>
        <a href="leaderboard.php" class="nav-item active">
            <i class="fas fa-trophy"></i>
            <span>อันดับ</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออก</span>
        </a>
    </div>
</body>
</html>
