<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];
$role = '';
$name = '';

// ดึง role และ name ของผู้ใช้
$sql_user = "SELECT role, name FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $role, $name);
mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

// รับค่าเดือนและปีจาก GET หรือใช้ค่าปัจจุบัน
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// ดึงจำนวนงานทั้งหมดในระบบ
$sql_total_system = "SELECT COUNT(*) as total FROM content_brief";
$result_total_system = mysqli_query($conn, $sql_total_system);
$row_total_system = mysqli_fetch_assoc($result_total_system);
$total_system = $row_total_system['total'];

// ดึงงานแบ่งตามสถานะทั้งระบบ
$status_counts = [];
$sql_status = "SELECT status, COUNT(*) as count FROM content_brief GROUP BY status";
$result_status = mysqli_query($conn, $sql_status);
while ($row = mysqli_fetch_assoc($result_status)) {
    $status_counts[$row['status']] = $row['count'];
}

// ดึงจำนวนงานแต่ละแบรนด์ในเดือนที่เลือก
$brand_counts = [];
$sql_brand = "SELECT brand, COUNT(*) as count 
        FROM content_brief 
        WHERE YEAR(due_date) = ? AND MONTH(due_date) = ?
        GROUP BY brand
        ORDER BY count DESC";
$stmt_brand = mysqli_prepare($conn, $sql_brand);
mysqli_stmt_bind_param($stmt_brand, "ii", $year, $month);
mysqli_stmt_execute($stmt_brand);
$result_brand = mysqli_stmt_get_result($stmt_brand);
$total_brand_month = 0;
while ($row = mysqli_fetch_assoc($result_brand)) {
    $brand_counts[$row['brand']] = $row['count'];
    $total_brand_month += $row['count'];
}
mysqli_stmt_close($stmt_brand);

// ดึงจำนวนงานแต่ละหมวดหมู่ในเดือนที่เลือก
$category_counts = [];
$sql_category = "SELECT category, COUNT(*) as count 
        FROM content_brief 
        WHERE YEAR(due_date) = ? AND MONTH(due_date) = ?
        AND category IS NOT NULL AND category != ''
        GROUP BY category
        ORDER BY count DESC";
$stmt_category = mysqli_prepare($conn, $sql_category);
mysqli_stmt_bind_param($stmt_category, "ii", $year, $month);
mysqli_stmt_execute($stmt_category);
$result_category = mysqli_stmt_get_result($stmt_category);
$total_category_month = 0;
while ($row = mysqli_fetch_assoc($result_category)) {
    $category_counts[$row['category']] = $row['count'];
    $total_category_month += $row['count'];
}
mysqli_stmt_close($stmt_category);

function percent($count, $total) {
    if ($total == 0) return 0;
    return round(($count / $total) * 100, 1);
}

$months_th = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// ตรวจสอบว่าผู้ใช้มีสิทธิ์บรีฟงานหรือไม่
$can_create_brief = in_array($role, ['admin', 'marketing', 'brand']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Content - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Tippy.js for Tooltips -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d1a;
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }

        /* Header */
        .header {
            background: #1a1a2e;
            padding: 20px 25px;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #2a2a4a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-left h1 {
            font-size: 1.5em;
            color: #ffffff;
            margin-bottom: 5px;
        }

        .user-info {
            color: #a0a0b0;
            font-size: 0.9em;
        }

        .user-info strong {
            color: #a78bfa;
        }

        .header-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9em;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669 0%, #34d399 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #f87171 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }

        /* Stats Container */
        .stats-container {
            background: #1a1a2e;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid #2a2a4a;
        }

        .stats-header {
            color: #ffffff;
            font-size: 1.3em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #7c3aed;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #252542;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #3a3a5a;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .stat-label {
            color: #a0a0b0;
            font-size: 0.85em;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-percent {
            color: #6a6a7a;
            font-size: 0.85em;
        }

        .stat-card.total .stat-number { color: #a78bfa; }
        .stat-card.pending .stat-number { color: #fbbf24; }
        .stat-card.in_progress .stat-number { color: #3b82f6; }
        .stat-card.completed .stat-number { color: #34d399; }
        .stat-card.approved .stat-number { color: #10b981; }
        .stat-card.need_info .stat-number { color: #f87171; }
        .stat-card.need_update .stat-number { color: #fb923c; }

        /* Calendar Container - ปรับให้เล็กลง */
        .calendar-container {
            background: #1a1a2e;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid #2a2a4a;
            max-width: 900px; /* จำกัดความกว้าง */
            margin-left: auto;
            margin-right: auto;
        }

        .calendar-header {
            color: #ffffff;
            font-size: 1.2em;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #7c3aed;
        }

        /* FullCalendar Dark Theme - ปรับให้เล็กลง */
        #calendar {
            background: #252542;
            padding: 10px;
            border-radius: 12px;
            font-size: 0.85em; /* ลดขนาดตัวอักษร */
        }

        .fc {
            --fc-border-color: #3a3a5a;
            --fc-button-bg-color: #7c3aed;
            --fc-button-border-color: #7c3aed;
            --fc-button-hover-bg-color: #6d28d9;
            --fc-button-hover-border-color: #6d28d9;
            --fc-button-active-bg-color: #5b21b6;
            --fc-today-bg-color: rgba(124, 58, 237, 0.15);
            --fc-page-bg-color: #252542;
            --fc-neutral-bg-color: #1a1a2e;
            --fc-list-event-hover-bg-color: #3a3a5a;
        }

        .fc-theme-standard td, .fc-theme-standard th {
            border-color: #3a3a5a;
        }

        .fc-col-header-cell-cushion,
        .fc-daygrid-day-number {
            color: #ffffff !important;
            font-size: 0.9em;
        }

        .fc-toolbar-title {
            color: #ffffff !important;
            font-size: 1.2em !important;
        }

        .fc-button {
            font-weight: 500 !important;
            padding: 5px 12px !important;
            font-size: 0.85em !important;
        }

        .fc-day-today {
            background: rgba(124, 58, 237, 0.15) !important;
        }

        .fc-daygrid-day.can-create-brief:hover {
            background: rgba(124, 58, 237, 0.25) !important;
            cursor: pointer;
        }

        /* ปรับขนาด cell ของปฏิทิน */
        .fc-daygrid-day {
            min-height: 80px !important;
        }

        .fc-daygrid-day-frame {
            min-height: 80px !important;
        }

        /* ปรับ event ให้เล็กลง */
        .fc-event {
            padding: 2px 4px !important;
            font-size: 0.75em !important;
            margin-bottom: 2px !important;
            cursor: pointer;
            border-radius: 4px !important;
        }

        .fc-daygrid-event-dot {
            display: none;
        }

        .fc-event-title {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tippy Tooltip Style */
        .tippy-box[data-theme~='custom'] {
            background-color: #1a1a2e;
            color: #ffffff;
            border: 1px solid #7c3aed;
            border-radius: 8px;
            font-size: 0.9em;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .tippy-box[data-theme~='custom'] .tippy-arrow {
            color: #7c3aed;
        }

        .tooltip-content {
            padding: 8px 4px;
        }

        .tooltip-content .tooltip-title {
            font-weight: 600;
            font-size: 1.05em;
            margin-bottom: 8px;
            color: #a78bfa;
            border-bottom: 1px solid #3a3a5a;
            padding-bottom: 5px;
        }

        .tooltip-content .tooltip-row {
            display: flex;
            margin-bottom: 4px;
        }

        .tooltip-content .tooltip-label {
            color: #a0a0b0;
            width: 80px;
            flex-shrink: 0;
        }

        .tooltip-content .tooltip-value {
            color: #ffffff;
        }

        .tooltip-content .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-pending { background: #fbbf24; color: #000; }
        .status-in_progress { background: #3b82f6; color: #fff; }
        .status-completed { background: #34d399; color: #000; }
        .status-approved { background: #10b981; color: #fff; }
        .status-need_info { background: #f87171; color: #fff; }
        .status-need_update { background: #fb923c; color: #000; }

        /* Month Selector */
        .month-selector {
            background: #1a1a2e;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid #2a2a4a;
        }

        .month-selector h3 {
            color: #ffffff;
            font-size: 1.2em;
            margin-bottom: 15px;
        }

        .month-selector form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .month-selector select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #3a3a5a;
            background: #252542;
            color: #ffffff;
            font-size: 1em;
            cursor: pointer;
            min-width: 150px;
        }

        .month-selector select:focus {
            outline: none;
            border-color: #7c3aed;
        }

        .month-selector select option {
            background: #252542;
            color: #ffffff;
        }

        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .brand-table-container,
        .chart-box {
            background: #1a1a2e;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #2a2a4a;
        }

        .brand-table-container h3,
        .chart-box h3 {
            color: #ffffff;
            font-size: 1.1em;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.5;
        }

        /* Brand Table */
        .brand-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-table th {
            background: #252542;
            color: #a78bfa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #7c3aed;
        }

        .brand-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #3a3a5a;
            color: #ffffff;
        }

        .brand-table tbody tr:hover {
            background: #252542;
        }

        .brand-table .brand-count {
            text-align: center;
            font-weight: 600;
            color: #34d399;
        }

        .brand-table .brand-percent {
            text-align: right;
            color: #a0a0b0;
        }

        .brand-table .total-row {
            background: #252542;
            font-weight: 600;
        }

        .brand-table .total-row td {
            border-top: 2px solid #7c3aed;
            color: #a78bfa;
        }

        /* Chart Wrapper */
        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        /* Empty State */
        .empty-state {
            background: #1a1a2e;
            padding: 50px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid #2a2a4a;
            color: #a0a0b0;
            font-size: 1.1em;
        }

        /* Responsive */
        @media screen and (max-width: 768px) {
            body {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                text-align: center;
            }

            .header-right {
                justify-content: center;
            }

            .btn {
                padding: 8px 14px;
                font-size: 0.85em;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 1.5em;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .month-selector form {
                flex-direction: column;
            }

            .month-selector select {
                width: 100%;
            }

            .calendar-container {
                max-width: 100%;
            }
        }

        @media screen and (max-width: 480px) {
            .header-left h1 {
                font-size: 1.2em;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <h1>📅 Content - Dashboard</h1>
        <div class="user-info">
            ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong> 
            <span>(<?php echo htmlspecialchars($username); ?>)</span>
        </div>
    </div>
    <div class="header-right">
        <?php if (in_array($role, ['admin', 'marketing', 'brand', 'owner', 'area', 'approve'])): ?>
            <a href="../dashboard.php" class="btn btn-danger">🏠 กลับหน้าหลัก</a>
            <a href="../content/report/index.php" class="btn btn-success">📋 รายงาน</a>
            <a href="user_dashboard.php" class="btn btn-primary">📝 บรีฟงาน</a>
            <a href="../content/daily/index.php" class="btn btn-success">📅 Daily</a>
        <?php endif; ?>
        <?php if ($role == "admin"): ?>
            <a href="admin_dashboard.php" class="btn btn-primary">⚙️ จัดการ</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

<!-- Stats Dashboard -->
<div class="stats-container">
    <h2 class="stats-header">📊 สรุปงานทั้งหมดในระบบ</h2>
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-label">งานทั้งหมด</div>
            <div class="stat-number"><?php echo $total_system; ?></div>
            <div class="stat-percent">งาน</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">งานรอรับบรีฟ</div>
            <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['pending'] ?? 0, $total_system); ?>%</div>
        </div>
        <div class="stat-card in_progress">
            <div class="stat-label">กำลังดำเนินงาน</div>
            <div class="stat-number"><?php echo $status_counts['in_progress'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['in_progress'] ?? 0, $total_system); ?>%</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-label">งานเสร็จรออนุมัติ</div>
            <div class="stat-number"><?php echo $status_counts['completed'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['completed'] ?? 0, $total_system); ?>%</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-label">อนุมัติแล้ว</div>
            <div class="stat-number"><?php echo $status_counts['approved'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['approved'] ?? 0, $total_system); ?>%</div>
        </div>
        <div class="stat-card need_info">
            <div class="stat-label">ตีกลับ/สอบถาม</div>
            <div class="stat-number"><?php echo $status_counts['need_info'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['need_info'] ?? 0, $total_system); ?>%</div>
        </div>
        <div class="stat-card need_update">
            <div class="stat-label">ขอแก้ไข</div>
            <div class="stat-number"><?php echo $status_counts['need_update'] ?? 0; ?></div>
            <div class="stat-percent"><?php echo percent($status_counts['need_update'] ?? 0, $total_system); ?>%</div>
        </div>
    </div>
</div>

<!-- Calendar -->
<div class="calendar-container">
    <h2 class="calendar-header">📅 ปฏิทินงาน</h2>
    <div id="calendar"></div>
</div>

<!-- Month Selector -->
<div class="month-selector">
    <h3>📈 สถิติรายเดือน</h3>
    <form method="GET" action="index.php">
        <select name="month" id="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
                    <?php echo $months_th[$m]; ?>
                </option>
            <?php endfor; ?>
        </select>
        
        <select name="year" id="year">
            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
                    <?php echo $y + 543; ?>
                </option>
            <?php endfor; ?>
        </select>
        
        <button type="submit" class="btn btn-primary">🔍 แสดงข้อมูล</button>
    </form>
</div>

<!-- Charts and Table -->
<?php if (!empty($brand_counts) || !empty($category_counts)): ?>
<div class="charts-container">
    <!-- Brand Table -->
    <?php if (!empty($brand_counts)): ?>
    <div class="brand-table-container">
        <h3>📊 ตารางจำนวนงานแต่ละแบรนด์<br><?php echo $months_th[$month] . ' ' . ($year + 543); ?></h3>
        <table class="brand-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ลำดับ</th>
                    <th>แบรนด์</th>
                    <th style="width: 100px; text-align: center;">จำนวนงาน</th>
                    <th style="width: 80px; text-align: right;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $index = 1;
                foreach ($brand_counts as $brand => $count): 
                    $percentage = percent($count, $total_brand_month);
                ?>
                <tr>
                    <td style="text-align: center;"><?php echo $index++; ?></td>
                    <td class="brand-name"><?php echo htmlspecialchars($brand); ?></td>
                    <td class="brand-count"><?php echo $count; ?></td>
                    <td class="brand-percent"><?php echo $percentage; ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;">รวมทั้งหมด</td>
                    <td class="brand-count"><?php echo $total_brand_month; ?></td>
                    <td class="brand-percent">100%</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Category Chart -->
    <?php if (!empty($category_counts)): ?>
    <div class="chart-box">
        <h3>🏷️ จำนวนงานแต่ละหมวดหมู่<br><?php echo $months_th[$month] . ' ' . ($year + 543); ?></h3>
        <div class="chart-wrapper">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <p>📭 ไม่มีข้อมูลในเดือน <?php echo $months_th[$month] . ' ' . ($year + 543); ?></p>
</div>
<?php endif; ?>

<script>
// Chart.js Configuration
<?php if (!empty($category_counts)): ?>
const categoryData = {
    labels: <?php echo json_encode(array_keys($category_counts)); ?>,
    datasets: [{
        label: 'จำนวนงาน',
        data: <?php echo json_encode(array_values($category_counts)); ?>,
        backgroundColor: [
            'rgba(124, 58, 237, 0.8)',
            'rgba(167, 139, 250, 0.8)',
            'rgba(52, 211, 153, 0.8)',
            'rgba(251, 191, 36, 0.8)',
            'rgba(248, 113, 113, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(236, 72, 153, 0.8)',
        ],
        borderColor: [
            'rgba(124, 58, 237, 1)',
            'rgba(167, 139, 250, 1)',
            'rgba(52, 211, 153, 1)',
            'rgba(251, 191, 36, 1)',
            'rgba(248, 113, 113, 1)',
            'rgba(59, 130, 246, 1)',
            'rgba(236, 72, 153, 1)',
        ],
        borderWidth: 2
    }]
};

const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: categoryData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    color: '#ffffff',
                    font: {
                        size: 13
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.parsed || 0;
                        let total = <?php echo $total_category_month; ?>;
                        let percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' งาน (' + percent + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Status mapping for Thai text
const statusMap = {
    'pending': 'รอรับบรีฟ',
    'in_progress': 'กำลังดำเนินงาน',
    'completed': 'เสร็จรออนุมัติ',
    'approved': 'อนุมัติแล้ว',
    'need_info': 'ตีกลับ/สอบถาม',
    'need_update': 'ขอแก้ไข'
};

// FullCalendar Configuration
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const canCreateBrief = <?php echo $can_create_brief ? 'true' : 'false'; ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        firstDay: 0,
        height: 'auto',
        contentHeight: 500,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            fetch('fetch_events.php?start=' + fetchInfo.startStr + '&end=' + fetchInfo.endStr)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(err => failureCallback(err));
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                // ไปหน้า detail โดยไม่เปิดแท็บใหม่
                window.location.href = info.event.url;
            }
        },
        dateClick: function(info) {
            if (canCreateBrief) {
                const clickedDate = info.dateStr;
                const today = new Date().toISOString().split('T')[0];
                
                if (clickedDate >= today) {
                    if (confirm('ต้องการสร้างบรีฟงานสำหรับวันที่ ' + clickedDate + ' หรือไม่?')) {
                        window.location.href = 'brief_form.php?date=' + clickedDate;
                    }
                } else {
                    alert('ไม่สามารถสร้างบรีฟย้อนหลังได้');
                }
            }
        },
        eventDidMount: function(info) {
            // ตั้งค่าสี
            if (info.event.extendedProps.color) {
                info.el.style.backgroundColor = info.event.extendedProps.color;
            }
            
            // สร้าง tooltip content
            const props = info.event.extendedProps;
            const status = props.status || 'pending';
            const statusText = statusMap[status] || status;
            
            let tooltipContent = `
                <div class="tooltip-content">
                    <div class="tooltip-title">${info.event.title}</div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">แบรนด์:</span>
                        <span class="tooltip-value">${props.brand || '-'}</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">หมวดหมู่:</span>
                        <span class="tooltip-value">${props.category || '-'}</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">กำหนดส่ง:</span>
                        <span class="tooltip-value">${info.event.startStr || '-'}</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">สถานะ:</span>
                        <span class="tooltip-value"><span class="status-badge status-${status}">${statusText}</span></span>
                    </div>
                </div>
            `;
            
            // สร้าง tooltip ด้วย Tippy.js
            tippy(info.el, {
                content: tooltipContent,
                allowHTML: true,
                theme: 'custom',
                placement: 'top',
                arrow: true,
                interactive: false,
                delay: [200, 0],
                maxWidth: 300
            });
        },
        dayCellDidMount: function(info) {
            if (canCreateBrief) {
                info.el.classList.add('can-create-brief');
                info.el.title = 'คลิกเพื่อสร้างบรีฟงาน';
            }
        }
    });

    calendar.render();
});
</script>

</body>
</html>