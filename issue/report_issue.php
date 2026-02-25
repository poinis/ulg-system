<?php
require_once "config.php";
require_once "pumble_notification.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];

// ดึงข้อมูลผู้ใช้ รวมถึง id เพื่อความแน่ใจ
$sql_user = "SELECT id, name, role, location_type, branch_name, department FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $user_id, $name, $role, $location_type, $branch_name, $department);
$fetch_result = mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

if (!$fetch_result || !$user_id) {
    die("ข้อผิดพลาด: ไม่พบข้อมูลผู้ใช้ในระบบ กรุณาเข้าสู่ระบบใหม่");
}

$is_admin = in_array($role, ['admin', 'support']);

// สร้าง location string
$location = ($location_type == 'headquarters') ? 'สำนักงานใหญ่' : "สาขา: $branch_name";
if ($department) {
    $location .= " | แผนก: $department";
}

// ดึงหมวดหมู่ปัญหา
$sql_categories = "SELECT id, name_th, name_en, icon FROM issue_categories WHERE is_active = 1 ORDER BY display_order";
$result_categories = mysqli_query($conn, $sql_categories);
$categories = [];
while ($row = mysqli_fetch_assoc($result_categories)) {
    $categories[] = $row;
}

// ประมวลผลฟอร์ม
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $issue_types = $_POST['issue_types'] ?? [];
    $issue_other = trim($_POST['issue_other'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $urgency = $_POST['urgency'] ?? 'normal';
    $suggestions = trim($_POST['suggestions'] ?? '');
    
    // Validation
    if (empty($issue_types)) {
        $error = "กรุณาเลือกประเภทปัญหาอย่างน้อย 1 ข้อ";
    } elseif (empty($description)) {
        $error = "กรุณาระบุรายละเอียดปัญหา";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // สร้างเลขที่ปัญหา
            $stmt_number = mysqli_prepare($conn, "CALL generate_issue_number(@new_number)");
            mysqli_stmt_execute($stmt_number);
            mysqli_stmt_close($stmt_number);
            
            $result_number = mysqli_query($conn, "SELECT @new_number as issue_number");
            $row_number = mysqli_fetch_assoc($result_number);
            $issue_number = $row_number['issue_number'];
            
            // เตรียมข้อมูล
            $issue_types_json = json_encode($issue_types, JSON_UNESCAPED_UNICODE);
            $issue_other_value = in_array('other', $issue_types) ? $issue_other : null;
            
            // บันทึกปัญหา
            $sql = "INSERT INTO issues 
                    (issue_number, reporter_id, reporter_name, reporter_location, 
                     issue_types, issue_other_type, issue_description, urgency_level, suggestions)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sisssssss", 
                $issue_number, $user_id, $name, $location,
                $issue_types_json, $issue_other_value, $description, $urgency, $suggestions
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $issue_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                // บันทึกประวัติ
                $sql_history = "INSERT INTO issue_updates (issue_id, updated_by, updated_by_name, update_type, new_value)
                               VALUES (?, ?, ?, 'status_change', 'pending')";
                $stmt_history = mysqli_prepare($conn, $sql_history);
                mysqli_stmt_bind_param($stmt_history, "iis", $issue_id, $user_id, $name);
                mysqli_stmt_execute($stmt_history);
                mysqli_stmt_close($stmt_history);
                
                mysqli_commit($conn);
                
                // ส่งการแจ้งเตือนไปยัง Pumble
                sendIssueNotification($issue_number, $name, $location, $issue_types, $description, $urgency, $categories);
                
                $success = "แจ้งปัญหาสำเร็จ! เลขที่: $issue_number";
                
                // Redirect หลัง 2 วินาที
                header("refresh:2;url=issue_list.php");
            } else {
                throw new Exception("ไม่สามารถบันทึกข้อมูลได้: " . mysqli_error($conn));
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ฟังก์ชันส่งการแจ้งเตือน
function sendIssueNotification($issue_number, $reporter, $location, $types, $description, $urgency, $categories) {
    try {
        $pumble = new PumbleNotification();
        
        $urgency_icons = [
            'urgent' => '🔴',
            'medium' => '🟠',
            'normal' => '🟢'
        ];
        
        $urgency_text = [
            'urgent' => 'เร่งด่วนมาก',
            'medium' => 'ปานกลาง',
            'normal' => 'ทั่วไป'
        ];
        
        $message = "🚨 *แจ้งปัญหาใหม่*\n\n";
        $message .= "📋 เลขที่: *$issue_number*\n";
        $message .= "👤 ผู้แจ้ง: $reporter\n";
        $message .= "📍 สถานที่: $location\n\n";
        
        $message .= "🏷️ *ประเภทปัญหา:*\n";
        foreach ($types as $type_id) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $type_id) {
                    $message .= "  • {$cat['icon']} {$cat['name_th']}\n";
                    break;
                }
            }
        }
        
        $message .= "\n📝 *รายละเอียด:*\n" . substr($description, 0, 200);
        if (strlen($description) > 200) {
            $message .= "...";
        }
        
        $message .= "\n\n" . $urgency_icons[$urgency] . " *ระดับความเร่งด่วน:* " . $urgency_text[$urgency];
        $message .= "\n\n🔗 ดูรายละเอียดเพิ่มเติมที่ระบบ Issue Tracking";
        
        $pumble->sendToRoles($message, ['admin', 'support']);
        
    } catch (Exception $e) {
        error_log("Pumble notification error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 แจ้งปัญหา - Topologie Daily</title>
    <link rel="stylesheet" href="dashboard_styles.css?v=2.0">
    <style>
        .issue-form-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-label {
            display: block;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .form-label .required {
            color: #e74c3c;
            font-weight: 800;
        }
        
        .form-label small {
            color: #7f8c8d;
            font-weight: 500;
            font-size: 13px;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 12px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .checkbox-item:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.2);
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 22px;
            height: 22px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .checkbox-item.checked {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f3ff 0%, #e6ebff 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
        }
        
        .checkbox-label {
            flex: 1;
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .checkbox-sublabel {
            font-size: 13px;
            color: #7f8c8d;
            display: block;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 14px 18px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: white;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            min-height: 140px;
            resize: vertical;
            line-height: 1.8;
        }
        
        .radio-group {
            display: flex;
            gap: 18px;
            margin-top: 12px;
        }
        
        .radio-item {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 700;
            font-size: 15px;
        }
        
        .radio-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .radio-item.selected {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        
        .radio-item input[type="radio"] {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .urgency-urgent { border-color: #e74c3c !important; }
        .urgency-urgent.selected { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important; 
            color: white;
        }
        
        .urgency-medium { border-color: #f39c12 !important; }
        .urgency-medium.selected { 
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%) !important; 
            color: white;
        }
        
        .urgency-normal { border-color: #2ecc71 !important; }
        .urgency-normal.selected { 
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%) !important; 
            color: white;
        }
        
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: alertSlide 0.5s ease-out;
        }
        
        @keyframes alertSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .submit-btn:hover::before {
            width: 500px;
            height: 500px;
        }
        
        .submit-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        
        .user-info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .user-info-box::before {
            content: '👤';
            position: absolute;
            font-size: 120px;
            opacity: 0.1;
            right: -20px;
            bottom: -20px;
        }
        
        .user-info-box h3 {
            margin: 0 0 15px 0;
            font-size: 20px;
            position: relative;
            z-index: 1;
        }
        
        .user-info-box p {
            margin: 8px 0;
            opacity: 0.95;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }
        
        .user-info-box strong {
            color: #ffd700;
            font-weight: 700;
        }
        
        .debug-info {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 8px;
        }
        
        @media (max-width: 768px) {
            .issue-form-container {
                padding: 25px;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>🚨 แจ้งปัญหา</h1>
        <div class="user-info">
            ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong>
            <?php if ($is_admin): ?>
                <span class="admin-badge">👑 Admin/Support</span>
            <?php endif; ?>
        </div>
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
    <div class="issue-form-container">
        <div class="user-info-box">
            <h3>👤 ข้อมูลผู้แจ้ง</h3>
            <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($name); ?></p>
            <p><strong>สถานที่:</strong> <?php echo htmlspecialchars($location); ?></p>
            <p class="debug-info">User ID: <?php echo htmlspecialchars($user_id); ?> | Username: <?php echo htmlspecialchars($username); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="issueForm">
            <!-- 1. ประเภทปัญหา -->
            <div class="form-group">
                <label class="form-label">
                    1. กรุณาระบุประเภทของปัญหา <span class="required">*</span>
                    <small>(เลือกได้มากกว่า 1 ข้อ)</small>
                </label>
                <div class="checkbox-group">
                    <?php foreach ($categories as $cat): ?>
                    <label class="checkbox-item" data-checkbox="category">
                        <input type="checkbox" name="issue_types[]" value="<?php echo $cat['id']; ?>">
                        <span class="checkbox-label">
                            <?php echo $cat['icon'] . ' ' . htmlspecialchars($cat['name_th']); ?>
                            <span class="checkbox-sublabel"><?php echo htmlspecialchars($cat['name_en']); ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                    
                    <label class="checkbox-item" data-checkbox="other">
                        <input type="checkbox" name="issue_types[]" value="other" id="otherCheck">
                        <span class="checkbox-label">
                            ⚠️ อื่นๆ (โปรดระบุ)
                        </span>
                    </label>
                </div>
                
                <div id="otherField" style="display: none; margin-top: 15px;">
                    <input type="text" class="form-input" name="issue_other" placeholder="โปรดระบุประเภทอื่นๆ">
                </div>
            </div>
            
            <!-- 2. รายละเอียดปัญหา -->
            <div class="form-group">
                <label class="form-label" for="description">
                    2. รายละเอียดของปัญหา <span class="required">*</span>
                </label>
                <textarea class="form-textarea" name="description" id="description" 
                          placeholder="โปรดอธิบายปัญหาที่พบโดยละเอียด..." required></textarea>
            </div>
            
            <!-- 3. ระดับความเร่งด่วน -->
            <div class="form-group">
                <label class="form-label">
                    3. ระดับความเร่งด่วน <span class="required">*</span>
                </label>
                <div class="radio-group">
                    <label class="radio-item urgency-urgent" data-radio="urgency">
                        <input type="radio" name="urgency" value="urgent" required>
                        <span>🔴 เร่งด่วนมาก</span>
                    </label>
                    <label class="radio-item urgency-medium selected" data-radio="urgency">
                        <input type="radio" name="urgency" value="medium" checked required>
                        <span>🟠 ปานกลาง</span>
                    </label>
                    <label class="radio-item urgency-normal" data-radio="urgency">
                        <input type="radio" name="urgency" value="normal" required>
                        <span>🟢 ทั่วไป</span>
                    </label>
                </div>
            </div>
            
            <!-- 4. ข้อแนะนำ -->
            <div class="form-group">
                <label class="form-label" for="suggestions">
                    4. ข้อแนะนำ / การสนับสนุนด้านต่างๆ จากบริษัทเพื่อช่วยในการทำงาน
                </label>
                <textarea class="form-textarea" name="suggestions" id="suggestions" 
                          placeholder="หากมีข้อเสนอแนะเพิ่มเติม โปรดระบุที่นี่..."></textarea>
            </div>
            
            <button type="submit" class="submit-btn">📤 ส่งแจ้งปัญหา</button>
        </form>
    </div>
</div>

<script>
// Checkbox highlighting
document.querySelectorAll('[data-checkbox]').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            item.classList.add('checked');
        } else {
            item.classList.remove('checked');
        }
    });
});

// Radio highlighting
document.querySelectorAll('[data-radio]').forEach(item => {
    const radio = item.querySelector('input[type="radio"]');
    radio.addEventListener('change', function() {
        if (this.checked) {
            const group = this.name;
            document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
                r.closest('[data-radio]').classList.remove('selected');
            });
            item.classList.add('selected');
        }
    });
    
    if (radio.checked) {
        item.classList.add('selected');
    }
});

// Other field toggle
document.getElementById('otherCheck').addEventListener('change', function() {
    const otherField = document.getElementById('otherField');
    if (this.checked) {
        otherField.style.display = 'block';
    } else {
        otherField.style.display = 'none';
    }
});

// Form validation
document.getElementById('issueForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('input[name="issue_types[]"]');
    const checked = Array.from(checkboxes).some(cb => cb.checked);
    
    if (!checked) {
        e.preventDefault();
        alert('กรุณาเลือกประเภทปัญหาอย่างน้อย 1 ข้อ');
        return false;
    }
});
</script>

</body>
</html>