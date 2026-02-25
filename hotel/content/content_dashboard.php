<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_name = $_SESSION['name'];
$role = $_SESSION['role'];

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// ดึงข้อมูลคอนเทนต์
$events = [];
$sql_events = "SELECT c.*, p.page_name
               FROM content_calendar c
               LEFT JOIN facebook_pages p ON c.page_id = p.id
               WHERE (c.brief_date BETWEEN ? AND ?) 
                  OR (c.post_date BETWEEN ? AND ?)
               ORDER BY c.post_date ASC, c.post_time ASC";
$stmt = mysqli_prepare($conn, $sql_events);
mysqli_stmt_bind_param($stmt, "ssss", $month_start, $month_end, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
}
mysqli_stmt_close($stmt);

// จัดเตรียมข้อมูลปฏิทิน
$calendar = [];
$engage_calendar = [];

foreach ($events as $event) {
    $date = !empty($event['post_date']) ? $event['post_date'] : $event['brief_date'];
    
    // ข้ามถ้าไม่มีเวลา
    if (empty($event['post_time'])) {
        continue;
    }
    
    $time = $event['post_time'];

    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }
    if (!isset($calendar[$date][$time])) {
        $calendar[$date][$time] = [];
    }
    $calendar[$date][$time][] = $event;
    }


// สถิติ
$stats = [
    'total' => count($events),
    'pending_engage' => 0,
    'posted' => 0,
    'product_knowledge' => 0,
    'promotion' => 0,
    'lifestyle' => 0
];

foreach ($events as $event) {
    if ($event['status'] === 'posted') {
        $stats['posted']++;
        
        // ตรวจสอบว่ามี engagement record หรือยัง
        $sql = "SELECT COUNT(*) as cnt FROM facebook_engagement WHERE content_id = ? AND status = 'pending'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $event['id']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ($row['cnt'] > 0) {
            $stats['pending_engage']++;
        }
        mysqli_stmt_close($stmt);
    }
    
    if ($event['category'] === 'Product Knowledge') {
        $stats['product_knowledge']++;
    } elseif ($event['category'] === 'Promotion') {
        $stats['promotion']++;
    } elseif ($event['category'] === 'Lifestyle') {
        $stats['lifestyle']++;
    }
}

// สร้างปฏิทิน
$first_day = new DateTime($month_start);
$last_day = new DateTime($month_end);
$days_in_month = (int) $last_day->format('j');
$current_date = clone $first_day;
$day_of_week = (int) $current_date->format('w');

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

function getTimeSlotLabel($time) {
    $hour = (int) substr($time, 0, 2);
    return sprintf('%02d:00', $hour);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Calendar - Facebook</title>
    <link rel="stylesheet" type="text/css" href="calendar_dashboard.css?v=1">
    <style>
        .badge-event { background: #2196f3; color: white; }
        .badge-news { background: #ff5722; color: white; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>📱 Content Calendar - Facebook</h1>
        <div>
            <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
            <small><?php echo htmlspecialchars($username); ?></small>
        </div>
    </div>
</div>

<div class="nav">
    <div class="nav-content">
        <a href="content_add.php">➕ เพิ่มงาน</a>
        <a href="facebook_engage.php">📊 Facebook Engagement</a>
        <a href="content_reports.php">📈 รายงาน</a>
        <a href="../dashboard.php" class="secondary">🏠 หน้าหลัก</a>
    </div>
</div>

<div class="container">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>📋 ทั้งหมด</p>
        </div>
        <div class="stat-card pk">
            <h3><?php echo $stats['product_knowledge']; ?></h3>
            <p>Product Knowledge</p>
        </div>
        <div class="stat-card promo">
            <h3><?php echo $stats['promotion']; ?></h3>
            <p>Promotion</p>
        </div>
        <div class="stat-card lifestyle">
            <h3><?php echo $stats['lifestyle']; ?></h3>
            <p>Lifestyle</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['pending_engage']; ?></h3>
            <p>⏰ รอเช็ค Engage</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['posted']; ?></h3>
            <p>✅ โพสต์แล้ว</p>
        </div>
    </div>

    <!-- Month Selector -->
    <div class="month-selector">
        <h2>📅 <?php echo getThaiDate($month_start, 'short'); ?></h2>
        <form method="GET">
            <input type="month" name="month" value="<?php echo $selected_month; ?>" onchange="this.form.submit()">
        </form>
    </div>

    <!-- Calendar -->
    <div class="calendar-wrapper">
        <table class="calendar-table">
            <colgroup>
                <col style="width: 100px;">
                <?php for($i=0; $i<7; $i++): ?>
                    <col style="width: 14.28%;">
                <?php endfor; ?>
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="folder-header" onclick="window.open('https://drive.google.com/drive/', '_blank')">
                        📁 Drive
                    </th>
                    <th class="header-day">อาทิตย์</th>
                    <th class="header-day">จันทร์</th>
                    <th class="header-day">อังคาร</th>
                    <th class="header-day">พุธ</th>
                    <th class="header-day">พฤหัสบดี</th>
                    <th class="header-day">ศุกร์</th>
                    <th class="header-day">เสาร์</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $all_times = [];
                foreach ($calendar as $date => $times) {
                    foreach ($times as $time => $evt) {
                        if (!in_array($time, $all_times)) {
                            $all_times[] = $time;
                        }
                    }
                }
                sort($all_times);

                foreach ($weeks as $week):
                    // แถววันที่
                    echo '<tr>';
                    echo '<th class="time-slot-header">วันที่</th>';
                    foreach ($week as $idx => $date):
                        $today = ($date === date('Y-m-d'));
                        $weekend = ($idx == 0 || $idx == 6);
                        ?>
                        <td class="event-cell <?php echo $weekend ? 'weekend' : ''; ?>">
                            <?php if ($date): ?>
                                <div class="date-header <?php echo $today ? 'today' : ''; ?>">
                                    <?php echo date('j', strtotime($date)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach;
                    echo '</tr>';

                    // แถวเวลา
                    foreach ($all_times as $time):
                        ?>
                        <tr>
                            <th class="time-slot-header"></th>
                            <?php foreach ($week as $idx => $date):
                                $weekend = ($idx == 0 || $idx == 6);
                                $evts = isset($calendar[$date][$time]) ? $calendar[$date][$time] : [];
                                $click = $date ? "onclick=\"location.href='content_add.php?date=$date&time=$time'\"" : '';
                                ?>
                                <td class="event-cell <?php echo $weekend ? 'weekend' : ''; ?> <?php echo !$date ? 'empty' : ''; ?>" <?php echo $click; ?>>
                                    <?php foreach ($evts as $e):
                                        $cat = $e['category'];
                                        
                                        if ($cat == 'Product Knowledge') {
                                            $badge = 'badge-pk';
                                            $short = 'PK';
                                        } elseif ($cat == 'Promotion') {
                                            $badge = 'badge-promo';
                                            $short = 'Promo';
                                        } elseif ($cat == 'Lifestyle') {
                                            $badge = 'badge-lifestyle';
                                            $short = 'Life';
                                        } elseif ($cat == 'Event') {
                                            $badge = 'badge-event';
                                            $short = 'Event';
                                        } else {
                                            $badge = 'badge-news';
                                            $short = 'News';
                                        }
                                        
                                        $stat = 'status-' . $e['status'];
                                        $aname = htmlspecialchars($e['assignee']);
                                        
                                        $colors = [
                                            'จันทร์' => '#dc3545',
                                            'ชลธาร' => '#e91e63',
                                            'มร' => '#4caf50',
                                            'พอลล่า' => '#ff9800',
                                            'ชูศิริ' => '#00bcd4',
                                            'เชอร์' => '#9c27b0'
                                        ];
                                        $acolor = isset($colors[$aname]) ? $colors[$aname] : '#6c757d';
                                        ?>
                                        <a href="content_detail.php?id=<?php echo $e['id']; ?>" 
                                           class="event-item <?php echo $stat; ?>" 
                                           onclick="event.stopPropagation();">
                                            <span class="event-category-badge <?php echo $badge; ?>">
                                                <?php echo $short; ?>
                                            </span>
                                            <div class="event-title">
                                                <?php echo htmlspecialchars(mb_substr($e['job_title'], 0, 20)); ?>
                                            </div>
                                            <span class="event-assignee" style="background:<?php echo $acolor; ?>">
                                                <?php echo $aname; ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- แถว Facebook Engagement (2 Weeks Check) -->
                    <tr>
                        <th class="engage-slot">Facebook<br>Engagement<br>(2 Weeks)</th>
                        <?php foreach ($week as $idx => $date):
                            $weekend = ($idx == 0 || $idx == 6);
                            $engages = isset($engage_calendar[$date]) ? $engage_calendar[$date] : [];
                            ?>
                            <td class="event-cell engage-slot <?php echo $weekend ? 'weekend' : ''; ?> <?php echo !$date ? 'empty' : ''; ?>">
                                <?php if (!empty($engages)): ?>
                                    <strong style="color:#00838f; display:block; margin-bottom:5px;">
                                        📊 <?php echo count($engages); ?> งาน
                                    </strong>
                                    <?php foreach ($engages as $e):
                                        // ตรวจสอบสถานะ engagement
                                        $sql = "SELECT status FROM facebook_engagement WHERE content_id = ? ORDER BY check_date DESC LIMIT 1";
                                        $stmt = mysqli_prepare($conn, $sql);
                                        mysqli_stmt_bind_param($stmt, "i", $e['id']);
                                        mysqli_stmt_execute($stmt);
                                        $res = mysqli_stmt_get_result($stmt);
                                        $engage_status = 'pending';
                                        if ($row = mysqli_fetch_assoc($res)) {
                                            $engage_status = $row['status'];
                                        }
                                        mysqli_stmt_close($stmt);
                                        
                                        $cls = ($engage_status == 'completed') ? 'completed' : '';
                                        ?>
                                        <div class="engage-item <?php echo $cls; ?>" 
                                             onclick="location.href='content_detail.php?id=<?php echo $e['id']; ?>'">
                                            <?php echo htmlspecialchars(mb_substr($e['job_title'], 0, 18)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>