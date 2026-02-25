<?php
// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$userId = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Get user info
$user = null;
$userRole = '';
$userName = $username;

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userRole = isset($user['role']) ? strtolower($user['role']) : '';
        $userName = isset($user['name']) && $user['name'] ? $user['name'] : $username;
    }
    $stmt->close();
}

// Check access
$superadmins = array('admin', 'oat', 'it', 'may');
$is_superadmin = in_array(strtolower($username), $superadmins);

// Admin access
$admin_roles = array('admin', 'owner', 'brand', 'marketing');
$is_admin = $is_superadmin || in_array($userRole, $admin_roles);

// Current month
$currentMonth = date('Y-m');
$currentMonthThai = date('m/Y');

// Create tables
$conn->query("
    CREATE TABLE IF NOT EXISTS incentive_content_categories (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_key varchar(50) NOT NULL,
        category_name varchar(100) NOT NULL,
        target_clips int(11) NOT NULL DEFAULT 0,
        points_per_clip int(11) NOT NULL DEFAULT 3,
        is_active tinyint(1) DEFAULT 1,
        sort_order int(11) DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS incentive_daily_logs (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        log_date date NOT NULL,
        task_key varchar(50) NOT NULL,
        content_category varchar(50) DEFAULT NULL,
        quantity int(11) NOT NULL DEFAULT 1,
        points_earned int(11) NOT NULL DEFAULT 0,
        proof_url varchar(500) DEFAULT NULL,
        proof_image varchar(500) DEFAULT NULL,
        note text DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        approved_by int(11) DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Insert default categories
$catResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_content_categories");
if ($catResult) {
    $row = $catResult->fetch_assoc();
    if ($row['cnt'] == 0) {
        $conn->query("INSERT INTO incentive_content_categories (category_key, category_name, target_clips, sort_order) VALUES
            ('product_review', 'คลิปรีวิวสินค้า', 8, 1),
            ('creative', 'คลิปสร้างสรรค์', 4, 2),
            ('store_vibe', 'คลิปบรรยากาศร้าน', 2, 3),
            ('team_allstar', 'คลิปทีมงาน All-Star', 2, 4),
            ('mix_match', 'คลิป Mix & Match', 4, 5)
        ");
    }
}

// Get categories
$categories = array();
$catQuery = $conn->query("SELECT * FROM incentive_content_categories WHERE is_active = 1 ORDER BY sort_order");
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Upload directory
$uploadDir = 'uploads/' . date('Y-m') . '/';
if (!is_dir('uploads')) {
    @mkdir('uploads', 0755);
}
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Handle form
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $taskKey = isset($_POST['task_key']) ? $_POST['task_key'] : 'tiktok_clip';
    $logDate = isset($_POST['log_date']) && $_POST['log_date'] ? $_POST['log_date'] : date('Y-m-d');
    $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
    $contentCategory = isset($_POST['content_category']) ? $_POST['content_category'] : null;
    $proofUrl = isset($_POST['proof_url']) ? trim($_POST['proof_url']) : '';
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $proofImage = '';
    
    // Handle upload
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $fileType = $_FILES['proof_image']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
            $filename = $userId . '_' . time() . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $targetPath)) {
                $proofImage = $targetPath;
            }
        } else {
            $message = 'รูปภาพต้องเป็น JPG, PNG, GIF หรือ WEBP';
            $messageType = 'error';
        }
    }
    
    if ($messageType !== 'error') {
        if (empty($proofUrl) && empty($proofImage)) {
            $message = 'กรุณาแนบหลักฐาน';
            $messageType = 'error';
        } else {
            $points = ($taskKey === 'tiktok_clip') ? (3 * $quantity) : (1 * $quantity);
            
            $stmt = $conn->prepare("INSERT INTO incentive_daily_logs (user_id, log_date, task_key, content_category, quantity, points_earned, proof_url, proof_image, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            if ($stmt) {
                $stmt->bind_param("isssiisss", $userId, $logDate, $taskKey, $contentCategory, $quantity, $points, $proofUrl, $proofImage, $note);
                if ($stmt->execute()) {
                    $message = 'บันทึกสำเร็จ!';
                    $messageType = 'success';
                } else {
                    $message = 'Error: ' . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = 'Prepare Error: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
}

// Stats
$stats = array(
    'tiktok_approved' => 0,
    'google_maps' => 0,
    'google_reviews' => 0,
    'total_points' => 0,
    'categories' => array()
);

foreach ($categories as $cat) {
    $stats['categories'][$cat['category_key']] = array(
        'name' => $cat['category_name'],
        'target' => $cat['target_clips'],
        'done' => 0,
        'pending' => 0
    );
}

$logQuery = $conn->prepare("SELECT task_key, content_category, quantity, points_earned, status FROM incentive_daily_logs WHERE user_id = ? AND DATE_FORMAT(log_date, '%Y-%m') = ?");
if ($logQuery) {
    $logQuery->bind_param("is", $userId, $currentMonth);
    $logQuery->execute();
    $logResult = $logQuery->get_result();
    
    while ($log = $logResult->fetch_assoc()) {
        if ($log['task_key'] === 'tiktok_clip') {
            if ($log['status'] === 'approved') {
                $stats['tiktok_approved'] += $log['quantity'];
                $catKey = $log['content_category'];
                if ($catKey && isset($stats['categories'][$catKey])) {
                    $stats['categories'][$catKey]['done'] += $log['quantity'];
                }
            } elseif ($log['status'] === 'pending') {
                $catKey = $log['content_category'];
                if ($catKey && isset($stats['categories'][$catKey])) {
                    $stats['categories'][$catKey]['pending'] += $log['quantity'];
                }
            }
        } elseif ($log['task_key'] === 'google_maps_update' && $log['status'] === 'approved') {
            $stats['google_maps'] += $log['quantity'];
        } elseif ($log['task_key'] === 'google_review' && $log['status'] === 'approved') {
            $stats['google_reviews'] += $log['quantity'];
        }
        
        if ($log['status'] === 'approved') {
            $stats['total_points'] += $log['points_earned'];
        }
    }
    $logQuery->close();
}

$baseIncentiveEarned = $stats['tiktok_approved'] >= 20;
$pointsBonusEarned = $stats['total_points'] >= 100;

// Recent logs
$recentLogsResult = array();
$recentQuery = $conn->prepare("SELECT l.*, c.category_name FROM incentive_daily_logs l LEFT JOIN incentive_content_categories c ON l.content_category = c.category_key WHERE l.user_id = ? ORDER BY l.created_at DESC LIMIT 10");
if ($recentQuery) {
    $recentQuery->bind_param("i", $userId);
    $recentQuery->execute();
    $result = $recentQuery->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentLogsResult[] = $row;
    }
    $recentQuery->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incentive 2026</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; min-height: 100vh; color: #fff; padding: 15px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .header { background: linear-gradient(135deg, #ec4899, #f472b6); padding: 20px; border-radius: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 1.3em; }
        .header-info { text-align: right; }
        .header-info .name { font-weight: 600; }
        .header-info .month { font-size: 12px; opacity: 0.9; }
        
        .nav-links { display: flex; gap: 8px; }
        .nav-links a { background: rgba(255,255,255,0.2); color: #fff; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 13px; }
        
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert.success { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .alert.error { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        
        .rewards-overview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .reward-card { background: #16213e; border-radius: 15px; padding: 20px; text-align: center; border: 2px solid transparent; position: relative; }
        .reward-card.earned { border-color: #2ed573; }
        .reward-card .icon { font-size: 30px; margin-bottom: 10px; }
        .reward-card .title { font-size: 12px; color: #888; margin-bottom: 5px; }
        .reward-card .amount { font-size: 24px; font-weight: 700; }
        .reward-card .amount.cash { color: #2ed573; }
        .reward-card .amount.credit { color: #fbbf24; }
        .reward-card .condition { font-size: 11px; color: #666; margin-top: 8px; }
        .reward-card .status { position: absolute; top: 10px; right: 10px; font-size: 18px; }
        
        .section { background: #16213e; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .section-title { font-size: 1em; margin-bottom: 15px; color: #fbbf24; }
        
        .points-display { text-align: center; padding: 20px; background: #252542; border-radius: 12px; margin-bottom: 20px; }
        .points-display .value { font-size: 48px; font-weight: 700; color: #ec4899; }
        .points-display .label { font-size: 14px; color: #888; }
        
        .progress-bar-container { margin-bottom: 15px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
        .progress-bar { height: 12px; background: #252542; border-radius: 6px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 6px; }
        .progress-fill.tiktok { background: #ec4899; }
        .progress-fill.maps { background: #3b82f6; }
        .progress-fill.review { background: #f59e0b; }
        .progress-fill.points { background: #2ed573; }
        
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 15px; }
        .category-item { background: #252542; padding: 12px; border-radius: 10px; text-align: center; }
        .category-item .name { font-size: 11px; color: #aaa; margin-bottom: 5px; }
        .category-item .count { font-size: 18px; font-weight: 700; }
        .category-item .count.complete { color: #2ed573; }
        .category-item .target { font-size: 10px; color: #666; }
        
        .task-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .task-tab { padding: 12px 20px; background: #252542; border: none; border-radius: 10px; color: #aaa; cursor: pointer; font-size: 13px; }
        .task-tab.active { background: #ec4899; color: #fff; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; color: #aaa; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 10px; color: #fff; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .upload-area { border: 2px dashed #3a3a5a; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; }
        .upload-area:hover { border-color: #ec4899; }
        .upload-area.has-file { border-color: #2ed573; }
        .upload-area i { font-size: 30px; color: #666; margin-bottom: 10px; }
        .upload-area p { font-size: 12px; color: #888; }
        .upload-area .filename { color: #2ed573; font-weight: 600; margin-top: 5px; }
        .upload-area input { display: none; }
        .preview-img { max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 8px; }
        
        .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg, #ec4899, #f472b6); border: none; border-radius: 10px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        
        .log-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #252542; border-radius: 10px; margin-bottom: 8px; }
        .log-info .task-name { font-size: 13px; font-weight: 600; }
        .log-info .task-detail { font-size: 11px; color: #888; }
        .log-points { font-size: 14px; font-weight: 700; color: #ec4899; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; margin-left: 10px; }
        .status-badge.pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .status-badge.approved { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .status-badge.rejected { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        
        .proof-icon { color: #3b82f6; margin-left: 5px; }
        
        .tip-box { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .tip-box .title { font-size: 13px; font-weight: 600; color: #fbbf24; margin-bottom: 8px; }
        .tip-box .text { font-size: 12px; color: #aaa; line-height: 1.6; }
        
        @media (max-width: 768px) {
            .rewards-overview { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .task-tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Incentive 2026</h1>
                <div class="nav-links">
                    <?php if ($is_admin): ?>
                    <a href="incentive_approve.php"><i class="fas fa-check"></i> อนุมัติ</a>
                    <a href="incentive_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                    <?php endif; ?>
                    <a href="../dashboard.php"><i class="fas fa-home"></i></a>
                </div>
            </div>
            <div class="header-info">
                <div class="name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="month">เดือน <?php echo $currentMonthThai; ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="rewards-overview">
            <div class="reward-card <?php echo $baseIncentiveEarned ? 'earned' : ''; ?>">
                <span class="status"><?php echo $baseIncentiveEarned ? '✅' : '⏳'; ?></span>
                <div class="icon">💵</div>
                <div class="title">Base Incentive</div>
                <div class="amount cash">฿2,000</div>
                <div class="condition">TikTok <?php echo $stats['tiktok_approved']; ?>/20 คลิป</div>
            </div>
            <div class="reward-card <?php echo $pointsBonusEarned ? 'earned' : ''; ?>">
                <span class="status"><?php echo $pointsBonusEarned ? '✅' : '⏳'; ?></span>
                <div class="icon">👕</div>
                <div class="title">Points Bonus</div>
                <div class="amount credit">฿500</div>
                <div class="condition">แต้ม <?php echo $stats['total_points']; ?>/100</div>
            </div>
            <div class="reward-card">
                <span class="status">⏳</span>
                <div class="icon">📈</div>
                <div class="title">Growth Bonus</div>
                <div class="amount credit">฿500</div>
                <div class="condition">Engagement ตามเป้า</div>
            </div>
        </div>
        
        <div class="tip-box">
            <div class="title">💡 Trick: วิธีได้ครบ 100 แต้ม</div>
            <div class="text">
                TikTok 20 คลิป = 60 แต้ม | อีก 40 แต้มต้องเก็บจาก <strong>Google Maps</strong> และ <strong>Reviews</strong>
            </div>
        </div>
        
        <div class="section">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> ความคืบหน้า</h3>
            
            <div class="points-display">
                <div class="value"><?php echo $stats['total_points']; ?></div>
                <div class="label">แต้มสะสม (เป้า 100)</div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-label">
                    <span>🎬 TikTok (<?php echo $stats['tiktok_approved']; ?>/20)</span>
                    <span><?php echo min(100, round($stats['tiktok_approved'] / 20 * 100)); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill tiktok" style="width: <?php echo min(100, $stats['tiktok_approved'] / 20 * 100); ?>%"></div>
                </div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-label">
                    <span>📍 Google Maps (<?php echo $stats['google_maps']; ?>)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill maps" style="width: <?php echo min(100, $stats['google_maps'] / 40 * 100); ?>%"></div>
                </div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-label">
                    <span>⭐ Reviews (<?php echo $stats['google_reviews']; ?>)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill review" style="width: <?php echo min(100, $stats['google_reviews'] / 20 * 100); ?>%"></div>
                </div>
            </div>
            
            <h4 style="font-size: 13px; color: #888; margin: 20px 0 10px;">หมวดหมู่ TikTok:</h4>
            <div class="category-grid">
                <?php foreach ($stats['categories'] as $key => $cat): ?>
                <div class="category-item">
                    <div class="name"><?php echo htmlspecialchars($cat['name']); ?></div>
                    <div class="count <?php echo $cat['done'] >= $cat['target'] ? 'complete' : ''; ?>">
                        <?php echo $cat['done']; ?><?php echo $cat['pending'] > 0 ? " <small style='color:#fbbf24'>(+{$cat['pending']})</small>" : ''; ?>
                    </div>
                    <div class="target">เป้า: <?php echo $cat['target']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="section">
            <h3 class="section-title"><i class="fas fa-plus-circle"></i> บันทึกงาน</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="task-tabs">
                    <button type="button" class="task-tab active" onclick="setTask('tiktok_clip', this)">🎬 TikTok (+3)</button>
                    <button type="button" class="task-tab" onclick="setTask('google_maps_update', this)">📍 Maps (+1)</button>
                    <button type="button" class="task-tab" onclick="setTask('google_review', this)">⭐ Review (+1)</button>
                </div>
                
                <input type="hidden" name="task_key" id="taskKey" value="tiktok_clip">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>วันที่</label>
                        <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>จำนวน</label>
                        <input type="number" name="quantity" value="1" min="1" max="10">
                    </div>
                </div>
                
                <div class="form-group" id="categoryGroup">
                    <label>หมวดหมู่</label>
                    <select name="content_category">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_key']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?> (<?php echo $cat['target_clips']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>📷 รูปหลักฐาน *</label>
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('proofImage').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>คลิกเพื่อเลือกรูป (JPG, PNG)</p>
                        <div class="filename" id="fileName"></div>
                        <img id="imagePreview" class="preview-img" style="display:none">
                        <input type="file" name="proof_image" id="proofImage" accept="image/*" onchange="previewImage(this)">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>🔗 ลิงก์ (ถ้ามี)</label>
                    <input type="url" name="proof_url" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <input type="text" name="note" placeholder="รายละเอียด (ถ้ามี)">
                </div>
                
                <button type="submit" name="submit_task" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> บันทึก
                </button>
            </form>
        </div>
        
        <div class="section">
            <h3 class="section-title"><i class="fas fa-history"></i> รายการล่าสุด</h3>
            
            <?php if (empty($recentLogsResult)): ?>
            <p style="text-align:center; color:#666; padding:20px;">ยังไม่มีรายการ</p>
            <?php else: ?>
            <?php foreach ($recentLogsResult as $log): ?>
            <div class="log-item">
                <div class="log-info">
                    <div class="task-name">
                        <?php
                        $icons = array('tiktok_clip' => '🎬', 'google_maps_update' => '📍', 'google_review' => '⭐');
                        echo isset($icons[$log['task_key']]) ? $icons[$log['task_key']] : '';
                        echo ' ' . $log['task_key'];
                        if (!empty($log['category_name'])) echo " <small style='color:#888'>- {$log['category_name']}</small>";
                        if (!empty($log['proof_image'])) echo " <a href='{$log['proof_image']}' target='_blank' class='proof-icon'><i class='fas fa-image'></i></a>";
                        if (!empty($log['proof_url'])) echo " <a href='{$log['proof_url']}' target='_blank' class='proof-icon'><i class='fas fa-link'></i></a>";
                        ?>
                    </div>
                    <div class="task-detail"><?php echo date('d/m/Y', strtotime($log['log_date'])); ?> • จำนวน <?php echo $log['quantity']; ?></div>
                </div>
                <div>
                    <span class="log-points">+<?php echo $log['points_earned']; ?></span>
                    <span class="status-badge <?php echo $log['status']; ?>"><?php echo $log['status'] === 'pending' ? 'รอ' : ($log['status'] === 'approved' ? '✓' : '✗'); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function setTask(task, btn) {
            document.getElementById('taskKey').value = task;
            document.querySelectorAll('.task-tab').forEach(function(t) { t.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('categoryGroup').style.display = (task === 'tiktok_clip') ? 'block' : 'none';
        }
        
        function previewImage(input) {
            var file = input.files[0];
            var area = document.getElementById('uploadArea');
            var fname = document.getElementById('fileName');
            var preview = document.getElementById('imagePreview');
            
            if (file) {
                fname.textContent = file.name;
                area.classList.add('has-file');
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>