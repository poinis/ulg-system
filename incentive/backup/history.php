<?php
// history.php - Submission History
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

// Get user's submissions for the month
$stmt = $conn->prepare("
    SELECT s.*, t.task_code, t.task_name_th, t.points as task_points, t.icon
    FROM incentive_submissions s
    JOIN incentive_task_types t ON s.task_type_id = t.id
    WHERE s.user_id = ? AND DATE_FORMAT(s.submission_date, '%Y-%m') = ?
    ORDER BY s.submission_date DESC, s.created_at DESC
");
$stmt->bind_param("is", $userId, $yearMonth);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate stats
$totalSubmissions = count($submissions);
$approvedPoints = 0;
$pendingCount = 0;
$rejectedCount = 0;

foreach ($submissions as $sub) {
    if ($sub['status'] === 'approved') {
        $approvedPoints += $sub['points_earned'];
    } elseif ($sub['status'] === 'pending') {
        $pendingCount++;
    } else {
        $rejectedCount++;
    }
}

$targetPoints = (int) getSetting($conn, 'target_points', 100);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ประวัติการส่งงาน | PRONTO</title>
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
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-box .value {
            font-size: 24px;
            font-weight: 700;
        }
        .stat-box .value.green { color: #2ed573; }
        .stat-box .value.yellow { color: #ffc107; }
        .stat-box .value.red { color: #ff6b6b; }
        .stat-box .label {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }
        
        /* History List */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .history-item {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 16px;
            backdrop-filter: blur(10px);
        }
        .history-item.approved { border-left: 4px solid #2ed573; }
        .history-item.pending { border-left: 4px solid #ffc107; }
        .history-item.rejected { border-left: 4px solid #ff6b6b; }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .history-task {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .history-task .icon {
            font-size: 24px;
        }
        .history-task .name {
            font-weight: 600;
        }
        .history-points {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .history-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-badge.approved { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .status-badge.pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-badge.rejected { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        
        .reject-reason {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
            font-size: 12px;
            color: #ff6b6b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255,255,255,0.5);
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
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
            transition: color 0.3s;
        }
        .nav-item.active, .nav-item:hover { color: #667eea; }
        .nav-item i { font-size: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-history"></i> ประวัติ</h1>
            <div class="avatar"><?= mb_substr($userName, 0, 1) ?></div>
        </div>
        
        <!-- Month Selector -->
        <form class="month-selector" method="GET">
            <input type="month" name="month" value="<?= $yearMonth ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="value green"><?= $approvedPoints ?></div>
                <div class="label">คะแนนที่ได้</div>
            </div>
            <div class="stat-box">
                <div class="value yellow"><?= $pendingCount ?></div>
                <div class="label">รอตรวจสอบ</div>
            </div>
            <div class="stat-box">
                <div class="value"><?= $totalSubmissions ?></div>
                <div class="label">ส่งทั้งหมด</div>
            </div>
        </div>
        
        <!-- History List -->
        <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>ยังไม่มีประวัติ</h3>
            <p>คุณยังไม่ได้ส่งงานในเดือนนี้</p>
        </div>
        <?php else: ?>
        <div class="history-list">
            <?php 
            $currentDate = '';
            foreach ($submissions as $sub): 
                if ($currentDate !== $sub['submission_date']):
                    $currentDate = $sub['submission_date'];
            ?>
            <div style="font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 8px; margin-bottom: -4px;">
                <i class="fas fa-calendar"></i> <?= thaiDate($sub['submission_date']) ?>
            </div>
            <?php endif; ?>
            <div class="history-item <?= $sub['status'] ?>">
                <div class="history-header">
                    <div class="history-task">
                        <span class="icon"><?= $sub['icon'] ?></span>
                        <span class="name"><?= htmlspecialchars($sub['task_name_th']) ?></span>
                    </div>
                    <span class="history-points">+<?= $sub['task_points'] ?> pts</span>
                </div>
                <div class="history-meta">
                    <span><i class="fas fa-clock"></i> <?= date('H:i', strtotime($sub['created_at'])) ?> น.</span>
                    <span class="status-badge <?= $sub['status'] ?>">
                        <?php if ($sub['status'] === 'pending'): ?>
                        <i class="fas fa-hourglass-half"></i> รอตรวจสอบ
                        <?php elseif ($sub['status'] === 'approved'): ?>
                        <i class="fas fa-check"></i> อนุมัติแล้ว
                        <?php else: ?>
                        <i class="fas fa-times"></i> ไม่อนุมัติ
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($sub['status'] === 'rejected' && $sub['reject_reason']): ?>
                <div class="reject-reason">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($sub['reject_reason']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Nav -->
    <div class="bottom-nav">
        <a href="checklist.php" class="nav-item">
            <i class="fas fa-clipboard-check"></i>
            <span>Checklist</span>
        </a>
        <a href="history.php" class="nav-item active">
            <i class="fas fa-history"></i>
            <span>ประวัติ</span>
        </a>
        <a href="leaderboard.php" class="nav-item">
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
