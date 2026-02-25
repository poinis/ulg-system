<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

// ดึงสถิติปัญหา
$stats = [
    'total_issues' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed_today' => 0,
    'urgent' => 0,
    'my_assigned' => 0
];

$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' AND DATE(completed_date) = CURDATE() THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as urgent,
    SUM(CASE WHEN assigned_to = ? AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as my_assigned
FROM issues";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $stats = [
        'total_issues' => $row['total'],
        'pending' => $row['pending'],
        'in_progress' => $row['in_progress'],
        'completed_today' => $row['completed_today'],
        'urgent' => $row['urgent'],
        'my_assigned' => $row['my_assigned']
    ];
}
mysqli_stmt_close($stmt);

// ดึงปัญหาล่าสุด
$recent_issues = [];
$sql = "SELECT i.*, u1.name as reporter_name, u2.name as assignee_name, c.name as category_name, c.color as category_color
        FROM issues i
        LEFT JOIN users u1 ON i.reported_by = u1.id
        LEFT JOIN users u2 ON i.assigned_to = u2.id
        LEFT JOIN issue_categories c ON i.category_id = c.id
        ORDER BY i.created_at DESC
        LIMIT 10";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $recent_issues[] = $row;
}

// ดึงการแจ้งเตือนที่ยังไม่อ่าน
$unread_notifications = 0;
$sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $unread_notifications = $row['unread'];
}
mysqli_stmt_close($stmt);

// สถิติ Content Calendar
$content_stats = [
    'total_posts' => 0,
    'this_month' => 0,
    'pending_engage' => 0,
    'posted' => 0
];

$current_month = date('Y-m');
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN DATE_FORMAT(post_date, '%Y-%m') = ? THEN 1 ELSE 0 END) as this_month,
    SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted
FROM content_calendar";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $current_month);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $content_stats = [
        'total_posts' => $row['total'],
        'this_month' => $row['this_month'],
        'posted' => $row['posted']
    ];
}
mysqli_stmt_close($stmt);

// นับ engagement ที่รอตรวจสอบ
$sql = "SELECT COUNT(*) as pending FROM facebook_engagement WHERE status = 'pending'";
$result = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($result)) {
    $content_stats['pending_engage'] = $row['pending'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hotel Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info strong {
            font-size: 1.1em;
        }
        
        .user-info .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .nav-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .nav-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            display: block;
        }
        
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .nav-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .nav-card-icon {
            font-size: 3em;
        }
        
        .nav-card h2 {
            font-size: 1.5em;
            color: #667eea;
        }
        
        .nav-card p {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .nav-card-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .mini-stat {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        .mini-stat strong {
            color: #667eea;
            font-size: 1.2em;
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #6c757d;
            font-size: 0.95em;
        }
        
        .stat-card.pending h3 { color: #ffc107; }
        .stat-card.progress h3 { color: #17a2b8; }
        .stat-card.urgent h3 { color: #dc3545; }
        .stat-card.success h3 { color: #28a745; }
        .stat-card.primary h3 { color: #667eea; }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .section h2 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .issue-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .issue-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .issue-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .issue-item.urgent {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .issue-item.pending {
            border-left-color: #ffc107;
        }
        
        .issue-item.progress {
            border-left-color: #17a2b8;
        }
        
        .issue-info {
            flex: 1;
        }
        
        .issue-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.05em;
        }
        
        .issue-meta {
            font-size: 0.85em;
            color: #6c757d;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-progress { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-urgent { background: #f8d7da; color: #721c24; }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.3em;
            }
            
            .nav-cards {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏨 Hotel Management System</h1>
            <div class="user-info">
                <strong><?php echo htmlspecialchars($name); ?></strong>
                <div class="role-badge"><?php echo htmlspecialchars($role); ?></div>
                <?php if ($unread_notifications > 0): ?>
                    <div style="margin-top: 5px;">
                        <span style="background: #ff4757; padding: 4px 10px; border-radius: 20px; font-size: 0.85em;">
                            🔔 <?php echo $unread_notifications; ?> แจ้งเตือนใหม่
                        </span>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 10px;">
                    <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
            <?php if (in_array($role, ['admin', 'manager'])): ?>
                <a href="users/user_management.php" class="nav-card">
                    <div class="nav-card-header">
                        <div class="nav-card-icon">👥</div>
                        <div>
                            <h2>จัดการผู้ใช้</h2>
                        </div>
                    </div>
                    <p>จัดการบัญชีผู้ใช้ กำหนด Role และตั้งค่า Pumble Webhook สำหรับการแจ้งเตือน</p>
                    <div class="nav-card-stats">
                        <div class="mini-stat">
                            <strong>
                                <?php 
                                $sql_users = "SELECT COUNT(*) as cnt FROM users";
                                $res_users = mysqli_query($conn, $sql_users);
                                $row_users = mysqli_fetch_assoc($res_users);
                                echo $row_users['cnt'];
                                ?>
                            </strong>
                            <span>ผู้ใช้ทั้งหมด</span>
                        </div>
                        <div class="mini-stat">
                            <strong>
                                <?php 
                                $sql_pumble = "SELECT COUNT(*) as cnt FROM users WHERE pumble_webhook IS NOT NULL AND pumble_webhook != ''";
                                $res_pumble = mysqli_query($conn, $sql_pumble);
                                $row_pumble = mysqli_fetch_assoc($res_pumble);
                                echo $row_pumble['cnt'];
                                ?>
                            </strong>
                            <span>มี Pumble</span>
                        </div>
                    </div>
                </a>
            <?php endif; ?>
            
            <a href="issues/issues_dashboard.php" class="nav-card">
                <div class="nav-card-header">
                    <div class="nav-card-icon">🔧</div>
                    <div>
                        <h2>ระบบแจ้งปัญหา</h2>
                    </div>
                </div>
                <p>จัดการปัญหาและการซ่อมบำรุงภายในโรงแรม ติดตามสถานะ และแจ้งเตือนผ่าน Pumble</p>
                <div class="nav-card-stats">
                    <div class="mini-stat">
                        <strong><?php echo $stats['pending']; ?></strong>
                        <span>รอดำเนินการ</span>
                    </div>
                    <div class="mini-stat">
                        <strong><?php echo $stats['in_progress']; ?></strong>
                        <span>กำลังดำเนินการ</span>
                    </div>
                    <div class="mini-stat">
                        <strong><?php echo $stats['urgent']; ?></strong>
                        <span>เร่งด่วน</span>
                    </div>
                    <div class="mini-stat">
                        <strong><?php echo $stats['my_assigned']; ?></strong>
                        <span>งานของฉัน</span>
                    </div>
                </div>
            </a>
            
            <a href="content/content_dashboard.php" class="nav-card">
                <div class="nav-card-header">
                    <div class="nav-card-icon">📱</div>
                    <div>
                        <h2>Content Calendar</h2>
                    </div>
                </div>
                <p>วางแผนและจัดการคอนเทนต์ Facebook ติดตาม Engagement และวิเคราะห์ผลลัพธ์</p>
                <div class="nav-card-stats">
                    <div class="mini-stat">
                        <strong><?php echo $content_stats['this_month']; ?></strong>
                        <span>โพสต์เดือนนี้</span>
                    </div>
                    <div class="mini-stat">
                        <strong><?php echo $content_stats['posted']; ?></strong>
                        <span>โพสต์แล้ว</span>
                    </div>
                    <div class="mini-stat">
                        <strong><?php echo $content_stats['pending_engage']; ?></strong>
                        <span>รอเช็ค Engage</span>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- สถิติรวม -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>⏳ รอดำเนินการ</p>
            </div>
            <div class="stat-card progress">
                <h3><?php echo $stats['in_progress']; ?></h3>
                <p>🔄 กำลังดำเนินการ</p>
            </div>
            <div class="stat-card urgent">
                <h3><?php echo $stats['urgent']; ?></h3>
                <p>🚨 เร่งด่วน</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $stats['completed_today']; ?></h3>
                <p>✅ เสร็จวันนี้</p>
            </div>
            <div class="stat-card primary">
                <h3><?php echo $stats['my_assigned']; ?></h3>
                <p>👤 งานของฉัน</p>
            </div>
        </div>
        
        <!-- รายการปัญหาล่าสุด -->
        <div class="section">
            <h2>📋 ปัญหาล่าสุด</h2>
            <div class="issue-list">
                <?php if (empty($recent_issues)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 20px;">ไม่มีรายการปัญหา</p>
                <?php else: ?>
                    <?php foreach ($recent_issues as $issue): 
                        $priority_class = $issue['priority'] == 'urgent' ? 'urgent' : $issue['status'];
                    ?>
                        <div class="issue-item <?php echo $priority_class; ?>" 
                             onclick="location.href='/hotel/issues/issue_detail.php?id=<?php echo $issue['id']; ?>'">
                            <div class="issue-info">
                                <div class="issue-title">
                                    <?php echo htmlspecialchars($issue['title']); ?>
                                </div>
                                <div class="issue-meta">
                                    <span>🏷️ <?php echo htmlspecialchars($issue['category_name']); ?></span>
                                    <span>📍 <?php echo htmlspecialchars($issue['location']); ?></span>
                                    <span>👤 <?php echo htmlspecialchars($issue['reporter_name']); ?></span>
                                    <span>⏰ <?php echo timeAgo($issue['created_at']); ?></span>
                                </div>
                            </div>
                            <span class="badge badge-<?php echo $issue['status']; ?>">
                                <?php 
                                $status_thai = [
                                    'pending' => 'รอดำเนินการ',
                                    'assigned' => 'มอบหมายแล้ว',
                                    'in_progress' => 'กำลังดำเนินการ',
                                    'on_hold' => 'พักงาน',
                                    'completed' => 'เสร็จสิ้น',
                                    'cancelled' => 'ยกเลิก'
                                ];
                                echo $status_thai[$issue['status']];
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>