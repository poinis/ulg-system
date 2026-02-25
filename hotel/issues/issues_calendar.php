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

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// ดึงข้อมูลปัญหาในเดือนที่เลือก
$events = [];
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
        WHERE (DATE(i.reported_date) BETWEEN ? AND ?)
           OR (DATE(i.due_date) BETWEEN ? AND ?)
        ORDER BY i.reported_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $month_start, $month_end, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}
mysqli_stmt_close($stmt);

// จัดเตรียมข้อมูลปฏิทิน
$calendar = [];
foreach ($events as $event) {
    $date = date('Y-m-d', strtotime($event['reported_date']));
    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }
    $calendar[$date][] = $event;
}

// สร้างโครงสร้างปฏิทิน
$first_day = new DateTime($month_start);
$last_day = new DateTime($month_end);
$days_in_month = (int) $last_day->format('j');
$day_of_week = (int) $first_day->format('w');

$weeks = [];
$week = [];

for ($i = 0; $i < $day_of_week; $i++) {
    $week[] = null;
}

for ($day = 1; $day <= $days_in_month; $day++) {
    $date = date('Y-m-d', strtotime($selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
    $week[] = $date;
    if (count($week) == 7) {
        $weeks[] = $week;
        $week = [];
    }
}

if (!empty($week)) {
    while (count($week) < 7) {
        $week[] = null;
    }
    $weeks[] = $week;
}

// สถิติ
$stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
FROM issues
WHERE DATE(reported_date) BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินปัญหา - Hotel Management</title>
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
        
        .nav a {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .nav a:hover {
            background: #5568d3;
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
        
        .month-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .month-selector input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .calendar-wrapper {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .calendar-table th {
            padding: 15px;
            background: #667eea;
            color: white;
            font-weight: 600;
            text-align: center;
        }
        
        .calendar-table td {
            border: 1px solid #e9ecef;
            padding: 10px;
            vertical-align: top;
            height: 120px;
            position: relative;
        }
        
        .calendar-table td.empty {
            background: #f8f9fa;
        }
        
        .calendar-table td.weekend {
            background: #fff9e6;
        }
        
        .calendar-table td.today {
            background: #e7f3ff;
            border: 2px solid #667eea;
        }
        
        .date-number {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .issue-badge {
            display: block;
            padding: 4px 8px;
            margin: 3px 0;
            border-radius: 4px;
            font-size: 0.75em;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: white;
        }
        
        .issue-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .issue-badge.urgent {
            background: #dc3545;
        }
        
        .issue-badge.high {
            background: #ff9800;
        }
        
        .issue-badge.medium {
            background: #ffc107;
            color: #333;
        }
        
        .issue-badge.low {
            background: #28a745;
        }
        
        .issue-badge.pending {
            border-left: 3px solid #ffc107;
        }
        
        .issue-badge.in_progress {
            border-left: 3px solid #17a2b8;
        }
        
        .issue-badge.completed {
            opacity: 0.7;
            text-decoration: line-through;
        }
        
        .issue-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .legend {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 20px;
        }
        
        .legend h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .calendar-table {
                font-size: 0.8em;
            }
            
            .calendar-table td {
                height: 100px;
                padding: 5px;
            }
            
            .issue-badge {
                font-size: 0.7em;
                padding: 3px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📅 ปฏิทินปัญหา</h1>
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong><br>
                <small><?php echo htmlspecialchars($role); ?></small>
            </div>
        </div>
    </div>
    
    <div class="nav">
        <div class="nav-content">
            <a href="issue_create.php">➕ แจ้งปัญหาใหม่</a>
            <a href="issues_dashboard.php">📋 รายการปัญหา</a>
            <a href="issue_reports.php">📊 รายงาน</a>
            <a href="../dashboard.php" class="secondary">🏠 หน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <!-- สถิติ -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>📋 ทั้งหมดเดือนนี้</p>
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
        
        <!-- เลือกเดือน -->
        <div class="month-selector">
            <h2><?php echo getThaiDate($month_start, 'short'); ?></h2>
            <form method="GET">
                <input type="month" name="month" value="<?php echo $selected_month; ?>" onchange="this.form.submit()">
            </form>
        </div>
        
        <!-- ปฏิทิน -->
        <div class="calendar-wrapper">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>อาทิตย์</th>
                        <th>จันทร์</th>
                        <th>อังคาร</th>
                        <th>พุธ</th>
                        <th>พฤหัสบดี</th>
                        <th>ศุกร์</th>
                        <th>เสาร์</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeks as $week): ?>
                        <tr>
                            <?php foreach ($week as $idx => $date): 
                                $is_today = ($date === date('Y-m-d'));
                                $is_weekend = ($idx == 0 || $idx == 6);
                                $issues_on_date = isset($calendar[$date]) ? $calendar[$date] : [];
                                $class = [];
                                if (!$date) $class[] = 'empty';
                                if ($is_weekend) $class[] = 'weekend';
                                if ($is_today) $class[] = 'today';
                            ?>
                                <td class="<?php echo implode(' ', $class); ?>" 
                                    <?php if ($date): ?>
                                        onclick="location.href='issue_create.php?date=<?php echo $date; ?>'"
                                        style="cursor: pointer;"
                                    <?php endif; ?>>
                                    <?php if ($date): ?>
                                        <div class="date-number">
                                            <?php echo date('j', strtotime($date)); ?>
                                        </div>
                                        
                                        <?php if (!empty($issues_on_date)): ?>
                                            <span class="issue-count"><?php echo count($issues_on_date); ?></span>
                                            <?php 
                                            $shown = 0;
                                            foreach ($issues_on_date as $issue): 
                                                if ($shown >= 3) break;
                                                $shown++;
                                            ?>
                                                <a href="issue_detail.php?id=<?php echo $issue['id']; ?>" 
                                                   class="issue-badge <?php echo $issue['priority']; ?> <?php echo $issue['status']; ?>"
                                                   onclick="event.stopPropagation();"
                                                   title="<?php echo htmlspecialchars($issue['title']); ?>">
                                                    <?php echo $issue['category_icon']; ?> 
                                                    <?php echo htmlspecialchars(mb_substr($issue['title'], 0, 15)); ?>
                                                </a>
                                            <?php endforeach; ?>
                                            
                                            <?php if (count($issues_on_date) > 3): ?>
                                                <div style="text-align: center; font-size: 0.8em; color: #6c757d; margin-top: 5px;">
                                                    +<?php echo count($issues_on_date) - 3; ?> เพิ่มเติม
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- คำอธิบายสี -->
        <div class="legend">
            <h3>🎨 คำอธิบายสี</h3>
            <div class="legend-grid">
                <div class="legend-item">
                    <div class="legend-color" style="background: #dc3545;"></div>
                    <span>เร่งด่วน (Urgent)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ff9800;"></div>
                    <span>สูง (High)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ffc107;"></div>
                    <span>ปานกลาง (Medium)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #28a745;"></div>
                    <span>ต่ำ (Low)</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>