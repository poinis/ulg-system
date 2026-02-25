<?php
require_once "config.php";
require_once "pumble_notification.php";

session_start();

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$username = $_SESSION['username'];
$name = '';
$userRole = '';

// ดึงชื่อจริงและ role ของผู้ใช้
$sql_user = "SELECT name, role FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $name, $userRole);
mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

// ดึงรายชื่อผู้ใช้ทั้งหมดสำหรับ dropdown ผู้สั่งงาน
$users_list = array();
$sql_users = "SELECT username, name FROM users ORDER BY name ASC";
$result_users = mysqli_query($conn, $sql_users);
if ($result_users) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $users_list[] = $row;
    }
    mysqli_free_result($result_users);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = "";
$success = "";

// ดึงข้อมูลบรีฟเดิม - Admin แก้ไขได้ทุกงาน, User แก้ไขได้เฉพาะงานตัวเอง
if ($id > 0) {
    if ($userRole === 'admin') {
        // Admin แก้ไขได้ทุกงาน
        $sql = "SELECT * FROM content_brief WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
    } else {
        // User แก้ไขได้เฉพาะงานตัวเอง
        $sql = "SELECT * FROM content_brief WHERE id = ? AND username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $id, $username);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $brief = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$brief) {
        die("ไม่พบบรีฟงานนี้หรือไม่มีสิทธิ์แก้ไข");
    }
} else {
    die("ไม่พบข้อมูลบรีฟสำหรับแก้ไข");
}

// แปลง platform จาก string เป็น array
$selected_platforms = !empty($brief['platform']) ? explode(',', $brief['platform']) : array();

// อัพเดตข้อมูลเมื่อ submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $job_title = mysqli_real_escape_string($conn, $_POST['job_title']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    
    if(isset($_POST['platform']) && is_array($_POST['platform'])) {
        $platform_array = array_map(function($p) use ($conn) { return mysqli_real_escape_string($conn, $p); }, $_POST['platform']);
        $platform = implode(',', $platform_array);
    } else {
        $platform = '';
    }
    
    $content_detail = mysqli_real_escape_string($conn, $_POST['content_detail']);
    $content_format = mysqli_real_escape_string($conn, $_POST['content_format']);
    $due_date = $_POST['due_date'];
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);
    $category = isset($_POST['category']) ? mysqli_real_escape_string($conn, $_POST['category']) : '';
    
    // เพิ่ม check_ad
    $check_ad = isset($_POST['check_ad']) ? 1 : 0;
    
    // รับค่าผู้สั่งงานจาก dropdown
    $requester_username = mysqli_real_escape_string($conn, $_POST['requester']);
    $requester_name = '';
    
    // ดึงชื่อผู้สั่งงาน
    $sql_req = "SELECT name FROM users WHERE username = ?";
    $stmt_req = mysqli_prepare($conn, $sql_req);
    mysqli_stmt_bind_param($stmt_req, "s", $requester_username);
    mysqli_stmt_execute($stmt_req);
    mysqli_stmt_bind_result($stmt_req, $requester_name);
    mysqli_stmt_fetch($stmt_req);
    mysqli_stmt_close($stmt_req);

    // อัพโหลดไฟล์แนบ (ถ้ามี)
    $attachment = $brief['attachment'];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . time() . "_" . $filename;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment = $target_file;
        } else {
            $error = "Upload ไฟล์แนบล้มเหลว";
        }
    }

    if (empty($error)) {
        // เก็บสถานะเดิมไว้เพื่อส่งการแจ้งเตือน
        $old_status = $brief['status'];
        $update_success = false;
        
        // อัพเดตข้อมูลพร้อมเปลี่ยนสถานะเป็น pending รออนุมัติใหม่
        if ($userRole === 'admin') {
            // Admin อัพเดตงานใดก็ได้
            $sql = "UPDATE content_brief SET 
                    job_title=?, brand=?, platform=?, content_detail=?, content_format=?, 
                    due_date=?, remark=?, attachment=?, category=?, requester_username=?, requester_name=?, check_ad=?,
                    status='pending', updated_at=NOW() 
                    WHERE id=?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssssssssii", 
                    $job_title, $brand, $platform, $content_detail, $content_format, 
                    $due_date, $remark, $attachment, $category, $requester_username, $requester_name, $check_ad, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $update_success = true;
                } else {
                    $error = "บันทึกข้อมูลผิดพลาด: " . mysqli_stmt_error($stmt);
                }
            } else {
                $error = "เตรียมคำสั่ง SQL ผิดพลาด";
            }
        } else {
            // User อัพเดตได้เฉพาะงานตัวเอง
            $sql = "UPDATE content_brief SET 
                    job_title=?, brand=?, platform=?, content_detail=?, content_format=?, 
                    due_date=?, remark=?, attachment=?, category=?, requester_username=?, requester_name=?, check_ad=?,
                    status='pending', updated_at=NOW() 
                    WHERE id=? AND username=?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssssssssiis", 
                    $job_title, $brand, $platform, $content_detail, $content_format, 
                    $due_date, $remark, $attachment, $category, $requester_username, $requester_name, $check_ad, $id, $username);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $update_success = true;
                } else {
                    $error = "บันทึกข้อมูลผิดพลาด: " . mysqli_stmt_error($stmt);
                }
            } else {
                $error = "เตรียมคำสั่ง SQL ผิดพลาด";
            }
        }
        
        // ส่งการแจ้งเตือนครั้งเดียวหลังจากอัพเดตสำเร็จ
        if ($update_success) {
            try {
                $pumble = new PumbleNotification();
                $new_status = 'pending';
                $notification_sent = $pumble->notifyStatusChange($id, $job_title, $old_status, $new_status, $name);
                
                if ($notification_sent) {
                    error_log("Pumble notification sent successfully: Brief #$id updated from $old_status to $new_status by $name");
                } else {
                    error_log("Pumble notification failed: Brief #$id updated from $old_status to $new_status by $name");
                }
            } catch (Exception $e) {
                error_log("Pumble notification error: " . $e->getMessage());
            }
            
            header("location: user_dashboard.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>แก้ไขบรีฟงาน</title>
    <meta charset="utf-8" />
    <style>
      body { font-family: Tahoma; max-width: 600px; margin:auto; padding: 20px; position: relative; }
      .user-info { position: absolute; top: 10px; right: 20px; text-align: right; font-size: 14px; color: #333; }
      .user-info strong { color: #007bff; }
      label { display:block; margin-top: 10px; font-weight: bold; }
      input[type="text"], textarea, input[type="date"], select { width: 100%; padding: 8px; box-sizing: border-box; }
      select { cursor: pointer; }
      .checkbox-group label, .radio-group label { display: inline-block; margin-right: 15px; font-weight: normal; }
      .message { margin: 10px 0; padding: 10px; border-radius: 5px; }
      .error { color: red; background: #ffe6e6; border: 1px solid red; }
      .success { color: green; background: #e6ffe6; border: 1px solid green; }
      input[type="submit"] { margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
      input[type="submit"]:hover { background: #0056b3; }
      h2 { margin-top: 40px; color: #d9534f; }
      .current-file { background: #f0f0f0; padding: 10px; border-radius: 5px; margin-top: 5px; }
      .ad-check-container {
        background: #fff3cd;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #ffc107;
        margin-top: 15px;
      }
      .ad-check-container label {
        display: inline-flex;
        align-items: center;
        font-weight: bold;
        color: #856404;
        cursor: pointer;
      }
      .ad-check-container input[type="checkbox"] {
        width: auto;
        margin-right: 10px;
        transform: scale(1.3);
        cursor: pointer;
      }
    </style>
</head>
<body>

<div class="user-info">
    ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong><br>
    <span style="font-size: 12px; color: #666;">(<?php echo htmlspecialchars($username); ?>)</span>
    <?php if ($userRole === 'admin') { ?>
        <br><span style="font-size: 12px; color: #d9534f; font-weight: bold;">สิทธิ์: ADMIN</span>
    <?php } ?>
</div>

<h2>แก้ไขบรีฟงาน</h2>

<?php if (!empty($error)) { ?>
    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
<?php } ?>

<?php if (!empty($success)) { ?>
    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
<?php } ?>

<form method="POST" action="edit_brief_form.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
    <label>ชื่องาน *</label>
    <input type="text" name="job_title" value="<?php echo htmlspecialchars($brief['job_title']); ?>" required>

    <label>แบรนด์ *</label>
    <input type="text" name="brand" value="<?php echo htmlspecialchars($brief['brand']); ?>" required>

    <label>ผู้สั่งงาน *</label>
    <select name="requester" required>
        <option value="">-- เลือกผู้สั่งงาน --</option>
        <?php foreach ($users_list as $user) { ?>
            <option value="<?php echo htmlspecialchars($user['username']); ?>"
                <?php if ($brief['requester_username'] == $user['username']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($user['name']); ?>
            </option>
        <?php } ?>
    </select>

    <label>สำหรับ (เลือกมากกว่า 1 ตัวได้)</label>
    <div class="checkbox-group">
        <label><input type="checkbox" name="platform[]" value="FB" <?php if (in_array('FB', $selected_platforms)) echo 'checked'; ?>> Facebook</label>
        <label><input type="checkbox" name="platform[]" value="IG" <?php if (in_array('IG', $selected_platforms)) echo 'checked'; ?>> Instagram</label>
        <label><input type="checkbox" name="platform[]" value="TT" <?php if (in_array('TT', $selected_platforms)) echo 'checked'; ?>> TikTok</label>
        <label><input type="checkbox" name="platform[]" value="Website" <?php if (in_array('Website', $selected_platforms)) echo 'checked'; ?>> Website</label>
        <label><input type="checkbox" name="platform[]" value="media" <?php if (in_array('media', $selected_platforms)) echo 'checked'; ?>> In mall media</label>
        <label><input type="checkbox" name="platform[]" value="print" <?php if (in_array('print', $selected_platforms)) echo 'checked'; ?>> For print</label>
    </div>

    <label>หมวดหมู่ (เลือกได้ 1 ตัว) *</label>
    <div class="radio-group">
        <label><input type="radio" name="category" value="New arrival" <?php if ($brief['category'] == 'New arrival') echo 'checked'; ?> required> New arrival</label>
        <label><input type="radio" name="category" value="Highlight" <?php if ($brief['category'] == 'Highlight') echo 'checked'; ?> required> Highlight</label>
        <label><input type="radio" name="category" value="Promotion" <?php if ($brief['category'] == 'Promotion') echo 'checked'; ?> required> Promotion</label>
        <label><input type="radio" name="category" value="Event" <?php if ($brief['category'] == 'Event') echo 'checked'; ?> required> Event</label>
    </div>

    <!-- เพิ่มส่วน Check AD -->
    <div class="ad-check-container">
        <label>
            <input type="checkbox" name="check_ad" value="1" <?php if (!empty($brief['check_ad'])) echo 'checked'; ?>>
            ⭐ AD - คอนเทนต์นี้ต้องการการตรวจสอบพิเศษ
        </label>
    </div>

    <label>รายละเอียด *</label>
    <textarea name="content_detail" rows="4" required><?php echo htmlspecialchars($brief['content_detail']); ?></textarea>

    <label>รูปแบบเนื้อหา *</label>
    <input type="text" name="content_format" value="<?php echo htmlspecialchars($brief['content_format']); ?>" required>

    <label>กำหนดวันส่งงาน *</label>
    <input type="date" name="due_date" value="<?php echo htmlspecialchars($brief['due_date']); ?>" required>

    <label>ส่งไฟล์แนบ</label>
    <?php if (!empty($brief['attachment'])) { ?>
        <div class="current-file">
            📎 ไฟล์ปัจจุบัน: <a href="<?php echo htmlspecialchars($brief['attachment']); ?>" target="_blank">ดูไฟล์แนบ</a>
        </div>
    <?php } ?>
    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
    <small style="color: #666;">อัพโหลดไฟล์ใหม่เพื่อเปลี่ยนไฟล์เดิม (หรือเว้นว่างไว้เพื่อใช้ไฟล์เดิม)</small>

    <label>หมายเหตุ</label>
    <textarea name="remark" rows="2"><?php echo htmlspecialchars($brief['remark']); ?></textarea>

    <br>
    <input type="submit" value="อัปเดตบรีฟ">
</form>

<p style="margin-top: 20px;"><a href="user_dashboard.php">← กลับหน้าหลัก</a></p>
</body>
</html>