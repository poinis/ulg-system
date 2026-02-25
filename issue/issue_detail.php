<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];

// ดึงข้อมูลผู้ใช้ รวมถึง id และ role
$sql_user = "SELECT id, name, role FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $user_id, $user_name, $user_role);
$fetch_result = mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

if (!$fetch_result || !$user_id) {
    die("ข้อผิดพลาด: ไม่พบข้อมูลผู้ใช้ในระบบ กรุณาเข้าสู่ระบบใหม่");
}

if (!isset($_GET['id'])) {
    header("location: issue_list.php");
    exit;
}

$issue_id = intval($_GET['id']);

// ดึงข้อมูลปัญหา
$sql_issue = "SELECT i.*, u.name as reporter_name, u.role as reporter_role
              FROM issues i
              LEFT JOIN users u ON i.reporter_id = u.id
              WHERE i.id = ?";
$stmt_issue = mysqli_prepare($conn, $sql_issue);
mysqli_stmt_bind_param($stmt_issue, "i", $issue_id);
mysqli_stmt_execute($stmt_issue);
$result_issue = mysqli_stmt_get_result($stmt_issue);
$issue = mysqli_fetch_assoc($result_issue);
mysqli_stmt_close($stmt_issue);

if (!$issue) {
    die("ไม่พบข้อมูลปัญหานี้");
}

// ตรวจสอบสิทธิ์ในการดู
$is_reporter = ($issue['reporter_id'] == $user_id);
$is_admin = in_array($user_role, ['admin', 'support']);
$can_view = $is_reporter || $is_admin;

if (!$can_view) {
    die("คุณไม่มีสิทธิ์ในการดูข้อมูลนี้");
}

// ดึงหมวดหมู่ปัญหา
$sql_categories = "SELECT id, name_th, icon FROM issue_categories WHERE is_active = 1";
$result_categories = mysqli_query($conn, $sql_categories);
$categories = [];
while ($row = mysqli_fetch_assoc($result_categories)) {
    $categories[$row['id']] = $row;
}

// ดึงประวัติการอัพเดท
$sql_updates = "SELECT iu.*, u.name as updater_name, u.role as updater_role
                FROM issue_updates iu
                LEFT JOIN users u ON iu.updated_by = u.id
                WHERE iu.issue_id = ?
                ORDER BY iu.created_at DESC";
$stmt_updates = mysqli_prepare($conn, $sql_updates);
mysqli_stmt_bind_param($stmt_updates, "i", $issue_id);
mysqli_stmt_execute($stmt_updates);
$result_updates = mysqli_stmt_get_result($stmt_updates);
$updates = [];
while ($row = mysqli_fetch_assoc($result_updates)) {
    $updates[] = $row;
}
mysqli_stmt_close($stmt_updates);

// ประมวลผลฟอร์ม
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_comment') {
        $comment = trim($_POST['comment'] ?? '');
        
        if (empty($comment)) {
            $error = "กรุณาใส่ข้อความคอมเมนต์";
        } else {
            $sql_comment = "INSERT INTO issue_updates (issue_id, updated_by, updated_by_name, update_type, comment)
                           VALUES (?, ?, ?, 'comment', ?)";
            $stmt_comment = mysqli_prepare($conn, $sql_comment);
            mysqli_stmt_bind_param($stmt_comment, "iiss", $issue_id, $user_id, $user_name, $comment);
            
            if (mysqli_stmt_execute($stmt_comment)) {
                $success = "เพิ่มคอมเมนต์สำเร็จ";
                header("refresh:1;url=issue_detail.php?id=$issue_id");
            } else {
                $error = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_comment);
        }
    } 
    elseif ($action == 'update_status' && $is_admin) {
        $new_status = $_POST['status'] ?? '';
        $status_comment = trim($_POST['status_comment'] ?? '');
        
        if (empty($new_status)) {
            $error = "กรุณาเลือกสถานะ";
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                $sql_update_status = "UPDATE issues SET status = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update_status = mysqli_prepare($conn, $sql_update_status);
                mysqli_stmt_bind_param($stmt_update_status, "si", $new_status, $issue_id);
                mysqli_stmt_execute($stmt_update_status);
                mysqli_stmt_close($stmt_update_status);
                
                $sql_history = "INSERT INTO issue_updates (issue_id, updated_by, updated_by_name, update_type, old_value, new_value, comment)
                               VALUES (?, ?, ?, 'status_change', ?, ?, ?)";
                $stmt_history = mysqli_prepare($conn, $sql_history);
                mysqli_stmt_bind_param($stmt_history, "iissss", 
                    $issue_id, $user_id, $user_name, $issue['status'], $new_status, $status_comment);
                mysqli_stmt_execute($stmt_history);
                mysqli_stmt_close($stmt_history);
                
                mysqli_commit($conn);
                $success = "อัพเดทสถานะสำเร็จ";
                header("refresh:1;url=issue_detail.php?id=$issue_id");
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

function getStatusText($status) {
    $status_map = [
        'pending' => 'รอดำเนินการ',
        'in_progress' => 'กำลังดำเนินการ',
        'resolved' => 'แก้ไขแล้ว',
        'closed' => 'ปิดงาน'
    ];
    return $status_map[$status] ?? $status;
}

function getStatusBadge($status) {
    $badge_map = [
        'pending' => 'badge-warning',
        'in_progress' => 'badge-info',
        'resolved' => 'badge-success',
        'closed' => 'badge-secondary'
    ];
    return $badge_map[$status] ?? 'badge-secondary';
}

function getUrgencyText($urgency) {
    $urgency_map = [
        'urgent' => '🔴 เร่งด่วนมาก',
        'medium' => '🟠 ปานกลาง',
        'normal' => '🟢 ทั่วไป'
    ];
    return $urgency_map[$urgency] ?? $urgency;
}

$issue_types = json_decode($issue['issue_types'], true) ?? [];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 รายละเอียดปัญหา #<?php echo htmlspecialchars($issue['issue_number']); ?></title>
    <link rel="stylesheet" href="dashboard_styles.css">
    <link rel="stylesheet" href="issue_detail_styles.css?v=2.0">
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>📋 รายละเอียดปัญหา</h1>
    </div>
    <div class="header-right">
        <a href="../dashboard.php" class="btn btn-primary">🏠 หน้าหลัก</a>
        <a href="report_issue.php" class="btn btn-secondary">🚨 แจ้งปัญหาใหม่</a>
        <a href="issue_list.php" class="btn btn-secondary">📋 รายการปัญหา</a>
        <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
        <a href="logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

<div class="container">
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="issue-detail-container">
        <!-- ส่วนหัวปัญหา -->
        <div class="issue-header">
            <div class="issue-number">เลขที่: <?php echo htmlspecialchars($issue['issue_number']); ?></div>
            <div class="issue-meta">
                <span class="badge <?php echo getStatusBadge($issue['status']); ?>">
                    <?php echo getStatusText($issue['status']); ?>
                </span>
                <span class="urgency-badge">
                    <?php echo getUrgencyText($issue['urgency_level']); ?>
                </span>
            </div>
        </div>

        <!-- ข้อมูลผู้แจ้ง -->
        <div class="info-card">
            <h3>👤 ข้อมูลผู้แจ้ง</h3>
            <div class="info-row">
                <span class="info-label">ชื่อ:</span>
                <span class="info-value"><?php echo htmlspecialchars($issue['reporter_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">สถานที่:</span>
                <span class="info-value"><?php echo htmlspecialchars($issue['reporter_location']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">วันที่แจ้ง:</span>
                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($issue['created_at'])); ?></span>
            </div>
        </div>

        <!-- ประเภทปัญหา -->
        <div class="info-card">
            <h3>🏷️ ประเภทปัญหา</h3>
            <div class="category-tags">
                <?php foreach ($issue_types as $type_id): ?>
                    <?php if ($type_id === 'other'): ?>
                        <span class="category-tag">⚠️ อื่นๆ: <?php echo htmlspecialchars($issue['issue_other_type']); ?></span>
                    <?php elseif (isset($categories[$type_id])): ?>
                        <span class="category-tag">
                            <?php echo $categories[$type_id]['icon']; ?> 
                            <?php echo htmlspecialchars($categories[$type_id]['name_th']); ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- รายละเอียดปัญหา -->
        <div class="info-card">
            <h3>📝 รายละเอียดปัญหา</h3>
            <div class="description-box">
                <?php echo nl2br(htmlspecialchars($issue['issue_description'])); ?>
            </div>
        </div>

        <!-- ข้อเสนอแนะ -->
        <?php if (!empty($issue['suggestions'])): ?>
        <div class="info-card">
            <h3>💡 ข้อเสนอแนะ</h3>
            <div class="description-box">
                <?php echo nl2br(htmlspecialchars($issue['suggestions'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ฟอร์มอัพเดทสถานะ (เฉพาะ Admin) -->
        <?php if ($is_admin): ?>
        <div class="info-card admin-section">
            <h3>🔧 อัพเดทสถานะ (Admin Only)</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-group">
                    <label>สถานะใหม่:</label>
                    <select name="status" class="form-select" required>
                        <option value="pending" <?php echo $issue['status'] == 'pending' ? 'selected' : ''; ?>>
                            รอดำเนินการ
                        </option>
                        <option value="in_progress" <?php echo $issue['status'] == 'in_progress' ? 'selected' : ''; ?>>
                            กำลังดำเนินการ
                        </option>
                        <option value="resolved" <?php echo $issue['status'] == 'resolved' ? 'selected' : ''; ?>>
                            แก้ไขแล้ว
                        </option>
                        <option value="closed" <?php echo $issue['status'] == 'closed' ? 'selected' : ''; ?>>
                            ปิดงาน
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ:</label>
                    <textarea name="status_comment" class="form-textarea" rows="3" 
                              placeholder="ระบุรายละเอียดการอัพเดท (ถ้ามี)"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">💾 บันทึกการเปลี่ยนแปลง</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- ฟอร์มเพิ่มคอมเมนต์ -->
        <div class="info-card">
            <h3>💬 เพิ่มคอมเมนต์</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_comment">
                <div class="form-group">
                    <textarea name="comment" class="form-textarea" rows="4" 
                              placeholder="พิมพ์คอมเมนต์..." required></textarea>
                </div>
                <button type="submit" class="btn btn-success btn-full">💬 ส่งคอมเมนต์</button>
            </form>
        </div>

        <!-- ประวัติการอัพเดท -->
        <div class="info-card">
            <h3>📜 ประวัติการอัพเดท</h3>
            <div class="timeline">
                <?php if (empty($updates)): ?>
                    <p class="no-data">ยังไม่มีการอัพเดท</p>
                <?php else: ?>
                    <?php foreach ($updates as $update): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="updater-name">
                                    <?php echo htmlspecialchars($update['updater_name'] ?? 'ไม่ระบุ'); ?>
                                </span>
                                <span class="update-time">
                                    <?php echo date('d/m/Y H:i', strtotime($update['created_at'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($update['update_type'] == 'status_change'): ?>
                                <div class="update-status-change">
                                    <span class="status-change-label">เปลี่ยนสถานะ:</span>
                                    <span class="badge <?php echo getStatusBadge($update['old_value']); ?>">
                                        <?php echo getStatusText($update['old_value']); ?>
                                    </span>
                                    →
                                    <span class="badge <?php echo getStatusBadge($update['new_value']); ?>">
                                        <?php echo getStatusText($update['new_value']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($update['comment'])): ?>
                                <div class="update-comment">
                                    <?php echo nl2br(htmlspecialchars($update['comment'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>