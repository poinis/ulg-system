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

$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$issue_id = $is_edit ? intval($_GET['id']) : 0;

$issue = null;
$existing_images = [];

if ($is_edit) {
    $sql = "SELECT * FROM issues WHERE id = ?";
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
    
    // ดึงรูปภาพที่มีอยู่
    $sql = "SELECT * FROM issue_images WHERE issue_id = ? ORDER BY uploaded_at";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $issue_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $existing_images[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ดึงหมวดหมู่
$categories = [];
$sql = "SELECT * FROM issue_categories ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// ดึงแผนก
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}

// ดึงผู้ใช้งาน
$users = [];
$sql = "SELECT id, name, role, department FROM users ORDER BY name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$error = '';
$success = '';

// ลบรูปภาพ
if (isset($_GET['delete_image'])) {
    $image_id = intval($_GET['delete_image']);
    $sql = "SELECT * FROM issue_images WHERE id = ? AND issue_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $image_id, $issue_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $image = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($image) {
        // ลบไฟล์
        if (file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }
        // ลบจากฐานข้อมูล
        $sql = "DELETE FROM issue_images WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $image_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("location: issue_create.php?id={$issue_id}");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $location = cleanInput($_POST['location']);
    $room_number = cleanInput($_POST['room_number']);
    $priority = cleanInput($_POST['priority']);
    $status = cleanInput($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $due_date = !empty($_POST['due_date']) ? cleanInput($_POST['due_date']) : null;
    
    if (empty($title) || empty($category_id) || empty($location)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else {
        if ($is_edit) {
            // อัพเดท
            $sql = "UPDATE issues SET 
                    title = ?, description = ?, category_id = ?, location = ?, room_number = ?,
                    priority = ?, status = ?, assigned_to = ?, department_id = ?, due_date = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssissssiisi", 
                $title, $description, $category_id, $location, $room_number,
                $priority, $status, $assigned_to, $department_id, $due_date, $issue_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // สร้าง URL ของปัญหา
                $issue_url = "https://www.weedjai.com/hotel/issues/issue_detail.php?id={$issue_id}";
                
                // บันทึกประวัติการอัพเดท
                if ($issue['status'] != $status || $issue['assigned_to'] != $assigned_to) {
                    $comment = "อัพเดทสถานะจาก {$issue['status']} เป็น {$status}";
                    $sql_log = "INSERT INTO issue_updates (issue_id, user_id, old_status, new_status, old_assigned_to, new_assigned_to, comment) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_log = mysqli_prepare($conn, $sql_log);
                    mysqli_stmt_bind_param($stmt_log, "iissiis", 
                        $issue_id, $user_id, $issue['status'], $status, 
                        $issue['assigned_to'], $assigned_to, $comment);
                    mysqli_stmt_execute($stmt_log);
                    mysqli_stmt_close($stmt_log);
                    
                    // ส่งการแจ้งเตือน
                    if ($assigned_to != $issue['assigned_to'] && !empty($assigned_to)) {
                        $message = "คุณได้รับมอบหมายงาน: {$title}";
                        createNotification($conn, $assigned_to, $issue_id, "งานใหม่", $message, "issue_assigned");
                        
                        // ส่ง Pumble แจ้งผู้ที่ได้รับมอบหมาย (ส่งไปที่ webhook ของเขา)
                        $assigned_msg = "🔔 มอบหมายงานใหม่: {$title}\n🔗 ดูรายละเอียด: {$issue_url}";
                        sendPumbleToUser($conn, $assigned_to, $assigned_msg);
                        
                        // ส่งไปที่ webhook ทั่วไปด้วย
                        $general_msg = "🔔 มอบหมายงานใหม่: {$title}\nให้กับ " . getUserName($conn, $assigned_to) . "\n🔗 ดูรายละเอียด: {$issue_url}";
                        sendPumbleNotification($general_msg);
                    }
                }
                
                // อัปโหลดรูปภาพ
                uploadImages($conn, $issue_id, $user_id);
                
                $success = 'บันทึกข้อมูลเรียบร้อยแล้ว';
                header("refresh:1;url=issue_detail.php?id={$issue_id}");
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            // สร้างใหม่
            $sql = "INSERT INTO issues (title, description, category_id, location, room_number, priority, status, reported_by, assigned_to, department_id, due_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssissssiiss", 
                $title, $description, $category_id, $location, $room_number,
                $priority, $status, $user_id, $assigned_to, $department_id, $due_date);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // สร้าง URL ของปัญหา
                $issue_url = "https://www.weedjai.com/hotel/issues/issue_detail.php?id={$new_id}";
                
                // อัปโหลดรูปภาพ
                uploadImages($conn, $new_id, $user_id);
                
                // ส่งการแจ้งเตือน
                if (!empty($assigned_to)) {
                    $message = "คุณได้รับมอบหมายงาน: {$title}";
                    createNotification($conn, $assigned_to, $new_id, "งานใหม่", $message, "issue_assigned");
                    
                    // ส่ง Pumble แจ้งผู้ที่ได้รับมอบหมาย (ส่งไปที่ webhook ของเขา)
                    $assigned_msg = "🔔 มอบหมายงานใหม่: {$title}\n📍 สถานที่: {$location}\n⚠️ ความสำคัญ: {$priority}\n🔗 ดูรายละเอียด: {$issue_url}";
                    sendPumbleToUser($conn, $assigned_to, $assigned_msg);
                }
                
                // ส่งไปที่ webhook ทั่วไป
                $pumble_msg = "🆕 แจ้งปัญหาใหม่: {$title}\n📍 สถานที่: {$location}\n⚠️ ความสำคัญ: {$priority}\nแจ้งโดย: {$name}";
                if (!empty($assigned_to)) {
                    $pumble_msg .= "\nมอบหมายให้: " . getUserName($conn, $assigned_to);
                }
                $pumble_msg .= "\n🔗 ดูรายละเอียด: {$issue_url}";
                sendPumbleNotification($pumble_msg);
                
                $success = 'สร้างรายการปัญหาเรียบร้อยแล้ว';
                header("refresh:1;url=issue_detail.php?id={$new_id}");
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ฟังก์ชันอัปโหลดรูปภาพ
function uploadImages($conn, $issue_id, $user_id) {
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = "../uploads/issues/";
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $total_files = count($_FILES['images']['name']);
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['images']['error'][$i] == 0) {
                $file_name = $_FILES['images']['name'][$i];
                $file_tmp = $_FILES['images']['tmp_name'][$i];
                $file_size = $_FILES['images']['size'][$i];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // ตรวจสอบนามสกุลไฟล์
                $allowed = array('jpg', 'jpeg', 'png', 'gif');
                if (in_array($file_ext, $allowed)) {
                    // ตรวจสอบขนาดไฟล์ (5MB)
                    if ($file_size <= 5242880) {
                        $new_name = 'issue_' . $issue_id . '_' . time() . '_' . $i . '.' . $file_ext;
                        $file_path = $upload_dir . $new_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // บันทึกลงฐานข้อมูล
                            $sql = "INSERT INTO issue_images (issue_id, image_path, uploaded_by) VALUES (?, ?, ?)";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "isi", $issue_id, $file_path, $user_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }
        }
    }
}

// ฟังก์ชันดึงชื่อผู้ใช้
function getUserName($conn, $user_id) {
    $sql = "SELECT name FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user ? $user['name'] : 'ไม่ทราบชื่อ';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'แก้ไขปัญหา' : 'แจ้งปัญหาใหม่'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .form-card h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 1.5em;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.95em;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group label.required:after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        /* สไตล์สำหรับอัปโหลดรูป */
        .image-upload-area {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #f8f9ff;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .image-upload-area:hover {
            background: #f0f2ff;
            border-color: #5568d3;
        }
        
        .image-upload-area input[type="file"] {
            display: none;
        }
        
        .existing-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .image-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-delete:hover {
            background: #dc3545;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><?php echo $is_edit ? '✏️ แก้ไขปัญหา' : '➕ แจ้งปัญหาใหม่'; ?></h1>
            <a href="issues_dashboard.php" class="back-link">← กลับ</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2><?php echo $is_edit ? 'แก้ไขข้อมูลปัญหา' : 'กรอกรายละเอียดปัญหา'; ?></h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="required">หัวข้อปัญหา</label>
                    <input type="text" name="title" value="<?php echo $issue ? htmlspecialchars($issue['title']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea name="description"><?php echo $issue ? htmlspecialchars($issue['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">ประเภทปัญหา</label>
                        <select name="category_id" required>
                            <option value="">-- เลือกประเภท --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($issue && $issue['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">ความสำคัญ</label>
                        <select name="priority" required>
                            <option value="low" <?php echo ($issue && $issue['priority'] == 'low') ? 'selected' : ''; ?>>ต่ำ</option>
                            <option value="medium" <?php echo ($issue && $issue['priority'] == 'medium') ? 'selected' : ''; ?> selected>ปานกลาง</option>
                            <option value="high" <?php echo ($issue && $issue['priority'] == 'high') ? 'selected' : ''; ?>>สูง</option>
                            <option value="urgent" <?php echo ($issue && $issue['priority'] == 'urgent') ? 'selected' : ''; ?>>เร่งด่วน</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">สถานที่</label>
                        <input type="text" name="location" value="<?php echo $issue ? htmlspecialchars($issue['location']) : ''; ?>" placeholder="เช่น ล็อบบี้ ชั้น 3" required>
                    </div>
                    
                    <div class="form-group">
                        <label>เลขห้อง</label>
                        <input type="text" name="room_number" value="<?php echo $issue ? htmlspecialchars($issue['room_number']) : ''; ?>" placeholder="เช่น 301">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status">
                            <option value="pending" <?php echo ($issue && $issue['status'] == 'pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                            <option value="assigned" <?php echo ($issue && $issue['status'] == 'assigned') ? 'selected' : ''; ?>>มอบหมายแล้ว</option>
                            <option value="in_progress" <?php echo ($issue && $issue['status'] == 'in_progress') ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="on_hold" <?php echo ($issue && $issue['status'] == 'on_hold') ? 'selected' : ''; ?>>พักงาน</option>
                            <option value="completed" <?php echo ($issue && $issue['status'] == 'completed') ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                            <option value="cancelled" <?php echo ($issue && $issue['status'] == 'cancelled') ? 'selected' : ''; ?>>ยกเลิก</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>กำหนดเสร็จ</label>
                        <input type="date" name="due_date" value="<?php echo $issue ? $issue['due_date'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>มอบหมายให้</label>
                        <select name="assigned_to">
                            <option value="">-- เลือกผู้รับผิดชอบ --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($issue && $issue['assigned_to'] == $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']) . ' (' . $u['role'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>แผนกที่รับผิดชอบ</label>
                        <select name="department_id">
                            <option value="">-- เลือกแผนก --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($issue && $issue['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- รูปภาพที่มีอยู่แล้ว -->
                <?php if (!empty($existing_images)): ?>
                    <div class="form-group">
                        <label>📸 รูปภาพที่มีอยู่</label>
                        <div class="existing-images">
                            <?php foreach ($existing_images as $img): ?>
                                <div class="image-item">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="รูปภาพปัญหา">
                                    <a href="?id=<?php echo $issue_id; ?>&delete_image=<?php echo $img['id']; ?>" 
                                       class="image-delete" 
                                       onclick="return confirm('ต้องการลบรูปนี้?')">×</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- อัปโหลดรูปภาพ -->
                <div class="form-group">
                    <label>📷 เพิ่มรูปภาพ (สูงสุด 5 รูป, ขนาดไม่เกิน 5MB/รูป)</label>
                    <div class="image-upload-area" onclick="document.getElementById('file-input').click()">
                        <p style="font-size: 3em; margin-bottom: 10px;">📁</p>
                        <p style="font-weight: 600; color: #667eea;">คลิกเพื่อเลือกรูปภาพ</p>
                        <p style="font-size: 0.9em; color: #6c757d; margin-top: 5px;">รองรับ JPG, PNG, GIF</p>
                        <input type="file" id="file-input" name="images[]" multiple accept="image/*">
                    </div>
                    <div id="preview" style="margin-top: 15px;"></div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? '💾 บันทึกการแก้ไข' : '📝 สร้างรายการปัญหา'; ?>
                    </button>
                    <a href="issues_dashboard.php" class="btn btn-secondary">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // แสดงตัวอย่างรูปก่อนอัปโหลด
        document.getElementById('file-input').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            preview.innerHTML = '';
            
            const files = e.target.files;
            if (files.length > 5) {
                alert('สามารถอัปโหลดได้สูงสุด 5 รูปเท่านั้น');
                this.value = '';
                return;
            }
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (file.size > 5242880) {
                    alert('ไฟล์ ' + file.name + ' มีขนาดใหญ่เกิน 5MB');
                    continue;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.style.cssText = 'display:inline-block; margin:5px; border:2px solid #ddd; border-radius:8px; overflow:hidden;';
                    div.innerHTML = '<img src="' + e.target.result + '" style="width:150px; height:150px; object-fit:cover;">';
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>