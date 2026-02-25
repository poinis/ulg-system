<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];
$user_name = '';
$role = '';

$sql_user = "SELECT name, role FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $user_name, $role);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

$events = array();
$sql_events = "SELECT * FROM sw19_calendar_events 
               WHERE (brief_date BETWEEN ? AND ?) 
                  OR (post_date BETWEEN ? AND ?)
               ORDER BY post_date ASC, post_time ASC";
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

$calendar = array();
$engage_calendar = array();

foreach ($events as $event) {
    $date = !empty($event['post_date']) ? $event['post_date'] : $event['brief_date'];
    $time = !empty($event['post_time']) ? $event['post_time'] : '12:00:00';

    if (!isset($calendar[$date])) {
        $calendar[$date] = array();
    }
    if (!isset($calendar[$date][$time])) {
        $calendar[$date][$time] = array();
    }
    $calendar[$date][$time][] = $event;

    if (!empty($event['post_date'])) {
        $post_date = $event['post_date'];
        if (!isset($engage_calendar[$post_date])) {
            $engage_calendar[$post_date] = array();
        }
        $engage_calendar[$post_date][] = $event;
    }
}

$stats = array(
    'total' => count($events),
    'pending_engage' => 0,
    'posted' => 0,
    'product_knowledge' => 0,
    'promotion' => 0,
    'lifestyle' => 0
);

foreach ($events as $event) {
    if ($event['engage_status'] === 'pending' && !empty($event['engage_date'])) {
        $stats['pending_engage']++;
    }
    if ($event['status'] === 'posted') {
        $stats['posted']++;
    }
    if ($event['category'] === 'Product Knowledge') {
        $stats['product_knowledge']++;
    } elseif ($event['category'] === 'Promotion') {
        $stats['promotion']++;
    } elseif ($event['category'] === 'Lifestyle') {
        $stats['lifestyle']++;
    }
}

$first_day = new DateTime($month_start);
$last_day = new DateTime($month_end);
$days_in_month = (int) $last_day->format('j');
$current_date = clone $first_day;
$day_of_week = (int) $current_date->format('w'); // 0=อาทิตย์, 6=เสาร์

$weeks = array();
$week = array();

// เติมวันว่างก่อนวันแรกของเดือน (สำหรับอาทิตย์เป็นวันแรก)
for ($i = 0; $i < $day_of_week; $i++) {
    $week[] = null;
}

for ($day = 1; $day <= $days_in_month; $day++) {
    $date = date('Y-m-d', strtotime($selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
    $week[] = $date;
    if (count($week) == 7) {
        $weeks[] = $week;
        $week = array();
    }
}

if (!empty($week)) {
    while (count($week) < 7) {
        $week[] = null;
    }
    $weeks[] = $week;
}

function getThaiMonth($month_start) {
    $thai_months = array(
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
        '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
        '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
        '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    );
    $m = date('m', strtotime($month_start));
    $y = date('Y', strtotime($month_start)) + 543;
    return $thai_months[$m] . ' ' . $y;
}

function getTimeSlotLabel($time) {
    $hour = (int) substr($time, 0, 2);
    return sprintf('%02d:00', $hour);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sw19 </title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5"/>
    <link rel="stylesheet" href="calendar_dashboard.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>📅 Sw19 Daily</h1>
        <div>
            <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
            <small><?php echo htmlspecialchars($username); ?></small>
        </div>
    </div>
</div>

<div class="nav">
    <div class="nav-content">
        <a href="calendar_add.php">➕ เพิ่มงาน</a>
        <a href="calendar_engage.php">📊 Engage</a>
        <a href="tiktok_manage.php">🎵 TikTok</a>
        <a href="../index.php" class="secondary">🔙 กลับ</a>
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
            <p>PK</p>
        </div>
        <div class="stat-card promo">
            <h3><?php echo $stats['promotion']; ?></h3>
            <p>Promo</p>
        </div>
        <div class="stat-card lifestyle">
            <h3><?php echo $stats['lifestyle']; ?></h3>
            <p>Life</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['pending_engage']; ?></h3>
            <p>⏰ รอ</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['posted']; ?></h3>
            <p>✅ โพสต์</p>
        </div>
    </div>

    <!-- Month Selector -->
    <div class="month-selector">
        <h2>📅 <?php echo getThaiMonth($month_start); ?></h2>
        <form method="GET">
            <input type="month" name="month" value="<?php echo $selected_month; ?>" onchange="this.form.submit()">
        </form>
    </div>

    <!-- Calendar -->
    <div class="calendar-wrapper">
        <table class="calendar-table">
            <thead>
                <tr>
                    <th rowspan="2" class="folder-header" onclick="window.open('https://drive.google.com/drive/folders/1oqRKE1ctH3Giwf4lsxP8x8P9koROTxWV', '_blank')">
                        📁 Folder
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
                $all_times = array();
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
                        $weekend = ($idx == 0 || $idx == 6); // 0=อาทิตย์, 6=เสาร์
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
                            <th class="time-slot-header"><?php echo getTimeSlotLabel($time); ?></th>
                            <?php foreach ($week as $idx => $date):
                                $weekend = ($idx == 0 || $idx == 6);
                                $evts = isset($calendar[$date][$time]) ? $calendar[$date][$time] : array();
                                $click = $date ? "onclick=\"location.href='calendar_add.php?date=$date&time=$time'\"" : '';
                                ?>
                                <td class="event-cell <?php echo $weekend ? 'weekend' : ''; ?> <?php echo !$date ? 'empty' : ''; ?>" <?php echo $click; ?>>
                                    <?php foreach ($evts as $e):
                                        $cat = $e['category'];
                                        
                                        if ($cat == 'Product Knowledge') {
                                            $badge = 'badge-pk';
                                            $short = 'Product Knowledge';
                                        } elseif ($cat == 'Promotion') {
                                            $badge = 'badge-promo';
                                            $short = 'Promotion';
                                        } else {
                                            $badge = 'badge-lifestyle';
                                            $short = 'lifestyle';
                                        }
                                        
                                        $stat = 'status-' . $e['status'];
                                        $aname = htmlspecialchars($e['assignee']);
                                        
                                        $colors = array(
                                            'จันทร์' => '#dc3545',
                                            'ชลธาร' => '#e91e63',
                                            'มร' => '#4caf50',
                                            'พอลล่า' => '#ff9800',
                                            'ชูศิริ' => '#00bcd4',
                                            'เชอร์' => '#9c27b0'
                                        );
                                        $acolor = isset($colors[$aname]) ? $colors[$aname] : '#6c757d';
                                        ?>
                                        <a href="calendar_detail.php?id=<?php echo $e['id']; ?>" 
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
                    
                    <!-- แถว Engage -->
                    <tr>
                        <th class="engage-slot">View Engage<br>2 Weeks</th>
                        <?php foreach ($week as $idx => $date):
                            $weekend = ($idx == 0 || $idx == 6);
                            $engages = isset($engage_calendar[$date]) ? $engage_calendar[$date] : array();
                            ?>
                            <td class="event-cell engage-slot <?php echo $weekend ? 'weekend' : ''; ?> <?php echo !$date ? 'empty' : ''; ?>">
                                <?php if (!empty($engages)): ?>
                                    <strong style="color:#00838f; display:block; margin-bottom:5px;">
                                        📊 <?php echo count($engages); ?> งาน
                                    </strong>
                                    <?php foreach ($engages as $e):
                                        $cls = ($e['engage_status'] == 'completed') ? 'completed' : '';
                                        ?>
                                        <div class="engage-item <?php echo $cls; ?>" 
                                             onclick="location.href='calendar_detail.php?id=<?php echo $e['id']; ?>'">
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