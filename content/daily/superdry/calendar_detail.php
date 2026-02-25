<?php
require_once "config.php";
require_once "pumble_notification.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ไม่พบข้อมูลงาน");
}

$event_id = intval($_GET['id']);
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

// จัดการการบันทึก Engage
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_engage'])) {
    $reach = intval($_POST['reach']);
    $impressions = intval($_POST['impressions']);
    $likes = intval($_POST['likes']);
    $comments = intval($_POST['comments']);
    $shares = intval($_POST['shares']);
    $saves = intval($_POST['saves']);
    $note = mysqli_real_escape_string($conn, trim($_POST['note']));
    
    // บันทึกหรืออัปเดต Engage
    $sql_check = "SELECT id FROM calendar_engage WHERE event_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $event_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $engage_exists = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);
    
    if ($engage_exists) {
        // อัปเดต
        $sql = "UPDATE calendar_engage 
                SET reach = ?, impressions = ?, likes = ?, comments = ?, shares = ?, saves = ?, 
                    note = ?, updated_by = ?, updated_at = NOW()
                WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiisssi", 
            $reach, $impressions, $likes, $comments, $shares, $saves, $note, $username, $event_id);
    } else {
        // เพิ่มใหม่
        $engage_date = date('Y-m-d');
        $sql = "INSERT INTO calendar_engage 
                (event_id, engage_date, reach, impressions, likes, comments, shares, saves, note, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isiiiiiiss", 
            $event_id, $engage_date, $reach, $impressions, $likes, $comments, $shares, $saves, $note, $username);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        // อัปเดตสถานะ engage_status ในตาราง calendar_events
        $sql_update_status = "UPDATE calendar_events SET engage_status = 'completed' WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update_status);
        mysqli_stmt_bind_param($stmt_update, "i", $event_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        $success = "บันทึกข้อมูล Engage เรียบร้อยแล้ว!";
        
        // ส่งการแจ้งเตือนไปยัง Pumble
        try {
            $pumble = new PumbleNotification();
            
            // ดึงข้อมูลงาน
            $sql_event = "SELECT job_title, category FROM calendar_events WHERE id = ?";
            $stmt_event = mysqli_prepare($conn, $sql_event);
            mysqli_stmt_bind_param($stmt_event, "i", $event_id);
            mysqli_stmt_execute($stmt_event);
            mysqli_stmt_bind_result($stmt_event, $job_title, $category);
            mysqli_stmt_fetch($stmt_event);
            mysqli_stmt_close($stmt_event);
            
            $message = "📊 *อัปเดต Engage แล้ว*\n\n";
            $message .= "📋 งาน: $job_title\n";
            $message .= "📂 หมวดหมู่: $category\n\n";
            $message .= "📈 ผลลัพธ์:\n";
            $message .= "• Reach: " . number_format($reach) . "\n";
            $message .= "• Impressions: " . number_format($impressions) . "\n";
            $message .= "• Likes: " . number_format($likes) . "\n";
            $message .= "• Comments: " . number_format($comments) . "\n";
            $message .= "• Shares: " . number_format($shares) . "\n";
            $message .= "• Saves: " . number_format($saves) . "\n";
            
            if (!empty($note)) {
                $message .= "\n📝 หมายเหตุ: $note\n";
            }
            
            $message .= "\n👤 บันทึกโดย: $user_name\n";
            $message .= "⏰ เวลา: " . date('d/m/Y H:i:s') . "\n";
            $message .= "\n🔗 ดูรายละเอียด: https://www.weedjai.com/content/calendar_detail.php?id=$event_id";
            
            $pumble->sendToRoles($message, ['admin', 'marketing']);
            
        } catch (Exception $e) {
            error_log("Pumble notification error: " . $e->getMessage());
        }
        
    } else {
        $error = "เกิดข้อผิดพลาด: " . mysqli_stmt_error($stmt);
    }
    
    mysqli_stmt_close($stmt);
}

// ดึงข้อมูลงาน
$sql_event = "SELECT * FROM calendar_events WHERE id = ?";
$stmt_event = mysqli_prepare($conn, $sql_event);
mysqli_stmt_bind_param($stmt_event, "i", $event_id);
mysqli_stmt_execute($stmt_event);
$result = mysqli_stmt_get_result($stmt_event);
$event = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt_event);

if (!$event) {
    die("ไม่พบข้อมูลงานนี้");
}

// ดึงข้อมูล Engage (ถ้ามี)
$engage_data = null;
$sql_engage = "SELECT * FROM calendar_engage WHERE event_id = ?";
$stmt_engage = mysqli_prepare($conn, $sql_engage);
mysqli_stmt_bind_param($stmt_engage, "i", $event_id);
mysqli_stmt_execute($stmt_engage);
$result_engage = mysqli_stmt_get_result($stmt_engage);
$engage_data = mysqli_fetch_assoc($result_engage);
mysqli_stmt_close($stmt_engage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>รายละเอียดงาน: <?php echo htmlspecialchars($event['job_title']); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
/* Common Calendar Styles */
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #f5f7fa;
  margin: 0;
  padding: 20px;
  color: #333;
}

.container {
  max-width: 900px;
  margin: 0 auto;
}

/* Header Card */
.header-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  margin-bottom: 20px;
}

.header-card h1 {
  font-size: 2em;
  margin-bottom: 10px;
}

.header-meta {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  margin-top: 15px;
  opacity: 0.9;
}

/* Card */
.card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin-bottom: 20px;
}

.card h2 {
  color: #2c3e50;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #ecf0f1;
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 15px;
}

.info-item {
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #667eea;
}

.info-item label {
  display: block;
  font-weight: 600;
  color: #666;
  font-size: 0.85em;
  margin-bottom: 5px;
}

.info-item .value {
  font-size: 1.1em;
  color: #2c3e50;
  font-weight: 600;
}

/* Badges */
.badge {
  padding: 6px 15px;
  border-radius: 20px;
  font-size: 0.9em;
  font-weight: 600;
  display: inline-block;
}

.badge-category {
  background: #e3f2fd;
  color: #1976d2;
}

.badge-status {
  background: #fff3e0;
  color: #f57c00;
}

.badge-status.posted {
  background: #e8f5e9;
  color: #388e3c;
}

.badge-status.completed {
  background: #e1f5fe;
  color: #0277bd;
}

.badge-pending {
  background: #fff3cd;
  color: #856404;
}

.badge-completed {
  background: #d4edda;
  color: #155724;
}

.badge-overdue {
  background: #f8d7da;
  color: #721c24;
}

/* Engage Alert */
.engage-alert {
  background: #fff3cd;
  border: 2px solid #ffc107;
  padding: 15px;
  border-radius: 8px;
  margin-top: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.engage-alert.completed {
  background: #d4edda;
  border-color: #28a745;
}

.engage-alert.overdue {
  background: #f8d7da;
  border-color: #dc3545;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.stat-box {
  text-align: center;
  padding: 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.stat-box strong {
  display: block;
  font-size: 2em;
  margin-bottom: 5px;
}

.stat-box span {
  font-size: 0.9em;
  opacity: 0.9;
}

/* Buttons */
.btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
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

.btn-success {
  background: #27ae60;
  color: white;
}

.btn-success:hover {
  background: #229954;
}

.btn-danger {
  background: #e74c3c;
  color: white;
}

.btn-danger:hover {
  background: #c0392b;
}

.button-group {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  margin-top: 20px;
}

/* Engage Form */
.engage-form {
  display: none;
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 2px solid #667eea;
  margin-top: 15px;
}

.engage-form.active {
  display: block;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 5px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-family: inherit;
  font-size: 1em;
  box-sizing: border-box;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

/* Alerts */
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

/* Result Summary */
.result-summary {
  background: #e3f2fd;
  padding: 15px;
  border-radius: 8px;
  margin-top: 15px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
}

.result-item {
  text-align: center;
}

.result-item strong {
  display: block;
  font-size: 1.5em;
  color: #1976d2;
}

.result-item span {
  font-size: 0.85em;
  color: #666;
}

/* Responsive */
@media (max-width: 768px) {
  body {
    padding: 10px;
  }
  
  .header-card {
    padding: 20px;
  }
  
  .header-card h1 {
    font-size: 1.5em;
  }
  
  .info-grid,
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .result-summary {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .button-group {
    flex-direction: column;
  }
}
    </style>
</head>
<body>

<div class="container">
    
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
    
    <!-- Header Card -->
    <div class="header-card">
        <h1><?php echo htmlspecialchars($event['job_title']); ?></h1>
        <div class="header-meta">
            <span>📂 <?php echo htmlspecialchars($event['category']); ?></span>
            <span>👤 <?php echo htmlspecialchars($event['assignee']); ?></span>
            <span>📅 <?php echo date('d/m/Y', strtotime($event['brief_date'])); ?></span>
        </div>
    </div>
    
    <!-- ข้อมูลหลัก -->
    <div class="card">
        <h2>📋 ข้อมูลงาน</h2>
        
        <div class="info-grid">
            <div class="info-item">
                <label>หมวดหมู่</label>
                <div class="value">
                    <span class="badge badge-category"><?php echo htmlspecialchars($event['category']); ?></span>
                </div>
            </div>
            
            <div class="info-item">
                <label>สถานะ</label>
                <div class="value">
                    <span class="badge badge-status <?php echo $event['status']; ?>">
                        <?php 
                        $status_text = [
                            'planned' => 'วางแผน',
                            'in_progress' => 'กำลังทำ',
                            'posted' => 'โพสต์แล้ว',
                            'completed' => 'เสร็จสิ้น',
                            'cancelled' => 'ยกเลิก'
                        ];
                        echo $status_text[$event['status']] ?? $event['status'];
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="info-item">
                <label>ผู้รับผิดชอบ</label>
                <div class="value"><?php echo htmlspecialchars($event['assignee']); ?></div>
            </div>
            
            <div class="info-item">
                <label>วันที่บรีฟ</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($event['brief_date'])); ?></div>
            </div>
            
            <?php if (!empty($event['post_date'])): ?>
            <div class="info-item">
                <label>วันที่โพสต์</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($event['post_date'])); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($event['post_time'])): ?>
            <div class="info-item">
                <label>เวลาโพสต์</label>
                <div class="value"><?php echo htmlspecialchars($event['post_time']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($event['platform'])): ?>
            <div class="info-item">
                <label>แพลตฟอร์ม</label>
                <div class="value"><?php echo htmlspecialchars($event['platform']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($event['description'])): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
            <label style="display: block; font-weight: 600; color: #666; margin-bottom: 10px;">📝 รายละเอียด</label>
            <div style="color: #2c3e50; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($event['engage_date'])): ?>
        <?php
        $today = date('Y-m-d');
        $is_completed = ($event['engage_status'] === 'completed');
        $is_overdue = (!$is_completed && $event['engage_date'] < $today);
        $alert_class = $is_completed ? 'completed' : ($is_overdue ? 'overdue' : '');
        ?>
        <div class="engage-alert <?php echo $alert_class; ?>">
            <?php if ($is_completed): ?>
                <strong>✅</strong> 
                <div>
                    <strong>Engage เสร็จแล้ว</strong><br>
                    <small>วันที่กำหนด: <?php echo date('d/m/Y', strtotime($event['engage_date'])); ?></small>
                </div>
            <?php elseif ($is_overdue): ?>
                <strong>⚠️</strong>
                <div>
                    <strong>เลยกำหนด Engage!</strong><br>
                    <small>กำหนดการ: <?php echo date('d/m/Y', strtotime($event['engage_date'])); ?></small>
                </div>
            <?php else: ?>
                <strong>⏰</strong>
                <div>
                    <strong>ต้องทำ Engage: <?php echo date('d/m/Y', strtotime($event['engage_date'])); ?></strong><br>
                    <small>
                        <?php
                        $days_until = (strtotime($event['engage_date']) - strtotime($today)) / 86400;
                        if ($days_until == 0) {
                            echo "วันนี้!";
                        } elseif ($days_until == 1) {
                            echo "พรุ่งนี้";
                        } else {
                            echo "อีก " . ceil($days_until) . " วัน";
                        }
                        ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ผลลัพธ์ Engage -->
    <?php if ($engage_data): ?>
    <div class="card">
        <h2>📊 ผลลัพธ์ Engage</h2>
        
        <div class="stats-grid">
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['reach']); ?></strong>
                <span>Reach</span>
            </div>
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['impressions']); ?></strong>
                <span>Impressions</span>
            </div>
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['likes']); ?></strong>
                <span>Likes</span>
            </div>
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['comments']); ?></strong>
                <span>Comments</span>
            </div>
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['shares']); ?></strong>
                <span>Shares</span>
            </div>
            <div class="stat-box">
                <strong><?php echo number_format($engage_data['saves']); ?></strong>
                <span>Saves</span>
            </div>
        </div>
        
        <?php if (!empty($engage_data['note'])): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <label style="display: block; font-weight: 600; color: #666; margin-bottom: 10px;">📝 หมายเหตุ</label>
            <div style="color: #2c3e50;">
                <?php echo nl2br(htmlspecialchars($engage_data['note'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
            บันทึกโดย: <?php echo htmlspecialchars($engage_data['updated_by']); ?> 
            | <?php echo date('d/m/Y H:i', strtotime($engage_data['updated_at'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ฟอร์มบันทึก/แก้ไข Engage -->
    <?php if (!empty($event['engage_date'])): ?>
    <div class="card">
        <h2>📊 <?php echo $engage_data ? 'แก้ไข Engage' : 'บันทึก Engage'; ?></h2>
        
        <button class="btn btn-success" onclick="toggleEngageForm()">
            <?php echo $engage_data ? '✏️ แก้ไขข้อมูล Engage' : '📊 เริ่มบันทึก Engage'; ?>
        </button>
        
        <div class="engage-form" id="engage-form">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Reach</label>
                        <input type="number" name="reach" value="<?php echo $engage_data['reach'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Impressions</label>
                        <input type="number" name="impressions" value="<?php echo $engage_data['impressions'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Likes</label>
                        <input type="number" name="likes" value="<?php echo $engage_data['likes'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Comments</label>
                        <input type="number" name="comments" value="<?php echo $engage_data['comments'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Shares</label>
                        <input type="number" name="shares" value="<?php echo $engage_data['shares'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Saves</label>
                        <input type="number" name="saves" value="<?php echo $engage_data['saves'] ?? 0; ?>" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <textarea name="note" placeholder="บันทึกข้อสังเกต หรือหมายเหตุเพิ่มเติม..."><?php echo htmlspecialchars($engage_data['note'] ?? ''); ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="save_engage" class="btn btn-success">💾 บันทึก</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleEngageForm()">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ข้อมูลระบบ -->
    <div class="card">
        <h2>ℹ️ ข้อมูลระบบ</h2>
        
        <div class="info-grid">
            <div class="info-item">
                <label>สร้างโดย</label>
                <div class="value"><?php echo htmlspecialchars($event['created_by']); ?></div>
            </div>
            
            <div class="info-item">
                <label>วันที่สร้าง</label>
                <div class="value"><?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?></div>
            </div>
            
            <?php if ($event['updated_at'] != $event['created_at']): ?>
            <div class="info-item">
                <label>แก้ไขล่าสุด</label>
                <div class="value"><?php echo date('d/m/Y H:i', strtotime($event['updated_at'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ปุ่มดำเนินการ -->
    <div class="card">
        <div class="button-group">
            <a href="calendar_dashboard.php" class="btn btn-secondary">🔙 กลับปฏิทิน</a>
            
            <?php if ($role === 'admin'): ?>
            <a href="calendar_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">✏️ แก้ไข</a>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<script>
function toggleEngageForm() {
    const form = document.getElementById('engage-form');
    form.classList.toggle('active');
}

// ถ้ามี success message ให้ซ่อนฟอร์ม
<?php if (!empty($success)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('engage-form');
    if (form) {
        form.classList.remove('active');
    }
    
    // Scroll to top เพื่อเห็น success message
    window.scrollTo(0, 0);
    
    // รีโหลดหน้าหลัง 2 วินาที
    setTimeout(function() {
        window.location.reload();
    }, 2000);
});
<?php endif; ?>
</script>

</body>
</html>