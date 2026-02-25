<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config.php";
require_once "pumble_notification.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];
$user_name = '';
$role = '';

// ดึงข้อมูล user
$sql_user = "SELECT name, role FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $user_name, $role);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// ดึงรายชื่อผู้ใช้ทั้งหมดสำหรับ dropdown
$users_list = array();
$sql_users = "SELECT username, name FROM users ORDER BY name ASC";
$result_users = mysqli_query($conn, $sql_users);
if ($result_users) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $users_list[] = $row;
    }
    mysqli_free_result($result_users);
}

// รับค่าจาก URL (ถ้ามีการคลิกจากปฏิทิน)
$preselect_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$preselect_time = isset($_GET['time']) ? $_GET['time'] : '12:00';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $job_title = mysqli_real_escape_string($conn, trim($_POST['job_title']));
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $assignee_username = mysqli_real_escape_string($conn, $_POST['assignee_username']);
    $brief_date = $_POST['brief_date'];
    $post_date = !empty($_POST['post_date']) ? $_POST['post_date'] : NULL;
    $post_time = !empty($_POST['post_time']) ? $_POST['post_time'] : NULL;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Platform
    $platform = '';
    if (isset($_POST['platform']) && is_array($_POST['platform'])) {
        $platform_array = array_map(function($p) use ($conn) {
            return mysqli_real_escape_string($conn, $p);
        }, $_POST['platform']);
        $platform = implode(',', $platform_array);
    }
    
    // ดึงชื่อผู้รับผิดชอบ
    $assignee_name = '';
    $sql_assignee = "SELECT name FROM users WHERE username = ?";
    $stmt_assignee = mysqli_prepare($conn, $sql_assignee);
    mysqli_stmt_bind_param($stmt_assignee, "s", $assignee_username);
    mysqli_stmt_execute($stmt_assignee);
    mysqli_stmt_bind_result($stmt_assignee, $assignee_name);
    mysqli_stmt_fetch($stmt_assignee);
    mysqli_stmt_close($stmt_assignee);
    
    // คำนวณ engage_date (+14 วันจาก post_date แต่แสดงในวันเดียวกับ post_date)
    $engage_date = NULL;
    $engage_due_date = NULL;
    if (!empty($post_date)) {
        $engage_date = $post_date; // วันที่แสดงใน View Engage
        $engage_due_date = date('Y-m-d', strtotime($post_date . ' +14 days')); // วันครบกำหนด 14 วัน
    }
    
    // บันทึกข้อมูล
    $sql = "INSERT INTO hooga_calendar_events 
            (job_title, category, assignee, assignee_username, brief_date, post_date, post_time,
             engage_date, engage_due_date, description, platform, status, engage_status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssssssssss",
            $job_title, $category, $assignee_name, $assignee_username, 
            $brief_date, $post_date, $post_time, $engage_date, $engage_due_date, 
            $description, $platform, $status, $username
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            // ส่งการแจ้งเตือนไปยัง Pumble
            try {
                $pumble = new PumbleNotification();
                
                $message = "📅 *เพิ่มงานใหม่ในปฏิทิน*\n\n";
                $message .= "📋 ชื่องาน: $job_title\n";
                $message .= "📂 หมวดหมู่: $category\n";
                $message .= "👤 ผู้รับผิดชอบ: $assignee_name\n";
                $message .= "📅 วันที่บรีฟ: " . date('d/m/Y', strtotime($brief_date)) . "\n";
                
                if (!empty($post_date)) {
                    $message .= "📤 วันที่โพสต์: " . date('d/m/Y', strtotime($post_date));
                    if (!empty($post_time)) {
                        $message .= " เวลา " . $post_time;
                    }
                    $message .= "\n";
                }
                
                if (!empty($engage_due_date)) {
                    $message .= "⏰ วันทำ Engage: " . date('d/m/Y', strtotime($engage_due_date)) . "\n";
                }
                
                $message .= "👤 สร้างโดย: $user_name\n";
                $message .= "\n🔗 ดูรายละเอียด: https://www.weedjai.com/content/daily/calendar_detail.php?id=$new_id";
                
                $pumble->sendToRoles($message, ['admin', 'marketing']);
                
            } catch (Exception $e) {
                error_log("Pumble notification error: " . $e->getMessage());
            }
            
            $success = "เพิ่มงานเรียบร้อยแล้ว!";
            
            // Redirect หลัง 2 วินาที
            header("refresh:2;url=index.php");
        } else {
            $error = "เกิดข้อผิดพลาด: " . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $error = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>เพิ่มงานในปฏิทิน</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        margin: 0;
        padding: 20px;
      }
      .container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      h1 {
        color: #667eea;
        margin-bottom: 10px;
      }
      .user-info {
        color: #666;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #ecf0f1;
      }
      
      .form-group {
        margin-bottom: 20px;
      }
      label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
      }
      label .required {
        color: #e74c3c;
      }
      input[type="text"],
      input[type="date"],
      input[type="time"],
      select,
      textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-family: inherit;
        font-size: 1em;
        box-sizing: border-box;
        transition: border-color 0.3s ease;
      }
      input:focus,
      select:focus,
      textarea:focus {
        outline: none;
        border-color: #667eea;
      }
      textarea {
        resize: vertical;
        min-height: 100px;
        line-height: 1.6;
      }
      
      .date-time-group {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 10px;
      }
      
      .checkbox-group {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
      }
      .checkbox-group label {
        display: flex;
        align-items: center;
        font-weight: normal;
        cursor: pointer;
      }
      .checkbox-group input[type="checkbox"] {
        width: auto;
        margin-right: 5px;
      }
      
      .info-box {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
      }
      .info-box strong {
        color: #1565c0;
      }
      
      .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
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
      
      .button-group {
        display: flex;
        gap: 15px;
        margin-top: 30px;
      }
      .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
      }
      .btn-primary {
        background: #667eea;
        color: white;
      }
      .btn-primary:hover {
        background: #5568d3;
        transform: translateY(-2px);
      }
      .btn-secondary {
        background: #95a5a6;
        color: white;
      }
      .btn-secondary:hover {
        background: #7f8c8d;
      }
      
      @media (max-width: 768px) {
        body {
          padding: 10px;
        }
        .container {
          padding: 20px;
        }
        .date-time-group {
          grid-template-columns: 1fr;
        }
        .button-group {
          flex-direction: column;
        }
      }
    </style>
</head>
<body>

<div class="container">
    <h1>➕ เพิ่มงานในปฏิทิน</h1>
    <div class="user-info">
        ผู้ใช้งาน: <strong><?php echo htmlspecialchars($user_name); ?></strong> (<?php echo htmlspecialchars($username); ?>)
    </div>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
        ✅ <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-error">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <div class="info-box">
        <strong>💡 คำแนะนำ:</strong> วันทำ Engage จะถูกคำนวณอัตโนมัติเป็น 14 วันหลังจากวันที่โพสต์ แต่จะแสดงลิงก์ใน View Engage ในวันเดียวกับโพสต์
    </div>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>ชื่องาน / หัวข้อ <span class="required">*</span></label>
            <input type="text" name="job_title" required placeholder="ระบุชื่องานหรือหัวข้อคอนเทนต์">
        </div>
        
        <div class="form-group">
            <label>หมวดหมู่ <span class="required">*</span></label>
            <select name="category" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <option value="Product Knowledge">Product Knowledge</option>
                <option value="Promotion">Promotion</option>
                <option value="Lifestyle">Lifestyle</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>ผู้รับผิดชอบ <span class="required">*</span></label>
            <select name="assignee_username" required>
                <option value="">-- เลือกผู้รับผิดชอบ --</option>
                <?php foreach ($users_list as $user): ?>
                <option value="<?php echo htmlspecialchars($user['username']); ?>">
                    <?php echo htmlspecialchars($user['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>วันที่บรีฟ <span class="required">*</span></label>
            <input type="date" name="brief_date" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <div class="form-group">
            <label>วันที่และเวลาโพสต์ (ถ้ามี)</label>
            <div class="date-time-group">
                <input type="date" name="post_date" value="<?php echo $preselect_date; ?>" onchange="calculateEngageDate()">
                <input type="time" name="post_time" value="<?php echo $preselect_time; ?>">
            </div>
            <small style="color: #666; display: block; margin-top: 5px;">
                ⏰ วันทำ Engage จะเป็น: <span id="engage_preview" style="font-weight: bold;">-</span>
            </small>
        </div>
        
        <div class="form-group">
            <label>แพลตฟอร์ม (เลือกได้หลายตัว)</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="platform[]" value="FB"> Facebook</label>
                <label><input type="checkbox" name="platform[]" value="IG"> Instagram</label>
                <label><input type="checkbox" name="platform[]" value="TT"> TikTok</label>
                <label><input type="checkbox" name="platform[]" value="Website"> Website</label>
                <label><input type="checkbox" name="platform[]" value="Youtube"> Youtube</label>
            </div>
        </div>
        
        <div class="form-group">
            <label>รายละเอียด</label>
            <textarea name="description" placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
        </div>
        
        <div class="form-group">
            <label>สถานะ <span class="required">*</span></label>
            <select name="status" required>
                <option value="planned" selected>วางแผน</option>
                <option value="in_progress">กำลังทำ</option>
                <option value="posted">โพสต์แล้ว</option>
                <option value="completed">เสร็จสิ้น</option>
            </select>
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn btn-primary">💾 บันทึก</button>
            <a href="index.php" class="btn btn-secondary">❌ ยกเลิก</a>
        </div>
    </form>
</div>

<script>
function calculateEngageDate() {
    const postDateInput = document.querySelector('input[name="post_date"]');
    const engagePreview = document.getElementById('engage_preview');
    
    if (postDateInput.value) {
        const postDate = new Date(postDateInput.value);
        const engageDate = new Date(postDate);
        engageDate.setDate(engageDate.getDate() + 14);
        
        const day = String(engageDate.getDate()).padStart(2, '0');
        const month = String(engageDate.getMonth() + 1).padStart(2, '0');
        const year = engageDate.getFullYear();
        
        engagePreview.textContent = `${day}/${month}/${year} (อีก 14 วันหลังโพสต์)`;
        engagePreview.style.color = '#27ae60';
    } else {
        engagePreview.textContent = '-';
        engagePreview.style.color = '#666';
    }
}

// เรียกครั้งแรกเมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    calculateEngageDate();
});
</script>

</body>
</html>