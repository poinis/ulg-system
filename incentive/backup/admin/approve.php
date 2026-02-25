<?php
// admin/approve.php - Approve/Reject Submissions
session_start();
require_once '../config.php';
require_once '../includes/functions.php';

// Check login & admin
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION)) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Handle approve/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $submissionId = (int) $_POST['submission_id'];
    $action = $_POST['action'];
    $rejectReason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : null;
    
    if ($action === 'approve') {
        reviewSubmission($conn, $submissionId, 'approved', $userId);
    } elseif ($action === 'reject') {
        reviewSubmission($conn, $submissionId, 'rejected', $userId, $rejectReason);
    }
    
    // Redirect back
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filters
$filters = [];
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedBranch = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : 'pending';
$yearMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if ($selectedDate) {
    $filters['date'] = $selectedDate;
} else {
    $filters['year_month'] = $yearMonth;
}

if ($selectedBranch) {
    $filters['branch_id'] = $selectedBranch;
}

if ($selectedStatus) {
    $filters['status'] = $selectedStatus;
}

$submissions = getSubmissions($conn, $filters);
$branches = getBranches($conn);

// Get pending count
$pendingResult = $conn->query("SELECT COUNT(*) as cnt FROM incentive_submissions WHERE status = 'pending'");
$pendingCount = $pendingResult->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบงาน | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 20px 0;
            z-index: 100;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h2 { font-size: 20px; font-weight: 600; }
        .sidebar-header p { font-size: 13px; color: rgba(255,255,255,0.6); margin-top: 4px; }
        .nav-menu { list-style: none; }
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: #667eea;
        }
        .nav-menu a i { width: 20px; text-align: center; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge.pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 24px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .top-bar h1 { font-size: 24px; color: #1a1a2e; }
        
        /* Filter Bar */
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filter-bar select, .filter-bar input {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            min-width: 150px;
        }
        .filter-bar button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        .filter-bar button.reset {
            background: #eee;
            color: #666;
        }
        
        /* Submissions Grid */
        .submissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .submission-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .submission-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .submission-card.pending { border-left: 4px solid #ffc107; }
        .submission-card.approved { border-left: 4px solid #2ed573; }
        .submission-card.rejected { border-left: 4px solid #ff6b6b; }
        
        .submission-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .submission-header .task-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .submission-header .task-icon {
            width: 40px;
            height: 40px;
            background: #f5f7fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .submission-header .task-name {
            font-weight: 600;
            color: #1a1a2e;
        }
        .submission-header .task-points {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .submission-body {
            padding: 20px;
        }
        .submission-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .submission-meta .meta-item {
            color: #666;
        }
        .submission-meta .meta-item strong {
            color: #1a1a2e;
        }
        
        .submission-content {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .submission-content a {
            color: #667eea;
            word-break: break-all;
            font-size: 14px;
        }
        .submission-content img {
            max-width: 100%;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .submission-content img:hover {
            transform: scale(1.02);
        }
        
        .submission-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-approve {
            background: #2ed573;
            color: #fff;
        }
        .btn-approve:hover { background: #26b863; }
        .btn-reject {
            background: #ff6b6b;
            color: #fff;
        }
        .btn-reject:hover { background: #ee5a52; }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .status-badge.pending { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .status-badge.approved { background: rgba(46, 213, 115, 0.1); color: #2ed573; }
        .status-badge.rejected { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }
        
        .reject-reason {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 12px;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        .modal h3 {
            margin-bottom: 16px;
            color: #1a1a2e;
        }
        .modal textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        /* Image Modal */
        .image-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            cursor: pointer;
        }
        .image-modal.show { display: flex; }
        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🎯 Incentive</h2>
            <p>Admin Panel</p>
        </div>
        <ul class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="approve.php" class="active">
                <i class="fas fa-clipboard-check"></i> ตรวจสอบงาน
                <?php if ($pendingCount > 0): ?>
                <span class="badge pending" style="margin-left: auto;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php"><i class="fas fa-calculator"></i> คำนวณเงิน</a>
            <a href="trophy.php"><i class="fas fa-trophy"></i> Trophy Bonus</a>
            <a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a>
            <a href="export.php"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="../checklist.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> กลับหน้า Checklist
            </a>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-clipboard-check"></i> ตรวจสอบงาน</h1>
        </div>
        
        <!-- Filter Bar -->
        <form class="filter-bar" method="GET">
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>รอตรวจสอบ</option>
                <option value="approved" <?= $selectedStatus === 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                <option value="rejected" <?= $selectedStatus === 'rejected' ? 'selected' : '' ?>>ไม่อนุมัติ</option>
            </select>
            <select name="branch">
                <option value="">ทุกสาขา</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selectedBranch == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="month" name="month" value="<?= $yearMonth ?>" placeholder="เดือน">
            <input type="date" name="date" value="<?= $selectedDate ?>" placeholder="วันที่">
            <button type="submit"><i class="fas fa-filter"></i> กรอง</button>
            <a href="approve.php" style="text-decoration: none;">
                <button type="button" class="reset"><i class="fas fa-redo"></i> รีเซ็ต</button>
            </a>
        </form>
        
        <!-- Submissions Grid -->
        <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>ไม่มีรายการ</h3>
            <p>ไม่พบงานที่ตรงกับเงื่อนไขที่เลือก</p>
        </div>
        <?php else: ?>
        <div class="submissions-grid">
            <?php foreach ($submissions as $sub): ?>
            <div class="submission-card <?= $sub['status'] ?>">
                <div class="submission-header">
                    <div class="task-type">
                        <div class="task-icon">
                            <?php
                            $icons = ['tiktok_reel' => '🎬', 'google_maps_update' => '📍', 'google_review' => '⭐', 'reply_qa' => '💬'];
                            echo $icons[$sub['task_code']] ?? '📋';
                            ?>
                        </div>
                        <div>
                            <div class="task-name"><?= htmlspecialchars($sub['task_name_th']) ?></div>
                            <small style="color: #999;"><?= htmlspecialchars($sub['branch_name']) ?></small>
                        </div>
                    </div>
                    <div class="task-points">+<?= $sub['task_points'] ?> pts</div>
                </div>
                
                <div class="submission-body">
                    <div class="submission-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i> <strong><?= htmlspecialchars($sub['user_name'] ?? $sub['username']) ?></strong>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i> <?= thaiDate($sub['submission_date']) ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i> <?= date('H:i', strtotime($sub['created_at'])) ?> น.
                        </div>
                        <div class="meta-item">
                            <span class="status-badge <?= $sub['status'] ?>">
                                <?php if ($sub['status'] === 'pending'): ?>รอตรวจสอบ
                                <?php elseif ($sub['status'] === 'approved'): ?>อนุมัติแล้ว
                                <?php else: ?>ไม่อนุมัติ<?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="submission-content">
                        <?php if ($sub['link_url']): ?>
                        <a href="<?= htmlspecialchars($sub['link_url']) ?>" target="_blank">
                            <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($sub['link_url']) ?>
                        </a>
                        <?php elseif ($sub['image_path']): ?>
                        <img src="../<?= htmlspecialchars($sub['image_path']) ?>" alt="Screenshot" 
                             onclick="showImage('../<?= htmlspecialchars($sub['image_path']) ?>')">
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($sub['status'] === 'pending'): ?>
                    <div class="submission-actions">
                        <form method="POST" style="flex: 1; display: flex;">
                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-approve" style="width: 100%;">
                                <i class="fas fa-check"></i> อนุมัติ
                            </button>
                        </form>
                        <button class="btn btn-reject" onclick="openRejectModal(<?= $sub['id'] ?>)" style="flex: 1;">
                            <i class="fas fa-times"></i> ไม่อนุมัติ
                        </button>
                    </div>
                    <?php else: ?>
                        <?php if ($sub['reviewer_name']): ?>
                        <div style="font-size: 13px; color: #999; margin-top: 10px;">
                            <i class="fas fa-user-check"></i> ตรวจสอบโดย: <?= htmlspecialchars($sub['reviewer_name']) ?>
                            <br><i class="fas fa-clock"></i> <?= $sub['reviewed_at'] ? date('d/m/Y H:i', strtotime($sub['reviewed_at'])) : '-' ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($sub['status'] === 'rejected' && $sub['reject_reason']): ?>
                        <div class="reject-reason">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($sub['reject_reason']) ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal">
            <h3><i class="fas fa-times-circle" style="color: #ff6b6b;"></i> ไม่อนุมัติงาน</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="submission_id" id="rejectSubmissionId">
                <input type="hidden" name="action" value="reject">
                <textarea name="reject_reason" placeholder="เหตุผลที่ไม่อนุมัติ (ถ้ามี)"></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #eee; color: #666;" onclick="closeRejectModal()">
                        ยกเลิก
                    </button>
                    <button type="submit" class="btn btn-reject">
                        <i class="fas fa-times"></i> ยืนยันไม่อนุมัติ
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <img src="" alt="Full Image" id="fullImage">
    </div>
    
    <script>
        function openRejectModal(submissionId) {
            document.getElementById('rejectSubmissionId').value = submissionId;
            document.getElementById('rejectModal').classList.add('show');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('show');
        }
        
        function showImage(src) {
            document.getElementById('fullImage').src = src;
            document.getElementById('imageModal').classList.add('show');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('show');
        }
        
        // Close modal on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRejectModal();
                closeImageModal();
            }
        });
    </script>
</body>
</html>
