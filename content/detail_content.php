<?php
require_once "config.php";
require_once "pumble_notification.php";
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ไม่พบข้อมูลงาน");
}

$id = intval($_GET['id']);
$username = $_SESSION['username'] ?? '';
$role = '';
$user_name = '';

if ($username) {
    $sql_role = "SELECT role, name FROM users WHERE username = ?";
    $stmt_role = mysqli_prepare($conn, $sql_role);
    mysqli_stmt_bind_param($stmt_role, "s", $username);
    mysqli_stmt_execute($stmt_role);
    mysqli_stmt_bind_result($stmt_role, $role, $user_name);
    mysqli_stmt_fetch($stmt_role);
    mysqli_stmt_close($stmt_role);
}

// จัดการการอนุมัติงาน
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $brief_id = intval($_POST['id']);
    
    // ดึงข้อมูลบรีฟก่อนอัปเดต
    $sql_old = "SELECT job_title, status FROM content_brief WHERE id = ?";
    $stmt_old = mysqli_prepare($conn, $sql_old);
    mysqli_stmt_bind_param($stmt_old, "i", $brief_id);
    mysqli_stmt_execute($stmt_old);
    mysqli_stmt_bind_result($stmt_old, $job_title, $old_status);
    mysqli_stmt_fetch($stmt_old);
    mysqli_stmt_close($stmt_old);
    
    $update_success = false;
    $new_status = '';
    
    if ($action === 'approve' && in_array($role, ['admin', 'approve'])) {
        $new_status = 'approved';
        $sql_approve = "UPDATE content_brief SET status = 'approved', updated_at = NOW() WHERE id = ?";
        $stmt_approve = mysqli_prepare($conn, $sql_approve);
        mysqli_stmt_bind_param($stmt_approve, "i", $brief_id);
        $update_success = mysqli_stmt_execute($stmt_approve);
        mysqli_stmt_close($stmt_approve);
        
    } elseif ($action === 'need_update' && in_array($role, ['admin', 'approve'])) {
        // ขอให้แก้ไขงาน
        $new_status = 'need_update';
        $update_reason = isset($_POST['update_reason']) ? trim($_POST['update_reason']) : '';
        
        if (!empty($update_reason)) {
            $sql_update = "UPDATE content_brief 
                          SET status = 'need_update', update_reason = ?, update_name = ?, updated_at = NOW() 
                          WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssi", $update_reason, $user_name, $brief_id);
            $update_success = mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        }
        
    } elseif ($action === 'completed' && $role === 'admin') {
        $new_status = 'completed';
        $completion_note = isset($_POST['completion_note']) ? trim($_POST['completion_note']) : '';
        
        // จัดการไฟล์แนบ
        $uploaded_files = array();
        if (isset($_FILES['completion_files']) && !empty($_FILES['completion_files']['name'][0])) {
            $upload_dir = "uploads/completed/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_count = count($_FILES['completion_files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['completion_files']['error'][$i] == 0) {
                    $filename = basename($_FILES['completion_files']['name'][$i]);
                    $target_file = $upload_dir . time() . "_" . $i . "_" . $filename;
                    if (move_uploaded_file($_FILES['completion_files']['tmp_name'][$i], $target_file)) {
                        $uploaded_files[] = $target_file;
                    }
                }
            }
        }
        
        $files_json = !empty($uploaded_files) ? json_encode($uploaded_files) : NULL;
        
        $sql = "UPDATE content_brief 
                SET status = 'completed', complete_name = ?, completion_note = ?, completion_files = ?, 
                    update_reason = NULL, updated_at = NOW() 
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $user_name, $completion_note, $files_json, $brief_id);
        $update_success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // ส่งการแจ้งเตือนไปยัง Pumble
    if ($update_success && !empty($new_status)) {
        try {
            $pumble = new PumbleNotification();
            $pumble->notifyStatusChange($brief_id, $job_title, $old_status, $new_status, $user_name);
        } catch (Exception $e) {
            error_log("Pumble notification error: " . $e->getMessage());
        }
        
        // Redirect กลับมาหน้าเดิมพร้อมข้อความสำเร็จ
        header("Location: detail_content.php?id=$brief_id&success=1");
        exit;
    } elseif (!$update_success) {
        $error_message = "เกิดข้อผิดพลาดในการอัปเดตงาน";
    }
}

$sql = "SELECT * FROM content_brief WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$content = mysqli_fetch_assoc($result);

if (!$content) {
    die("ไม่พบข้อมูลงานนี้");
}

// แปลง JSON ของไฟล์ที่ส่งงาน
$completion_files = array();
if (!empty($content['completion_files'])) {
    $completion_files = json_decode($content['completion_files'], true);
    if (!is_array($completion_files)) {
        $completion_files = array();
    }
}

// ฟังก์ชันแปลง URL ในข้อความให้เป็นลิงก์ที่คลิกได้
function makeLinksClickable($text) {
    $pattern = '/(https?:\/\/[^\s]+)|(www\.[^\s]+)/i';
    
    $result = preg_replace_callback($pattern, function($matches) {
        $url = $matches[0];
        if (strpos($url, 'www.') === 0) {
            $href = 'http://' . $url;
        } else {
            $href = $url;
        }
        return '<a href="' . htmlspecialchars($href) . '" target="_blank" style="color: #3498db; text-decoration: underline;">' . htmlspecialchars($url) . '</a>';
    }, $text);
    
    return $result;
}

function displayFormattedText($text) {
    // แปลง HTML entities ก่อน
    $text = htmlspecialchars($text);
    // แปลง \r\n ที่เป็น string literal ให้เป็น newline จริง
    $text = str_replace(['\r\n', '\n', '\r'], "\n", $text);
    // แปลง URL ให้เป็นลิงก์
    $text = makeLinksClickable($text);
    // แปลง line breaks เป็น <br>
    $text = nl2br($text);
    return $text;
}




?>

<!DOCTYPE html>
<html>
<head>
    <title>รายละเอียดงาน: <?php echo htmlspecialchars($content['job_title'] ?? ''); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body {
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 15px;
        background: #f9f9f9;
        color: #333;
      }
      h1 {
        font-size: 1.8em;
        margin-bottom: 15px;
        color: #3a3a3a;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .ad-star {
        font-size: 1.2em;
        color: #ffc107;
        text-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        animation: pulse 2s ease-in-out infinite;
      }
      @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
      }
      
      /* Success/Error Messages */
      .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: 600;
      }
      .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
      }
      .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
      }
      
      /* Section Styles */
      .section {
        background: white;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      }
      .section-title {
        font-size: 1.3em;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
        display: flex;
        align-items: center;
      }
      .section-title .icon {
        margin-right: 8px;
        font-size: 1.2em;
      }
      
      .label {
        font-weight: 700;
        margin-top: 12px;
        color: #555;
        display: block;
      }
      .value {
        margin-left: 10px;
        font-size: 1.05em;
        margin-top: 4px;
        color: #2c3e50;
        line-height: 1.6;
      }
      /* ปรับแต่งการแสดงผลข้อความที่มีการจัดรูปแบบ */
      .formatted-text {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #3498db;
        margin-top: 8px;
        white-space: pre-wrap;
        word-wrap: break-word;
        line-height: 1.8;
      }
      .attachment {
        margin-top: 15px;
      }
      .attachment img {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        margin-top: 10px;
      }
      
      /* AD Check Badge */
      .ad-check-badge {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        color: #856404;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.95em;
        border: 2px solid #ffc107;
        box-shadow: 0 2px 6px rgba(255, 193, 7, 0.3);
      }
      .ad-check-badge .star {
        font-size: 1.2em;
        margin-right: 5px;
        animation: pulse 2s ease-in-out infinite;
      }
      
      /* Completion Section */
      .completion-section {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-left: 5px solid #4caf50;
      }
      .completion-note {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #a5d6a7;
        white-space: pre-wrap;
        line-height: 1.8;
      }
      
      /* Files Grid */
      .files-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
      }
      .file-item {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
      }
      .file-item:hover {
        border-color: #4caf50;
        box-shadow: 0 4px 12px rgba(76,175,80,0.2);
        transform: translateY(-2px);
      }
      .file-item img {
        max-width: 100%;
        max-height: 150px;
        border-radius: 6px;
        margin-bottom: 10px;
      }
      .file-icon {
        font-size: 3em;
        margin-bottom: 10px;
        color: #4caf50;
      }
      .file-name {
        font-size: 0.9em;
        color: #666;
        word-break: break-word;
        margin-bottom: 10px;
      }
      .file-download {
        display: inline-block;
        background: #4caf50;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 600;
        transition: background 0.3s ease;
      }
      .file-download:hover {
        background: #45a049;
      }
      
      /* Status Badge */
      .status-badge {
        display: inline-block;
        padding: 6px 15px;
        border-radius: 20px;
        color: white;
        font-weight: 600;
        font-size: 0.95em;
      }
      .status-pending { background: #e74c3c; }
      .status-need_info { background: #c0392b; }
      .status-need_update { background: #e67e22; }
      .status-in_progress { background: #f39c12; }
      .status-completed { background: #2ecc71; }
      .status-approved { background: #3498db; }
      
      .btn-container {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
      }
      .btn-group-left {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      .btn-group-right {
        display: flex;
        gap: 10px;
      }
      .btn {
        background-color: #3498db;
        color: white;
        padding: 10px 20px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 2px 6px rgba(52,152,219,0.4);
        transition: all 0.3s ease;
        display: inline-block;
      }
      .btn:hover {
        background-color: #217dbb;
        transform: translateY(-2px);
      }
      .btn-approve {
        background-color: #27ae60;
        box-shadow: 0 2px 6px rgba(39,174,96,0.4);
      }
      .btn-approve:hover {
        background-color: #1e8449;
      }
      .btn-need-update {
        background-color: #e67e22;
        box-shadow: 0 2px 6px rgba(230,126,34,0.4);
      }
      .btn-need-update:hover {
        background-color: #d35400;
      }
      .btn-complete {
        background-color: #2ecc71;
        box-shadow: 0 2px 6px rgba(46,204,113,0.4);
      }
      .btn-complete:hover {
        background-color: #27ae60;
      }
      
      /* Highlight for recorder */
      .recorder-info {
        background: #fff3cd;
        padding: 10px;
        border-radius: 6px;
        border-left: 4px solid #ffc107;
        margin-top: 15px;
      }
      
      /* Update reason section */
      .update-reason-section {
        background: #fff3e0;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #e67e22;
        margin-top: 15px;
      }
      .update-reason-section .label {
        color: #d35400;
        font-weight: bold;
      }
      .update-reason-section .value {
        color: #e67e22;
        font-weight: 600;
        margin-top: 5px;
      }
      
      /* Modal Styles */
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
      }
      .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 30px;
        border: 1px solid #888;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
      }
      .modal-header {
        font-size: 1.5em;
        font-weight: bold;
        margin-bottom: 20px;
        color: #2ecc71;
      }
      .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
      }
      .close:hover,
      .close:focus {
        color: #000;
      }
      .form-group {
        margin-bottom: 20px;
      }
      .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 8px;
        color: #333;
      }
      .form-group textarea {
        width: 100%;
        height: 100px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: inherit;
        resize: vertical;
        box-sizing: border-box;
        line-height: 1.6;
      }
      .form-group input[type="file"] {
        width: 100%;
        padding: 10px;
        border: 2px dashed #ddd;
        border-radius: 6px;
        background: #f9f9f9;
        box-sizing: border-box;
      }
      .modal-footer {
        margin-top: 20px;
        text-align: right;
      }
      .modal-btn {
        padding: 10px 20px;
        margin-left: 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
      }
      .modal-btn-submit {
        background: #2ecc71;
        color: white;
      }
      .modal-btn-submit:hover {
        background: #27ae60;
      }
      .modal-btn-cancel {
        background: #95a5a6;
        color: white;
      }
      .modal-btn-cancel:hover {
        background: #7f8c8d;
      }
      
      @media (max-width: 768px) {
        body {
            margin: 10px;
            padding: 10px;
        }
        h1 {
            font-size: 1.5em;
        }
        .section {
            padding: 15px;
        }
        .files-grid {
            grid-template-columns: 1fr;
        }
        .btn-container {
            flex-direction: column;
        }
        .btn-group-left,
        .btn-group-right {
            width: 100%;
            flex-direction: column;
        }
        .btn {
            width: 100%;
            text-align: center;
        }
        .modal-content {
            width: 95%;
            margin: 10% auto;
            padding: 20px;
        }
      }
    </style>
</head>
<body>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<div class="alert alert-success">
    ✅ อัปเดตงานเรียบร้อยแล้ว!
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-error">
    ❌ <?php echo htmlspecialchars($error_message ?? ''); ?>
</div>
<?php endif; ?>

<h1>
    <?php if (!empty($content['check_ad'])): ?>
        <span class="ad-star">⭐</span>
    <?php endif; ?>
    รายละเอียดงาน: <?php echo htmlspecialchars($content['job_title'] ?? ''); ?>
</h1>

<!-- ส่วนข้อมูลบรีฟ -->
<div class="section">
    <div class="section-title">
        <span class="icon">📋</span> ข้อมูลบรีฟงาน
    </div>
    
    <div><span class="label">ผู้สั่งงาน:</span><span class="value"><?php echo htmlspecialchars(!empty($content['requester_name']) ? $content['requester_name'] : '-'); ?></span></div>
    
    <div class="recorder-info">
        <span class="label">👤 ผู้บันทึกงาน:</span><span class="value"><?php echo htmlspecialchars(!empty($content['name']) ? $content['name'] : '-'); ?></span>
    </div>
    
    <div><span class="label">แบรนด์:</span><span class="value"><?php echo htmlspecialchars($content['brand'] ?? ''); ?></span></div>
    <div><span class="label">แพลตฟอร์ม:</span><span class="value"><?php echo htmlspecialchars(!empty($content['platform']) ? $content['platform'] : '-'); ?></span></div>
    <div><span class="label">หมวดหมู่:</span><span class="value"><?php echo htmlspecialchars(!empty($content['category']) ? $content['category'] : '-'); ?></span></div>
    
    <?php if (!empty($content['check_ad'])): ?>
    <div style="margin-top: 15px;">
        <span class="ad-check-badge">
            <span class="star">⭐</span>
            AD - ต้องการการตรวจสอบพิเศษ
        </span>
    </div>
    <?php endif; ?>
    
    <div>
        <span class="label">สถานะ:</span>
        <span class="status-badge status-<?php echo htmlspecialchars($content['status'] ?? ''); ?>">
            <?php 
            $status_text = [
                'pending' => 'รอดำเนินการ',
                'in_progress' => 'กำลังดำเนินการ',
                'completed' => 'เสร็จสิ้น',
                'need_info' => 'ต้องการข้อมูลเพิ่ม',
                'need_update' => 'ต้องแก้ไขงาน',
                'approved' => 'อนุมัติแล้ว',
                'cancelled' => 'ยกเลิก'
            ];
            echo htmlspecialchars($status_text[$content['status']] ?? $content['status']);
            ?>
        </span>
    </div>
    <div><span class="label">กำหนดวันส่งงาน:</span><span class="value"><?php echo date('d/m/Y', strtotime($content['due_date'])); ?></span></div>
    
    <div>
        <span class="label">รายละเอียด:</span>
        <div class="formatted-text">
            <?php echo displayFormattedText($content['content_detail'] ?? ''); ?>
        </div>
    </div>
    
    <div><span class="label">รูปแบบเนื้อหา:</span><span class="value"><?php echo htmlspecialchars($content['content_format'] ?? ''); ?></span></div>
    
    <?php if (!empty($content['remark'])): ?>
    <div>
        <span class="label">หมายเหตุ:</span>
        <div class="formatted-text">
            <?php echo displayFormattedText($content['remark'] ?? ''); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($content['attachment']) && file_exists($content['attachment'])): ?>
    <div class="attachment">
        <span class="label">ไฟล์แนบบรีฟ:</span><br/>
        <?php 
        $ext = strtolower(pathinfo($content['attachment'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
            <img src="<?php echo htmlspecialchars($content['attachment']); ?>" alt="ไฟล์แนบ">
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($content['attachment']); ?>" target="_blank" class="file-download">ดาวน์โหลดไฟล์แนบ</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ส่วนการดำเนินงาน -->
<div class="section">
    <div class="section-title">
        <span class="icon">👥</span> การดำเนินงาน
    </div>
    
    <?php if (!empty($content['pending_name'])): ?>
    <div><span class="label">ผู้แจ้งติกลับ/สอบถาม:</span><span class="value"><?php echo htmlspecialchars($content['pending_name'] ?? ''); ?></span></div>
    <?php endif; ?>
    
    <?php if (!empty($content['reject_reason'])): ?>
    <div>
        <span class="label">เหตุผลติกลับ/สอบถาม:</span>
        <div class="formatted-text" style="border-left-color: #e74c3c;">
            <?php echo displayFormattedText($content['reject_reason'] ?? ''); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($content['update_reason'])): ?>
    <div class="update-reason-section">
        <span class="label">🔧 เหตุผลที่ต้องแก้ไข:</span>
        <div class="formatted-text" style="background: white; border-left-color: #e67e22;">
            <?php echo displayFormattedText($content['update_reason'] ?? ''); ?>
        </div>
        <?php if (!empty($content['update_name'])): ?>
        <div style="margin-top: 10px; font-size: 0.9em; color: #7f8c8d;">
            ผู้แจ้ง: <?php echo htmlspecialchars($content['update_name']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($content['progress_name'])): ?>
    <div><span class="label">ผู้รับเรื่อง:</span><span class="value"><?php echo htmlspecialchars($content['progress_name'] ?? ''); ?></span></div>
    <?php endif; ?>
    
    <?php if (!empty($content['complete_name'])): ?>
    <div><span class="label">ผู้ส่งงาน:</span><span class="value"><?php echo htmlspecialchars($content['complete_name'] ?? ''); ?></span></div>
    <?php endif; ?>
</div>

<!-- ส่วนงานที่ส่ง (แสดงเมื่อสถานะ completed หรือ approved) -->
<?php if (in_array($content['status'], ['completed', 'approved']) && (!empty($content['completion_note']) || !empty($completion_files))): ?>
<div class="section completion-section">
    <div class="section-title">
        <span class="icon">✅</span> งานที่ส่ง
    </div>
    
    <?php if (!empty($content['completion_note'])): ?>
    <div>
        <span class="label">📝 รายละเอียดงานที่เสร็จ:</span>
        <div class="completion-note">
            <?php echo displayFormattedText($content['completion_note'] ?? ''); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($completion_files)): ?>
    <div style="margin-top: 20px;">
        <span class="label">📎 ไฟล์งานที่ส่ง (<?php echo count($completion_files); ?> ไฟล์):</span>
        <div class="files-grid">
            <?php foreach ($completion_files as $index => $file_path): ?>
                <?php if (file_exists($file_path)): ?>
                <div class="file-item">
                    <?php 
                    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                    $filename = basename($file_path);
                    
                    // แสดงรูปภาพ
                    if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                        <img src="<?php echo htmlspecialchars($file_path); ?>" alt="<?php echo htmlspecialchars($filename); ?>">
                    <?php else: 
                        // แสดง icon ตามประเภทไฟล์
                        $icon = '📄';
                        if (in_array($ext, ['pdf'])) $icon = '📕';
                        elseif (in_array($ext, ['doc', 'docx'])) $icon = '📘';
                        elseif (in_array($ext, ['zip', 'rar'])) $icon = '📦';
                        elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '📊';
                    ?>
                        <div class="file-icon"><?php echo $icon; ?></div>
                    <?php endif; ?>
                    
                    <div class="file-name"><?php echo htmlspecialchars($filename); ?></div>
                    <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="file-download" download>
                        ⬇️ ดาวน์โหลด
                    </a>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($content['updated_at'])): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);">
        <span class="label">🕐 วันที่ส่งงาน:</span>
        <span class="value"><?php echo date('d/m/Y H:i:s', strtotime($content['updated_at'])); ?></span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="btn-container">
    <div class="btn-group-left">
        <?php if ($role === 'admin' && in_array($content['status'], ['need_info', 'in_progress', 'need_update'])): ?>
            <button type="button" class="btn btn-complete" onclick="openCompleteModal(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars($content['job_title'] ?? '', ENT_QUOTES); ?>')">
                ✅ ส่งงาน
            </button>
        <?php endif; ?>
        
        <?php if (in_array($role, ['admin', 'approve']) && $content['status'] === 'completed'): ?>
            <button type="button" class="btn btn-need-update" onclick="openUpdateModal(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars($content['job_title'] ?? '', ENT_QUOTES); ?>')">
                🔧 ขอแก้ไขงาน
            </button>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('ยืนยันการอนุมัติงานนี้?');">
                <input type="hidden" name="id" value="<?php echo $content['id']; ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-approve">🎉 อนุมัติงาน</button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="btn-group-right">
        <?php if ($role === 'admin'): ?>
            <a href="admin_dashboard.php" class="btn">จัดการงานคอนเทนต์</a>
        <?php endif; ?>
        <a href="index.php" class="btn">กลับสู่หน้าหลัก</a>
    </div>
</div>

<!-- Modal สำหรับกรอกรายละเอียดเมื่อส่งงาน -->
<div id="completeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeCompleteModal()">&times;</span>
        <div class="modal-header">✅ บันทึกงานเสร็จสิ้น</div>
        <form id="completeForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" id="complete_brief_id">
            <input type="hidden" name="action" value="completed">
            
            <div class="form-group">
                <label>📋 ชื่องาน:</label>
                <div id="complete_job_title" style="padding: 10px; background: #f0f0f0; border-radius: 6px; font-weight: bold;"></div>
            </div>
            
            <div class="form-group">
                <label>📝 รายละเอียดงานที่เสร็จ: <span style="color: red;">*</span></label>
                <textarea name="completion_note" placeholder="กรอกรายละเอียดงานที่เสร็จแล้ว เช่น สิ่งที่ทำ, ผลลัพธ์, หมายเหตุ&#10;สามารถขึ้นบรรทัดใหม่ได้ตามต้องการ" required></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">💡 ข้อความจะแสดงผลตามรูปแบบที่คุณจัด</small>
            </div>
            
            <div class="form-group">
                <label>📎 แนบไฟล์งาน (สามารถเลือกหลายไฟล์):</label>
                <input type="file" name="completion_files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip,.rar">
                <small style="color: #666; display: block; margin-top: 5px;">รองรับ: JPG, PNG, PDF, DOC, DOCX, ZIP, RAR</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCompleteModal()">ยกเลิก</button>
                <button type="submit" class="modal-btn modal-btn-submit">✅ บันทึกเสร็จสิ้น</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal สำหรับขอแก้ไขงาน -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeUpdateModal()">&times;</span>
        <div class="modal-header" style="color: #e67e22;">🔧 ขอแก้ไขงาน</div>
        <form id="updateForm" method="POST">
            <input type="hidden" name="id" id="update_brief_id">
            <input type="hidden" name="action" value="need_update">
            
            <div class="form-group">
                <label>📋 ชื่องาน:</label>
                <div id="update_job_title" style="padding: 10px; background: #f0f0f0; border-radius: 6px; font-weight: bold;"></div>
            </div>
            
            <div class="form-group">
                <label>🔧 เหตุผลที่ต้องแก้ไข: <span style="color: red;">*</span></label>
                <textarea name="update_reason" placeholder="กรอกเหตุผลและรายละเอียดที่ต้องการให้แก้ไข เช่น ต้องการเปลี่ยนสี, ปรับขนาด, แก้ไขข้อความ ฯลฯ&#10;สามารถขึ้นบรรทัดใหม่ได้ตามต้องการ" required></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">💡 ข้อความจะแสดงผลตามรูปแบบที่คุณจัด</small>
            </div>
            
            <div style="background: #fff3e0; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="color: #e67e22;">💡 หมายเหตุ:</strong>
                <p style="margin: 5px 0 0 0; color: #7f8c8d; font-size: 0.95em;">
                    เมื่อกดขอแก้ไขงาน สถานะจะเปลี่ยนเป็น "ต้องแก้ไขงาน" และผู้ทำงานสามารถส่งงานใหม่ได้อีกครั้ง
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeUpdateModal()">ยกเลิก</button>
                <button type="submit" class="modal-btn modal-btn-submit" style="background: #e67e22;">🔧 ขอแก้ไขงาน</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCompleteModal(briefId, jobTitle) {
    document.getElementById('complete_brief_id').value = briefId;
    document.getElementById('complete_job_title').textContent = jobTitle;
    document.getElementById('completeModal').style.display = 'block';
}

function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
    document.getElementById('completeForm').reset();
}

function openUpdateModal(briefId, jobTitle) {
    document.getElementById('update_brief_id').value = briefId;
    document.getElementById('update_job_title').textContent = jobTitle;
    document.getElementById('updateModal').style.display = 'block';
}

function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
    document.getElementById('updateForm').reset();
}

// ปิด modal เมื่อคลิกนอก modal
window.onclick = function(event) {
    const completeModal = document.getElementById('completeModal');
    const updateModal = document.getElementById('updateModal');
    
    if (event.target == completeModal) {
        closeCompleteModal();
    }
    if (event.target == updateModal) {
        closeUpdateModal();
    }
}
</script>

</body>
</html>