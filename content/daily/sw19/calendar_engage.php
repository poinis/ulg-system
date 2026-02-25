<?php
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

// จัดการการบันทึก Engage
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_engage'])) {
    $event_id = intval($_POST['event_id']);
    $reach = intval($_POST['reach']);
    $impressions = intval($_POST['impressions']);
    $likes = intval($_POST['likes']);
    $comments = intval($_POST['comments']);
    $shares = intval($_POST['shares']);
    $saves = intval($_POST['saves']);
    $note = mysqli_real_escape_string($conn, trim($_POST['note']));
    
    // บันทึกหรืออัปเดต Engage
    $sql_check = "SELECT id FROM sw19_calendar_engage WHERE event_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $event_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $engage_exists = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);
    
    if ($engage_exists) {
        // อัปเดต
        $sql = "UPDATE sw19_calendar_engage 
                SET reach = ?, impressions = ?, likes = ?, comments = ?, shares = ?, saves = ?, 
                    note = ?, updated_by = ?, updated_at = NOW()
                WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiisssi", 
            $reach, $impressions, $likes, $comments, $shares, $saves, $note, $username, $event_id);
    } else {
        // เพิ่มใหม่
        $engage_date = date('Y-m-d');
        $sql = "INSERT INTO sw19_calendar_engage 
                (event_id, engage_date, reach, impressions, likes, comments, shares, saves, note, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isiiiiiiss", 
            $event_id, $engage_date, $reach, $impressions, $likes, $comments, $shares, $saves, $note, $username);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        // อัปเดตสถานะ engage_status ในตาราง calendar_events
        $sql_update_status = "UPDATE sw19_calendar_events SET engage_status = 'completed' WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update_status);
        mysqli_stmt_bind_param($stmt_update, "i", $event_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        $success = "บันทึกข้อมูล Engage เรียบร้อยแล้ว!";
        
        // ส่งการแจ้งเตือน
        try {
            $pumble = new PumbleNotification();
            
            // ดึงข้อมูลงาน
            $sql_event = "SELECT job_title, category FROM sw19_calendar_events WHERE id = ?";
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
            $message .= "⏰ เวลา: " . date('d/m/Y H:i:s');
            
            $pumble->sendToRoles($message, ['admin', 'marketing']);
            
        } catch (Exception $e) {
            error_log("Pumble notification error: " . $e->getMessage());
        }
        
    } else {
        $error = "เกิดข้อผิดพลาด: " . mysqli_stmt_error($stmt);
    }
    
    mysqli_stmt_close($stmt);
}

// ดึงงานที่ต้องทำ Engage (engage_status = pending)
$pending_engages = array();
$sql_pending = "SELECT e.*, eng.reach, eng.impressions, eng.likes, eng.comments, eng.shares, eng.saves, eng.note
                FROM sw19_calendar_events e
                LEFT JOIN sw19_calendar_engage eng ON e.id = eng.event_id
                WHERE e.engage_date IS NOT NULL 
                ORDER BY e.engage_date ASC";
$result_pending = mysqli_query($conn, $sql_pending);
if ($result_pending) {
    while ($row = mysqli_fetch_assoc($result_pending)) {
        $pending_engages[] = $row;
    }
    mysqli_free_result($result_pending);
}

// สถิติ
$stats = array(
    'pending' => 0,
    'completed' => 0,
    'overdue' => 0
);

$today = date('Y-m-d');
foreach ($pending_engages as $engage) {
    if ($engage['engage_status'] === 'completed') {
        $stats['completed']++;
    } else {
        $stats['pending']++;
        if ($engage['engage_date'] < $today) {
            $stats['overdue']++;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>จัดการ Engage | Content Calendar</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
        padding: 20px 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      .header-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .header h1 {
        font-size: 1.8em;
        font-weight: 600;
      }
      
      .nav {
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        padding: 15px 30px;
        margin-bottom: 20px;
      }
      .nav-content {
        max-width: 1400px;
        margin: 0 auto;
      }
      .nav a {
        padding: 10px 20px;
        background: #95a5a6;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
      }
      .nav a:hover {
        background: #7f8c8d;
      }
      
      .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 30px 30px 30px;
      }
      
      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
      }
      .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid #667eea;
      }
      .stat-card.warning {
        border-left-color: #e67e22;
      }
      .stat-card.success {
        border-left-color: #27ae60;
      }
      .stat-card h3 {
        font-size: 2em;
        margin-bottom: 5px;
      }
      .stat-card p {
        color: #666;
        font-size: 0.95em;
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
      
      .engage-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      }
      .engage-section h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
      }
      
      .engage-card {
        background: #f8f9fa;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 10px;
        border-left: 5px solid #667eea;
      }
      .engage-card.overdue {
        border-left-color: #e74c3c;
        background: #ffebee;
      }
      .engage-card.completed {
        border-left-color: #27ae60;
        background: #e8f5e9;
      }
      
      .engage-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
      }
      .engage-title {
        font-size: 1.2em;
        font-weight: bold;
        color: #2c3e50;
      }
      .engage-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 15px;
        font-size: 0.9em;
        color: #666;
      }
      
      .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
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
      }
      .form-group textarea {
        resize: vertical;
        min-height: 80px;
      }
      
      .btn {
        padding: 10px 20px;
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
      
      @media (max-width: 768px) {
        .header-content {
          flex-direction: column;
          gap: 15px;
        }
        .form-grid {
          grid-template-columns: 1fr;
        }
        .result-summary {
          grid-template-columns: repeat(2, 1fr);
        }
      }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>📊 จัดการ Engage</h1>
        <div>
            <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
            <small><?php echo htmlspecialchars($username); ?></small>
        </div>
    </div>
</div>

<div class="nav">
    <div class="nav-content">
        <a href="index.php">🔙 กลับปฏิทิน</a>
    </div>
</div>

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
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>⏰ รอทำ Engage</p>
        </div>
        <div class="stat-card warning">
            <h3><?php echo $stats['overdue']; ?></h3>
            <p>⚠️ เลยกำหนด</p>
        </div>
        <div class="stat-card success">
            <h3><?php echo $stats['completed']; ?></h3>
            <p>✅ เสร็จแล้ว</p>
        </div>
    </div>
    
    <div class="engage-section">
        <h2>📋 รายการ Engage</h2>
        
        <?php if (empty($pending_engages)): ?>
            <p style="text-align: center; padding: 40px; color: #999;">ไม่มีงานที่ต้องทำ Engage</p>
        <?php else: ?>
            <?php foreach ($pending_engages as $engage): ?>
            <?php
            $is_completed = ($engage['engage_status'] === 'completed');
            $is_overdue = (!$is_completed && $engage['engage_date'] < $today);
            $card_class = $is_completed ? 'completed' : ($is_overdue ? 'overdue' : '');
            ?>
            <div class="engage-card <?php echo $card_class; ?>">
                <div class="engage-header">
                    <div>
                        <div class="engage-title"><?php echo htmlspecialchars($engage['job_title']); ?></div>
                        <div class="engage-meta">
                            <span>📂 <?php echo htmlspecialchars($engage['category']); ?></span>
                            <span>👤 <?php echo htmlspecialchars($engage['assignee']); ?></span>
                            <span>📅 โพสต์: <?php echo date('d/m/Y', strtotime($engage['post_date'])); ?></span>
                            <span>⏰ Engage: <?php echo date('d/m/Y', strtotime($engage['engage_date'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_completed): ?>
                            <span class="badge badge-completed">✅ เสร็จแล้ว</span>
                        <?php elseif ($is_overdue): ?>
                            <span class="badge badge-overdue">⚠️ เลยกำหนด</span>
                        <?php else: ?>
                            <span class="badge badge-pending">⏰ รอดำเนินการ</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($is_completed && !empty($engage['reach'])): ?>
                <!-- แสดงผลลัพธ์ที่บันทึกแล้ว -->
                <div class="result-summary">
                    <div class="result-item">
                        <strong><?php echo number_format($engage['reach']); ?></strong>
                        <span>Reach</span>
                    </div>
                    <div class="result-item">
                        <strong><?php echo number_format($engage['impressions']); ?></strong>
                        <span>Impressions</span>
                    </div>
                    <div class="result-item">
                        <strong><?php echo number_format($engage['likes']); ?></strong>
                        <span>Likes</span>
                    </div>
                    <div class="result-item">
                        <strong><?php echo number_format($engage['comments']); ?></strong>
                        <span>Comments</span>
                    </div>
                    <div class="result-item">
                        <strong><?php echo number_format($engage['shares']); ?></strong>
                        <span>Shares</span>
                    </div>
                    <div class="result-item">
                        <strong><?php echo number_format($engage['saves']); ?></strong>
                        <span>Saves</span>
                    </div>
                </div>
                <?php if (!empty($engage['note'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 6px;">
                    <strong>📝 หมายเหตุ:</strong> <?php echo nl2br(htmlspecialchars($engage['note'])); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <button class="btn btn-primary" onclick="toggleForm(<?php echo $engage['id']; ?>)">
                        <?php echo $is_completed ? '✏️ แก้ไข Engage' : '📊 บันทึก Engage'; ?>
                    </button>
                </div>
                
                <!-- ฟอร์มบันทึก Engage -->
                <div class="engage-form" id="form-<?php echo $engage['id']; ?>">
                    <form method="POST" action="">
                        <input type="hidden" name="event_id" value="<?php echo $engage['id']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Reach</label>
                                <input type="number" name="reach" value="<?php echo $engage['reach'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Impressions</label>
                                <input type="number" name="impressions" value="<?php echo $engage['impressions'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Likes</label>
                                <input type="number" name="likes" value="<?php echo $engage['likes'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Comments</label>
                                <input type="number" name="comments" value="<?php echo $engage['comments'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Shares</label>
                                <input type="number" name="shares" value="<?php echo $engage['shares'] ?? 0; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Saves</label>
                                <input type="number" name="saves" value="<?php echo $engage['saves'] ?? 0; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>หมายเหตุ</label>
                            <textarea name="note" placeholder="บันทึกข้อสังเกต หรือหมายเหตุเพิ่มเติม..."><?php echo htmlspecialchars($engage['note'] ?? ''); ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="save_engage" class="btn btn-success">💾 บันทึก</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleForm(<?php echo $engage['id']; ?>)">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
</div>

<script>
function toggleForm(eventId) {
    const form = document.getElementById('form-' + eventId);
    form.classList.toggle('active');
}
</script>

</body>
</html>