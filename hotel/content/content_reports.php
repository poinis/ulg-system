<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$name = $_SESSION['name'];
$role = $_SESSION['role'];

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// สถิติรวม
$stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
FROM content_calendar
WHERE (post_date BETWEEN ? AND ?) OR (brief_date BETWEEN ? AND ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $date_from, $date_to, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// สถิติตามหมวดหมู่
$category_stats = [];
$sql = "SELECT category, COUNT(*) as count
        FROM content_calendar
        WHERE (post_date BETWEEN ? AND ?) OR (brief_date BETWEEN ? AND ?)
        GROUP BY category
        ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $date_from, $date_to, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $category_stats[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติตามผู้รับผิดชอบ
$assignee_stats = [];
$sql = "SELECT assignee, COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM content_calendar
        WHERE (post_date BETWEEN ? AND ?) OR (brief_date BETWEEN ? AND ?)
        AND assignee IS NOT NULL AND assignee != ''
        GROUP BY assignee
        ORDER BY total DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $date_from, $date_to, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $assignee_stats[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติ Engagement เฉลี่ย
$engagement_avg = [];
$sql = "SELECT 
    AVG(e.views) as avg_views,
    AVG(e.likes) as avg_likes,
    AVG(e.comments) as avg_comments,
    AVG(e.shares) as avg_shares,
    AVG(e.reach) as avg_reach,
    AVG(e.engagement_rate) as avg_engagement_rate
FROM facebook_engagement e
JOIN content_calendar c ON e.content_id = c.id
WHERE e.status = 'completed'
AND c.post_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$engagement_avg = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Top performing posts
$top_posts = [];
$sql = "SELECT c.job_title, c.category, c.post_date, e.views, e.likes, e.comments, e.shares, e.engagement_rate
        FROM content_calendar c
        JOIN facebook_engagement e ON c.id = e.content_id
        WHERE e.status = 'completed'
        AND c.post_date BETWEEN ? AND ?
        ORDER BY e.engagement_rate DESC
        LIMIT 10";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $top_posts[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติตามประเภทคอนเทนต์
$type_stats = [];
$sql = "SELECT content_type, COUNT(*) as count
        FROM content_calendar
        WHERE (post_date BETWEEN ? AND ?) OR (brief_date BETWEEN ? AND ?)
        GROUP BY content_type
        ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $date_from, $date_to, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $type_stats[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน Content - Facebook</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        
        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav a, .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .nav a:hover, .btn:hover {
            background: #5568d3;
        }
        
        .nav a.secondary {
            background: #95a5a6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 30px;
        }
        
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card.primary h3 { color: #667eea; }
        .stat-card.success h3 { color: #28a745; }
        .stat-card.info h3 { color: #17a2b8; }
        .stat-card.warning h3 { color: #ffc107; }
        
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.2em;
        }
        
        .chart-bar {
            margin-bottom: 15px;
        }
        
        .chart-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .chart-progress {
            height: 30px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .chart-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 10px;
            color: white;
            font-weight: 600;
            font-size: 0.85em;
            transition: width 0.5s;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .print-btn {
            background: #28a745;
        }
        
        @media print {
            .nav, .filter-card, .print-btn { display: none; }
        }
        
        @media (max-width: 768px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📊 รายงาน Content Calendar</h1>
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong><br>
                <small><?php echo htmlspecialchars($role); ?></small>
            </div>
        </div>
    </div>
    
    <div class="nav">
        <div class="nav-content">
            <a href="content_add.php">➕ เพิ่มคอนเทนต์</a>
            <a href="content_dashboard.php">📅 ปฏิทิน</a>
            <a href="facebook_engage.php">📊 Engagement</a>
            <a href="../dashboard.php" class="secondary">🏠 หน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <!-- ฟิลเตอร์ -->
        <div class="filter-card">
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label>📅 จากวันที่</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>📅 ถึงวันที่</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <button type="submit" class="btn">ค้นหา</button>
                <button type="button" class="print-btn btn" onclick="window.print()">🖨️ พิมพ์</button>
            </form>
        </div>
        
        <!-- สถิติรวม -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <h3><?php echo $stats['total']; ?></h3>
                <p>📋 ทั้งหมด</p>
            </div>
            <div class="stat-card warning">
                <h3><?php echo $stats['planned'] + $stats['in_progress']; ?></h3>
                <p>⏳ กำลังดำเนินการ</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $stats['posted']; ?></h3>
                <p>✅ โพสต์แล้ว</p>
            </div>
            <div class="stat-card info">
                <h3><?php echo $stats['completed']; ?></h3>
                <p>🎉 เสร็จสมบูรณ์</p>
            </div>
        </div>
        
        <!-- Engagement เฉลี่ย -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>📊 Facebook Engagement เฉลี่ย</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 style="color: #667eea;"><?php echo number_format($engagement_avg['avg_views'] ?? 0); ?></h3>
                    <p>👁️ Views</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #667eea;"><?php echo number_format($engagement_avg['avg_likes'] ?? 0); ?></h3>
                    <p>👍 Likes</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #667eea;"><?php echo number_format($engagement_avg['avg_comments'] ?? 0); ?></h3>
                    <p>💬 Comments</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #667eea;"><?php echo number_format($engagement_avg['avg_shares'] ?? 0); ?></h3>
                    <p>🔄 Shares</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #667eea;"><?php echo number_format($engagement_avg['avg_reach'] ?? 0); ?></h3>
                    <p>📈 Reach</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #28a745;"><?php echo number_format($engagement_avg['avg_engagement_rate'] ?? 0, 2); ?>%</h3>
                    <p>📊 Engagement Rate</p>
                </div>
            </div>
        </div>
        
        <!-- กราฟ -->
        <div class="chart-row">
            <!-- ตามหมวดหมู่ -->
            <div class="card">
                <h3>📂 คอนเทนต์แยกตามหมวดหมู่</h3>
                <?php if (empty($category_stats)): ?>
                    <p style="text-align: center; color: #6c757d;">ไม่มีข้อมูล</p>
                <?php else: ?>
                    <?php 
                    $max_cat = max(array_column($category_stats, 'count'));
                    $colors = [
                        'Product Knowledge' => '#dc3545',
                        'Promotion' => '#ffc107',
                        'Lifestyle' => '#9c27b0',
                        'Event' => '#2196f3',
                        'News' => '#ff5722'
                    ];
                    foreach ($category_stats as $cat): 
                        $percentage = $max_cat > 0 ? ($cat['count'] / $max_cat * 100) : 0;
                        $color = $colors[$cat['category']] ?? '#667eea';
                    ?>
                        <div class="chart-bar">
                            <div class="chart-label">
                                <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                <strong><?php echo $cat['count']; ?> รายการ</strong>
                            </div>
                            <div class="chart-progress">
                                <div class="chart-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>">
                                    <?php if ($percentage > 20): echo $cat['count']; endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- ตามผู้รับผิดชอบ -->
            <div class="card">
                <h3>👥 คอนเทนต์แยกตามผู้รับผิดชอบ</h3>
                <?php if (empty($assignee_stats)): ?>
                    <p style="text-align: center; color: #6c757d;">ไม่มีข้อมูล</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อ</th>
                                <th style="text-align: center;">ทั้งหมด</th>
                                <th style="text-align: center;">เสร็จแล้ว</th>
                                <th style="text-align: center;">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignee_stats as $assignee): 
                                $completion = $assignee['total'] > 0 ? 
                                    round(($assignee['completed'] / $assignee['total']) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($assignee['assignee']); ?></strong></td>
                                    <td style="text-align: center;"><?php echo $assignee['total']; ?></td>
                                    <td style="text-align: center;">
                                        <span style="color: #28a745;"><?php echo $assignee['completed']; ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <strong style="color: <?php echo $completion >= 80 ? '#28a745' : ($completion >= 50 ? '#ffc107' : '#dc3545'); ?>">
                                            <?php echo $completion; ?>%
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Performing Posts -->
        <div class="card">
            <h3>🏆 โพสต์ที่มี Engagement สูงสุด (Top 10)</h3>
            <?php if (empty($top_posts)): ?>
                <p style="text-align: center; color: #6c757d; padding: 20px;">ยังไม่มีข้อมูล Engagement</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>หัวข้อ</th>
                            <th>หมวดหมู่</th>
                            <th style="text-align: center;">Views</th>
                            <th style="text-align: center;">Likes</th>
                            <th style="text-align: center;">Comments</th>
                            <th style="text-align: center;">Shares</th>
                            <th style="text-align: center;">Engagement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_posts as $post): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(mb_substr($post['job_title'], 0, 40)); ?></strong></td>
                                <td><?php echo htmlspecialchars($post['category']); ?></td>
                                <td style="text-align: center;"><?php echo number_format($post['views']); ?></td>
                                <td style="text-align: center;"><?php echo number_format($post['likes']); ?></td>
                                <td style="text-align: center;"><?php echo number_format($post['comments']); ?></td>
                                <td style="text-align: center;"><?php echo number_format($post['shares']); ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: #28a745;"><?php echo $post['engagement_rate']; ?>%</strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- ตามประเภทคอนเทนต์ -->
        <?php if (!empty($type_stats)): ?>
            <div class="card" style="margin-top: 20px;">
                <h3>🎬 คอนเทนต์แยกตามประเภท</h3>
                <?php 
                $max_type = max(array_column($type_stats, 'count'));
                foreach ($type_stats as $type): 
                    $percentage = $max_type > 0 ? ($type['count'] / $max_type * 100) : 0;
                    $type_icon = [
                        'image' => '🖼️',
                        'video' => '🎥',
                        'carousel' => '🎠',
                        'reel' => '🎬',
                        'story' => '📖'
                    ];
                ?>
                    <div class="chart-bar">
                        <div class="chart-label">
                            <span><?php echo $type_icon[$type['content_type']] ?? '📄'; ?> <?php echo ucfirst($type['content_type']); ?></span>
                            <strong><?php echo $type['count']; ?> รายการ</strong>
                        </div>
                        <div class="chart-progress">
                            <div class="chart-fill" style="width: <?php echo $percentage; ?>%">
                                <?php if ($percentage > 20): echo $type['count']; endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>