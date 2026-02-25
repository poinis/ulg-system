<?php
/**
 * Social Media Report
 * เปรียบเทียบช่วงเวลาที่เลือก พร้อม Engagement Rate และแสดง Posts
 */

require_once 'SocialMediaImporter.php';

// ฟังก์ชันจัดการ Settings
function getFollowersFromDB($pdo) {
    $sql = "SELECT platform, followers FROM platform_settings";
    $stmt = $pdo->query($sql);
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['platform']] = $row['followers'];
    }
    return $result;
}

function updateFollowers($pdo, $platform, $followers) {
    $sql = "INSERT INTO platform_settings (platform, followers) 
            VALUES (:platform, :followers) 
            ON DUPLICATE KEY UPDATE followers = :followers2";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':platform' => $platform,
        ':followers' => $followers,
        ':followers2' => $followers
    ]);
}

// ดึงข้อมูลสำหรับ Report
function getReportData($pdo, $startDate, $endDate, $platform = null) {
    $platformFilter = $platform ? "AND social = :platform" : "";
    
    $sql = "SELECT 
                social,
                account_name,
                account_username,
                COUNT(*) as post_count,
                SUM(views) as total_views,
                SUM(likes) as total_likes,
                SUM(comments) as total_comments,
                SUM(shares) as total_shares,
                SUM(saves) as total_saves,
                SUM(favorites) as total_favorites,
                SUM(reach) as total_reach,
                AVG(views) as avg_views,
                AVG(likes) as avg_likes
            FROM social_posts 
            WHERE publish_time >= :start_date 
            AND publish_time <= :end_date
            $platformFilter
            GROUP BY social, account_name, account_username";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
    $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
    if ($platform) {
        $stmt->bindValue(':platform', $platform);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// ดึงข้อมูลรายวัน
function getDailyData($pdo, $startDate, $endDate, $platform = null) {
    $platformFilter = $platform ? "AND social = :platform" : "";
    
    $sql = "SELECT 
                DATE(publish_time) as date,
                social,
                COUNT(*) as post_count,
                SUM(views) as views,
                SUM(likes) as likes,
                SUM(comments) as comments,
                SUM(shares) as shares,
                SUM(saves + favorites) as saves,
                SUM(reach) as reach
            FROM social_posts 
            WHERE publish_time >= :start_date
            AND publish_time <= :end_date
            $platformFilter
            GROUP BY DATE(publish_time), social
            ORDER BY date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
    $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
    if ($platform) {
        $stmt->bindValue(':platform', $platform);
    }
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// ดึง Posts
function getPosts($pdo, $startDate, $endDate, $platform = null, $limit = 50) {
    $platformFilter = $platform ? "AND social = :platform" : "";
    
    $sql = "SELECT * FROM social_posts 
            WHERE publish_time >= :start_date
            AND publish_time <= :end_date
            $platformFilter
            ORDER BY publish_time DESC
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
    $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
    if ($platform) {
        $stmt->bindValue(':platform', $platform);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// คำนวณ Engagement Rate
function calculateER($likes, $comments, $saves, $followers) {
    if ($followers <= 0) return 0;
    return (($likes + $comments + $saves) / $followers) * 100;
}

// คำนวณ % การเปลี่ยนแปลง
function calculateChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / $previous) * 100;
}

try {
    $pdo = getDBConnection();
    
    // Handle followers update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_followers'])) {
        if (isset($_POST['fb_followers'])) {
            updateFollowers($pdo, 'Facebook', intval($_POST['fb_followers']));
        }
        if (isset($_POST['ig_followers'])) {
            updateFollowers($pdo, 'Instagram', intval($_POST['ig_followers']));
        }
        if (isset($_POST['tt_followers'])) {
            updateFollowers($pdo, 'TikTok', intval($_POST['tt_followers']));
        }
        $followersSaved = true;
    }
    
    // Get followers from DB
    $followersDB = getFollowersFromDB($pdo);
    $fbFollowers = $followersDB['Facebook'] ?? 50000;
    $igFollowers = $followersDB['Instagram'] ?? 35000;
    $ttFollowers = $followersDB['TikTok'] ?? 15000;
    
    // Date filters
    $filter = $_GET['platform'] ?? 'all';
    $platformParam = $filter !== 'all' ? $filter : null;
    
    // Default dates: current period = 7 days, compare = 7 days before
    $now = new DateTime();
    $endDate1 = $_GET['end_date1'] ?? $now->format('Y-m-d');
    $startDate1 = $_GET['start_date1'] ?? (new DateTime($endDate1))->modify('-6 days')->format('Y-m-d');
    
    $endDate2 = $_GET['end_date2'] ?? (new DateTime($startDate1))->modify('-1 day')->format('Y-m-d');
    $startDate2 = $_GET['start_date2'] ?? (new DateTime($endDate2))->modify('-6 days')->format('Y-m-d');
    
    // Get data
    $currentData = getReportData($pdo, $startDate1, $endDate1, $platformParam);
    $previousData = getReportData($pdo, $startDate2, $endDate2, $platformParam);
    $dailyData = getDailyData($pdo, $startDate2, $endDate1, $platformParam);
    $posts = getPosts($pdo, $startDate1, $endDate1, $platformParam, 100);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// รวมข้อมูลตาม platform
$currentByPlatform = [];
$previousByPlatform = [];

foreach ($currentData as $row) {
    $p = $row['social'];
    if (!isset($currentByPlatform[$p])) {
        $currentByPlatform[$p] = ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'posts' => 0, 'reach' => 0];
    }
    $currentByPlatform[$p]['views'] += $row['total_views'];
    $currentByPlatform[$p]['likes'] += $row['total_likes'];
    $currentByPlatform[$p]['comments'] += $row['total_comments'];
    $currentByPlatform[$p]['shares'] += $row['total_shares'];
    $currentByPlatform[$p]['saves'] += $row['total_saves'] + $row['total_favorites'];
    $currentByPlatform[$p]['posts'] += $row['post_count'];
    $currentByPlatform[$p]['reach'] += $row['total_reach'];
}

foreach ($previousData as $row) {
    $p = $row['social'];
    if (!isset($previousByPlatform[$p])) {
        $previousByPlatform[$p] = ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'posts' => 0, 'reach' => 0];
    }
    $previousByPlatform[$p]['views'] += $row['total_views'];
    $previousByPlatform[$p]['likes'] += $row['total_likes'];
    $previousByPlatform[$p]['comments'] += $row['total_comments'];
    $previousByPlatform[$p]['shares'] += $row['total_shares'];
    $previousByPlatform[$p]['saves'] += $row['total_saves'] + $row['total_favorites'];
    $previousByPlatform[$p]['posts'] += $row['post_count'];
    $previousByPlatform[$p]['reach'] += $row['total_reach'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d0d2b 100%);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 25px 35px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 26px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .nav-links {
            display: flex;
            gap: 12px;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 14px;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .filters-section {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-group label {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
        }
        
        .filter-group input[type="date"] {
            padding: 10px 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .filter-group input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-btn {
            padding: 10px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .date-separator {
            color: rgba(255,255,255,0.4);
            font-size: 20px;
            padding-bottom: 10px;
        }
        
        .period-label {
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .period-label.current { border-left: 3px solid #00f5a0; }
        .period-label.previous { border-left: 3px solid #667eea; }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            text-decoration: none;
            color: rgba(255,255,255,0.7);
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .filter-tab:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 22px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        
        .stat-card.facebook::before { background: linear-gradient(90deg, #1877F2, #166FE5); }
        .stat-card.instagram::before { background: linear-gradient(90deg, #E4405F, #C13584, #833AB4); }
        .stat-card.tiktok::before { background: linear-gradient(90deg, #25F4EE, #FE2C55); }
        
        .stat-card .platform-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        
        .stat-card .platform-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .stat-card.facebook .platform-icon { background: rgba(24, 119, 242, 0.2); }
        .stat-card.instagram .platform-icon { background: rgba(228, 64, 95, 0.2); }
        .stat-card.tiktok .platform-icon { background: rgba(37, 244, 238, 0.2); }
        
        .stat-card .platform-name {
            font-size: 16px;
            font-weight: 700;
        }
        
        .stat-card .account-name {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        
        .er-display {
            text-align: center;
            padding: 18px 0;
            margin-bottom: 18px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
        }
        
        .er-value {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #00f5a0, #00d9f5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .er-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .comparison-item {
            background: rgba(255,255,255,0.02);
            border-radius: 8px;
            padding: 12px;
        }
        
        .comparison-item .label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 4px;
        }
        
        .comparison-item .values {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        
        .comparison-item .current {
            font-size: 20px;
            font-weight: 700;
        }
        
        .comparison-item .change {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .comparison-item .change.positive {
            background: rgba(0, 245, 160, 0.2);
            color: #00f5a0;
        }
        
        .comparison-item .change.negative {
            background: rgba(255, 82, 82, 0.2);
            color: #ff5252;
        }
        
        .comparison-item .change.neutral {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255,255,255,0.5);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }
        
        .followers-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .followers-card h3 {
            font-size: 15px;
            margin-bottom: 15px;
            color: rgba(255,255,255,0.8);
        }
        
        .followers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .follower-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .follower-item label {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        
        .follower-item input {
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: white;
            font-size: 15px;
            width: 100%;
        }
        
        .follower-item input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .save-btn {
            padding: 10px 25px;
            background: linear-gradient(135deg, #00f5a0, #00d9f5);
            border: none;
            border-radius: 8px;
            color: #000;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 245, 160, 0.3);
        }
        
        .saved-msg {
            background: rgba(0, 245, 160, 0.2);
            color: #00f5a0;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        /* Posts Table */
        .posts-section {
            margin-top: 25px;
        }
        
        .posts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .posts-table th,
        .posts-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .posts-table th {
            background: rgba(255,255,255,0.03);
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            font-size: 11px;
            text-transform: uppercase;
            position: sticky;
            top: 0;
        }
        
        .posts-table tr:hover {
            background: rgba(255,255,255,0.02);
        }
        
        .posts-table .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }
        
        .posts-table .badge.facebook { background: #1877F2; }
        .posts-table .badge.instagram { background: linear-gradient(135deg, #E4405F, #C13584); }
        .posts-table .badge.tiktok { background: #000; }
        
        .posts-table .content-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .posts-table .number {
            text-align: right;
            font-family: 'Consolas', monospace;
            font-weight: 600;
        }
        
        .posts-table .er-cell {
            color: #00f5a0;
            font-weight: 700;
        }
        
        .posts-table .link-cell a {
            color: #667eea;
            text-decoration: none;
            padding: 4px 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 5px;
            font-size: 11px;
        }
        
        .posts-table .link-cell a:hover {
            background: rgba(102, 126, 234, 0.2);
        }
        
        .table-scroll {
            max-height: 500px;
            overflow-y: auto;
            border-radius: 10px;
        }
        
        .table-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-scroll::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .table-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }
        
        @media (max-width: 768px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
            
            .comparison-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Social Media Report</h1>
            <div class="nav-links">
                <a href="index.php" class="nav-link">📤 อัพโหลด</a>
                <a href="view_data.php" class="nav-link">📋 ดูข้อมูล</a>
            </div>
        </div>
        
        <!-- Date Filters -->
        <div class="filters-section">
            <form method="GET" id="filterForm">
                <input type="hidden" name="platform" value="<?php echo htmlspecialchars($filter); ?>">
                <div class="filters-row">
                    <div>
                        <div class="period-label current">📅 ช่วงเวลาปัจจุบัน</div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div class="filter-group">
                                <label>เริ่มต้น</label>
                                <input type="date" name="start_date1" value="<?php echo $startDate1; ?>">
                            </div>
                            <span class="date-separator">→</span>
                            <div class="filter-group">
                                <label>สิ้นสุด</label>
                                <input type="date" name="end_date1" value="<?php echo $endDate1; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding-bottom: 10px; color: rgba(255,255,255,0.3);">VS</div>
                    
                    <div>
                        <div class="period-label previous">📅 ช่วงเวลาเปรียบเทียบ</div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div class="filter-group">
                                <label>เริ่มต้น</label>
                                <input type="date" name="start_date2" value="<?php echo $startDate2; ?>">
                            </div>
                            <span class="date-separator">→</span>
                            <div class="filter-group">
                                <label>สิ้นสุด</label>
                                <input type="date" name="end_date2" value="<?php echo $endDate2; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="filter-btn">🔍 กรองข้อมูล</button>
                </div>
            </form>
        </div>
        
        <!-- Platform Filter -->
        <div class="filter-tabs">
            <a href="?platform=all&start_date1=<?php echo $startDate1; ?>&end_date1=<?php echo $endDate1; ?>&start_date2=<?php echo $startDate2; ?>&end_date2=<?php echo $endDate2; ?>" 
               class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">📊 ทุก Platform</a>
            <a href="?platform=Facebook&start_date1=<?php echo $startDate1; ?>&end_date1=<?php echo $endDate1; ?>&start_date2=<?php echo $startDate2; ?>&end_date2=<?php echo $endDate2; ?>" 
               class="filter-tab <?php echo $filter === 'Facebook' ? 'active' : ''; ?>">📘 Facebook</a>
            <a href="?platform=Instagram&start_date1=<?php echo $startDate1; ?>&end_date1=<?php echo $endDate1; ?>&start_date2=<?php echo $startDate2; ?>&end_date2=<?php echo $endDate2; ?>" 
               class="filter-tab <?php echo $filter === 'Instagram' ? 'active' : ''; ?>">📸 Instagram</a>
            <a href="?platform=TikTok&start_date1=<?php echo $startDate1; ?>&end_date1=<?php echo $endDate1; ?>&start_date2=<?php echo $startDate2; ?>&end_date2=<?php echo $endDate2; ?>" 
               class="filter-tab <?php echo $filter === 'TikTok' ? 'active' : ''; ?>">🎵 TikTok</a>
        </div>
        
        <!-- Followers Settings -->
        <div class="followers-card">
            <?php if (isset($followersSaved)): ?>
                <div class="saved-msg">✅ บันทึก Followers เรียบร้อยแล้ว!</div>
            <?php endif; ?>
            <h3>⚙️ ตั้งค่าจำนวน Followers (สำหรับคำนวณ Engagement Rate)</h3>
            <form method="POST">
                <div class="followers-grid">
                    <div class="follower-item">
                        <label>📘 Facebook</label>
                        <input type="number" name="fb_followers" value="<?php echo $fbFollowers; ?>">
                    </div>
                    <div class="follower-item">
                        <label>📸 Instagram</label>
                        <input type="number" name="ig_followers" value="<?php echo $igFollowers; ?>">
                    </div>
                    <div class="follower-item">
                        <label>🎵 TikTok</label>
                        <input type="number" name="tt_followers" value="<?php echo $ttFollowers; ?>">
                    </div>
                    <button type="submit" name="update_followers" value="1" class="save-btn">💾 บันทึก</button>
                </div>
            </form>
        </div>
        
        <!-- Stats Cards by Platform -->
        <div class="stats-grid">
            <?php
            $platforms = [
                'Facebook' => ['icon' => '📘', 'class' => 'facebook', 'followers' => $fbFollowers],
                'Instagram' => ['icon' => '📸', 'class' => 'instagram', 'followers' => $igFollowers],
                'TikTok' => ['icon' => '🎵', 'class' => 'tiktok', 'followers' => $ttFollowers]
            ];
            
            foreach ($platforms as $platform => $info):
                if ($filter !== 'all' && $filter !== $platform) continue;
                
                $current = $currentByPlatform[$platform] ?? ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'posts' => 0, 'reach' => 0];
                $previous = $previousByPlatform[$platform] ?? ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'posts' => 0, 'reach' => 0];
                
                $currentER = calculateER($current['likes'], $current['comments'], $current['saves'], $info['followers']);
                $previousER = calculateER($previous['likes'], $previous['comments'], $previous['saves'], $info['followers']);
                $erChange = calculateChange($currentER, $previousER);
                
                $viewsChange = calculateChange($current['views'], $previous['views']);
                $likesChange = calculateChange($current['likes'], $previous['likes']);
                $commentsChange = calculateChange($current['comments'], $previous['comments']);
                $sharesChange = calculateChange($current['shares'], $previous['shares']);
            ?>
            <div class="stat-card <?php echo $info['class']; ?>">
                <div class="platform-header">
                    <div class="platform-icon"><?php echo $info['icon']; ?></div>
                    <div>
                        <div class="platform-name"><?php echo $platform; ?></div>
                        <div class="account-name"><?php echo number_format($info['followers']); ?> followers • <?php echo $current['posts']; ?> posts</div>
                    </div>
                </div>
                
                <div class="er-display">
                    <div class="er-value"><?php echo number_format($currentER, 2); ?>%</div>
                    <div class="er-label">Engagement Rate</div>
                    <div style="margin-top: 8px;">
                        <span class="change <?php echo $erChange >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $erChange >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($erChange), 1); ?>%
                        </span>
                    </div>
                </div>
                
                <div class="comparison-grid">
                    <div class="comparison-item">
                        <div class="label">👁️ Views</div>
                        <div class="values">
                            <span class="current"><?php echo number_format($current['views']); ?></span>
                            <span class="change <?php echo $viewsChange >= 0 ? 'positive' : ($viewsChange < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo $viewsChange >= 0 ? '+' : ''; ?><?php echo number_format($viewsChange, 1); ?>%
                            </span>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <div class="label">❤️ Likes</div>
                        <div class="values">
                            <span class="current"><?php echo number_format($current['likes']); ?></span>
                            <span class="change <?php echo $likesChange >= 0 ? 'positive' : ($likesChange < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo $likesChange >= 0 ? '+' : ''; ?><?php echo number_format($likesChange, 1); ?>%
                            </span>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <div class="label">💬 Comments</div>
                        <div class="values">
                            <span class="current"><?php echo number_format($current['comments']); ?></span>
                            <span class="change <?php echo $commentsChange >= 0 ? 'positive' : ($commentsChange < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo $commentsChange >= 0 ? '+' : ''; ?><?php echo number_format($commentsChange, 1); ?>%
                            </span>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <div class="label">🔄 Shares</div>
                        <div class="values">
                            <span class="current"><?php echo number_format($current['shares']); ?></span>
                            <span class="change <?php echo $sharesChange >= 0 ? 'positive' : ($sharesChange < 0 ? 'negative' : 'neutral'); ?>">
                                <?php echo $sharesChange >= 0 ? '+' : ''; ?><?php echo number_format($sharesChange, 1); ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Charts -->
        <div class="chart-row">
            <div class="card">
                <h2 class="card-title">📈 Views Trend</h2>
                <div class="chart-container">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">💖 Engagement Trend</h2>
                <div class="chart-container">
                    <canvas id="engagementChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">📊 Engagement Rate Comparison</h2>
            <div class="chart-container">
                <canvas id="erCompareChart"></canvas>
            </div>
        </div>
        
        <!-- Posts Table -->
        <div class="card posts-section">
            <h2 class="card-title">📝 Posts ในช่วงเวลาที่เลือก (<?php echo count($posts); ?> posts)</h2>
            <div class="table-scroll">
                <table class="posts-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Content</th>
                            <th>Type</th>
                            <th>Published</th>
                            <th class="number">Views</th>
                            <th class="number">Likes</th>
                            <th class="number">Comments</th>
                            <th class="number">Shares</th>
                            <th class="number">ER%</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $postFollowers = $platforms[$post['social']]['followers'] ?? 10000;
                            $postSaves = $post['saves'] + $post['favorites'];
                            $postER = calculateER($post['likes'], $post['comments'], $postSaves, $postFollowers);
                        ?>
                        <tr>
                            <td>
                                <span class="badge <?php echo strtolower($post['social']); ?>">
                                    <?php echo $post['social'] === 'Facebook' ? '📘' : ($post['social'] === 'Instagram' ? '📸' : '🎵'); ?>
                                    <?php echo $post['social']; ?>
                                </span>
                            </td>
                            <td class="content-cell" title="<?php echo htmlspecialchars($post['title'] ?: $post['description']); ?>">
                                <?php 
                                $content = $post['title'] ?: $post['description'];
                                echo htmlspecialchars(mb_substr($content, 0, 50)) . (mb_strlen($content) > 50 ? '...' : ''); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($post['post_type'] ?: '-'); ?></td>
                            <td style="white-space: nowrap;"><?php echo $post['publish_time'] ? date('d/m H:i', strtotime($post['publish_time'])) : '-'; ?></td>
                            <td class="number"><?php echo number_format($post['views']); ?></td>
                            <td class="number"><?php echo number_format($post['likes']); ?></td>
                            <td class="number"><?php echo number_format($post['comments']); ?></td>
                            <td class="number"><?php echo number_format($post['shares']); ?></td>
                            <td class="number er-cell"><?php echo number_format($postER, 2); ?>%</td>
                            <td class="link-cell">
                                <?php if ($post['permalink']): ?>
                                    <a href="<?php echo htmlspecialchars($post['permalink']); ?>" target="_blank">🔗 View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Prepare data for charts
        <?php
        $dailyByPlatform = [];
        $dates = [];
        
        foreach ($dailyData as $row) {
            $date = $row['date'];
            $platform = $row['social'];
            
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }
            
            if (!isset($dailyByPlatform[$platform])) {
                $dailyByPlatform[$platform] = [];
            }
            
            $dailyByPlatform[$platform][$date] = [
                'views' => $row['views'],
                'likes' => $row['likes'],
                'comments' => $row['comments'],
                'shares' => $row['shares'],
                'saves' => $row['saves']
            ];
        }
        
        sort($dates);
        ?>
        
        const dates = <?php echo json_encode(array_map(function($d) { return date('d/m', strtotime($d)); }, $dates)); ?>;
        
        const platformColors = {
            'Facebook': { bg: 'rgba(24, 119, 242, 0.2)', border: '#1877F2' },
            'Instagram': { bg: 'rgba(228, 64, 95, 0.2)', border: '#E4405F' },
            'TikTok': { bg: 'rgba(37, 244, 238, 0.2)', border: '#25F4EE' }
        };
        
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: 'rgba(255,255,255,0.7)', font: { size: 12 } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 11 } }
                }
            }
        };
        
        // Views Chart
        new Chart(document.getElementById('viewsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    <?php foreach ($dailyByPlatform as $platform => $data): ?>
                    {
                        label: '<?php echo $platform; ?>',
                        data: [<?php 
                            $values = [];
                            foreach ($dates as $date) {
                                $values[] = $data[$date]['views'] ?? 0;
                            }
                            echo implode(',', $values);
                        ?>],
                        borderColor: platformColors['<?php echo $platform; ?>']?.border || '#667eea',
                        backgroundColor: platformColors['<?php echo $platform; ?>']?.bg || 'rgba(102,126,234,0.2)',
                        fill: true,
                        tension: 0.4
                    },
                    <?php endforeach; ?>
                ]
            },
            options: chartOptions
        });
        
        // Engagement Chart
        new Chart(document.getElementById('engagementChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    <?php foreach ($dailyByPlatform as $platform => $data): ?>
                    {
                        label: '<?php echo $platform; ?>',
                        data: [<?php 
                            $values = [];
                            foreach ($dates as $date) {
                                $d = $data[$date] ?? ['likes' => 0, 'comments' => 0, 'saves' => 0];
                                $values[] = $d['likes'] + $d['comments'] + $d['saves'];
                            }
                            echo implode(',', $values);
                        ?>],
                        backgroundColor: platformColors['<?php echo $platform; ?>']?.border || '#667eea',
                        borderRadius: 4
                    },
                    <?php endforeach; ?>
                ]
            },
            options: chartOptions
        });
        
        // ER Compare Chart
        new Chart(document.getElementById('erCompareChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Facebook', 'Instagram', 'TikTok'],
                datasets: [
                    {
                        label: 'ช่วงปัจจุบัน',
                        data: [
                            <?php echo number_format(calculateER($currentByPlatform['Facebook']['likes'] ?? 0, $currentByPlatform['Facebook']['comments'] ?? 0, $currentByPlatform['Facebook']['saves'] ?? 0, $fbFollowers), 2); ?>,
                            <?php echo number_format(calculateER($currentByPlatform['Instagram']['likes'] ?? 0, $currentByPlatform['Instagram']['comments'] ?? 0, $currentByPlatform['Instagram']['saves'] ?? 0, $igFollowers), 2); ?>,
                            <?php echo number_format(calculateER($currentByPlatform['TikTok']['likes'] ?? 0, $currentByPlatform['TikTok']['comments'] ?? 0, $currentByPlatform['TikTok']['saves'] ?? 0, $ttFollowers), 2); ?>
                        ],
                        backgroundColor: ['#1877F2', '#E4405F', '#25F4EE'],
                        borderRadius: 6
                    },
                    {
                        label: 'ช่วงเปรียบเทียบ',
                        data: [
                            <?php echo number_format(calculateER($previousByPlatform['Facebook']['likes'] ?? 0, $previousByPlatform['Facebook']['comments'] ?? 0, $previousByPlatform['Facebook']['saves'] ?? 0, $fbFollowers), 2); ?>,
                            <?php echo number_format(calculateER($previousByPlatform['Instagram']['likes'] ?? 0, $previousByPlatform['Instagram']['comments'] ?? 0, $previousByPlatform['Instagram']['saves'] ?? 0, $igFollowers), 2); ?>,
                            <?php echo number_format(calculateER($previousByPlatform['TikTok']['likes'] ?? 0, $previousByPlatform['TikTok']['comments'] ?? 0, $previousByPlatform['TikTok']['saves'] ?? 0, $ttFollowers), 2); ?>
                        ],
                        backgroundColor: ['rgba(24,119,242,0.4)', 'rgba(228,64,95,0.4)', 'rgba(37,244,238,0.4)'],
                        borderRadius: 6
                    }
                ]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                scales: {
                    ...chartOptions.scales,
                    y: {
                        ...chartOptions.scales.y,
                        ticks: { 
                            color: 'rgba(255,255,255,0.5)',
                            callback: function(value) { return value + '%'; }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
