<?php


require_once "config.php";
require_once "pumble_notification.php"; // เปลี่ยนเป็นไฟล์ใหม่

session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];

// ดึงชื่อจริง (name) จากตาราง users
$sql_name = "SELECT name FROM users WHERE username = ?";
$stmt_name = mysqli_prepare($conn, $sql_name);
mysqli_stmt_bind_param($stmt_name, "s", $username);
mysqli_stmt_execute($stmt_name);
mysqli_stmt_bind_result($stmt_name, $name);
mysqli_stmt_fetch($stmt_name);
mysqli_stmt_close($stmt_name);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $reject_reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';

    // ดึงข้อมูลบรีฟเดิมก่อนอัพเดท (เพื่อส่งการแจ้งเตือน)
    $sql_old = "SELECT job_title, status FROM content_brief WHERE id = ?";
    $stmt_old = mysqli_prepare($conn, $sql_old);
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    mysqli_stmt_bind_result($stmt_old, $job_title, $old_status);
    mysqli_stmt_fetch($stmt_old);
    mysqli_stmt_close($stmt_old);

    $new_status = '';
    $update_success = false;

    if ($action === 'need_info' && !empty($reject_reason)) {
        $new_status = 'need_info';
        $sql = "UPDATE content_brief 
                SET status = 'need_info', reject_reason = ?, pending_name = ?, updated_at = NOW() 
                WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $reject_reason, $name, $id);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'in_progress') {
        $new_status = 'in_progress';
        $sql = "UPDATE content_brief 
                SET status = 'in_progress', progress_name = ?, reject_reason = NULL, updated_at = NOW() 
                WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $name, $id);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'completed') {
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
                SET status = 'completed', complete_name = ?, completion_note = ?, completion_files = ?, updated_at = NOW() 
                WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssi", $name, $completion_note, $files_json, $id);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'approved') {
        $new_status = 'approved';
        $sql = "UPDATE content_brief SET status = 'approved', updated_at = NOW() WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // ส่งการแจ้งเตือนไปยัง Pumble หลังจากอัพเดทสำเร็จ
    if ($update_success && !empty($new_status)) {
        try {
            $pumble = new PumbleNotification();
            $notification_sent = $pumble->notifyStatusChange($id, $job_title, $old_status, $new_status, $name);
            
            if ($notification_sent) {
                error_log("Pumble notification sent successfully: Brief #$id status changed from $old_status to $new_status");
            } else {
                error_log("Pumble notification failed: Brief #$id status changed from $old_status to $new_status");
            }
        } catch (Exception $e) {
            error_log("Pumble notification error: " . $e->getMessage());
        }
    }
}

// ดึงข้อมูลบรีฟงานตามสถานะที่ต้องการ
$sql = "SELECT * FROM content_brief WHERE status IN ('pending', 'need_info', 'need_update', 'in_progress', 'completed', 'approved') ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - จัดการคอนเทนต์</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
            background: #fff;
            color: #222;
        }
        header {
            background: #fff;
            padding: 15px 20px;
            text-align: center;
            font-size: 1.5em;
            font-weight: bold;
            color: #222;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        .back-btn {
            position: absolute;
            right: 20px;
            top: 20px;
            background: #3498db;
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.9em;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(50, 100, 200, 0.3);
            z-index: 10;
        }
        .back-btn:hover {
            background: #217dbb;
        }
        .table-container {
            width: 95%;
            max-width: 1200px;
            margin: 20px auto 40px auto;
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }
        th {
            background-color: #000;
            color: white;
            text-align: left;
            padding: 10px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        tr:hover {
            background: #f4f4f4;
        }
        img.attachment-thumb {
            max-width: 80px;
            max-height: 60px;
            border-radius: 4px;
            filter: grayscale(100%);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            color: white;
            font-weight: 600;
        }
        .status-pending { background: #e74c3c; }
        .status-need_info { background: #c0392b; }
        .status-need_update { background: #e67e22; }
        .status-in_progress { background: #f39c12; }
        .status-completed { background: #2ecc71; }
        .status-approved { background: #387c00ff; }

        button, .btn-link {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: #fff;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 5px;
            display: inline-block;
        }
        .btn-progress { background-color: #f39c12; }
        .btn-complete { background-color: #2ecc71; }
        .btn-detail { background-color: #3498db; }
        .btn-reject { background-color: #e74c3c; }
        .btn-approve { background-color: #387c00ff; }
        textarea {
            width: 100%;
            height: 60px;
            margin-top: 5px;
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #ccc;
            resize: vertical;
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
        }
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 6px;
            background: #f9f9f9;
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
            table, th, td {
                font-size: 14px;
                padding: 8px;
            }
            header {
                font-size: 1.2em;
            }
            .back-btn {
                position: fixed;
                right: 10px;
                top: 10px;
                padding: 7px 12px;
                font-size: 0.85em;
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

<header>จัดการงานคอนเทนต์ (Admin Dashboard)
    <a href="index.php" class="back-btn">กลับหน้าหลัก</a>
</header>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>วันที่สร้าง</th>
                <th>ชื่องาน</th>                
                <th>แบรนด์</th>
                <th>แพลตฟอร์ม</th>
                <th>สถานะ</th>
                <th>เหตุผลตีกลับ/สอบถาม</th>
                <th>ไฟล์แนบ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php $index = 1; while ($row = mysqli_fetch_assoc($result)) { ?>
                
            <tr><td><?php echo $index++; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                <td><?php echo htmlspecialchars($row['brand']); ?></td>
                <td><?php echo htmlspecialchars($row['platform']); ?></td>
                <td>
                    <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td><?php echo nl2br(htmlspecialchars($row['reject_reason'])); ?></td>
                <td>
                    <?php 
                    if (!empty($row['attachment']) && file_exists($row['attachment'])) {
                        $ext = strtolower(pathinfo($row['attachment'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            echo '<img src="'.htmlspecialchars($row['attachment']).'" class="attachment-thumb">';
                        } else {
                            echo '<a href="'.htmlspecialchars($row['attachment']).'" target="_blank">ดูไฟล์แนบ</a>';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td>
                    <a href="detail_content.php?id=<?php echo $row['id']; ?>" class="btn-link btn-detail">รายละเอียด</a>
                    <?php if ($row['status'] == 'pending') { ?>
                        <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('ยืนยันตีกลับ/สอบถามงานนี้?');">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" value="need_info">
                            <textarea name="reject_reason" placeholder="กรอกเหตุผลที่ตีกลับ/สอบถาม" required></textarea>
                            <button type="submit" class="btn-reject">ตีกลับ/สอบถาม</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="in_progress" class="btn-progress">รับงานบรีฟ</button>
                        </form>
                    <?php } elseif ($row['status'] == 'need_info') { ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="in_progress" class="btn-progress">กำลังดำเนินการ</button>
                        </form>
                        <button type="button" class="btn-complete" onclick="openCompleteModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['job_title'], ENT_QUOTES); ?>')">ส่งงาน</button>
                    <?php } elseif ($row['status'] == 'need_update') { ?>
                        <button type="button" class="btn-complete" onclick="openCompleteModal(<?php echo intval($row['id']); ?>, '<?php echo htmlspecialchars($row['job_title'] ?? '', ENT_QUOTES); ?>')">ส่งงานใหม่</button>
                    <?php } elseif ($row['status'] == 'in_progress') { ?>
                        <button type="button" class="btn-complete" onclick="openCompleteModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['job_title'], ENT_QUOTES); ?>')">ส่งงาน</button>
                    <?php } elseif ($row['status'] == 'completed') { ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการอนุมัติงานนี้?');">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="approved" class="btn-approve">อนุมัติงาน</button>
                        </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Modal สำหรับกรอกรายละเอียดเมื่อเสร็จสิ้น -->
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
                <textarea name="completion_note" placeholder="กรอกรายละเอียดงานที่เสร็จแล้ว เช่น สิ่งที่ทำ, ผลลัพธ์, หมายเหตุ" required></textarea>
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

// ปิด modal เมื่อคลิกนอก modal
window.onclick = function(event) {
    const modal = document.getElementById('completeModal');
    if (event.target == modal) {
        closeCompleteModal();
    }
}
</script>

</body>
</html>