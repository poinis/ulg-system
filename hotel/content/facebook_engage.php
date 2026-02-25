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

// ถ้ามี ID ให้แสดงฟอร์มบันทึกเฉพาะ content นั้น
$specific_content_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงรายการที่ต้องเช็ค engagement (posted แล้ว)
$contents = [];
if ($specific_content_id) {
    $sql = "SELECT c.*, p.page_name
            FROM content_calendar c
            LEFT JOIN facebook_pages p ON c.page_id = p.id
            WHERE c.id = ? AND c.status = 'posted'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $specific_content_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $contents[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $sql = "SELECT c.*, p.page_name
            FROM content_calendar c
            LEFT JOIN facebook_pages p ON c.page_id = p.id
            WHERE c.status = 'posted' 
            AND c.post_date IS NOT NULL
            ORDER BY c.post_date DESC
            LIMIT 50";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $contents[] = $row;
    }
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $content_id = intval($_POST['content_id']);
    $check_date = cleanInput($_POST['check_date']);
    $views = intval($_POST['views']);
    $likes = intval($_POST['likes']);
    $comments = intval($_POST['comments']);
    $shares = intval($_POST['shares']);
    $reach = intval($_POST['reach']);
    $notes = cleanInput($_POST['notes']);
    
    // คำนวณ engagement rate
    $total_interactions = $likes + $comments + $shares;
    $engagement_rate = $reach > 0 ? round(($total_interactions / $reach) * 100, 2) : 0;
    
    // ตรวจสอบว่ามีข้อมูลวันนี้แล้วหรือยัง
    $sql = "SELECT id FROM facebook_engagement WHERE content_id = ? AND check_date = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $content_id, $check_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($existing) {
        // อัพเดท
        $sql = "UPDATE facebook_engagement SET 
                views = ?, likes = ?, comments = ?, shares = ?, reach = ?, 
                engagement_rate = ?, status = 'completed', notes = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiddsi", 
            $views, $likes, $comments, $shares, $reach, 
            $engagement_rate, $notes, $existing['id']);
    } else {
        // เพิ่มใหม่
        $post_id = '';
        $sql_content = "SELECT facebook_post_id FROM content_calendar WHERE id = ?";
        $stmt_content = mysqli_prepare($conn, $sql_content);
        mysqli_stmt_bind_param($stmt_content, "i", $content_id);
        mysqli_stmt_execute($stmt_content);
        $result_content = mysqli_stmt_get_result($stmt_content);
        $content_data = mysqli_fetch_assoc($result_content);
        $post_id = $content_data['facebook_post_id'];
        mysqli_stmt_close($stmt_content);
        
        $sql = "INSERT INTO facebook_engagement 
                (content_id, post_id, check_date, views, likes, comments, shares, reach, engagement_rate, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issiiiidds", 
            $content_id, $post_id, $check_date, $views, $likes, $comments, $shares, $reach, 
            $engagement_rate, $notes);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $success = 'บันทึก Engagement สำเร็จ!';
        
        // อัพเดทสถานะคอนเทนต์เป็น completed ถ้ายังไม่ completed
        $sql_update = "UPDATE content_calendar SET status = 'completed' WHERE id = ? AND status = 'posted'";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "i", $content_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        if ($specific_content_id) {
            header("refresh:1;url=content_detail.php?id={$specific_content_id}");
        }
    } else {
        $error = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึก Facebook Engagement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
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
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .content-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
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
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .content-list {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .content-list h3 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .content-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        
        .content-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .content-item-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .content-item-meta {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .tip-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .tip-box strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📊 บันทึก Facebook Engagement</h1>
            <a href="content_dashboard.php" class="back-link">← กลับปฏิทิน</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($specific_content_id && !empty($contents)): ?>
            <!-- ฟอร์มบันทึกเฉพาะ content -->
            <div class="form-card">
                <h2>บันทึก Engagement</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php $content = $contents[0]; ?>
                
                <div class="content-info">
                    <strong style="color: #2196f3;">📱 <?php echo htmlspecialchars($content['job_title']); ?></strong><br>
                    <small>
                        <?php if (!empty($content['page_name'])): ?>
                            Page: <?php echo htmlspecialchars($content['page_name']); ?> |
                        <?php endif; ?>
                        โพสต์วันที่: <?php echo getThaiDate($content['post_date'], 'short'); ?>
                    </small>
                </div>
                
                <div class="tip-box">
                    <strong>💡 คำแนะนำ:</strong> เข้าไปที่ Facebook Page และดูข้อมูล Insights ของโพสต์ แล้วกรอกข้อมูลด้านล่าง
                </div>
                
                <form method="POST">
                    <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
                    
                    <div class="form-group">
                        <label class="required">วันที่ตรวจสอบ</label>
                        <input type="date" name="check_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">👁️ Views (การดู)</label>
                            <input type="number" name="views" min="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">👍 Likes (ถูกใจ)</label>
                            <input type="number" name="likes" min="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">💬 Comments (ความคิดเห็น)</label>
                            <input type="number" name="comments" min="0" value="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">🔄 Shares (แชร์)</label>
                            <input type="number" name="shares" min="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">📈 Reach (การเข้าถึง)</label>
                            <input type="number" name="reach" min="0" value="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>📝 หมายเหตุ</label>
                        <textarea name="notes" rows="3" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 บันทึก Engagement</button>
                        <a href="content_detail.php?id=<?php echo $content['id']; ?>" class="btn btn-secondary">ยกเลิก</a>
                    </div>
                </form>
            </div>
            
        <?php else: ?>
            <!-- รายการทั้งหมดที่โพสต์แล้ว -->
            <div class="content-list">
                <h3>📋 รายการคอนเทนต์ที่โพสต์แล้ว</h3>
                
                <?php if (empty($contents)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 40px;">
                        ไม่มีคอนเทนต์ที่โพสต์แล้ว
                    </p>
                <?php else: ?>
                    <?php foreach ($contents as $content): ?>
                        <div class="content-item" onclick="location.href='facebook_engage.php?id=<?php echo $content['id']; ?>'">
                            <div class="content-item-title">
                                <?php echo htmlspecialchars($content['job_title']); ?>
                            </div>
                            <div class="content-item-meta">
                                <?php if (!empty($content['page_name'])): ?>
                                    📱 <?php echo htmlspecialchars($content['page_name']); ?> |
                                <?php endif; ?>
                                📅 โพสต์: <?php echo getThaiDate($content['post_date'], 'short'); ?>
                                <?php if (!empty($content['assignee'])): ?>
                                    | 👤 <?php echo htmlspecialchars($content['assignee']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto calculate engagement rate
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const views = parseInt(document.querySelector('input[name="views"]').value) || 0;
                    const reach = parseInt(document.querySelector('input[name="reach"]').value) || 0;
                    
                    if (views > 0 && reach === 0) {
                        if (!confirm('คุณยังไม่ได้กรอก Reach ต้องการบันทึกหรือไม่?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>