<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? 0;

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT * FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $location_type = $_POST['location_type'] ?? 'headquarters';
    $branch_name = trim($_POST['branch_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    // Validation
    if ($location_type == 'branch' && empty($branch_name)) {
        $error = "กรุณาระบุชื่อสาขา";
    } else {
        $branch_value = ($location_type == 'branch') ? $branch_name : null;
        
        $sql_update = "UPDATE users SET location_type = ?, branch_name = ?, department = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sssi", $location_type, $branch_value, $department, $user_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $message = "อัปเดตข้อมูลเรียบร้อยแล้ว";
            header("refresh:2;url=dashboard_content.php");
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
        
        mysqli_stmt_close($stmt_update);
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏢 อัปเดตข้อมูลสถานที่</title>
    <link rel="stylesheet" href="dashboard_styles.css">
    <style>
        .location-form-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
        }
        
        .radio-item {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .radio-item:hover {
            border-color: #667eea;
        }
        
        .radio-item.selected {
            border-color: #667eea;
            background: #f0f3ff;
        }
        
        .radio-item input {
            margin-right: 8px;
        }
        
        .branch-field {
            display: none;
            margin-top: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="location-form-container">
    <h2 style="text-align: center; margin-bottom: 30px;">🏢 อัปเดตข้อมูลสถานที่</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label">ประเภทสถานที่</label>
            <div class="radio-group">
                <label class="radio-item <?php echo ($user['location_type'] == 'headquarters' || !$user['location_type']) ? 'selected' : ''; ?>" data-location="headquarters">
                    <input type="radio" name="location_type" value="headquarters" 
                           <?php echo ($user['location_type'] == 'headquarters' || !$user['location_type']) ? 'checked' : ''; ?>>
                    🏢 สำนักงานใหญ่
                </label>
                <label class="radio-item <?php echo $user['location_type'] == 'branch' ? 'selected' : ''; ?>" data-location="branch">
                    <input type="radio" name="location_type" value="branch"
                           <?php echo $user['location_type'] == 'branch' ? 'checked' : ''; ?>>
                    🏪 สาขา
                </label>
            </div>
        </div>
        
        <div class="branch-field" id="branchField" <?php echo $user['location_type'] == 'branch' ? 'style="display:block;"' : ''; ?>>
            <div class="form-group">
                <label class="form-label">ชื่อสาขา</label>
                <input type="text" name="branch_name" class="form-input" 
                       placeholder="เช่น สาขาสยาม, สาขาเซ็นทรัล..."
                       value="<?php echo htmlspecialchars($user['branch_name'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">แผนก (ถ้ามี)</label>
            <input type="text" name="department" class="form-input" 
                   placeholder="เช่น การตลาด, บัญชี, ขาย..."
                   value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
        </div>
        
        <button type="submit" class="submit-btn">💾 บันทึกข้อมูล</button>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="dashboard_content.php" style="color: #667eea; text-decoration: none;">← กลับหน้าหลัก</a>
        </div>
    </form>
</div>

<script>
// Radio selection handling
document.querySelectorAll('[data-location]').forEach(item => {
    const radio = item.querySelector('input[type="radio"]');
    
    radio.addEventListener('change', function() {
        // Remove selected class from all
        document.querySelectorAll('[data-location]').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Add to current
        if (this.checked) {
            item.classList.add('selected');
            
            // Show/hide branch field
            const branchField = document.getElementById('branchField');
            if (this.value === 'branch') {
                branchField.style.display = 'block';
            } else {
                branchField.style.display = 'none';
            }
        }
    });
});
</script>

</body>
</html>