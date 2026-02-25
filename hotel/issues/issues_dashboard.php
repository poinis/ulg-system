<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];

// ฟิลเตอร์
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$filter_assigned = isset($_GET['assigned']) ? $_GET['assigned'] : '';

// ดึงรายการปัญหา
$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_status)) {
    $where_clauses[] = "i.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_priority)) {
    $where_clauses[] = "i.priority = ?";
    $params[] = $filter_priority;
    $types .= 's';
}

if (!empty($filter_category)) {
    $where_clauses[] = "i.category_id = ?";
    $params[] = $filter_category;
    $types .= 'i';
}

if ($filter_assigned === 'me') {
    $where_clauses[] = "i.assigned_to = ?";
    $params[] = $user_id;
    $types .= 'i';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "SELECT i.*, 
               u1.name as reporter_name, 
               u2.name as assignee_name, 
               c.name as category_name, 
               c.color as category_color,
               c.icon as category_icon
        FROM issues i
        LEFT JOIN users u1 ON i.reported_by = u1.id
        LEFT JOIN users u2 ON i.assigned_to = u2.id
        LEFT JOIN issue_categories c ON i.category_id = c.id
        $where_sql
        ORDER BY 
            CASE i.priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            i.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$issues = [];
while ($row = mysqli_fetch_assoc($result)) {
    $issues[] = $row;
}

// ดึงหมวดหมู่
$categories = [];
$sql = "SELECT * FROM issue_categories ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// สถิติ
$stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as urgent
FROM issues";
$result = mysqli_query($conn, $sql);
$stats = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งปัญหา - Hotel Management</title>
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
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .header h1 {
            font-size: 1.5em;
            font-weight: 600;
        }
        
        .nav {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        
        .nav-content {
            max-width: 1600px;
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
            font-size: 0.95em;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        
        .nav a:hover, .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .nav a.secondary {
            background: #95a5a6;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }
        
        .stat-card.pending h3 { color: #ffc107; }
        .stat-card.progress h3 { color: #17a2b8; }
        .stat-card.urgent h3 { color: #dc3545; }
        .stat-card.success h3 { color: #28a745; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .filters h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
        }
        
        .issues-grid {
            display: grid;
            gap: 15px;
        }
        
        .issue-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .issue-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .issue-card.urgent { border-left-color: #dc3545; background: #fff5f5; }
        .issue-card.high { border-left-color: #ff9800; }
        .issue-card.medium { border-left-color: #ffc107; }
        .issue-card.low { border-left-color: #28a745; }
        
        .issue-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .issue-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .issue-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-urgent { background: #dc3545; color: white; }
        .badge-high { background: #ff9800; color: white; }
        .badge-medium { background: #ffc107; color: #333; }
        .badge-low { background: #28a745; color: white; }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-assigned { background: #cce5ff; color: #004085; }
        .badge-progress { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .issue-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .info-item strong {
            color: #333;
        }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🔧 ระบบแจ้งปัญหาโรงแรม</h1>
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong><br>
                <small><?php echo htmlspecialchars($role); ?></small>
            </div>
        </div>
    </div>
    
    <div class="nav">
        <div class="nav-content">
            <a href="issue_create.php">➕ แจ้งปัญหาใหม่</a>
            <a href="issues_calendar.php">📅 ปฏิทิน</a>
            <a href="issue_reports.php">📊 รายงาน</a>
            <a href="../dashboard.php" class="secondary">🏠 หน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <!-- สถิติ -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>📋 ทั้งหมด</p>
            </div>
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
                <h3><?php echo $stats['completed']; ?></h3>
                <p>✅ เสร็จสิ้น</p>
            </div>
        </div>
        
        <!-- ฟิลเตอร์ -->
        <div class="filters">
            <h3>🔍 ค้นหาและกรอง</h3>
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label>สถานะ</label>
                    <select name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                        <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                        <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                        <option value="on_hold" <?php echo $filter_status == 'on_hold' ? 'selected' : ''; ?>>พักงาน</option>
                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>ความสำคัญ</label>
                    <select name="priority">
                        <option value="">ทั้งหมด</option>
                        <option value="urgent" <?php echo $filter_priority == 'urgent' ? 'selected' : ''; ?>>เร่งด่วน</option>
                        <option value="high" <?php echo $filter_priority == 'high' ? 'selected' : ''; ?>>สูง</option>
                        <option value="medium" <?php echo $filter_priority == 'medium' ? 'selected' : ''; ?>>ปานกลาง</option>
                        <option value="low" <?php echo $filter_priority == 'low' ? 'selected' : ''; ?>>ต่ำ</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>ประเภท</label>
                    <select name="category">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>การมอบหมาย</label>
                    <select name="assigned">
                        <option value="">ทั้งหมด</option>
                        <option value="me" <?php echo $filter_assigned == 'me' ? 'selected' : ''; ?>>งานของฉัน</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">ค้นหา</button>
                <a href="issues_dashboard.php" class="btn secondary">ล้างฟิลเตอร์</a>
            </form>
        </div>
        
        <!-- รายการปัญหา -->
        <div class="issues-grid">
            <?php if (empty($issues)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>ไม่พบรายการปัญหา</h3>
                    <p>ลองปรับเปลี่ยนเงื่อนไขการค้นหา หรือเพิ่มปัญหาใหม่</p>
                </div>
            <?php else: ?>
                <?php foreach ($issues as $issue): ?>
                    <div class="issue-card <?php echo $issue['priority']; ?>" 
                         onclick="location.href='issue_detail.php?id=<?php echo $issue['id']; ?>'">
                        <div class="issue-header">
                            <div class="issue-title">
                                <?php echo htmlspecialchars($issue['title']); ?>
                            </div>
                            <div class="issue-badges">
                                <span class="badge badge-<?php echo $issue['priority']; ?>">
                                    <?php 
                                    $priority_thai = [
                                        'urgent' => '🚨 เร่งด่วน',
                                        'high' => '⚠️ สูง',
                                        'medium' => '📌 ปานกลาง',
                                        'low' => '📎 ต่ำ'
                                    ];
                                    echo $priority_thai[$issue['priority']];
                                    ?>
                                </span>
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
                        </div>
                        
                        <?php if (!empty($issue['description'])): ?>
                            <p style="color: #6c757d; margin-bottom: 15px; line-height: 1.6;">
                                <?php echo htmlspecialchars(mb_substr($issue['description'], 0, 150)); ?>
                                <?php echo mb_strlen($issue['description']) > 150 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="issue-info">
                            <div class="info-item">
                                <span class="category-badge" style="background: <?php echo $issue['category_color']; ?>">
                                    <?php echo $issue['category_icon']; ?> <?php echo htmlspecialchars($issue['category_name']); ?>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                📍 <strong><?php echo htmlspecialchars($issue['location']); ?></strong>
                                <?php if (!empty($issue['room_number'])): ?>
                                    | ห้อง <?php echo htmlspecialchars($issue['room_number']); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="info-item">
                                👤 แจ้งโดย: <strong><?php echo htmlspecialchars($issue['reporter_name']); ?></strong>
                            </div>
                            
                            <?php if (!empty($issue['assignee_name'])): ?>
                                <div class="info-item">
                                    🔧 มอบหมายให้: <strong><?php echo htmlspecialchars($issue['assignee_name']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                ⏰ <?php echo timeAgo($issue['created_at']); ?>
                            </div>
                            
                            <?php if (!empty($issue['due_date'])): ?>
                                <div class="info-item">
                                    📅 ครบกำหนด: <strong><?php echo getThaiDate($issue['due_date'], 'short'); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>