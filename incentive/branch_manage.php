<?php
// incentive/branch_manage.php - จัดการสาขาที่เข้าร่วม Incentive
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$username = $_SESSION['username'];
$userId = $_SESSION['id'];

// Check admin access
$superadmins = ['admin', 'oat', 'it', 'may'];
$is_superadmin = in_array(strtolower($username), $superadmins);

$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRole = strtolower($stmt->get_result()->fetch_assoc()['role']);

$admin_roles = ['admin', 'owner', 'brand', 'marketing'];
$is_admin = $is_superadmin || in_array($userRole, $admin_roles);

if (!$is_admin) {
    header("location: index.php");
    exit;
}

// Create table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS incentive_branch_settings (
        id int(11) NOT NULL AUTO_INCREMENT,
        branch_name varchar(255) NOT NULL,
        is_active tinyint(1) DEFAULT 1,
        note text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_branch (branch_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle branch status
    if (isset($_POST['toggle_branch'])) {
        $branchName = trim($_POST['branch_name']);
        $newStatus = (int)$_POST['new_status'];
        
        // Check if branch exists in settings
        $checkStmt = $conn->prepare("SELECT id FROM incentive_branch_settings WHERE branch_name = ?");
        $checkStmt->bind_param("s", $branchName);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE incentive_branch_settings SET is_active = ? WHERE branch_name = ?");
            $stmt->bind_param("is", $newStatus, $branchName);
        } else {
            $stmt = $conn->prepare("INSERT INTO incentive_branch_settings (branch_name, is_active) VALUES (?, ?)");
            $stmt->bind_param("si", $branchName, $newStatus);
        }
        
        if ($stmt->execute()) {
            $message = $newStatus ? "เปิดการเข้าร่วม \"$branchName\" สำเร็จ!" : "ปิดการเข้าร่วม \"$branchName\" สำเร็จ!";
            $messageType = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
    
    // Update note
    if (isset($_POST['update_note'])) {
        $branchName = trim($_POST['branch_name']);
        $note = trim($_POST['note']);
        
        $checkStmt = $conn->prepare("SELECT id FROM incentive_branch_settings WHERE branch_name = ?");
        $checkStmt->bind_param("s", $branchName);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE incentive_branch_settings SET note = ? WHERE branch_name = ?");
            $stmt->bind_param("ss", $note, $branchName);
        } else {
            $stmt = $conn->prepare("INSERT INTO incentive_branch_settings (branch_name, note, is_active) VALUES (?, ?, 1)");
            $stmt->bind_param("ss", $branchName, $note);
        }
        
        if ($stmt->execute()) {
            $message = "บันทึกหมายเหตุสำเร็จ!";
            $messageType = 'success';
        }
        $stmt->close();
    }
    
    // Bulk update
    if (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selectedBranches = $_POST['selected_branches'] ?? [];
        
        if (!empty($selectedBranches)) {
            $newStatus = ($action === 'activate') ? 1 : 0;
            
            foreach ($selectedBranches as $branchName) {
                $branchName = trim($branchName);
                $checkStmt = $conn->prepare("SELECT id FROM incentive_branch_settings WHERE branch_name = ?");
                $checkStmt->bind_param("s", $branchName);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
                
                if ($exists) {
                    $stmt = $conn->prepare("UPDATE incentive_branch_settings SET is_active = ? WHERE branch_name = ?");
                    $stmt->bind_param("is", $newStatus, $branchName);
                } else {
                    $stmt = $conn->prepare("INSERT INTO incentive_branch_settings (branch_name, is_active) VALUES (?, ?)");
                    $stmt->bind_param("si", $branchName, $newStatus);
                }
                $stmt->execute();
                $stmt->close();
            }
            
            $message = ($action === 'activate') ? 
                'เปิดการเข้าร่วม ' . count($selectedBranches) . ' สาขาสำเร็จ!' : 
                'ปิดการเข้าร่วม ' . count($selectedBranches) . ' สาขาสำเร็จ!';
            $messageType = 'success';
        }
    }
}

// Get all unique branches from users table with shop role
$branchesQuery = "
    SELECT 
        u.branch_name,
        COUNT(u.id) as staff_count,
        COALESCE(bs.is_active, 1) as is_active,
        bs.note,
        bs.updated_at
    FROM users u
    LEFT JOIN incentive_branch_settings bs ON u.branch_name = bs.branch_name
    WHERE u.role = 'shop' AND u.branch_name IS NOT NULL AND u.branch_name != ''
    GROUP BY u.branch_name
    ORDER BY bs.is_active DESC, u.branch_name
";
$branches = $conn->query($branchesQuery)->fetch_all(MYSQLI_ASSOC);

// Stats
$totalBranches = count($branches);
$activeBranches = count(array_filter($branches, fn($b) => $b['is_active']));
$inactiveBranches = $totalBranches - $activeBranches;
$totalStaff = array_sum(array_column($branches, 'staff_count'));
$activeStaff = array_sum(array_map(fn($b) => $b['is_active'] ? $b['staff_count'] : 0, $branches));

// Filter
$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== '') {
    $branches = array_filter($branches, fn($b) => $filterStatus === 'active' ? $b['is_active'] : !$b['is_active']);
    $branches = array_values($branches);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสาขา | Incentive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; min-height: 100vh; color: #fff; padding: 15px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header { background: linear-gradient(135deg, #0f3460 0%, #16213e 100%); padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 1.3em; display: flex; align-items: center; gap: 10px; }
        .header h1 i { color: #ec4899; }
        
        .nav-links { display: flex; gap: 8px; flex-wrap: wrap; }
        .nav-links a { background: #252542; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; transition: all 0.2s; }
        .nav-links a:hover { background: #3a3a5a; transform: translateY(-2px); }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s; }
        .alert.success { background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.3); }
        .alert.error { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: #16213e; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #252542; }
        .summary-card .icon { font-size: 24px; margin-bottom: 10px; }
        .summary-card .value { font-size: 28px; font-weight: 700; }
        .summary-card .value.green { color: #2ed573; }
        .summary-card .value.red { color: #ff6b6b; }
        .summary-card .value.blue { color: #3b82f6; }
        .summary-card .value.pink { color: #ec4899; }
        .summary-card .label { font-size: 12px; color: #888; margin-top: 5px; }
        
        .toolbar { background: #16213e; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .toolbar-left { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar-right { display: flex; gap: 10px; align-items: center; }
        
        .filters select { padding: 10px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; cursor: pointer; }
        
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; font-weight: 500; }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: #e94560; color: #fff; }
        .btn-success { background: #2ed573; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .btn-secondary { background: #252542; color: #fff; border: 1px solid #3a3a5a; }
        .btn-sm { padding: 8px 12px; font-size: 12px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .table-container { background: #16213e; border-radius: 15px; overflow: hidden; border: 1px solid #252542; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #252542; }
        th { background: #0f3460; font-size: 12px; color: #aaa; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: rgba(255,255,255,0.03); }
        tr:last-child td { border-bottom: none; }
        
        .branch-info { display: flex; align-items: center; gap: 12px; }
        .branch-icon { width: 40px; height: 40px; background: #252542; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .branch-icon.active { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
        .branch-icon.inactive { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; }
        .branch-name { font-weight: 600; font-size: 14px; }
        .branch-note { font-size: 11px; color: #888; margin-top: 2px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .staff-count { display: flex; align-items: center; gap: 6px; font-size: 13px; }
        .staff-count i { color: #3b82f6; }
        
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .status-badge.active { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
        .status-badge.inactive { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; }
        
        .actions { display: flex; gap: 8px; align-items: center; }
        
        .toggle-btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .toggle-btn.activate { background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.3); }
        .toggle-btn.activate:hover { background: #2ed573; color: #fff; }
        .toggle-btn.deactivate { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); }
        .toggle-btn.deactivate:hover { background: #ff6b6b; color: #fff; }
        
        .note-btn { background: transparent; border: 1px solid #3a3a5a; color: #888; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .note-btn:hover { border-color: #fbbf24; color: #fbbf24; }
        
        .checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #ec4899; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; color: #3a3a5a; }
        .empty-state p { font-size: 14px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #16213e; border-radius: 15px; padding: 25px; width: 90%; max-width: 400px; animation: modalIn 0.3s; }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .modal-close { background: none; border: none; color: #888; font-size: 20px; cursor: pointer; }
        .modal-close:hover { color: #fff; }
        .modal-body textarea { width: 100%; padding: 12px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; font-size: 13px; resize: vertical; min-height: 100px; }
        .modal-body textarea:focus { outline: none; border-color: #ec4899; }
        .modal-footer { margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end; }
        
        .info-box { margin-top: 20px; padding: 20px; background: #252542; border-radius: 12px; }
        .info-box h4 { font-size: 14px; margin-bottom: 10px; color: #fbbf24; display: flex; align-items: center; gap: 8px; }
        .info-box ul { font-size: 12px; color: #aaa; line-height: 2; padding-left: 20px; }
        .info-box ul li { margin-bottom: 5px; }
        
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar-left, .toolbar-right { justify-content: center; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .branch-note { max-width: 120px; }
            .actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-store"></i> จัดการสาขา Incentive
            </h1>
            <div class="nav-links">
                <a href="incentive_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                <a href="incentive_approve.php"><i class="fas fa-check"></i> อนุมัติ</a>
                <a href="index.php"><i class="fas fa-clipboard-list"></i> Checklist</a>
                <a href="../dashboard.php"><i class="fas fa-home"></i></a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="summary-cards">
            <div class="summary-card">
                <div class="icon">🏪</div>
                <div class="value blue"><?= $totalBranches ?></div>
                <div class="label">สาขาทั้งหมด</div>
            </div>
            <div class="summary-card">
                <div class="icon">✅</div>
                <div class="value green"><?= $activeBranches ?></div>
                <div class="label">เข้าร่วม</div>
            </div>
            <div class="summary-card">
                <div class="icon">❌</div>
                <div class="value red"><?= $inactiveBranches ?></div>
                <div class="label">ไม่เข้าร่วม</div>
            </div>
            <div class="summary-card">
                <div class="icon">👥</div>
                <div class="value pink"><?= $activeStaff ?>/<?= $totalStaff ?></div>
                <div class="label">พนักงานที่เข้าร่วม</div>
            </div>
        </div>
        
        <form method="POST" id="bulkForm">
            <div class="toolbar">
                <div class="toolbar-left">
                    <input type="checkbox" id="selectAll" class="checkbox" onclick="toggleAll()">
                    <label for="selectAll" style="font-size: 13px; cursor: pointer;">เลือกทั้งหมด</label>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-success btn-sm" id="bulkActivate" disabled>
                        <i class="fas fa-check"></i> เปิดที่เลือก
                    </button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-danger btn-sm" id="bulkDeactivate" disabled>
                        <i class="fas fa-times"></i> ปิดที่เลือก
                    </button>
                </div>
                <div class="toolbar-right">
                    <select onchange="filterByStatus(this.value)">
                        <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>ทุกสถานะ</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>เข้าร่วม</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>ไม่เข้าร่วม</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <th>สาขา</th>
                            <th>พนักงาน</th>
                            <th>สถานะ</th>
                            <th>อัปเดตล่าสุด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($branches)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-store-slash"></i>
                                    <p>ไม่พบข้อมูลสาขา</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_branches[]" value="<?= htmlspecialchars($branch['branch_name']) ?>" class="checkbox item-checkbox" onchange="updateBulkButtons()">
                            </td>
                            <td>
                                <div class="branch-info">
                                    <div class="branch-icon <?= $branch['is_active'] ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <div>
                                        <div class="branch-name"><?= htmlspecialchars($branch['branch_name']) ?></div>
                                        <?php if ($branch['note']): ?>
                                        <div class="branch-note" title="<?= htmlspecialchars($branch['note']) ?>"><?= htmlspecialchars($branch['note']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="staff-count">
                                    <i class="fas fa-users"></i>
                                    <?= $branch['staff_count'] ?> คน
                                </div>
                            </td>
                            <td>
                                <?php if ($branch['is_active']): ?>
                                <span class="status-badge active"><i class="fas fa-check-circle"></i> เข้าร่วม</span>
                                <?php else: ?>
                                <span class="status-badge inactive"><i class="fas fa-times-circle"></i> ไม่เข้าร่วม</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: #888;">
                                <?= $branch['updated_at'] ? date('d/m/Y H:i', strtotime($branch['updated_at'])) : '-' ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($branch['is_active']): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('ยืนยันปิดการเข้าร่วมสาขา <?= htmlspecialchars($branch['branch_name']) ?>?')">
                                        <input type="hidden" name="branch_name" value="<?= htmlspecialchars($branch['branch_name']) ?>">
                                        <input type="hidden" name="new_status" value="0">
                                        <button type="submit" name="toggle_branch" class="toggle-btn deactivate">
                                            <i class="fas fa-times"></i> ปิด
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="branch_name" value="<?= htmlspecialchars($branch['branch_name']) ?>">
                                        <input type="hidden" name="new_status" value="1">
                                        <button type="submit" name="toggle_branch" class="toggle-btn activate">
                                            <i class="fas fa-check"></i> เปิด
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button" class="note-btn" onclick="openNoteModal('<?= htmlspecialchars(addslashes($branch['branch_name'])) ?>', '<?= htmlspecialchars(addslashes($branch['note'] ?? '')) ?>')">
                                        <i class="fas fa-sticky-note"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> คำอธิบาย</h4>
            <ul>
                <li><strong>เข้าร่วม</strong>: พนักงานในสาขานี้สามารถบันทึกงานและรับ Incentive ได้</li>
                <li><strong>ไม่เข้าร่วม</strong>: พนักงานในสาขานี้จะไม่สามารถบันทึกงาน Incentive ได้ และจะไม่แสดงในรายงาน</li>
                <li>การเปลี่ยนสถานะจะมีผลทันที</li>
                <li>สามารถเพิ่มหมายเหตุเพื่อบันทึกเหตุผลได้</li>
            </ul>
        </div>
    </div>
    
    <!-- Note Modal -->
    <div class="modal" id="noteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-sticky-note"></i> หมายเหตุ</h3>
                <button type="button" class="modal-close" onclick="closeNoteModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="branch_name" id="modalBranchName">
                <div class="modal-body">
                    <label style="font-size: 12px; color: #888; display: block; margin-bottom: 8px;">
                        สาขา: <strong id="modalBranchLabel"></strong>
                    </label>
                    <textarea name="note" id="modalNote" placeholder="เพิ่มหมายเหตุ เช่น เหตุผลที่ไม่เข้าร่วม..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">ยกเลิก</button>
                    <button type="submit" name="update_note" class="btn btn-primary"><i class="fas fa-save"></i> บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleAll() {
            const selectAll = document.getElementById('selectAll').checked;
            document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = selectAll);
            updateBulkButtons();
        }
        
        function updateBulkButtons() {
            const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
            document.getElementById('bulkActivate').disabled = checkedCount === 0;
            document.getElementById('bulkDeactivate').disabled = checkedCount === 0;
        }
        
        function filterByStatus(status) {
            const url = new URL(window.location);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location = url;
        }
        
        function openNoteModal(branchName, note) {
            document.getElementById('modalBranchName').value = branchName;
            document.getElementById('modalBranchLabel').textContent = branchName;
            document.getElementById('modalNote').value = note;
            document.getElementById('noteModal').classList.add('show');
        }
        
        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('show');
        }
        
        // Close modal on outside click
        document.getElementById('noteModal').addEventListener('click', function(e) {
            if (e.target === this) closeNoteModal();
        });
    </script>
</body>
</html>