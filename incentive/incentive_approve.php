<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

// Check admin access
$superadmins = array('admin', 'oat', 'it', 'may');
$is_superadmin = in_array(strtolower($username), $superadmins);

$userRole = '';
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userRole = isset($row['role']) ? strtolower($row['role']) : '';
    }
    $stmt->close();
}

$admin_roles = array('admin', 'owner', 'brand', 'marketing');
$is_admin = $is_superadmin || in_array($userRole, $admin_roles);

if (!$is_admin) {
    header("location: index.php");
    exit;
}

$message = '';
$messageType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Single approve/reject with comment
    if (isset($_POST['approve_with_comment']) || isset($_POST['reject_with_comment'])) {
        $logId = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
        $comment = isset($_POST['admin_comment']) ? trim($_POST['admin_comment']) : '';
        $status = isset($_POST['approve_with_comment']) ? 'approved' : 'rejected';
        
        if ($logId > 0) {
            $stmt = $conn->prepare("UPDATE incentive_daily_logs SET status = ?, approved_by = ?, approved_at = NOW(), admin_comment = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sisi", $status, $userId, $comment, $logId);
                $stmt->execute();
                $stmt->close();
                $message = ($status === 'approved') ? 'อนุมัติสำเร็จ!' : 'ปฏิเสธสำเร็จ!';
                $messageType = 'success';
            }
        }
    }
    
    // Bulk approve
    if (isset($_POST['bulk_approve']) && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $ids = array_map('intval', $_POST['selected_ids']);
        $comment = isset($_POST['bulk_comment']) ? trim($_POST['bulk_comment']) : '';
        
        if (count($ids) > 0) {
            $idsStr = implode(',', $ids);
            $commentEsc = $conn->real_escape_string($comment);
            $sql = "UPDATE incentive_daily_logs SET status = 'approved', approved_by = $userId, approved_at = NOW(), admin_comment = '$commentEsc' WHERE id IN ($idsStr)";
            $conn->query($sql);
            $message = 'อนุมัติ ' . count($ids) . ' รายการสำเร็จ!';
            $messageType = 'success';
        }
    }
}

// Filter
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filterUser = isset($_GET['user']) ? $_GET['user'] : '';

// Build query
$sql = "SELECT l.*, u.name as user_name, u.branch_name, c.category_name, a.name as approver_name
        FROM incentive_daily_logs l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN users a ON l.approved_by = a.id
        LEFT JOIN incentive_content_categories c ON l.content_category = c.category_key
        WHERE u.role = 'shop'";

if ($filterStatus !== '') {
    $sql .= " AND l.status = '" . $conn->real_escape_string($filterStatus) . "'";
}
if ($filterUser !== '') {
    $sql .= " AND l.user_id = " . (int)$filterUser;
}
$sql .= " ORDER BY l.created_at DESC LIMIT 100";

$logs = array();
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Get shop users
$users = array();
$result = $conn->query("SELECT id, name, username FROM users WHERE role = 'shop' ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Pending count
$pendingCount = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM incentive_daily_logs l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending' AND u.role = 'shop'");
if ($result) {
    $row = $result->fetch_assoc();
    $pendingCount = isset($row['cnt']) ? (int)$row['cnt'] : 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติงาน | Incentive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; min-height: 100vh; color: #fff; padding: 15px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #0f3460; padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 1.3em; display: flex; align-items: center; gap: 10px; }
        .badge { background: #e94560; padding: 4px 12px; border-radius: 15px; font-size: 13px; }
        .nav-links { display: flex; gap: 8px; }
        .nav-links a { background: #252542; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; }
        .alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert.success { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .filters { background: #16213e; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filters select { padding: 10px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #e94560; color: #fff; }
        .btn-success { background: #2ed573; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .btn-sm { padding: 8px 12px; font-size: 12px; }
        .bulk-section { background: #16213e; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .bulk-section input[type="text"] { flex: 1; min-width: 200px; padding: 10px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; }
        .table-container { background: #16213e; border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #252542; }
        th { background: #0f3460; font-size: 12px; color: #aaa; }
        .user-info .name { font-weight: 600; }
        .user-info .branch { font-size: 11px; color: #888; }
        .task-info .task-name { font-size: 13px; }
        .task-info .category { font-size: 11px; color: #ec4899; }
        .task-info .note { font-size: 11px; color: #888; }
        .points { font-weight: 700; color: #ec4899; }
        .proof-cell { display: flex; gap: 8px; align-items: center; }
        .proof-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid #3a3a5a; }
        .proof-link { color: #3b82f6; font-size: 12px; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .status-badge.pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .status-badge.approved { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .status-badge.rejected { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .actions { display: flex; gap: 5px; }
        .checkbox { width: 18px; height: 18px; cursor: pointer; }
        .empty { text-align: center; padding: 50px; color: #666; }
        .comment-info { font-size: 11px; margin-top: 5px; }
        .comment-info .comment-text { color: #fbbf24; font-style: italic; }
        .comment-info .approver { color: #666; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal.show { display: flex; }
        .modal-content { background: #16213e; padding: 25px; border-radius: 15px; width: 90%; max-width: 450px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 1.1em; }
        .modal-close { background: none; border: none; color: #888; font-size: 20px; cursor: pointer; }
        .modal-body { margin-bottom: 20px; }
        .modal-body textarea { width: 100%; padding: 12px; background: #252542; border: 1px solid #3a3a5a; border-radius: 10px; color: #fff; font-size: 14px; resize: vertical; min-height: 100px; }
        .modal-body .info { font-size: 12px; color: #888; margin-bottom: 10px; }
        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
        .image-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1001; justify-content: center; align-items: center; }
        .image-modal.show { display: flex; }
        .image-modal img { max-width: 90%; max-height: 90%; border-radius: 10px; }
        .image-modal-close { position: absolute; top: 20px; right: 30px; color: #fff; font-size: 30px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-check-circle"></i> อนุมัติงาน
                <?php if ($pendingCount > 0): ?>
                <span class="badge"><?php echo $pendingCount; ?> รอ</span>
                <?php endif; ?>
            </h1>
            <div class="nav-links">
                <a href="incentive_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                <a href="index.php"><i class="fas fa-clipboard-list"></i> Checklist</a>
                <a href="../dashboard.php"><i class="fas fa-home"></i></a>
            </div>
        </div>
        
        <?php if ($message !== ''): ?>
        <div class="alert <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form class="filters" method="GET">
            <select name="status">
                <option value="pending" <?php if ($filterStatus === 'pending') echo 'selected'; ?>>รออนุมัติ</option>
                <option value="approved" <?php if ($filterStatus === 'approved') echo 'selected'; ?>>อนุมัติแล้ว</option>
                <option value="rejected" <?php if ($filterStatus === 'rejected') echo 'selected'; ?>>ไม่ผ่าน</option>
                <option value="" <?php if ($filterStatus === '') echo 'selected'; ?>>ทั้งหมด</option>
            </select>
            <select name="user">
                <option value="">พนักงานทุกคน</option>
                <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php if ($filterUser == $u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['name'] ? $u['name'] : $u['username']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> กรอง</button>
        </form>
        
        <form method="POST" id="bulkForm">
        <?php if ($filterStatus === 'pending' && count($logs) > 0): ?>
            <div class="bulk-section">
                <input type="checkbox" id="selectAll" class="checkbox" onclick="toggleAll()">
                <label for="selectAll" style="font-size: 13px;">เลือกทั้งหมด</label>
                <input type="text" name="bulk_comment" placeholder="💬 คอมเมนต์ (ถ้ามี)">
                <button type="submit" name="bulk_approve" class="btn btn-success btn-sm"><i class="fas fa-check"></i> อนุมัติที่เลือก</button>
            </div>
        <?php endif; ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php if ($filterStatus === 'pending'): ?><th width="40"></th><?php endif; ?>
                            <th>พนักงาน</th>
                            <th>งาน</th>
                            <th>วันที่</th>
                            <th>จำนวน</th>
                            <th>แต้ม</th>
                            <th>หลักฐาน</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) === 0): ?>
                        <tr><td colspan="9" class="empty"><i class="fas fa-inbox" style="font-size:30px;margin-bottom:10px;display:block"></i>ไม่มีรายการ</td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <?php
                        $userName = isset($log['user_name']) ? $log['user_name'] : '';
                        $branchName = isset($log['branch_name']) ? $log['branch_name'] : '';
                        $taskKey = isset($log['task_key']) ? $log['task_key'] : '';
                        $categoryName = isset($log['category_name']) ? $log['category_name'] : '';
                        $note = isset($log['note']) ? $log['note'] : '';
                        $logDate = isset($log['log_date']) ? $log['log_date'] : '';
                        $quantity = isset($log['quantity']) ? $log['quantity'] : 0;
                        $pointsEarned = isset($log['points_earned']) ? $log['points_earned'] : 0;
                        $proofImage = isset($log['proof_image']) ? $log['proof_image'] : '';
                        $proofUrl = isset($log['proof_url']) ? $log['proof_url'] : '';
                        $status = isset($log['status']) ? $log['status'] : '';
                        $adminComment = isset($log['admin_comment']) ? $log['admin_comment'] : '';
                        $approverName = isset($log['approver_name']) ? $log['approver_name'] : '';
                        $approvedAt = isset($log['approved_at']) ? $log['approved_at'] : '';
                        $logId = isset($log['id']) ? $log['id'] : 0;
                        
                        $taskIcons = array('tiktok_clip' => '🎬', 'google_maps_update' => '📍', 'google_review' => '⭐');
                        $taskNames = array('tiktok_clip' => 'TikTok', 'google_maps_update' => 'Google Maps', 'google_review' => 'Review');
                        $taskIcon = isset($taskIcons[$taskKey]) ? $taskIcons[$taskKey] : '';
                        $taskName = isset($taskNames[$taskKey]) ? $taskNames[$taskKey] : $taskKey;
                        ?>
                        <tr>
                            <?php if ($filterStatus === 'pending'): ?>
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $logId; ?>" class="checkbox item-checkbox"></td>
                            <?php endif; ?>
                            <td>
                                <div class="user-info">
                                    <div class="name"><?php echo htmlspecialchars($userName); ?></div>
                                    <div class="branch"><?php echo htmlspecialchars($branchName); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="task-info">
                                    <div class="task-name"><?php echo $taskIcon . ' ' . $taskName; ?></div>
                                    <?php if ($categoryName !== ''): ?>
                                    <div class="category"><?php echo htmlspecialchars($categoryName); ?></div>
                                    <?php endif; ?>
                                    <?php if ($note !== ''): ?>
                                    <div class="note">📝 <?php echo htmlspecialchars($note); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $logDate ? date('d/m', strtotime($logDate)) : '-'; ?></td>
                            <td><?php echo $quantity; ?></td>
                            <td><span class="points">+<?php echo $pointsEarned; ?></span></td>
                            <td>
                                <div class="proof-cell">
                                    <?php if ($proofImage !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($proofImage); ?>" class="proof-thumb" onclick="showImage('<?php echo htmlspecialchars($proofImage); ?>')">
                                    <?php endif; ?>
                                    <?php if ($proofUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($proofUrl); ?>" target="_blank" class="proof-link"><i class="fas fa-external-link-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if ($proofImage === '' && $proofUrl === ''): ?>
                                    <span style="color:#666">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status; ?>"><?php echo $status; ?></span>
                                <?php if ($status !== 'pending' && $adminComment !== ''): ?>
                                <div class="comment-info">
                                    <div class="comment-text">"<?php echo htmlspecialchars($adminComment); ?>"</div>
                                    <div class="approver">- <?php echo htmlspecialchars($approverName); ?></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                <div class="actions">
                                    <button type="button" class="btn btn-success btn-sm" onclick="openModal(<?php echo $logId; ?>, 'approve', '<?php echo htmlspecialchars(addslashes($userName)); ?>')"><i class="fas fa-check"></i></button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openModal(<?php echo $logId; ?>, 'reject', '<?php echo htmlspecialchars(addslashes($userName)); ?>')"><i class="fas fa-times"></i></button>
                                </div>
                                <?php else: ?>
                                <span style="color:#666;font-size:11px"><?php echo $approvedAt ? date('d/m H:i', strtotime($approvedAt)) : '-'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    
    <!-- Comment Modal -->
    <div class="modal" id="commentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">💬 คอมเมนต์</h3>
                <button type="button" class="modal-close" onclick="closeCommentModal()">&times;</button>
            </div>
            <form method="POST" id="commentForm">
                <input type="hidden" name="log_id" id="modalLogId" value="">
                <div class="modal-body">
                    <div class="info" id="modalInfo"></div>
                    <textarea name="admin_comment" id="adminComment" placeholder="ใส่คอมเมนต์ (ไม่บังคับ)..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#444" onclick="closeCommentModal()">ยกเลิก</button>
                    <button type="submit" id="modalSubmitBtn" name="approve_with_comment" class="btn btn-success">
                        <i class="fas fa-check"></i> อนุมัติ
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <span class="image-modal-close">&times;</span>
        <img id="modalImage" src="">
    </div>
    
    <script>
        function toggleAll() {
            var checkboxes = document.querySelectorAll('.item-checkbox');
            var selectAll = document.getElementById('selectAll');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = selectAll.checked;
            }
        }
        
        function openModal(logId, action, userName) {
            document.getElementById('modalLogId').value = logId;
            document.getElementById('adminComment').value = '';
            document.getElementById('modalInfo').textContent = 'พนักงาน: ' + userName;
            
            var submitBtn = document.getElementById('modalSubmitBtn');
            if (action === 'approve') {
                document.getElementById('modalTitle').textContent = '✅ อนุมัติงาน';
                submitBtn.name = 'approve_with_comment';
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-check"></i> อนุมัติ';
            } else {
                document.getElementById('modalTitle').textContent = '❌ ปฏิเสธงาน';
                submitBtn.name = 'reject_with_comment';
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-times"></i> ปฏิเสธ';
            }
            
            document.getElementById('commentModal').classList.add('show');
            document.getElementById('adminComment').focus();
        }
        
        function closeCommentModal() {
            document.getElementById('commentModal').classList.remove('show');
        }
        
        function showImage(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('show');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('show');
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCommentModal();
                closeImageModal();
            }
        });
    </script>
</body>
</html>