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

$issue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงข้อมูลปัญหา
$sql = "SELECT i.*, 
               u1.name as reporter_name, u1.email as reporter_email,
               u2.name as assignee_name, u2.email as assignee_email,
               c.name as category_name, c.color as category_color, c.icon as category_icon,
               d.name as department_name
        FROM issues i
        LEFT JOIN users u1 ON i.reported_by = u1.id
        LEFT JOIN users u2 ON i.assigned_to = u2.id
        LEFT JOIN issue_categories c ON i.category_id = c.id
        LEFT JOIN departments d ON i.department_id = d.id
        WHERE i.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $issue_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$issue = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$issue) {
    header("location: issues_dashboard.php");
    exit;
}

// ดึงรูปภาพ
$images = [];
$sql = "SELECT * FROM issue_images WHERE issue_id = ? ORDER BY uploaded_at";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $issue_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $images[] = $row;
}
mysqli_stmt_close($stmt);

// ดึงประวัติการอัพเดท
$updates = [];
$sql = "SELECT u.*, us.name as user_name 
        FROM issue_updates u
        LEFT JOIN users us ON u.user_id = us.id
        WHERE u.issue_id = ?
        ORDER BY u.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $issue_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $updates[] = $row;
}
mysqli_stmt_close($stmt);

// จัดการการอัพเดทสถานะและความคิดเห็น
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // สร้าง URL ของปัญหา
        $issue_url = "https://www.weedjai.com/hotel/issues/issue_detail.php?id={$issue_id}";
        
        if ($action == 'update_status') {
            $new_status = cleanInput($_POST['status']);
            $comment = cleanInput($_POST['comment']);
            
            $sql = "UPDATE issues SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $new_status, $issue_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // บันทึกประวัติ
                $sql_log = "INSERT INTO issue_updates (issue_id, user_id, old_status, new_status, comment) 
                           VALUES (?, ?, ?, ?, ?)";
                $stmt_log = mysqli_prepare($conn, $sql_log);
                mysqli_stmt_bind_param($stmt_log, "iisss", 
                    $issue_id, $user_id, $issue['status'], $new_status, $comment);
                mysqli_stmt_execute($stmt_log);
                mysqli_stmt_close($stmt_log);
                
                // แจ้งเตือน
                $status_thai = [
                    'pending' => 'รอดำเนินการ',
                    'assigned' => 'มอบหมายแล้ว',
                    'in_progress' => 'กำลังดำเนินการ',
                    'on_hold' => 'พักงาน',
                    'completed' => 'เสร็จสิ้น',
                    'cancelled' => 'ยกเลิก'
                ];
                
                $pumble_msg = "🔄 อัพเดทสถานะ: {$issue['title']}\n";
                $pumble_msg .= "จาก: {$status_thai[$issue['status']]} → {$status_thai[$new_status]}\n";
                $pumble_msg .= "โดย: {$name}\n";
                if (!empty($comment)) {
                    $pumble_msg .= "ความคิดเห็น: {$comment}\n";
                }
                $pumble_msg .= "🔗 ดูรายละเอียด: {$issue_url}";
                sendPumbleNotification($pumble_msg);
                
                // แจ้งเตือนผู้เกี่ยวข้อง
                if (!empty($issue['assigned_to'])) {
                    createNotification($conn, $issue['assigned_to'], $issue_id, 
                        "อัพเดทสถานะงาน", "งาน '{$issue['title']}' ถูกอัพเดทเป็น {$status_thai[$new_status]}", 
                        "issue_updated");
                }
                
                header("location: issue_detail.php?id={$issue_id}");
                exit;
            }
            mysqli_stmt_close($stmt);
        }
        
        elseif ($action == 'add_comment') {
            $comment = cleanInput($_POST['comment']);
            
            if (!empty($comment)) {
                $sql = "INSERT INTO issue_updates (issue_id, user_id, comment) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iis", $issue_id, $user_id, $comment);
                
                if (mysqli_stmt_execute($stmt)) {
                    // แจ้งเตือน
                    $pumble_msg = "💬 ความคิดเห็นใหม่: {$issue['title']}\n";
                    $pumble_msg .= "โดย: {$name}\n";
                    $pumble_msg .= "ความคิดเห็น: {$comment}\n";
                    $pumble_msg .= "🔗 ดูรายละเอียด: {$issue_url}";
                    sendPumbleNotification($pumble_msg);
                    
                    if (!empty($issue['assigned_to']) && $issue['assigned_to'] != $user_id) {
                        createNotification($conn, $issue['assigned_to'], $issue_id, 
                            "ความคิดเห็นใหม่", "{$name} แสดงความคิดเห็นในงาน '{$issue['title']}'", 
                            "comment_added");
                    }
                    
                    header("location: issue_detail.php?id={$issue_id}");
                    exit;
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดปัญหา - <?php echo htmlspecialchars($issue['title']); ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .issue-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .issue-title {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 15px;
        }
        
        .badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .badge-urgent { background: #dc3545; color: white; }
        .badge-high { background: #ff9800; color: white; }
        .badge-medium { background: #ffc107; color: #333; }
        .badge-low { background: #28a745; color: white; }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            color: white;
        }
        
        /* แสดงรูปภาพ */
        .issue-images {
            margin: 20px 0;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .image-item:hover {
            transform: scale(1.05);
        }
        
        .image-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        /* Modal สำหรับดูรูปใหญ่ */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
        }
        
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .info-grid {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .info-item {
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .description {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.8;
            margin: 20px 0;
        }
        
        .timeline {
            margin-top: 20px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 30px;
            border-left: 2px solid #e9ecef;
        }
        
        .timeline-item:last-child {
            border-left-color: transparent;
        }
        
        .timeline-marker {
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e9ecef;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .timeline-user {
            font-weight: 600;
            color: #667eea;
        }
        
        .timeline-time {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .timeline-text {
            color: #333;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #333;
            text-decoration: none;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .images-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📋 รายละเอียดปัญหา</h1>
            <a href="issues_dashboard.php" class="back-link">← กลับรายการ</a>
        </div>
    </div>
    
    <div class="container">
        <!-- คอลัมน์ซ้าย: ข้อมูลปัญหา -->
        <div>
            <div class="card">
                <div class="issue-header">
                    <h1 class="issue-title"><?php echo htmlspecialchars($issue['title']); ?></h1>
                    <div class="badges">
                        <span class="badge badge-<?php echo $issue['priority']; ?>">
                            <?php 
                            $priority_thai = ['urgent' => '🚨 เร่งด่วน', 'high' => '⚠️ สูง', 
                                            'medium' => '📌 ปานกลาง', 'low' => '🔽 ต่ำ'];
                            echo $priority_thai[$issue['priority']];
                            ?>
                        </span>
                        <span class="badge badge-<?php echo $issue['status']; ?>">
                            <?php 
                            $status_thai = ['pending' => 'รอดำเนินการ', 'assigned' => 'มอบหมายแล้ว',
                                          'in_progress' => 'กำลังดำเนินการ', 'on_hold' => 'พักงาน',
                                          'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิก'];
                            echo $status_thai[$issue['status']];
                            ?>
                        </span>
                        <span class="category-badge" style="background: <?php echo $issue['category_color']; ?>">
                            <?php echo $issue['category_icon'] . ' ' . htmlspecialchars($issue['category_name']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($issue['description'])): ?>
                    <div class="description">
                        <strong>รายละเอียด:</strong><br><br>
                        <?php echo nl2br(htmlspecialchars($issue['description'])); ?>
                    </div>
                <?php endif; ?>
                
                <!-- แสดงรูปภาพ -->
                <?php if (!empty($images)): ?>
                    <div class="issue-images">
                        <strong style="font-size: 1.1em; color: #667eea;">📸 รูปภาพประกอบ (<?php echo count($images); ?> รูป)</strong>
                        <div class="images-grid">
                            <?php foreach ($images as $img): ?>
                                <div class="image-item" onclick="openModal('<?php echo htmlspecialchars($img['image_path']); ?>')">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="รูปภาพปัญหา">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">📍 สถานที่:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($issue['location']); ?>
                            <?php if (!empty($issue['room_number'])): ?>
                                | ห้อง <?php echo htmlspecialchars($issue['room_number']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">👤 แจ้งโดย:</span>
                        <span class="info-value"><?php echo htmlspecialchars($issue['reporter_name']); ?></span>
                    </div>
                    
                    <?php if (!empty($issue['assignee_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">🔧 ผู้รับผิดชอบ:</span>
                            <span class="info-value"><?php echo htmlspecialchars($issue['assignee_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($issue['department_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">🏢 แผนก:</span>
                            <span class="info-value"><?php echo htmlspecialchars($issue['department_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <span class="info-label">📅 วันที่แจ้ง:</span>
                        <span class="info-value"><?php echo getThaiDate($issue['reported_date']); ?></span>
                    </div>
                    
                    <?php if (!empty($issue['due_date'])): ?>
                        <div class="info-item">
                            <span class="info-label">⏰ กำหนดเสร็จ:</span>
                            <span class="info-value"><?php echo getThaiDate($issue['due_date'], 'short'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($issue['status'] == 'completed' && !empty($issue['completed_date'])): ?>
                        <div class="info-item">
                            <span class="info-label">✅ เสร็จสิ้นเมื่อ:</span>
                            <span class="info-value"><?php echo getThaiDate($issue['completed_date']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="issue_create.php?id=<?php echo $issue_id; ?>" class="btn btn-edit">✏️ แก้ไข</a>
                </div>
            </div>
            
            <!-- ประวัติการอัพเดท -->
            <div class="card" style="margin-top: 20px;">
                <h2>📝 ประวัติการอัพเดท</h2>
                <div class="timeline">
                    <?php if (empty($updates)): ?>
                        <p style="color: #6c757d; text-align: center; padding: 20px;">ยังไม่มีประวัติการอัพเดท</p>
                    <?php else: ?>
                        <?php foreach ($updates as $update): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <span class="timeline-user"><?php echo htmlspecialchars($update['user_name']); ?></span>
                                        <span class="timeline-time"><?php echo timeAgo($update['created_at']); ?></span>
                                    </div>
                                    <div class="timeline-text">
                                        <?php if (!empty($update['old_status']) && !empty($update['new_status'])): ?>
                                            <strong>เปลี่ยนสถานะ:</strong> <?php echo $update['old_status']; ?> → <?php echo $update['new_status']; ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($update['comment'])): ?>
                                            <?php echo nl2br(htmlspecialchars($update['comment'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- คอลัมน์ขวา: ฟอร์มอัพเดท -->
        <div>
            <!-- อัพเดทสถานะ -->
            <div class="card">
                <h2>🔄 อัพเดทสถานะ</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-group">
                        <label>สถานะใหม่</label>
                        <select name="status">
                            <option value="pending" <?php echo $issue['status'] == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                            <option value="assigned" <?php echo $issue['status'] == 'assigned' ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                            <option value="in_progress" <?php echo $issue['status'] == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="on_hold" <?php echo $issue['status'] == 'on_hold' ? 'selected' : ''; ?>>พักงาน</option>
                            <option value="completed" <?php echo $issue['status'] == 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                            <option value="cancelled" <?php echo $issue['status'] == 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea name="comment" rows="3" placeholder="เพิ่มหมายเหตุ (ถ้ามี)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">อัพเดทสถานะ</button>
                </form>
            </div>
            
            <!-- เพิ่มความคิดเห็น -->
            <div class="card" style="margin-top: 20px;">
                <h2>💬 เพิ่มความคิดเห็น</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_comment">
                    <div class="form-group">
                        <textarea name="comment" rows="4" placeholder="พิมพ์ความคิดเห็น..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success" style="width: 100%;">ส่งความคิดเห็น</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal สำหรับดูรูปใหญ่ -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <span class="modal-close">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script>
        function openModal(imagePath) {
            document.getElementById('imageModal').style.display = 'block';
            document.getElementById('modalImage').src = imagePath;
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // ปิด modal เมื่อกด ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>