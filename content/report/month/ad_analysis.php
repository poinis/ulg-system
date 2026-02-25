<?php
/**
 * Ad Performance Analysis
 * ระบบวิเคราะห์ประสิทธิภาพการยิงโฆษณา
 */

require_once 'MonthlyImporter.php';

$importer = new MonthlyImporter();
$pdo = $importer->getPDO();

// ดึงเดือนที่มีข้อมูล
$availableMonths = $importer->getAvailableMonths();

// ค่าเริ่มต้น
$selectedMonth = $_GET['month'] ?? ($availableMonths[0]['report_month'] ?? date('n'));
$selectedYear = $_GET['year'] ?? ($availableMonths[0]['report_year'] ?? date('Y'));
$selectedPlatform = $_GET['platform'] ?? '';

// ดึงข้อมูลโพสต์
$allPosts = $importer->getPostsByMonth($selectedMonth, $selectedYear, $selectedPlatform ?: null);

// แยกโพสต์ที่ยิงแอดและไม่ยิงแอด
$adPosts = array_filter($allPosts, fn($p) => $p['is_ad']);
$organicPosts = array_filter($allPosts, fn($p) => !$p['is_ad']);

// คำนวณ Metrics
function calculateMetrics($posts) {
    if (empty($posts)) {
        return [
            'count' => 0,
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'engagement' => 0,
            'avg_views' => 0,
            'avg_likes' => 0,
            'avg_engagement' => 0,
            'ad_spend' => 0,
            'cpe' => 0,
            'cpv' => 0,
            'cpr' => 0
        ];
    }
    
    $posts = array_values($posts);
    $count = count($posts);
    $views = array_sum(array_column($posts, 'views'));
    $likes = array_sum(array_column($posts, 'likes'));
    $comments = array_sum(array_column($posts, 'comments'));
    $shares = array_sum(array_column($posts, 'shares'));
    $saves = array_sum(array_column($posts, 'saves')) + array_sum(array_column($posts, 'favorites'));
    $reach = array_sum(array_column($posts, 'reach'));
    $adSpend = array_sum(array_column($posts, 'ad_spend'));
    
    $engagement = $likes + $comments + $shares + $saves;
    
    return [
        'count' => $count,
        'views' => $views,
        'reach' => $reach,
        'likes' => $likes,
        'comments' => $comments,
        'shares' => $shares,
        'saves' => $saves,
        'engagement' => $engagement,
        'avg_views' => $count > 0 ? $views / $count : 0,
        'avg_likes' => $count > 0 ? $likes / $count : 0,
        'avg_engagement' => $count > 0 ? $engagement / $count : 0,
        'ad_spend' => $adSpend,
        'cpe' => $engagement > 0 && $adSpend > 0 ? $adSpend / $engagement : 0, // Cost Per Engagement
        'cpv' => $views > 0 && $adSpend > 0 ? $adSpend / $views * 1000 : 0, // Cost Per 1K Views
        'cpr' => $reach > 0 && $adSpend > 0 ? $adSpend / $reach * 1000 : 0, // Cost Per 1K Reach
        'roi' => $adSpend > 0 ? ($engagement / $adSpend) * 100 : 0 // Engagement per ฿100
    ];
}

$adMetrics = calculateMetrics($adPosts);
$organicMetrics = calculateMetrics($organicPosts);
$allMetrics = calculateMetrics($allPosts);

// แยกตาม Platform
function getMetricsByPlatform($posts) {
    $byPlatform = [];
    foreach ($posts as $post) {
        $platform = $post['social'];
        if (!isset($byPlatform[$platform])) {
            $byPlatform[$platform] = [];
        }
        $byPlatform[$platform][] = $post;
    }
    
    $result = [];
    foreach ($byPlatform as $platform => $platformPosts) {
        $result[$platform] = calculateMetrics($platformPosts);
    }
    return $result;
}

$adByPlatform = getMetricsByPlatform($adPosts);
$organicByPlatform = getMetricsByPlatform($organicPosts);

// Top performing ad posts
usort($adPosts, fn($a, $b) => ($b['likes'] + $b['comments'] + $b['shares']) - ($a['likes'] + $a['comments'] + $a['shares']));
$topAdPosts = array_slice($adPosts, 0, 10);

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิเคราะห์โฆษณา | Monthly Compare</title>
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
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header p {
            color: rgba(255,255,255,0.6);
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
            color: #f39c12;
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
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(243, 156, 18, 0.4);
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .kpi-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .kpi-card.highlight {
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.15), rgba(231, 76, 60, 0.15));
            border-color: rgba(243, 156, 18, 0.3);
        }
        
        .kpi-card .icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .kpi-card .value {
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .kpi-card .label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
        }
        
        .kpi-card .sub-value {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
            margin-top: 3px;
        }
        
        .comparison-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .comparison-box {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
        }
        
        .comparison-box h3 {
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .comparison-box h3 .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .comparison-box h3 .badge.ad {
            background: rgba(243, 156, 18, 0.2);
            color: #f39c12;
        }
        
        .comparison-box h3 .badge.organic {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .metric-row:last-child {
            border-bottom: none;
        }
        
        .metric-row .label {
            color: rgba(255,255,255,0.7);
        }
        
        .metric-row .value {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .platform-analysis {
            margin-top: 20px;
        }
        
        .platform-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .platform-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .platform-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .platform-badge.Facebook { background: rgba(24, 119, 242, 0.2); color: #1877F2; }
        .platform-badge.Instagram { background: rgba(228, 64, 95, 0.2); color: #E4405F; }
        .platform-badge.TikTok { background: rgba(37, 244, 238, 0.2); color: #25F4EE; }
        
        .platform-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .platform-stat {
            text-align: center;
        }
        
        .platform-stat .value {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .platform-stat .label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
        }
        
        .top-posts {
            margin-top: 20px;
        }
        
        .post-item {
            display: grid;
            grid-template-columns: 50px 1fr 120px 100px 100px 100px;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .post-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .post-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .post-stat {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        
        .post-stat .value {
            font-weight: 600;
        }
        
        .post-stat .label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
        }
        
        .efficiency-meter {
            margin-top: 15px;
        }
        
        .meter-bar {
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .meter-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .meter-fill.good { background: linear-gradient(90deg, #2ed573, #26de81); }
        .meter-fill.medium { background: linear-gradient(90deg, #f39c12, #fdcb6e); }
        .meter-fill.poor { background: linear-gradient(90deg, #e74c3c, #ff7675); }
        
        .insights-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .insight-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
        }
        
        .insight-item i {
            color: #667eea;
            font-size: 1.2rem;
            margin-top: 2px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: rgba(255,255,255,0.5);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #f39c12;
        }
        
        @media (max-width: 992px) {
            .comparison-section {
                grid-template-columns: 1fr;
            }
            
            .post-item {
                grid-template-columns: 40px 1fr;
            }
            
            .post-item > *:nth-child(n+3) {
                display: none;
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
            
            .platform-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-bullhorn"></i> วิเคราะห์ประสิทธิภาพโฆษณา</h1>
            <p>เปรียบเทียบผลลัพธ์ระหว่างโพสต์ที่ยิงแอด vs Organic</p>
        </div>
        
        <div class="nav-links">
            <a href="../index.php"><i class="fas fa-home"></i> หน้าหลัก</a>
            <a href="upload.php"><i class="fas fa-upload"></i> อัพโหลด</a>
            <a href="manage.php"><i class="fas fa-edit"></i> จัดการโพสต์</a>
            <a href="index.php"><i class="fas fa-chart-bar"></i> รายงาน</a>
            <a href="ad_analysis.php" class="active"><i class="fas fa-bullhorn"></i> วิเคราะห์โฆษณา</a>
        </div>
        
        <div class="card">
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>เดือน</label>
                    <select name="month" id="monthSelect">
                        <?php foreach ($availableMonths as $m): ?>
                        <option value="<?php echo $m['report_month']; ?>" 
                                data-year="<?php echo $m['report_year']; ?>"
                                <?php echo ($m['report_month'] == $selectedMonth && $m['report_year'] == $selectedYear) ? 'selected' : ''; ?>>
                            <?php echo $thaiMonthsFull[$m['report_month']]; ?> <?php echo $m['report_year'] + 543; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="year" id="yearInput" value="<?php echo $selectedYear; ?>">
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
                        <i class="fas fa-search"></i> วิเคราะห์
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (empty($adPosts)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-ad"></i>
                <p>ยังไม่มีโพสต์ที่ระบุว่ายิงแอดในเดือนนี้</p>
                <p><a href="manage.php?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" style="color: #f39c12;">ไปจัดการโพสต์</a></p>
            </div>
        </div>
        <?php else: ?>
        
        <!-- KPI Cards -->
        <div class="card">
            <h2><i class="fas fa-chart-pie"></i> ภาพรวมการยิงโฆษณา - <?php echo $thaiMonthsFull[$selectedMonth]; ?> <?php echo $selectedYear + 543; ?></h2>
            
            <div class="kpi-grid">
                <div class="kpi-card highlight">
                    <div class="icon" style="color: #f39c12;"><i class="fas fa-ad"></i></div>
                    <div class="value"><?php echo $adMetrics['count']; ?></div>
                    <div class="label">โพสต์ยิงแอด</div>
                    <div class="sub-value"><?php echo round(($adMetrics['count'] / max(1, $allMetrics['count'])) * 100, 1); ?>% ของทั้งหมด</div>
                </div>
                
                <div class="kpi-card highlight">
                    <div class="icon" style="color: #e74c3c;"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="value">฿<?php echo number_format($adMetrics['ad_spend']); ?></div>
                    <div class="label">ยอดใช้จ่ายรวม</div>
                    <div class="sub-value">เฉลี่ย ฿<?php echo number_format($adMetrics['count'] > 0 ? $adMetrics['ad_spend'] / $adMetrics['count'] : 0); ?>/โพสต์</div>
                </div>
                
                <div class="kpi-card">
                    <div class="icon" style="color: #3498db;"><i class="fas fa-hand-pointer"></i></div>
                    <div class="value">฿<?php echo number_format($adMetrics['cpe'], 2); ?></div>
                    <div class="label">Cost Per Engagement</div>
                    <div class="sub-value">ต่อ 1 interaction</div>
                </div>
                
                <div class="kpi-card">
                    <div class="icon" style="color: #9b59b6;"><i class="fas fa-eye"></i></div>
                    <div class="value">฿<?php echo number_format($adMetrics['cpv'], 2); ?></div>
                    <div class="label">Cost Per 1K Views</div>
                </div>
                
                <div class="kpi-card">
                    <div class="icon" style="color: #2ed573;"><i class="fas fa-users"></i></div>
                    <div class="value"><?php echo number_format($adMetrics['roi'], 1); ?></div>
                    <div class="label">Engagement per ฿100</div>
                </div>
            </div>
        </div>
        
        <!-- Ad vs Organic Comparison -->
        <div class="card">
            <h2><i class="fas fa-balance-scale"></i> เปรียบเทียบ Ad vs Organic</h2>
            
            <div class="comparison-section">
                <div class="comparison-box">
                    <h3><i class="fas fa-bullhorn"></i> โพสต์ยิงแอด <span class="badge ad"><?php echo $adMetrics['count']; ?> posts</span></h3>
                    
                    <div class="metric-row">
                        <span class="label">Views รวม</span>
                        <span class="value"><?php echo number_format($adMetrics['views']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Views เฉลี่ย</span>
                        <span class="value"><?php echo number_format($adMetrics['avg_views']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Likes รวม</span>
                        <span class="value"><?php echo number_format($adMetrics['likes']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Comments รวม</span>
                        <span class="value"><?php echo number_format($adMetrics['comments']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Shares รวม</span>
                        <span class="value"><?php echo number_format($adMetrics['shares']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Engagement รวม</span>
                        <span class="value"><?php echo number_format($adMetrics['engagement']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Engagement เฉลี่ย</span>
                        <span class="value"><?php echo number_format($adMetrics['avg_engagement']); ?></span>
                    </div>
                </div>
                
                <div class="comparison-box">
                    <h3><i class="fas fa-seedling"></i> Organic <span class="badge organic"><?php echo $organicMetrics['count']; ?> posts</span></h3>
                    
                    <div class="metric-row">
                        <span class="label">Views รวม</span>
                        <span class="value"><?php echo number_format($organicMetrics['views']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Views เฉลี่ย</span>
                        <span class="value"><?php echo number_format($organicMetrics['avg_views']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Likes รวม</span>
                        <span class="value"><?php echo number_format($organicMetrics['likes']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Comments รวม</span>
                        <span class="value"><?php echo number_format($organicMetrics['comments']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Shares รวม</span>
                        <span class="value"><?php echo number_format($organicMetrics['shares']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Engagement รวม</span>
                        <span class="value"><?php echo number_format($organicMetrics['engagement']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="label">Engagement เฉลี่ย</span>
                        <span class="value"><?php echo number_format($organicMetrics['avg_engagement']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
        
        <!-- Platform Analysis -->
        <div class="card">
            <h2><i class="fas fa-layer-group"></i> วิเคราะห์ตาม Platform</h2>
            
            <div class="platform-analysis">
                <?php 
                $platforms = ['Facebook', 'Instagram', 'TikTok'];
                foreach ($platforms as $platform):
                    $adP = $adByPlatform[$platform] ?? null;
                    $orgP = $organicByPlatform[$platform] ?? null;
                    
                    if (!$adP) continue;
                ?>
                <div class="platform-card">
                    <div class="platform-header">
                        <span class="platform-badge <?php echo $platform; ?>">
                            <i class="fab fa-<?php echo strtolower($platform); ?>"></i> <?php echo $platform; ?>
                        </span>
                        <span style="color: rgba(255,255,255,0.5);">
                            <?php echo $adP['count']; ?> โพสต์ยิงแอด | ฿<?php echo number_format($adP['ad_spend']); ?>
                        </span>
                    </div>
                    
                    <div class="platform-stats">
                        <div class="platform-stat">
                            <div class="value"><?php echo number_format($adP['avg_views']); ?></div>
                            <div class="label">Avg Views</div>
                        </div>
                        <div class="platform-stat">
                            <div class="value"><?php echo number_format($adP['avg_engagement']); ?></div>
                            <div class="label">Avg Engagement</div>
                        </div>
                        <div class="platform-stat">
                            <div class="value">฿<?php echo number_format($adP['cpe'], 2); ?></div>
                            <div class="label">CPE</div>
                        </div>
                        <div class="platform-stat">
                            <div class="value">฿<?php echo number_format($adP['cpv'], 2); ?></div>
                            <div class="label">CP1K Views</div>
                        </div>
                    </div>
                    
                    <?php
                    // คำนวณประสิทธิภาพ (ยิ่ง CPE ต่ำยิ่งดี)
                    $efficiency = 100;
                    if ($adP['cpe'] > 0) {
                        if ($adP['cpe'] <= 2) $efficiency = 100;
                        elseif ($adP['cpe'] <= 5) $efficiency = 70;
                        elseif ($adP['cpe'] <= 10) $efficiency = 40;
                        else $efficiency = 20;
                    }
                    $meterClass = $efficiency >= 70 ? 'good' : ($efficiency >= 40 ? 'medium' : 'poor');
                    ?>
                    <div class="efficiency-meter">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">ประสิทธิภาพ</span>
                            <span style="font-size: 0.8rem;"><?php echo $efficiency; ?>%</span>
                        </div>
                        <div class="meter-bar">
                            <div class="meter-fill <?php echo $meterClass; ?>" style="width: <?php echo $efficiency; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Top Ad Posts -->
        <?php if (!empty($topAdPosts)): ?>
        <div class="card">
            <h2><i class="fas fa-trophy"></i> Top 10 โพสต์ยิงแอดที่ได้ผลดีที่สุด</h2>
            
            <div class="top-posts">
                <?php foreach ($topAdPosts as $i => $post): ?>
                <div class="post-item">
                    <div class="post-rank"><?php echo $i + 1; ?></div>
                    <div class="post-title" title="<?php echo htmlspecialchars($post['title'] ?: $post['description']); ?>">
                        <span class="platform-badge <?php echo $post['social']; ?>" style="font-size: 0.7rem; padding: 2px 8px; margin-right: 8px;">
                            <?php echo $post['social']; ?>
                        </span>
                        <?php echo htmlspecialchars(mb_substr($post['title'] ?: $post['description'], 0, 60)); ?>...
                    </div>
                    <div class="post-stat">
                        <div class="value">฿<?php echo number_format($post['ad_spend']); ?></div>
                        <div class="label">Ad Spend</div>
                    </div>
                    <div class="post-stat">
                        <div class="value"><?php echo number_format($post['views']); ?></div>
                        <div class="label">Views</div>
                    </div>
                    <div class="post-stat">
                        <div class="value"><?php echo number_format($post['likes']); ?></div>
                        <div class="label">Likes</div>
                    </div>
                    <div class="post-stat">
                        <?php 
                        $eng = $post['likes'] + $post['comments'] + $post['shares'];
                        $cpe = $post['ad_spend'] > 0 && $eng > 0 ? $post['ad_spend'] / $eng : 0;
                        ?>
                        <div class="value">฿<?php echo number_format($cpe, 2); ?></div>
                        <div class="label">CPE</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Insights -->
        <div class="card">
            <h2><i class="fas fa-lightbulb"></i> Insights & คำแนะนำ</h2>
            
            <div class="insights-box">
                <?php
                $insights = [];
                
                // เปรียบเทียบ avg engagement
                if ($adMetrics['avg_engagement'] > $organicMetrics['avg_engagement']) {
                    $pct = round((($adMetrics['avg_engagement'] - $organicMetrics['avg_engagement']) / max(1, $organicMetrics['avg_engagement'])) * 100, 1);
                    $insights[] = "โพสต์ที่ยิงแอดมี Engagement สูงกว่า Organic ถึง {$pct}%";
                } else if ($organicMetrics['avg_engagement'] > $adMetrics['avg_engagement']) {
                    $insights[] = "Organic Engagement ดีกว่า Ad! อาจต้องทบทวน Content หรือ Targeting";
                }
                
                // CPE Analysis
                if ($adMetrics['cpe'] <= 2) {
                    $insights[] = "CPE อยู่ในระดับดีมาก (฿{$adMetrics['cpe']}) - ควบคุมต้นทุนได้ดี";
                } else if ($adMetrics['cpe'] <= 5) {
                    $insights[] = "CPE อยู่ในระดับปานกลาง (฿{$adMetrics['cpe']}) - มีโอกาสปรับปรุง";
                } else {
                    $insights[] = "CPE ค่อนข้างสูง (฿{$adMetrics['cpe']}) - ควรพิจารณาปรับ Targeting หรือ Creative";
                }
                
                // Platform recommendation
                $bestPlatform = null;
                $bestCPE = PHP_FLOAT_MAX;
                foreach ($adByPlatform as $platform => $metrics) {
                    if ($metrics['cpe'] > 0 && $metrics['cpe'] < $bestCPE) {
                        $bestCPE = $metrics['cpe'];
                        $bestPlatform = $platform;
                    }
                }
                if ($bestPlatform) {
                    $insights[] = "{$bestPlatform} เป็น Platform ที่คุ้มค่าที่สุด (CPE ฿" . number_format($bestCPE, 2) . ")";
                }
                
                // Ad spending efficiency
                $engPerBaht = $adMetrics['ad_spend'] > 0 ? $adMetrics['engagement'] / $adMetrics['ad_spend'] : 0;
                if ($engPerBaht > 0) {
                    $insights[] = "ทุกๆ ฿1 ที่ใช้จ่าย ได้รับ " . number_format($engPerBaht, 2) . " engagement";
                }
                
                foreach ($insights as $insight):
                ?>
                <div class="insight-item">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $insight; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        // อัพเดทปี
        document.getElementById('monthSelect')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('yearInput').value = selected.dataset.year;
        });
        
        // Chart
        <?php if (!empty($adPosts)): ?>
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Avg Views', 'Avg Likes', 'Avg Comments', 'Avg Shares', 'Avg Engagement'],
                datasets: [
                    {
                        label: 'Ad Posts',
                        data: [
                            <?php echo round($adMetrics['avg_views']); ?>,
                            <?php echo round($adMetrics['avg_likes']); ?>,
                            <?php echo round($adMetrics['count'] > 0 ? $adMetrics['comments'] / $adMetrics['count'] : 0); ?>,
                            <?php echo round($adMetrics['count'] > 0 ? $adMetrics['shares'] / $adMetrics['count'] : 0); ?>,
                            <?php echo round($adMetrics['avg_engagement']); ?>
                        ],
                        backgroundColor: 'rgba(243, 156, 18, 0.8)',
                        borderRadius: 6
                    },
                    {
                        label: 'Organic',
                        data: [
                            <?php echo round($organicMetrics['avg_views']); ?>,
                            <?php echo round($organicMetrics['avg_likes']); ?>,
                            <?php echo round($organicMetrics['count'] > 0 ? $organicMetrics['comments'] / $organicMetrics['count'] : 0); ?>,
                            <?php echo round($organicMetrics['count'] > 0 ? $organicMetrics['shares'] / $organicMetrics['count'] : 0); ?>,
                            <?php echo round($organicMetrics['avg_engagement']); ?>
                        ],
                        backgroundColor: 'rgba(46, 213, 115, 0.8)',
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
