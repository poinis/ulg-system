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

$content_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงข้อมูลคอนเทนต์
$sql = "SELECT c.*, p.page_name, u.name as creator_name
        FROM content_calendar c
        LEFT JOIN facebook_pages p ON c.page_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $content_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$content = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$content) {
    header("location: content_dashboard.php");
    exit;
}

// ดึงข้อมูล Engagement
$engagements = [];
$sql = "SELECT * FROM facebook_engagement WHERE content_id = ? ORDER BY check_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $content_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $engagements[] = $row;
}
mysqli_stmt_close($stmt);

// ดึง Target (ถ้ามี)
$target = null;
$sql = "SELECT * FROM engagement_targets WHERE content_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $content_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$target = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคอนเทนต์ - <?php echo htmlspecialchars($content['job_title']); ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .content-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .content-title {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 15px;
        }
        
        .badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            color: white;
        }
        
        .badge-pk { background: #dc3545; }
        .badge-promo { background: #ffc107; color: #333; }
        .badge-lifestyle { background: #9c27b0; }
        .badge-event { background: #2196f3; }
        .badge-news { background: #ff5722; }
        
        .badge-planned { background: #6c757d; }
        .badge-in_progress { background: #ffc107; color: #333; }
        .badge-approved { background: #17a2b8; }
        .badge-posted { background: #28a745; }
        .badge-completed { background: #155724; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .info-item {
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .description {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.8;
            margin: 20px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #333;
        }
        
        .btn-engage {
            background: #28a745;
            color: white;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .engagement-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .engagement-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .engagement-card.completed {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .engagement-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .engagement-date {
            font-weight: 600;
            color: #667eea;
        }
        
        .engagement-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: 600;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .target-card {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📱 รายละเอียดคอนเทนต์</h1>
            <a href="content_dashboard.php" class="back-link">← กลับปฏิทิน</a>
        </div>
    </div>
    
    <div class="container">
        <!-- ข้อมูลคอนเทนต์ -->
        <div class="card">
            <div class="content-header">
                <h1 class="content-title"><?php echo htmlspecialchars($content['job_title']); ?></h1>
                <div class="badges">
                    <?php
                    $cat_class = '';
                    switch($content['category']) {
                        case 'Product Knowledge': $cat_class = 'badge-pk'; break;
                        case 'Promotion': $cat_class = 'badge-promo'; break;
                        case 'Lifestyle': $cat_class = 'badge-lifestyle'; break;
                        case 'Event': $cat_class = 'badge-event'; break;
                        case 'News': $cat_class = 'badge-news'; break;
                    }
                    ?>
                    <span class="badge <?php echo $cat_class; ?>">
                        <?php echo htmlspecialchars($content['category']); ?>
                    </span>
                    <span class="badge badge-<?php echo $content['status']; ?>">
                        <?php 
                        $status_thai = [
                            'planned' => 'กำลังวางแผน',
                            'in_progress' => 'กำลังดำเนินการ',
                            'approved' => 'อนุมัติแล้ว',
                            'posted' => 'โพสต์แล้ว',
                            'completed' => 'เสร็จสมบูรณ์',
                            'cancelled' => 'ยกเลิก'
                        ];
                        echo $status_thai[$content['status']];
                        ?>
                    </span>
                    <span class="badge" style="background: #17a2b8;">
                        <?php 
                        $type_thai = [
                            'image' => '🖼️ รูปภาพ',
                            'video' => '🎥 วิดีโอ',
                            'carousel' => '🎠 Carousel',
                            'reel' => '🎬 Reel',
                            'story' => '📖 Story'
                        ];
                        echo $type_thai[$content['content_type']];
                        ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($content['description'])): ?>
                <div class="description">
                    <strong>รายละเอียด:</strong><br><br>
                    <?php echo nl2br(htmlspecialchars($content['description'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-grid">
                <?php if (!empty($content['page_name'])): ?>
                    <div class="info-item">
                        <span class="info-label">📱 Facebook Page:</span>
                        <span class="info-value"><?php echo htmlspecialchars($content['page_name']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($content['assignee'])): ?>
                    <div class="info-item">
                        <span class="info-label">👤 ผู้รับผิดชอบ:</span>
                        <span class="info-value"><?php echo htmlspecialchars($content['assignee']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($content['brief_date'])): ?>
                    <div class="info-item">
                        <span class="info-label">📋 วันที่สรุป Brief:</span>
                        <span class="info-value"><?php echo getThaiDate($content['brief_date'], 'short'); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($content['post_date'])): ?>
                    <div class="info-item">
                        <span class="info-label">📅 วันที่โพสต์:</span>
                        <span class="info-value">
                            <?php echo getThaiDate($content['post_date'], 'short'); ?>
                            <?php if (!empty($content['post_time'])): ?>
                                เวลา <?php echo substr($content['post_time'], 0, 5); ?> น.
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <span class="info-label">👨‍💼 สร้างโดย:</span>
                    <span class="info-value"><?php echo htmlspecialchars($content['creator_name']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">📅 สร้างเมื่อ:</span>
                    <span class="info-value"><?php echo getThaiDate($content['created_at']); ?></span>
                </div>
            </div>
            
            <?php if (!empty($content['facebook_post_url'])): ?>
                <div class="info-item" style="margin-top: 15px;">
                    <span class="info-label">🔗 Facebook Post:</span>
                    <span class="info-value">
                        <a href="<?php echo htmlspecialchars($content['facebook_post_url']); ?>" 
                           target="_blank" style="color: #667eea;">
                            ดูโพสต์ →
                        </a>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($content['drive_folder_url'])): ?>
                <div class="info-item" style="margin-top: 10px;">
                    <span class="info-label">📁 Google Drive:</span>
                    <span class="info-value">
                        <a href="<?php echo htmlspecialchars($content['drive_folder_url']); ?>" 
                           target="_blank" style="color: #667eea;">
                            เปิดโฟลเดอร์ →
                        </a>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($content['notes'])): ?>
                <div class="description" style="margin-top: 20px;">
                    <strong>หมายเหตุ:</strong><br><br>
                    <?php echo nl2br(htmlspecialchars($content['notes'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="content_add.php?id=<?php echo $content_id; ?>" class="btn btn-edit">
                    ✏️ แก้ไข
                </a>
                <?php if ($content['status'] == 'posted'): ?>
                    <a href="facebook_engage.php?id=<?php echo $content_id; ?>" class="btn btn-engage">
                        📊 บันทึก Engagement
                    </a>
                <?php endif; ?>
                <a href="content_dashboard.php" class="btn btn-primary">
                    ← กลับปฏิทิน
                </a>
            </div>
        </div>
        
        <!-- เป้าหมาย Engagement -->
        <?php if ($target): ?>
            <div class="target-card">
                <h3 style="color: #2196f3; margin-bottom: 15px;">🎯 เป้าหมาย Engagement</h3>
                <div class="engagement-stats">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #2196f3;"><?php echo number_format($target['target_views']); ?></div>
                        <div class="stat-label">Views</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: #2196f3;"><?php echo number_format($target['target_likes']); ?></div>
                        <div class="stat-label">Likes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: #2196f3;"><?php echo number_format($target['target_comments']); ?></div>
                        <div class="stat-label">Comments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: #2196f3;"><?php echo number_format($target['target_shares']); ?></div>
                        <div class="stat-label">Shares</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color: #2196f3;"><?php echo number_format($target['target_reach']); ?></div>
                        <div class="stat-label">Reach</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ประวัติ Engagement -->
        <div class="card">
            <h2>📊 ประวัติ Facebook Engagement</h2>
            
            <?php if (empty($engagements)): ?>
                <div class="empty-state">
                    <div style="font-size: 3em; margin-bottom: 15px;">📭</div>
                    <p>ยังไม่มีข้อมูล Engagement</p>
                    <?php if ($content['status'] == 'posted'): ?>
                        <a href="facebook_engage.php?id=<?php echo $content_id; ?>" 
                           class="btn btn-engage" style="margin-top: 15px;">
                            บันทึก Engagement
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="engagement-list">
                    <?php foreach ($engagements as $eng): ?>
                        <div class="engagement-card <?php echo $eng['status']; ?>">
                            <div class="engagement-header">
                                <div class="engagement-date">
                                    📅 <?php echo getThaiDate($eng['check_date'], 'short'); ?>
                                </div>
                                <div>
                                    <?php if ($eng['status'] == 'completed'): ?>
                                        <span class="badge" style="background: #28a745;">✅ ตรวจสอบแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #ffc107; color: #333;">⏳ รอตรวจสอบ</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($eng['status'] == 'completed'): ?>
                                <div class="engagement-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo number_format($eng['views']); ?></div>
                                        <div class="stat-label">👁️ Views</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo number_format($eng['likes']); ?></div>
                                        <div class="stat-label">👍 Likes</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo number_format($eng['comments']); ?></div>
                                        <div class="stat-label">💬 Comments</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo number_format($eng['shares']); ?></div>
                                        <div class="stat-label">🔄 Shares</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo number_format($eng['reach']); ?></div>
                                        <div class="stat-label">📈 Reach</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $eng['engagement_rate']; ?>%</div>
                                        <div class="stat-label">📊 Engagement Rate</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($eng['notes'])): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                        <strong>หมายเหตุ:</strong> <?php echo htmlspecialchars($eng['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="color: #6c757d; text-align: center;">
                                    รอบันทึกข้อมูล Engagement
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>