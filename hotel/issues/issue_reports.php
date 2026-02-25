<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$name = $_SESSION['name'];
$role = $_SESSION['role'];

// ช่วงเวลา
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// สถิติรวม
$stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low
FROM issues
WHERE DATE(reported_date) BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// สถิติตามหมวดหมู่
$category_stats = [];
$sql = "SELECT c.name, c.color, c.icon, COUNT(i.id) as count
        FROM issue_categories c
        LEFT JOIN issues i ON c.id = i.category_id 
            AND DATE(i.reported_date) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $category_stats[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติตามผู้รับผิดชอบ
$assignee_stats = [];
$sql = "SELECT u.name, 
        COUNT(i.id) as total,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN i.status IN ('pending', 'assigned', 'in_progress') THEN 1 ELSE 0 END) as pending
        FROM users u
        LEFT JOIN issues i ON u.id = i.assigned_to 
            AND DATE(i.reported_date) BETWEEN ? AND ?
        WHERE u.role IN ('maintenance', 'staff', 'admin', 'manager')
        GROUP BY u.id
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $assignee_stats[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติตามแผนก
$department_stats = [];
$sql = "SELECT d.name, COUNT(i.id) as count
        FROM departments d
        LEFT JOIN issues i ON d.id = i.department_id 
            AND DATE(i.reported_date) BETWEEN ? AND ?
        GROUP BY d.id
        ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $department_stats[] = $row;
}
mysqli_stmt_close($stmt);

// เวลาเฉลี่ยในการแก้ไข (สำหรับงานที่เสร็จแล้ว)
$avg_time = [];
$sql = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, reported_date, completed_date)) as avg_hours
        FROM issues
        WHERE status = 'completed' 
        AND completed_date IS NOT NULL
        AND DATE(reported_date) BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$avg_time = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุป - Issue Reports</title>
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
        
        .stat-card.pending h3 { color: #ffc107; }
        .stat-card.progress h3 { color: #17a2b8; }
        .stat-card.urgent h3 { color: #dc3545; }
        .stat-card.success h3 { color: #28a745; }
        .stat-card.primary h3 { color: #667eea; }
        
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
        
        .table-responsive {
            overflow-x: auto;
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
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .print-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
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
            <h1>📊 รายงานสรุปปัญหา</h1>
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
            <a href="issues_calendar.php">📅 ปฏิทิน</a>
            <a href="../dashboard.php" class="secondary">🏠 หน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <!-- ฟิลเตอร์ช่วงเวลา -->
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
                <button type="button" class="print-btn" onclick="window.print()">🖨️ พิมพ์รายงาน</button>
            </form>
        </div>
        
        <!-- สถิติรวม -->
        <div class="stats-grid">
            <div class="stat-card primary">
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
            <div class="stat-card success">
                <h3><?php echo $stats['completed']; ?></h3>
                <p>✅ เสร็จสิ้น</p>
            </div>
            <div class="stat-card urgent">
                <h3><?php echo $stats['urgent']; ?></h3>
                <p>🚨 เร่งด่วน</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $avg_time['avg_hours'] ? round($avg_time['avg_hours'], 1) : '0'; ?></h3>
                <p>⏱️ เวลาเฉลี่ย (ชม.)</p>
            </div>
        </div>
        
        <!-- กราฟแท่ง -->
        <div class="chart-row">
            <!-- สถิติตามหมวดหมู่ -->
            <div class="card">
                <h3>📊 ปัญหาแยกตามหมวดหมู่</h3>
                <?php if (empty($category_stats)): ?>
                    <p style="text-align: center; color: #6c757d;">ไม่มีข้อมูล</p>
                <?php else: ?>
                    <?php 
                    $max_count = max(array_column($category_stats, 'count'));
                    foreach ($category_stats as $cat): 
                        $percentage = $max_count > 0 ? ($cat['count'] / $max_count * 100) : 0;
                    ?>
                        <div class="chart-bar">
                            <div class="chart-label">
                                <span><?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?></span>
                                <strong><?php echo $cat['count']; ?> รายการ</strong>
                            </div>
                            <div class="chart-progress">
                                <div class="chart-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $cat['color']; ?>">
                                    <?php if ($percentage > 20): echo $cat['count']; endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- สถิติตามผู้รับผิดชอบ -->
            <div class="card">
                <h3>👥 ปัญหาแยกตามผู้รับผิดชอบ</h3>
                <?php if (empty($assignee_stats)): ?>
                    <p style="text-align: center; color: #6c757d;">ไม่มีข้อมูล</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ชื่อ</th>
                                    <th style="text-align: center;">ทั้งหมด</th>
                                    <th style="text-align: center;">เสร็จแล้ว</th>
                                    <th style="text-align: center;">คงเหลือ</th>
                                    <th style="text-align: center;">อัตราสำเร็จ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignee_stats as $assignee): 
                                    $completion_rate = $assignee['total'] > 0 ? 
                                        round(($assignee['completed'] / $assignee['total']) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assignee['name']); ?></strong></td>
                                        <td style="text-align: center;"><?php echo $assignee['total']; ?></td>
                                        <td style="text-align: center;">
                                            <span style="color: #28a745;"><?php echo $assignee['completed']; ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span style="color: #ffc107;"><?php echo $assignee['pending']; ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong style="color: <?php echo $completion_rate >= 80 ? '#28a745' : ($completion_rate >= 50 ? '#ffc107' : '#dc3545'); ?>">
                                                <?php echo $completion_rate; ?>%
                                            </strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- สถิติตามแผนก -->
        <div class="card">
            <h3>🏢 ปัญหาแยกตามแผนก</h3>
            <?php if (empty($department_stats)): ?>
                <p style="text-align: center; color: #6c757d;">ไม่มีข้อมูล</p>
            <?php else: ?>
                <?php 
                $max_dept = max(array_column($department_stats, 'count'));
                foreach ($department_stats as $dept): 
                    $percentage = $max_dept > 0 ? ($dept['count'] / $max_dept * 100) : 0;
                ?>
                    <div class="chart-bar">
                        <div class="chart-label">
                            <span><?php echo htmlspecialchars($dept['name']); ?></span>
                            <strong><?php echo $dept['count']; ?> รายการ</strong>
                        </div>
                        <div class="chart-progress">
                            <div class="chart-fill" style="width: <?php echo $percentage; ?>%">
                                <?php if ($percentage > 20): echo $dept['count']; endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- สถิติตามสถานะ -->
        <div class="card" style="margin-top: 20px;">
            <h3>📈 สถิติตามสถานะ</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>สถานะ</th>
                            <th style="text-align: center;">จำนวน</th>
                            <th style="text-align: center;">เปอร์เซ็นต์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $status_data = [
                            ['name' => 'รอดำเนินการ', 'count' => $stats['pending'], 'color' => '#ffc107'],
                            ['name' => 'มอบหมายแล้ว', 'count' => $stats['assigned'], 'color' => '#17a2b8'],
                            ['name' => 'กำลังดำเนินการ', 'count' => $stats['in_progress'], 'color' => '#2196f3'],
                            ['name' => 'พักงาน', 'count' => $stats['on_hold'], 'color' => '#ff9800'],
                            ['name' => 'เสร็จสิ้น', 'count' => $stats['completed'], 'color' => '#28a745'],
                            ['name' => 'ยกเลิก', 'count' => $stats['cancelled'], 'color' => '#dc3545']
                        ];
                        
                        foreach ($status_data as $status):
                            $percentage = $stats['total'] > 0 ? 
                                round(($status['count'] / $stats['total']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo $status['color']; ?>; border-radius: 50%; margin-right: 8px;"></span>
                                    <?php echo $status['name']; ?>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?php echo $status['count']; ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php echo $percentage; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>