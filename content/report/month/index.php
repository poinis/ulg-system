<?php
/**
 * Monthly Comparison Report
 * รายงานเปรียบเทียบข้อมูลระหว่าง 2 เดือน
 */

require_once 'MonthlyImporter.php';

$importer = new MonthlyImporter();
$pdo = $importer->getPDO();

// ดึงเดือนที่มีข้อมูล
$availableMonths = $importer->getAvailableMonths();

// ค่าเริ่มต้น
if (count($availableMonths) >= 2) {
    $month1 = $_GET['month1'] ?? $availableMonths[0]['report_month'];
    $year1 = $_GET['year1'] ?? $availableMonths[0]['report_year'];
    $month2 = $_GET['month2'] ?? $availableMonths[1]['report_month'];
    $year2 = $_GET['year2'] ?? $availableMonths[1]['report_year'];
} elseif (count($availableMonths) == 1) {
    $month1 = $month2 = $availableMonths[0]['report_month'];
    $year1 = $year2 = $availableMonths[0]['report_year'];
} else {
    $month1 = $month2 = date('n');
    $year1 = $year2 = date('Y');
}

$selectedPlatform = $_GET['platform'] ?? '';

// ดึงข้อมูล Summary
$summary1 = $importer->getMonthlySummary($month1, $year1, $selectedPlatform ?: null);
$summary2 = $importer->getMonthlySummary($month2, $year2, $selectedPlatform ?: null);

// ดึงโพสต์
$posts1 = $importer->getPostsByMonth($month1, $year1, $selectedPlatform ?: null);
$posts2 = $importer->getPostsByMonth($month2, $year2, $selectedPlatform ?: null);

// ดึง Followers (ถ้ามี method)
$followers = method_exists($importer, 'getFollowers') ? $importer->getFollowers() : [];

$thaiMonths = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
    5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
    9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];

$thaiMonthsFull = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// รวมข้อมูลตาม Platform
function aggregateByPlatform($summary) {
    $result = [];
    foreach ($summary as $s) {
        $result[$s['social']] = $s;
    }
    return $result;
}

$data1 = aggregateByPlatform($summary1);
$data2 = aggregateByPlatform($summary2);

// คำนวณ % เปลี่ยนแปลง
function calcChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

// คำนวณ ER
function calcER($likes, $comments, $saves, $followers) {
    if ($followers <= 0) return 0;
    return (($likes + $comments + $saves) / $followers) * 100;
}

// รวมทุก Platform
function sumAll($data, $field) {
    return array_sum(array_column($data, $field));
}

$total1 = [
    'posts' => sumAll($summary1, 'total_posts'),
    'views' => sumAll($summary1, 'total_views'),
    'reach' => sumAll($summary1, 'total_reach'),
    'likes' => sumAll($summary1, 'total_likes'),
    'comments' => sumAll($summary1, 'total_comments'),
    'shares' => sumAll($summary1, 'total_shares'),
    'saves' => sumAll($summary1, 'total_saves'),
    'ad_posts' => sumAll($summary1, 'ad_posts'),
    'ad_spend' => sumAll($summary1, 'total_ad_spend'),
];

$total2 = [
    'posts' => sumAll($summary2, 'total_posts'),
    'views' => sumAll($summary2, 'total_views'),
    'reach' => sumAll($summary2, 'total_reach'),
    'likes' => sumAll($summary2, 'total_likes'),
    'comments' => sumAll($summary2, 'total_comments'),
    'shares' => sumAll($summary2, 'total_shares'),
    'saves' => sumAll($summary2, 'total_saves'),
    'ad_posts' => sumAll($summary2, 'ad_posts'),
    'ad_spend' => sumAll($summary2, 'total_ad_spend'),
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเปรียบเทียบรายเดือน | Monthly Compare</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 20px 0;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.1);
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: rgba(102, 126, 234, 0.3);
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .card h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i {
            color: #667eea;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
        }
        
        select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            min-width: 180px;
        }
        
        select option {
            background: #1a1a2e;
            color: #fff;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 80px 1fr;
            gap: 20px;
            align-items: start;
        }
        
        .month-box {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
        }
        
        .month-box h3 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.1rem;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .month-box.current {
            border: 2px solid rgba(102, 126, 234, 0.5);
        }
        
        .month-box.previous {
            border: 2px solid rgba(255,255,255,0.1);
        }
        
        .vs-box {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            padding-top: 50px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .stat-row:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.7);
        }
        
        .stat-value {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        
        .change-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 5px;
        }
        
        .change-indicator.up {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .change-indicator.down {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        
        .change-indicator.neutral {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .summary-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .summary-card .icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .summary-card .label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .platform-comparison {
            margin-top: 20px;
        }
        
        .platform-row {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 100px;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .platform-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
        }
        
        .platform-badge.Facebook { background: rgba(24, 119, 242, 0.2); color: #1877F2; }
        .platform-badge.Instagram { background: rgba(228, 64, 95, 0.2); color: #E4405F; }
        .platform-badge.TikTok { background: rgba(37, 244, 238, 0.2); color: #25F4EE; }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: rgba(255,255,255,0.5);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .mini-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .mini-stat {
            font-size: 0.85rem;
        }
        
        .mini-stat .num {
            font-weight: 600;
        }
        
        @media (max-width: 992px) {
            .comparison-grid {
                grid-template-columns: 1fr;
            }
            
            .vs-box {
                padding: 10px 0;
            }
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            select {
                width: 100%;
            }
            
            .platform-row {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> รายงานเปรียบเทียบรายเดือน</h1>
        </div>
        
        <div class="nav-links">
            <a href="../index.php"><i class="fas fa-home"></i> หน้าหลัก</a>
            <a href="upload.php"><i class="fas fa-upload"></i> อัพโหลด</a>
            <a href="manage.php"><i class="fas fa-edit"></i> จัดการโพสต์</a>
            <a href="index.php"><i class="fas fa-chart-bar"></i> รายงาน</a>
            <a href="ad_analysis.php" class="active"><i class="fas fa-bullhorn"></i> วิเคราะห์โฆษณา</a>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-filter"></i> เลือกเดือนที่ต้องการเปรียบเทียบ</h2>
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>เดือนที่ 1 (ปัจจุบัน)</label>
                    <select name="month1" id="month1">
                        <?php foreach ($availableMonths as $m): ?>
                        <option value="<?php echo $m['report_month']; ?>" 
                                data-year="<?php echo $m['report_year']; ?>"
                                <?php echo ($m['report_month'] == $month1 && $m['report_year'] == $year1) ? 'selected' : ''; ?>>
                            <?php echo $thaiMonthsFull[$m['report_month']]; ?> <?php echo $m['report_year'] + 543; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="year1" id="year1" value="<?php echo $year1; ?>">
                </div>
                
                <div class="filter-group">
                    <label>เดือนที่ 2 (เปรียบเทียบ)</label>
                    <select name="month2" id="month2">
                        <?php foreach ($availableMonths as $m): ?>
                        <option value="<?php echo $m['report_month']; ?>" 
                                data-year="<?php echo $m['report_year']; ?>"
                                <?php echo ($m['report_month'] == $month2 && $m['report_year'] == $year2) ? 'selected' : ''; ?>>
                            <?php echo $thaiMonthsFull[$m['report_month']]; ?> <?php echo $m['report_year'] + 543; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="year2" id="year2" value="<?php echo $year2; ?>">
                </div>
                
                <div class="filter-group">
                    <label>Platform</label>
                    <select name="platform">
                        <option value="">ทั้งหมด</option>
                        <option value="Facebook" <?php echo $selectedPlatform === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                        <option value="Instagram" <?php echo $selectedPlatform === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                        <option value="TikTok" <?php echo $selectedPlatform === 'TikTok' ? 'selected' : ''; ?>>TikTok</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> เปรียบเทียบ
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (empty($availableMonths)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>ยังไม่มีข้อมูลในระบบ</p>
                <p><a href="upload.php" style="color: #667eea;">อัพโหลดข้อมูลใหม่</a></p>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <div class="value"><?php echo number_format($total1['posts']); ?></div>
                <div class="label">โพสต์ทั้งหมด</div>
                <?php $change = calcChange($total1['posts'], $total2['posts']); ?>
                <span class="change-indicator <?php echo $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'); ?>">
                    <i class="fas fa-<?php echo $change > 0 ? 'arrow-up' : ($change < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                    <?php echo abs(round($change, 1)); ?>%
                </span>
            </div>
            
            <div class="summary-card">
                <div class="icon"><i class="fas fa-eye"></i></div>
                <div class="value"><?php echo number_format($total1['views']); ?></div>
                <div class="label">Views รวม</div>
                <?php $change = calcChange($total1['views'], $total2['views']); ?>
                <span class="change-indicator <?php echo $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'); ?>">
                    <i class="fas fa-<?php echo $change > 0 ? 'arrow-up' : ($change < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                    <?php echo abs(round($change, 1)); ?>%
                </span>
            </div>
            
            <div class="summary-card">
                <div class="icon"><i class="fas fa-heart"></i></div>
                <div class="value"><?php echo number_format($total1['likes']); ?></div>
                <div class="label">Likes รวม</div>
                <?php $change = calcChange($total1['likes'], $total2['likes']); ?>
                <span class="change-indicator <?php echo $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'); ?>">
                    <i class="fas fa-<?php echo $change > 0 ? 'arrow-up' : ($change < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                    <?php echo abs(round($change, 1)); ?>%
                </span>
            </div>
            
            <div class="summary-card">
                <div class="icon"><i class="fas fa-bullhorn"></i></div>
                <div class="value"><?php echo number_format($total1['ad_posts']); ?></div>
                <div class="label">โพสต์ยิงแอด</div>
                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-top: 5px;">
                    ฿<?php echo number_format($total1['ad_spend']); ?>
                </div>
            </div>
        </div>
        
        <!-- Month Comparison -->
        <div class="card">
            <h2><i class="fas fa-balance-scale"></i> เปรียบเทียบ <?php echo $thaiMonths[$month1]; ?> <?php echo $year1 + 543; ?> vs <?php echo $thaiMonths[$month2]; ?> <?php echo $year2 + 543; ?></h2>
            
            <div class="comparison-grid">
                <div class="month-box current">
                    <h3><?php echo $thaiMonthsFull[$month1]; ?> <?php echo $year1 + 543; ?></h3>
                    
                    <div class="stat-row">
                        <span class="stat-label">โพสต์</span>
                        <span class="stat-value"><?php echo number_format($total1['posts']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Views</span>
                        <span class="stat-value"><?php echo number_format($total1['views']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Reach</span>
                        <span class="stat-value"><?php echo number_format($total1['reach']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Likes</span>
                        <span class="stat-value"><?php echo number_format($total1['likes']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Comments</span>
                        <span class="stat-value"><?php echo number_format($total1['comments']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Shares</span>
                        <span class="stat-value"><?php echo number_format($total1['shares']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Saves</span>
                        <span class="stat-value"><?php echo number_format($total1['saves']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">ยอดแอด</span>
                        <span class="stat-value">฿<?php echo number_format($total1['ad_spend']); ?></span>
                    </div>
                </div>
                
                <div class="vs-box">VS</div>
                
                <div class="month-box previous">
                    <h3><?php echo $thaiMonthsFull[$month2]; ?> <?php echo $year2 + 543; ?></h3>
                    
                    <div class="stat-row">
                        <span class="stat-label">โพสต์</span>
                        <span class="stat-value"><?php echo number_format($total2['posts']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Views</span>
                        <span class="stat-value"><?php echo number_format($total2['views']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Reach</span>
                        <span class="stat-value"><?php echo number_format($total2['reach']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Likes</span>
                        <span class="stat-value"><?php echo number_format($total2['likes']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Comments</span>
                        <span class="stat-value"><?php echo number_format($total2['comments']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Shares</span>
                        <span class="stat-value"><?php echo number_format($total2['shares']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Saves</span>
                        <span class="stat-value"><?php echo number_format($total2['saves']); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">ยอดแอด</span>
                        <span class="stat-value">฿<?php echo number_format($total2['ad_spend']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Platform Comparison -->
        <div class="card">
            <h2><i class="fas fa-th-list"></i> เปรียบเทียบตาม Platform</h2>
            
            <div class="platform-comparison">
                <?php 
                $platforms = ['Facebook', 'Instagram', 'TikTok'];
                foreach ($platforms as $platform):
                    $p1 = $data1[$platform] ?? null;
                    $p2 = $data2[$platform] ?? null;
                    
                    if (!$p1 && !$p2) continue;
                ?>
                <div class="platform-row">
                    <span class="platform-badge <?php echo $platform; ?>"><?php echo $platform; ?></span>
                    
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <i class="fas fa-file-alt"></i> 
                            <span class="num"><?php echo number_format($p1['total_posts'] ?? 0); ?></span>
                            <small>posts</small>
                        </div>
                        <div class="mini-stat">
                            <i class="fas fa-eye"></i> 
                            <span class="num"><?php echo number_format($p1['total_views'] ?? 0); ?></span>
                        </div>
                        <div class="mini-stat">
                            <i class="fas fa-heart"></i> 
                            <span class="num"><?php echo number_format($p1['total_likes'] ?? 0); ?></span>
                        </div>
                    </div>
                    
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <i class="fas fa-file-alt"></i> 
                            <span class="num"><?php echo number_format($p2['total_posts'] ?? 0); ?></span>
                            <small>posts</small>
                        </div>
                        <div class="mini-stat">
                            <i class="fas fa-eye"></i> 
                            <span class="num"><?php echo number_format($p2['total_views'] ?? 0); ?></span>
                        </div>
                        <div class="mini-stat">
                            <i class="fas fa-heart"></i> 
                            <span class="num"><?php echo number_format($p2['total_likes'] ?? 0); ?></span>
                        </div>
                    </div>
                    
                    <?php $viewChange = calcChange($p1['total_views'] ?? 0, $p2['total_views'] ?? 0); ?>
                    <span class="change-indicator <?php echo $viewChange > 0 ? 'up' : ($viewChange < 0 ? 'down' : 'neutral'); ?>">
                        <i class="fas fa-<?php echo $viewChange > 0 ? 'arrow-up' : ($viewChange < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                        <?php echo abs(round($viewChange, 1)); ?>%
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="card">
            <h2><i class="fas fa-chart-bar"></i> กราฟเปรียบเทียบ</h2>
            <div class="chart-container">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        // อัพเดทปีเมื่อเลือกเดือน
        document.getElementById('month1')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('year1').value = selected.dataset.year;
        });
        
        document.getElementById('month2')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('year2').value = selected.dataset.year;
        });
        
        // Chart
        <?php if (!empty($availableMonths)): ?>
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Posts', 'Views (K)', 'Likes (K)', 'Comments', 'Shares'],
                datasets: [
                    {
                        label: '<?php echo $thaiMonths[$month1]; ?> <?php echo $year1 + 543; ?>',
                        data: [
                            <?php echo $total1['posts']; ?>,
                            <?php echo round($total1['views'] / 1000, 1); ?>,
                            <?php echo round($total1['likes'] / 1000, 1); ?>,
                            <?php echo $total1['comments']; ?>,
                            <?php echo $total1['shares']; ?>
                        ],
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderRadius: 6
                    },
                    {
                        label: '<?php echo $thaiMonths[$month2]; ?> <?php echo $year2 + 543; ?>',
                        data: [
                            <?php echo $total2['posts']; ?>,
                            <?php echo round($total2['views'] / 1000, 1); ?>,
                            <?php echo round($total2['likes'] / 1000, 1); ?>,
                            <?php echo $total2['comments']; ?>,
                            <?php echo $total2['shares']; ?>
                        ],
                        backgroundColor: 'rgba(102, 126, 234, 0.3)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: 'rgba(255,255,255,0.7)' }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: 'rgba(255,255,255,0.5)' }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: 'rgba(255,255,255,0.5)' }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>