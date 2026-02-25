<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];

// ดึงข้อมูลผู้ใช้ รวมถึง id
$sql_user = "SELECT id, role, name FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $user_id, $role, $name);
$fetch_result = mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

// ตรวจสอบว่าพบ user หรือไม่
if (!$fetch_result || !$user_id) {
    die("ข้อผิดพลาด: ไม่พบข้อมูลผู้ใช้ในระบบ กรุณาเข้าสู่ระบบใหม่");
}

$is_admin = in_array($role, ['admin', 'support']);

// สถิติทั้งหมด
$sql_total = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN urgency_level = 'urgent' THEN 1 ELSE 0 END) as urgent,
    SUM(CASE WHEN urgency_level = 'medium' THEN 1 ELSE 0 END) as medium,
    SUM(CASE WHEN urgency_level = 'normal' THEN 1 ELSE 0 END) as normal
    FROM issues";

if (!$is_admin) {
    $sql_total .= " WHERE reporter_id = ?";
}

$stmt_total = mysqli_prepare($conn, $sql_total);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_total, "i", $user_id);
}
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$stats = mysqli_fetch_assoc($result_total);
mysqli_stmt_close($stmt_total);

// สถิติเดือนนี้
$current_month = date('Y-m');
$sql_month = "SELECT COUNT(*) as count FROM issues 
              WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
if (!$is_admin) {
    $sql_month .= " AND reporter_id = ?";
}

$stmt_month = mysqli_prepare($conn, $sql_month);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_month, "si", $current_month, $user_id);
} else {
    mysqli_stmt_bind_param($stmt_month, "s", $current_month);
}
mysqli_stmt_execute($stmt_month);
mysqli_stmt_bind_result($stmt_month, $this_month_count);
mysqli_stmt_fetch($stmt_month);
mysqli_stmt_close($stmt_month);

// สถิติตามประเภทปัญหา
$sql_categories = "SELECT 
    c.id, c.name_th, c.name_en, c.icon, c.color
    FROM issue_categories c
    WHERE c.is_active = 1
    ORDER BY c.display_order";

$result_categories = mysqli_query($conn, $sql_categories);
$category_stats = [];

while ($cat = mysqli_fetch_assoc($result_categories)) {
    // นับจำนวนปัญหาแต่ละประเภท
    $sql_count = "SELECT COUNT(*) as issue_count 
                  FROM issues 
                  WHERE JSON_CONTAINS(issue_types, ?)";
    
    if (!$is_admin) {
        $sql_count .= " AND reporter_id = ?";
    }
    
    $stmt_count = mysqli_prepare($conn, $sql_count);
    $cat_id_json = '"' . $cat['id'] . '"';
    
    if (!$is_admin) {
        mysqli_stmt_bind_param($stmt_count, "si", $cat_id_json, $user_id);
    } else {
        mysqli_stmt_bind_param($stmt_count, "s", $cat_id_json);
    }
    
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $issue_count);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
    
    $cat['issue_count'] = $issue_count;
    $category_stats[] = $cat;
}

// ปัญหาล่าสุด
$sql_recent = "SELECT i.*, 
    u_reporter.name as reporter_name,
    u_assigned.name as assigned_name
    FROM issues i
    LEFT JOIN users u_reporter ON i.reporter_id = u_reporter.id
    LEFT JOIN users u_assigned ON i.assigned_to = u_assigned.id
    WHERE 1=1";

if (!$is_admin) {
    $sql_recent .= " AND i.reporter_id = ?";
}

$sql_recent .= " ORDER BY i.created_at DESC LIMIT 10";

$stmt_recent = mysqli_prepare($conn, $sql_recent);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_recent, "i", $user_id);
}
mysqli_stmt_execute($stmt_recent);
$result_recent = mysqli_stmt_get_result($stmt_recent);
$recent_issues = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_issues[] = $row;
}
mysqli_stmt_close($stmt_recent);

// ปัญหาที่ต้องดำเนินการเร่งด่วน
$sql_urgent = "SELECT i.*, 
    u_reporter.name as reporter_name,
    u_assigned.name as assigned_name
    FROM issues i
    LEFT JOIN users u_reporter ON i.reporter_id = u_reporter.id
    LEFT JOIN users u_assigned ON i.assigned_to = u_assigned.id
    WHERE i.urgency_level = 'urgent' 
    AND i.status NOT IN ('resolved', 'closed')";

if (!$is_admin) {
    $sql_urgent .= " AND i.reporter_id = ?";
}

$sql_urgent .= " ORDER BY i.created_at DESC LIMIT 5";

$stmt_urgent = mysqli_prepare($conn, $sql_urgent);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_urgent, "i", $user_id);
}
mysqli_stmt_execute($stmt_urgent);
$result_urgent = mysqli_stmt_get_result($stmt_urgent);
$urgent_issues = [];
while ($row = mysqli_fetch_assoc($result_urgent)) {
    $urgent_issues[] = $row;
}
mysqli_stmt_close($stmt_urgent);

// ข้อมูลสำหรับ Calendar
$sql_calendar = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    GROUP_CONCAT(issue_number SEPARATOR '|') as issue_numbers,
    GROUP_CONCAT(urgency_level SEPARATOR '|') as urgencies
    FROM issues
    WHERE 1=1";

if (!$is_admin) {
    $sql_calendar .= " AND reporter_id = ?";
}

$sql_calendar .= " GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 90";

$stmt_calendar = mysqli_prepare($conn, $sql_calendar);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_calendar, "i", $user_id);
}
mysqli_stmt_execute($stmt_calendar);
$result_calendar = mysqli_stmt_get_result($stmt_calendar);
$calendar_data = [];
while ($row = mysqli_fetch_assoc($result_calendar)) {
    $calendar_data[] = $row;
}
mysqli_stmt_close($stmt_calendar);

// คำนวณเวลาเฉลี่ยในการแก้ไข
$sql_avg_time = "SELECT 
    AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
    FROM issues
    WHERE resolved_at IS NOT NULL";

if (!$is_admin) {
    $sql_avg_time .= " AND reporter_id = ?";
}

$stmt_avg_time = mysqli_prepare($conn, $sql_avg_time);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_avg_time, "i", $user_id);
}
mysqli_stmt_execute($stmt_avg_time);
mysqli_stmt_bind_result($stmt_avg_time, $avg_resolve_hours);
mysqli_stmt_fetch($stmt_avg_time);
mysqli_stmt_close($stmt_avg_time);

$avg_resolve_days = $avg_resolve_hours ? round($avg_resolve_hours / 24, 1) : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Issue Dashboard - Topologie Daily</title>
    <link rel="stylesheet" href="dashboard_styles.css?v=3.1">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>📊 Issue Dashboard</h1>
        <div class="user-info">
            ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong>
            <?php if ($is_admin): ?>
                <span class="admin-badge">👑 Admin/Support</span>
            <?php endif; ?>
            <span class="user-id-info">User ID: <?php echo htmlspecialchars($user_id); ?></span>
        </div>
    </div>
    <div class="header-right">
        <a href="../dashboard.php" class="btn btn-primary">🏠 หน้าหลัก</a>
        <a href="report_issue.php" class="btn btn-secondary">🚨 แจ้งปัญหาใหม่</a>
        <a href="issue_list.php" class="btn btn-secondary">📋 รายการปัญหา</a>
        <a href="logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

<div class="container">
    <!-- Urgent Alert -->
    <?php if (!empty($urgent_issues)): ?>
    <div class="urgent-alert">
        <h3>🚨 ปัญหาเร่งด่วนที่ต้องดำเนินการ (<?php echo count($urgent_issues); ?> รายการ)</h3>
        <p>มีปัญหาระดับเร่งด่วนมากที่ยังไม่ได้รับการแก้ไข กรุณาดำเนินการโดยเร็ว</p>
    </div>
    <?php endif; ?>

    <!-- 1. Main Metrics - ปัญหาทั้งหมด -->
    <div class="dashboard-grid">
        <div class="metric-card total">
            <div class="metric-header">
                <div>
                    <div class="metric-title">ปัญหาทั้งหมด</div>
                    <div class="metric-value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="metric-icon">📋</div>
            </div>
            <div class="metric-description">
                เดือนนี้: <strong><?php echo $this_month_count; ?></strong> รายการ
            </div>
        </div>
        
        <div class="metric-card pending">
            <div class="metric-header">
                <div>
                    <div class="metric-title">รอดำเนินการ</div>
                    <div class="metric-value"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="metric-icon">⏳</div>
            </div>
            <div class="metric-description">
                Pending: <?php echo $stats['pending']; ?> | In Progress: <?php echo $stats['in_progress']; ?>
            </div>
        </div>
        
        <div class="metric-card resolved">
            <div class="metric-header">
                <div>
                    <div class="metric-title">แก้ไขแล้ว</div>
                    <div class="metric-value"><?php echo $stats['resolved']; ?></div>
                </div>
                <div class="metric-icon">✅</div>
            </div>
            <div class="metric-description">
                Resolved: <?php echo $stats['resolved']; ?> | Closed: <?php echo $stats['closed']; ?>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <div>
                    <div class="metric-title">เวลาแก้ไขเฉลี่ย</div>
                    <div class="metric-value">
                        <?php echo $avg_resolve_days; ?><small style="font-size: 18px;">วัน</small>
                    </div>
                </div>
                <div class="metric-icon">⏱️</div>
            </div>
            <div class="metric-description">
                จากปัญหาที่แก้ไขแล้ว <?php echo $stats['resolved']; ?> รายการ
            </div>
        </div>
    </div>

    <!-- 2. Calendar & Recent Issues (เล็กลง) -->
    <div class="two-column-layout">
        <!-- Calendar (ย่อลง) -->
        <div class="calendar-section compact">
            <h3 class="section-title">📅 ปฏิทินวันที่บันทึกปัญหา</h3>
            <div id="issueCalendar"></div>
        </div>
        
        <!-- Recent Issues ทางขวา -->
        <div class="recent-section">
            <!-- Urgent Issues -->
            <?php if (!empty($urgent_issues)): ?>
            <div class="issue-list-box urgent-box">
                <h3 class="section-title">🔴 ปัญหาเร่งด่วน</h3>
                <div class="issue-items">
                    <?php foreach (array_slice($urgent_issues, 0, 3) as $issue): ?>
                    <div class="issue-item-compact" onclick="window.location.href='issue_detail.php?id=<?php echo $issue['id']; ?>'">
                        <div class="issue-number-compact"><?php echo htmlspecialchars($issue['issue_number']); ?></div>
                        <div class="issue-desc-compact"><?php echo htmlspecialchars(mb_substr($issue['issue_description'], 0, 60)); ?>...</div>
                        <div class="issue-meta-compact">
                            <span class="mini-badge status-<?php echo $issue['status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'รอดำเนินการ', 
                                    'in_progress' => 'กำลังดำเนินการ',
                                    'resolved' => 'แก้ไขแล้ว',
                                    'closed' => 'ปิดงาน'
                                ];
                                echo $status_text[$issue['status']] ?? $issue['status'];
                                ?>
                            </span>
                            <span class="issue-date"><?php echo date('d/m/Y', strtotime($issue['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Issues -->
            <div class="issue-list-box">
                <h3 class="section-title">🕒 ปัญหาล่าสุด</h3>
                <?php if (empty($recent_issues)): ?>
                    <p class="no-data">ยังไม่มีรายการปัญหา</p>
                <?php else: ?>
                    <div class="issue-items">
                        <?php foreach (array_slice($recent_issues, 0, 5) as $issue): ?>
                        <div class="issue-item-compact" onclick="window.location.href='issue_detail.php?id=<?php echo $issue['id']; ?>'">
                            <div class="issue-number-compact"><?php echo htmlspecialchars($issue['issue_number']); ?></div>
                            <div class="issue-desc-compact"><?php echo htmlspecialchars(mb_substr($issue['issue_description'], 0, 60)); ?>...</div>
                            <div class="issue-meta-compact">
                                <span class="mini-badge status-<?php echo $issue['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'รอดำเนินการ', 
                                        'in_progress' => 'กำลังดำเนินการ', 
                                        'resolved' => 'แก้ไขแล้ว', 
                                        'closed' => 'ปิดงาน'
                                    ];
                                    echo $status_text[$issue['status']] ?? $issue['status'];
                                    ?>
                                </span>
                                <span class="issue-date"><?php echo date('d/m/Y', strtotime($issue['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (count($recent_issues) > 5): ?>
                    <div class="view-all-btn">
                        <a href="issue_list.php" class="btn btn-secondary">ดูทั้งหมด</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Charts - สถานะ และ ความเร่งด่วน (ครึ่งหน้ากัน) -->
    <div class="two-column-grid">
        <!-- Status Chart -->
        <div class="chart-container">
            <h3 class="chart-title">📊 สถานะปัญหา</h3>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        
        <!-- Urgency Chart -->
        <div class="chart-container">
            <h3 class="chart-title">⚠️ ระดับความเร่งด่วน</h3>
            <div class="chart-wrapper">
                <canvas id="urgencyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 4. Category Stats - สถิติตามประเภทปัญหา -->
    <div class="chart-container full-width">
        <h3 class="chart-title">🏷️ สถิติตามประเภทปัญหา</h3>
        <div class="category-grid">
            <?php foreach ($category_stats as $cat): ?>
            <div class="category-card" onclick="window.location.href='issue_list.php?category=<?php echo $cat['id']; ?>'">
                <div class="category-header">
                    <span class="category-icon"><?php echo $cat['icon']; ?></span>
                    <span class="category-count"><?php echo $cat['issue_count']; ?></span>
                </div>
                <div class="category-name"><?php echo htmlspecialchars($cat['name_th']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['รอดำเนินการ', 'กำลังดำเนินการ', 'แก้ไขแล้ว', 'ปิดงาน', 'ปฏิเสธ'],
        datasets: [{
            data: [
                <?php echo $stats['pending']; ?>,
                <?php echo $stats['in_progress']; ?>,
                <?php echo $stats['resolved']; ?>,
                <?php echo $stats['closed']; ?>,
                <?php echo $stats['rejected']; ?>
            ],
            backgroundColor: [
                '#c0392b',
                '#e67e22',
                '#27ae60',
                '#7f8c8d',
                '#922b21'
            ],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    font: { size: 13, weight: '600' }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = <?php echo $stats['total']; ?>;
                        const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' (' + percent + '%)';
                    }
                }
            }
        }
    }
});

// Urgency Chart
const urgencyCtx = document.getElementById('urgencyChart').getContext('2d');
new Chart(urgencyCtx, {
    type: 'bar',
    data: {
        labels: ['เร่งด่วนมาก', 'ปานกลาง', 'ทั่วไป'],
        datasets: [{
            label: 'จำนวนปัญหา',
            data: [
                <?php echo $stats['urgent']; ?>,
                <?php echo $stats['medium']; ?>,
                <?php echo $stats['normal']; ?>
            ],
            backgroundColor: [
                'rgba(192, 57, 43, 0.8)',
                'rgba(230, 126, 34, 0.8)',
                'rgba(39, 174, 96, 0.8)'
            ],
            borderColor: [
                'rgba(192, 57, 43, 1)',
                'rgba(230, 126, 34, 1)',
                'rgba(39, 174, 96, 1)'
            ],
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: { weight: '600' }
                }
            },
            x: {
                ticks: {
                    font: { weight: '600' }
                }
            }
        }
    }
});

// Calendar Configuration
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('issueCalendar');
    
    const calendarData = <?php echo json_encode($calendar_data); ?>;
    const events = [];
    
    calendarData.forEach(item => {
        const issueNumbers = item.issue_numbers.split('|');
        const urgencies = item.urgencies.split('|');
        
        let color = '#27ae60';
        if (urgencies.includes('urgent')) {
            color = '#c0392b';
        } else if (urgencies.includes('medium')) {
            color = '#e67e22';
        }
        
        events.push({
            title: item.count + ' ปัญหา',
            start: item.date,
            backgroundColor: color,
            borderColor: color,
            extendedProps: {
                count: item.count,
                issues: issueNumbers,
                date: item.date
            }
        });
    });
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        firstDay: 0,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        events: events,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const date = info.event.extendedProps.date;
            window.location.href = 'issue_list.php?date=' + date;
        },
        eventDidMount: function(info) {
            info.el.style.cursor = 'pointer';
        },
        dayCellDidMount: function(info) {
            const dateStr = info.date.toISOString().split('T')[0];
            const hasIssue = events.some(e => e.start === dateStr);
            if (hasIssue) {
                info.el.classList.add('has-issues');
            }
        }
    });
    
    calendar.render();
});
</script>

</body>
</html>