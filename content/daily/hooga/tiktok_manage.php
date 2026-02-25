<?php
require_once "config.php";
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

$success = "";
$error = "";

// จัดการการบันทึก
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_tiktok'])) {
    $event_id = intval($_POST['event_id']);
    $tiktok_video_id = mysqli_real_escape_string($conn, trim($_POST['tiktok_video_id']));
    $tiktok_url = mysqli_real_escape_string($conn, trim($_POST['tiktok_url']));
    
    // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
    $sql_check = "SELECT id FROM tiktok_posts WHERE event_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $event_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $exists = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);
    
    if ($exists) {
        // อัปเดต
        $sql = "UPDATE hooga_tiktok_posts SET tiktok_video_id = ?, tiktok_url = ? WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $tiktok_video_id, $tiktok_url, $event_id);
    } else {
        // สร้างใหม่
        $sql = "INSERT INTO hooga_tiktok_posts (event_id, tiktok_video_id, tiktok_url) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $event_id, $tiktok_video_id, $tiktok_url);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "บันทึก TikTok Video ID เรียบร้อยแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาด: " . mysqli_stmt_error($stmt);
    }
    mysqli_stmt_close($stmt);
}

// ดึงงานที่มี TikTok Platform
$events = array();
$sql_events = "SELECT e.*, t.tiktok_video_id, t.tiktok_url 
               FROM hooga_calendar_events e
               LEFT JOIN hooga_tiktok_posts t ON e.id = t.event_id
               WHERE FIND_IN_SET('TT', e.platform) > 0
               AND e.post_date IS NOT NULL
               ORDER BY e.post_date DESC
               LIMIT 50";
$result = mysqli_query($conn, $sql_events);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
    mysqli_free_result($result);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>จัดการ TikTok Video ID</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        margin: 0;
        padding: 20px;
      }
      
      .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      .header h1 {
        margin: 0;
        font-size: 1.8em;
      }
      
      .container {
        max-width: 1200px;
        margin: 0 auto;
      }
      
      .nav {
        margin-bottom: 20px;
      }
      .nav a {
        padding: 10px 20px;
        background: #95a5a6;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-block;
      }
      .nav a:hover {
        background: #7f8c8d;
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
      
      .info-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      }
      .info-box h3 {
        color: #667eea;
        margin-top: 0;
      }
      
      .events-table {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      }
      
      table {
        width: 100%;
        border-collapse: collapse;
      }
      
      th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
      }
      
      th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
      }
      
      tr:hover {
        background: #f8f9fa;
      }
      
      .badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
      }
      .badge-success {
        background: #d4edda;
        color: #155724;
      }
      .badge-warning {
        background: #fff3cd;
        color: #856404;
      }
      
      .tiktok-input {
        width: 100%;
        max-width: 300px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.9em;
      }
      
      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9em;
      }
      .btn-primary {
        background: #667eea;
        color: white;
      }
      .btn-primary:hover {
        background: #5568d3;
      }
      .btn-sm {
        padding: 6px 12px;
        font-size: 0.85em;
      }
      
      @media (max-width: 768px) {
        table {
          font-size: 0.85em;
        }
        th, td {
          padding: 8px;
        }
        .tiktok-input {
          max-width: 200px;
        }
      }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <h1>🎵 จัดการ TikTok Video ID</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">สำหรับดึงข้อมูล Engage อัตโนมัติ</p>
    </div>
    
    <div class="nav">
        <a href="index.php">🔙 กลับปฏิทิน</a>
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
        <h3>📖 วิธีการใช้งาน</h3>
        <ol>
            <li>หา Video ID จาก URL ของ TikTok (เช่น: https://www.tiktok.com/@username/video/<strong>7123456789012345678</strong>)</li>
            <li>กรอก Video ID ในช่อง "TikTok Video ID"</li>
            <li>คลิก "บันทึก" เพื่อบันทึกข้อมูล</li>
            <li>ระบบจะดึงข้อมูล Engage อัตโนมัติเมื่อถึงวันที่กำหนด (14 วันหลังโพสต์)</li>
        </ol>
        <p style="color: #666; margin: 10px 0 0 0;">
            <strong>หมายเหตุ:</strong> ต้องตั้งค่า Cron Job ที่ไฟล์ <code>tiktok_auto_engage.php</code> ให้รันทุกวัน
        </p>
    </div>
    
    <div class="events-table">
        <h3 style="margin-top: 0; color: #2c3e50;">งานที่มี TikTok Platform</h3>
        
        <?php if (empty($events)): ?>
            <p style="text-align: center; padding: 40px; color: #999;">ไม่มีงานที่มี TikTok Platform</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ชื่องาน</th>
                    <th>วันที่โพสต์</th>
                    <th>วันทำ Engage</th>
                    <th>สถานะ</th>
                    <th>TikTok Video ID</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($event['job_title']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($event['category']); ?></small>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($event['post_date'])); ?></td>
                    <td>
                        <?php if ($event['engage_date']): ?>
                            <?php echo date('d/m/Y', strtotime($event['engage_date'])); ?>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($event['engage_status'] === 'completed'): ?>
                            <span class="badge badge-success">✅ เสร็จแล้ว</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⏰ รอดำเนินการ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="text" 
                                   name="tiktok_video_id" 
                                   class="tiktok-input" 
                                   placeholder="Video ID (ตัวเลข)"
                                   value="<?php echo htmlspecialchars($event['tiktok_video_id'] ?? ''); ?>">
                            <input type="hidden" 
                                   name="tiktok_url" 
                                   value="<?php echo htmlspecialchars($event['tiktok_url'] ?? ''); ?>">
                    </td>
                    <td>
                            <button type="submit" name="save_tiktok" class="btn btn-primary btn-sm">💾 บันทึก</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
</div>

</body>
</html>