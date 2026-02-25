<?php
// checklist.php - Employee Checklist Page (Mobile Friendly)
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Get user's branch
$userBranch = getUserBranch($conn, $userId);

// Handle branch selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_branch'])) {
    $branchId = (int) $_POST['branch_id'];
    if (setUserBranch($conn, $userId, $branchId)) {
        header('Location: checklist.php');
        exit;
    }
}

// Get data
$branches = getBranches($conn);
$taskTypes = getTaskTypes($conn);
$todaySubmissions = $userBranch ? getTodaySubmissions($conn, $userId) : [];
$yearMonth = date('Y-m');
$monthlyPoints = $userBranch ? getBranchMonthlyPoints($conn, $userBranch['id'], $yearMonth) : 0;
$targetPoints = (int) getSetting($conn, 'target_points', 100);

// Calculate today's points
$todayPoints = 0;
$submittedTasks = [];
foreach ($todaySubmissions as $sub) {
    if ($sub['status'] !== 'rejected') {
        $todayPoints += $sub['task_points'];
    }
    $submittedTasks[$sub['task_type_id']] = $sub;
}

// Progress percentage
$progressPercent = min(($monthlyPoints / $targetPoints) * 100, 100);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Incentive Checklist | PRONTO</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Branch Selector */
        .branch-selector {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .branch-selector h3 {
            margin-bottom: 12px;
            font-size: 16px;
            color: rgba(255,255,255,0.7);
        }
        .branch-selector select {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-size: 16px;
            font-family: inherit;
            cursor: pointer;
        }
        .branch-selector select option {
            background: #1a1a2e;
            color: #fff;
        }
        .branch-selector button {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .branch-selector button:active {
            transform: scale(0.98);
        }
        
        /* Current Branch Badge */
        .current-branch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(102, 126, 234, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .current-branch i {
            color: #667eea;
        }
        
        /* Progress Card */
        .progress-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .progress-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .progress-card .month {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        .progress-card .points {
            font-size: 48px;
            font-weight: 700;
            line-height: 1;
        }
        .progress-card .points span {
            font-size: 20px;
            font-weight: 400;
            opacity: 0.8;
        }
        .progress-bar-container {
            margin-top: 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: #fff;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* Today Stats */
        .today-stats {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-box .value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-box .label {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }
        
        /* Task List */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .task-card {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        .task-card.submitted {
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(102, 126, 234, 0.15);
        }
        .task-card.approved {
            border-color: rgba(46, 213, 115, 0.5);
            background: rgba(46, 213, 115, 0.15);
        }
        .task-card.rejected {
            border-color: rgba(255, 107, 107, 0.5);
            background: rgba(255, 107, 107, 0.15);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .task-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .task-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .task-name {
            font-size: 16px;
            font-weight: 600;
        }
        .task-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }
        .task-points {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Task Input */
        .task-input {
            margin-top: 12px;
        }
        .task-input input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            color: #fff;
            font-size: 14px;
            font-family: inherit;
        }
        .task-input input[type="text"]::placeholder {
            color: rgba(255,255,255,0.4);
        }
        .task-input input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* File Upload */
        .file-upload {
            position: relative;
        }
        .file-upload input[type="file"] {
            display: none;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            border: 2px dashed rgba(255,255,255,0.3);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }
        .file-upload-label:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .file-upload-label.has-file {
            border-color: #2ed573;
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
        }
        
        /* Submit Button */
        .submit-btn {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .submit-btn:not(:disabled):active {
            transform: scale(0.98);
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        .status-badge.approved {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        .status-badge.rejected {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }
        
        /* Submitted Info */
        .submitted-info {
            margin-top: 12px;
            padding: 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            font-size: 13px;
        }
        .submitted-info a {
            color: #667eea;
            word-break: break-all;
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #333;
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 14px;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            max-width: 90%;
            text-align: center;
        }
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .toast.success {
            background: linear-gradient(135deg, #2ed573 0%, #1abc9c 100%);
        }
        .toast.error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            padding-bottom: max(12px, env(safe-area-inset-bottom));
            display: flex;
            justify-content: space-around;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 12px;
            transition: color 0.3s;
        }
        .nav-item.active, .nav-item:hover {
            color: #667eea;
        }
        .nav-item i {
            font-size: 20px;
        }
        
        /* Admin Link */
        .admin-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .admin-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        
        /* Image Preview */
        .image-preview {
            margin-top: 10px;
            max-width: 100%;
            border-radius: 8px;
            display: none;
        }
        .image-preview.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📋 Checklist</h1>
            <div class="user-info">
                <?php if (isAdmin($_SESSION)): ?>
                <a href="admin/dashboard.php" class="admin-link">
                    <i class="fas fa-cog"></i> Admin
                </a>
                <?php endif; ?>
                <div class="avatar"><?= mb_substr($userName, 0, 1) ?></div>
            </div>
        </div>
        
        <?php if (!$userBranch): ?>
        <!-- Branch Selection -->
        <div class="branch-selector">
            <h3><i class="fas fa-store"></i> เลือกสาขาของคุณ</h3>
            <form method="POST">
                <select name="branch_id" required>
                    <option value="">-- เลือกสาขา --</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="select_branch">
                    <i class="fas fa-check"></i> ยืนยันสาขา
                </button>
            </form>
        </div>
        
        <?php else: ?>
        <!-- Current Branch -->
        <div class="current-branch">
            <i class="fas fa-store"></i>
            <span><?= htmlspecialchars($userBranch['branch_name']) ?></span>
            <a href="?change_branch=1" style="color: rgba(255,255,255,0.6); margin-left: 8px; font-size: 12px;">
                <i class="fas fa-edit"></i> เปลี่ยน
            </a>
        </div>
        
        <?php if (isset($_GET['change_branch'])): ?>
        <div class="branch-selector" style="margin-bottom: 20px;">
            <h3><i class="fas fa-exchange-alt"></i> เปลี่ยนสาขา</h3>
            <form method="POST">
                <select name="branch_id" required>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $userBranch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($branch['branch_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="select_branch">
                    <i class="fas fa-check"></i> ยืนยัน
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Progress Card -->
        <div class="progress-card">
            <div class="month"><i class="fas fa-calendar"></i> <?= thaiDate(date('Y-m-01'), 'full') ?></div>
            <div class="points"><?= $monthlyPoints ?> <span>/ <?= $targetPoints ?> คะแนน</span></div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?= $progressPercent ?>%"></div>
            </div>
            <div class="progress-label">
                <span><?= number_format($progressPercent, 1) ?>% ของเป้าหมาย</span>
                <span>เหลือ <?= max(0, $targetPoints - $monthlyPoints) ?> คะแนน</span>
            </div>
        </div>
        
        <!-- Today Stats -->
        <div class="today-stats">
            <div class="stat-box">
                <div class="value"><?= $todayPoints ?></div>
                <div class="label">คะแนนวันนี้</div>
            </div>
            <div class="stat-box">
                <div class="value"><?= count($todaySubmissions) ?></div>
                <div class="label">งานที่ส่งวันนี้</div>
            </div>
            <div class="stat-box">
                <div class="value"><?= date('j') ?></div>
                <div class="label"><?= thaiDate(date('Y-m-d')) ?></div>
            </div>
        </div>
        
        <!-- Task List -->
        <div class="section-title">
            <i class="fas fa-tasks"></i> รายการงานวันนี้
        </div>
        <div class="task-list">
            <?php foreach ($taskTypes as $task): 
                $submitted = $submittedTasks[$task['id']] ?? null;
                $cardClass = '';
                if ($submitted) {
                    $cardClass = $submitted['status'];
                }
            ?>
            <div class="task-card <?= $cardClass ?>" data-task-id="<?= $task['id'] ?>">
                <div class="task-header">
                    <div class="task-info">
                        <div class="task-icon"><?= $task['icon'] ?></div>
                        <div>
                            <div class="task-name"><?= htmlspecialchars($task['task_name_th']) ?></div>
                            <div class="task-desc"><?= htmlspecialchars($task['description']) ?></div>
                        </div>
                    </div>
                    <div class="task-points">+<?= $task['points'] ?> pts</div>
                </div>
                
                <?php if ($submitted): ?>
                <!-- Already Submitted -->
                <div class="submitted-info">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="color: rgba(255,255,255,0.6);">
                            <i class="fas fa-clock"></i> ส่งเมื่อ <?= date('H:i', strtotime($submitted['created_at'])) ?> น.
                        </span>
                        <span class="status-badge <?= $submitted['status'] ?>">
                            <?php if ($submitted['status'] === 'pending'): ?>
                            <i class="fas fa-hourglass-half"></i> รอตรวจสอบ
                            <?php elseif ($submitted['status'] === 'approved'): ?>
                            <i class="fas fa-check-circle"></i> อนุมัติแล้ว
                            <?php else: ?>
                            <i class="fas fa-times-circle"></i> ไม่อนุมัติ
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($submitted['link_url']): ?>
                    <div><a href="<?= htmlspecialchars($submitted['link_url']) ?>" target="_blank">
                        <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars(substr($submitted['link_url'], 0, 50)) ?>...
                    </a></div>
                    <?php endif; ?>
                    <?php if ($submitted['image_path']): ?>
                    <div style="margin-top: 8px;">
                        <img src="<?= htmlspecialchars($submitted['image_path']) ?>" alt="Screenshot" 
                             style="max-width: 100%; border-radius: 8px; cursor: pointer;"
                             onclick="window.open(this.src, '_blank')">
                    </div>
                    <?php endif; ?>
                    <?php if ($submitted['status'] === 'rejected' && $submitted['reject_reason']): ?>
                    <div style="margin-top: 8px; color: #ff6b6b;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($submitted['reject_reason']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php else: ?>
                <!-- Input Form -->
                <form class="task-input" data-task-type="<?= $task['input_type'] ?>" onsubmit="submitTask(event, <?= $task['id'] ?>)">
                    <?php if ($task['input_type'] === 'link'): ?>
                    <input type="text" name="link_url" placeholder="วาง Link Video ที่นี่..." required>
                    <?php else: ?>
                    <div class="file-upload">
                        <input type="file" name="image" id="file-<?= $task['id'] ?>" accept="image/*" required
                               onchange="previewImage(this, <?= $task['id'] ?>)">
                        <label for="file-<?= $task['id'] ?>" class="file-upload-label" id="label-<?= $task['id'] ?>">
                            <i class="fas fa-camera"></i> แตะเพื่ออัปโหลดภาพหน้าจอ
                        </label>
                        <img class="image-preview" id="preview-<?= $task['id'] ?>" alt="Preview">
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> ส่งงาน
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="checklist.php" class="nav-item active">
            <i class="fas fa-clipboard-check"></i>
            <span>Checklist</span>
        </a>
        <a href="history.php" class="nav-item">
            <i class="fas fa-history"></i>
            <span>ประวัติ</span>
        </a>
        <a href="leaderboard.php" class="nav-item">
            <i class="fas fa-trophy"></i>
            <span>อันดับ</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออก</span>
        </a>
    </div>
    
    <!-- Toast -->
    <div class="toast" id="toast"></div>
    
    <script>
        const branchId = <?= $userBranch ? $userBranch['id'] : 'null' ?>;
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        function previewImage(input, taskId) {
            const label = document.getElementById('label-' + taskId);
            const preview = document.getElementById('preview-' + taskId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('show');
                    label.classList.add('has-file');
                    label.innerHTML = '<i class="fas fa-check"></i> เลือกรูปแล้ว: ' + input.files[0].name.substring(0, 20);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        async function submitTask(event, taskId) {
            event.preventDefault();
            
            const form = event.target;
            const btn = form.querySelector('.submit-btn');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner"></div> กำลังส่ง...';
            
            const formData = new FormData(form);
            formData.append('task_type_id', taskId);
            formData.append('branch_id', branchId);
            
            try {
                const response = await fetch('api/submit_task.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (error) {
                showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>
