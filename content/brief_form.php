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

// ดึงชื่อจริง (name) จากตาราง users ผ่าน username
$sql_name = "SELECT name FROM users WHERE username = ?";
$stmt_name = mysqli_prepare($conn, $sql_name);
mysqli_stmt_bind_param($stmt_name, "s", $username);
mysqli_stmt_execute($stmt_name);
mysqli_stmt_bind_result($stmt_name, $name);
mysqli_stmt_fetch($stmt_name);
mysqli_stmt_close($stmt_name);

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

// รับค่าวันที่จาก URL (ถ้ามี)
$preset_date = isset($_GET['date']) ? $_GET['date'] : '';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    
    $check_ad = isset($_POST['check_ad']) ? 1 : 0;
    
    $requester_username = mysqli_real_escape_string($conn, $_POST['requester']);
    $requester_name = '';
    
    $sql_req = "SELECT name FROM users WHERE username = ?";
    $stmt_req = mysqli_prepare($conn, $sql_req);
    mysqli_stmt_bind_param($stmt_req, "s", $requester_username);
    mysqli_stmt_execute($stmt_req);
    mysqli_stmt_bind_result($stmt_req, $requester_name);
    mysqli_stmt_fetch($stmt_req);
    mysqli_stmt_close($stmt_req);
    
    $attachment = "";
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
        $sql = "INSERT INTO content_brief 
            (job_title, brand, platform, content_detail, content_format, due_date, attachment, remark, username, name, category, requester_username, requester_name, check_ad, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssssssi", 
                $job_title, $brand, $platform, $content_detail, $content_format, $due_date, $attachment, $remark, $username, $name, $category, $requester_username, $requester_name, $check_ad);
            if (mysqli_stmt_execute($stmt)) {
                $brief_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                try {
                    $pumble = new PumbleNotification();
                    
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $brief_url = "$protocol://$host/content/detail_content.php?id=$brief_id";
                    
                    $due_date_thai = date('d/m/Y', strtotime($due_date));
                    
                    $briefData = array(
                        'job_title' => $job_title,
                        'brand' => $brand,
                        'platform' => $platform ? $platform : '-',
                        'category' => $category ? $category : '-',
                        'due_date' => $due_date_thai,
                        'name' => $name,
                        'username' => $username,
                        'requester_name' => $requester_name,
                        'remark' => $remark,
                        'attachment' => $attachment,
                        'url' => $brief_url
                    );
                    
                    $pumbleSuccess = $pumble->notifyNewBrief($briefData);
                    
                    if ($pumbleSuccess) {
                        error_log("Pumble notification sent successfully: New brief #$brief_id created");
                    } else {
                        error_log("Pumble notification failed: New brief #$brief_id created");
                    }
                    
                } catch (Exception $e) {
                    error_log("Pumble notification error: " . $e->getMessage());
                }
                
                $success = "บันทึกบรีฟงานเรียบร้อยแล้ว! กำลังนำคุณกลับไปหน้า Dashboard...";
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'user_dashboard.php';
                    }, 2000);
                </script>";
            } else {
                $error = "บันทึกข้อมูลผิดพลาด: " . mysqli_stmt_error($stmt);
            }
        } else {
            $error = "เตรียมคำสั่ง SQL ผิดพลาด";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งบรีฟงาน - Topologie Daily</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .user-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .user-info strong {
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        label.required::after {
            content: " *";
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="date"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            min-height: 120px;
            line-height: 1.6;
            resize: vertical;
        }
        
        .checkbox-group,
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .checkbox-group label,
        .radio-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
            cursor: pointer;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        
        .checkbox-group label:hover,
        .radio-group label:hover {
            background: #e8edff;
        }
        
        .checkbox-group input[type="checkbox"],
        .radio-group input[type="radio"] {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .ad-check-container {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
            margin-top: 10px;
        }
        
        .ad-check-container label {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #856404;
            cursor: pointer;
            font-size: 16px;
        }
        
        .ad-check-container input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
            transform: scale(1.3);
            cursor: pointer;
        }
        
        .format-hint {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            padding: 12px;
            background: #f0f8ff;
            border-left: 3px solid #667eea;
            border-radius: 6px;
        }
        
        .format-hint strong {
            color: #667eea;
        }
        
        .message {
            margin-bottom: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .error {
            color: #c0392b;
            background: #ffe6e6;
            border-left: 4px solid #e74c3c;
        }
        
        .success {
            color: #27ae60;
            background: #e6ffe6;
            border-left: 4px solid #2ecc71;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.4);
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: #764ba2;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 22px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .checkbox-group,
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📝 ส่งบรีฟงาน</h1>
    </div>
    
    <div class="user-info">
        ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong><br>
        <span style="font-size: 14px; opacity: 0.9;">(<?php echo htmlspecialchars($username); ?>)</span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="message error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="message success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="brief_form.php<?php echo !empty($preset_date) ? '?date=' . urlencode($preset_date) : ''; ?>" enctype="multipart/form-data">
        <div class="form-group">
            <label class="required">ชื่องาน</label>
            <input type="text" name="job_title" required placeholder="กรอกชื่องาน">
        </div>

        <div class="form-group">
            <label class="required">แบรนด์</label>
            <input type="text" name="brand" required placeholder="กรอกชื่อแบรนด์">
        </div>

        <div class="form-group">
            <label class="required">ผู้สั่งงาน</label>
            <select name="requester" required>
                <option value="">-- เลือกผู้สั่งงาน --</option>
                <?php foreach ($users_list as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['username']); ?>">
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>สำหรับ (เลือกได้หลายตัว)</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="platform[]" value="FB"> Facebook</label>
                <label><input type="checkbox" name="platform[]" value="IG"> Instagram</label>
                <label><input type="checkbox" name="platform[]" value="TT"> TikTok</label>
                <label><input type="checkbox" name="platform[]" value="Website"> Website</label>
                <label><input type="checkbox" name="platform[]" value="media"> In mall media</label>
                <label><input type="checkbox" name="platform[]" value="print"> For print</label>
            </div>
        </div>

        <div class="form-group">
            <label class="required">หมวดหมู่</label>
            <div class="radio-group">
                <label><input type="radio" name="category" value="New arrival" required> New arrival</label>
                <label><input type="radio" name="category" value="Highlight"> Highlight</label>
                <label><input type="radio" name="category" value="Promotion"> Promotion</label>
                <label><input type="radio" name="category" value="Event"> Event</label>
            </div>
        </div>

        <div class="form-group">
            <div class="ad-check-container">
                <label>
                    <input type="checkbox" name="check_ad" value="1">
                    ⭐ AD - คอนเทนต์นี้ต้องการการตรวจสอบพิเศษ
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="required">รายละเอียด</label>
            <textarea name="content_detail" rows="6" required placeholder="กรอกรายละเอียดงาน สามารถขึ้นบรรทัดใหม่ได้ตามต้องการ"></textarea>
            <div class="format-hint">
                <strong>💡 คำแนะนำ:</strong> ข้อความที่พิมพ์จะแสดงผลตามรูปแบบที่คุณจัด รวมถึงการขึ้นบรรทัดใหม่
            </div>
        </div>

        <div class="form-group">
            <label>รูปแบบเนื้อหา</label>
            <input type="text" name="content_format" placeholder="เช่น รูป + ข้อความ, วิดีโอ, etc.">
        </div>

        <div class="form-group">
            <label class="required">กำหนดวันส่งงาน</label>
            <input type="date" name="due_date" required value="<?php echo htmlspecialchars($preset_date); ?>">
        </div>

        <div class="form-group">
            <label>ส่งไฟล์แนบ</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="padding: 10px;">
        </div>

        <div class="form-group">
            <label>หมายเหตุ</label>
            <textarea name="remark" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">✅ ส่งบรีฟ</button>
            <a href="user_dashboard.php" class="btn btn-secondary">← กลับ</a>
        </div>
    </form>
</div>

</body>
</html>