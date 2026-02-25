<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

$username = $_SESSION['username'];

// ดึงข้อมูลผู้ใช้
$sql_user = "SELECT id, role, name FROM users WHERE username = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
mysqli_stmt_bind_result($stmt_user, $user_id, $role, $name);
mysqli_stmt_fetch($stmt_user);
mysqli_stmt_close($stmt_user);

$is_admin = in_array($role, ['admin', 'support']);

// Filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_urgency = $_GET['urgency'] ?? 'all';
$filter_date = $_GET['date'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT i.*, 
        u_assigned.name as assigned_name,
        u_resolved.name as resolved_name,
        (SELECT COUNT(*) FROM issue_updates WHERE issue_id = i.id) as update_count
        FROM issues i
        LEFT JOIN users u_assigned ON i.assigned_to = u_assigned.id
        LEFT JOIN users u_resolved ON i.resolved_by = u_resolved.id
        WHERE 1=1";

$params = [];
$types = "";

if (!$is_admin) {
    $sql .= " AND i.reporter_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if ($filter_status != 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_urgency != 'all') {
    $sql .= " AND i.urgency_level = ?";
    $params[] = $filter_urgency;
    $types .= "s";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(i.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($filter_category)) {
    $sql .= " AND JSON_CONTAINS(i.issue_types, ?)";
    $params[] = $filter_category;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (i.issue_number LIKE ? OR i.issue_description LIKE ? OR i.reporter_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY 
          CASE i.urgency_level 
            WHEN 'urgent' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'normal' THEN 3 
          END,
          i.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$issues = [];
while ($row = mysqli_fetch_assoc($result)) {
    $issues[] = $row;
}
mysqli_stmt_close($stmt);

// สถิติ
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0
];

$sql_stats = "SELECT status, COUNT(*) as count FROM issues";
if (!$is_admin) {
    $sql_stats .= " WHERE reporter_id = ?";
}
$sql_stats .= " GROUP BY status";

$stmt_stats = mysqli_prepare($conn, $sql_stats);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_stats, "i", $user_id);
}
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);

while ($row = mysqli_fetch_assoc($result_stats)) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}
mysqli_stmt_close($stmt_stats);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 รายการปัญหา - Topologie Daily</title>
    <link rel="stylesheet" href="dashboard_styles.css?v=2.0">
    <style>
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-label {
            font-weight: 700;
            color: #555;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            background: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .search-box {
            flex: 1;
            min-width: 280px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .issue-stats {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
        }
        
        .stat-box {
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            color: white;
            transition: all 0.3s ease;
            cursor: default;
            position: relative;
            overflow: hidden;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .stat-box:hover::before {
            opacity: 1;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
        }
        
        .stat-box.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-box.pending { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .stat-box.in_progress { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .stat-box.resolved { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); }
        .stat-box.closed { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        
        .stat-number {
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .issue-table-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }
        
        .issue-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .issue-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px;
            text-align: left;
            font-weight: 700;
            white-space: nowrap;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .issue-table thead th:first-child {
            border-radius: 12px 0 0 0;
        }
        
        .issue-table thead th:last-child {
            border-radius: 0 12px 0 0;
        }
        
        .issue-table tbody td {
            padding: 18px;
            border-bottom: 2px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .issue-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .issue-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }
        
        .issue-number {
            font-weight: 700;
            color: #667eea;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #fdecea 0%, #fadbd8 100%); 
            color: #c0392b; 
            border: 1px solid #c0392b;
        }
        .status-in_progress { 
            background: linear-gradient(135deg, #fef5e7 0%, #fdebd0 100%); 
            color: #e67e22; 
            border: 1px solid #e67e22;
        }
        .status-resolved { 
            background: linear-gradient(135deg, #eafaf1 0%, #d5f4e6 100%); 
            color: #27ae60; 
            border: 1px solid #27ae60;
        }
        .status-closed { 
            background: linear-gradient(135deg, #ecf0f1 0%, #d5dbdb 100%); 
            color: #7f8c8d; 
            border: 1px solid #7f8c8d;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #fadbd8 0%, #f5b7b1 100%); 
            color: #922b21; 
            border: 1px solid #922b21;
        }
        
        .urgency-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .urgency-urgent { 
            background: linear-gradient(135deg, #fdecea 0%, #fadbd8 100%); 
            color: #c0392b; 
            border: 1px solid #c0392b;
        }
        .urgency-medium { 
            background: linear-gradient(135deg, #fef5e7 0%, #fdebd0 100%); 
            color: #e67e22; 
            border: 1px solid #e67e22;
        }
        .urgency-normal { 
            background: linear-gradient(135deg, #eafaf1 0%, #d5f4e6 100%); 
            color: #27ae60; 
            border: 1px solid #27ae60;
        }
        
        .issue-description {
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.6;
            color: #555;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #95a5a6;
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 25px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #7f8c8d;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        .empty-state a {
            color: #667eea;
            font-weight: 700;
            text-decoration: none;
        }
        
        .empty-state a:hover {
            text-decoration: underline;
        }
        
        .date-text {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .issue-table-container {
                padding: 20px;
            }
            
            .stat-row {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 12px;
            }
            
            .stat-number {
                font-size: 28px;
            }
            
            .stat-label {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>📋 รายการปัญหา</h1>
        <div class="user-info">
            ผู้ใช้งาน: <strong><?php echo htmlspecialchars($name); ?></strong>
            <?php if ($is_admin): ?>
                <span class="admin-badge">👑 Admin/Support</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="header-right">
        <a href="../dashboard.php" class="btn btn-primary">🏠 หน้าหลัก</a>
        <a href="report_issue.php" class="btn btn-secondary">🚨 แจ้งปัญหาใหม่</a>
        <a href="issue_list.php" class="btn btn-secondary">📋 รายการปัญหา</a>
        <a href="index.php" class="btn btn-secondary">📊 Dashboard</a>
        <?php if ($is_admin): ?>
            <a href="issue_admin.php" class="btn btn-warning">⚙️ จัดการปัญหา</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger">🚪 ออกจากระบบ</a>
    </div>
</div>

<div class="container">
    <!-- Stats -->
    <div class="issue-stats">
        <div class="stat-row">
            <div class="stat-box total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">ทั้งหมด</div>
            </div>
            <div class="stat-box pending">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">รอดำเนินการ</div>
            </div>
            <div class="stat-box in_progress">
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">กำลังดำเนินการ</div>
            </div>
            <div class="stat-box resolved">
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">แก้ไขแล้ว</div>
            </div>
            <div class="stat-box closed">
                <div class="stat-number"><?php echo $stats['closed']; ?></div>
                <div class="stat-label">ปิดงาน</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" style="display: contents;">
            <?php if (!empty($filter_date)): ?>
                <div class="filter-group">
                    <span class="filter-label">📅 วันที่: <?php echo date('d/m/Y', strtotime($filter_date)); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($filter_category)): ?>
                <div class="filter-group">
                    <span class="filter-label">🏷️ หมวดหมู่: ID <?php echo $filter_category; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <span class="filter-label">สถานะ:</span>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                    <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                    <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>แก้ไขแล้ว</option>
                    <option value="closed" <?php echo $filter_status == 'closed' ? 'selected' : ''; ?>>ปิดงาน</option>
                    <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>ปฏิเสธ</option>
                </select>
            </div>
            
            <div class="filter-group">
                <span class="filter-label">ความเร่งด่วน:</span>
                <select name="urgency" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_urgency == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                    <option value="urgent" <?php echo $filter_urgency == 'urgent' ? 'selected' : ''; ?>>🔴 เร่งด่วนมาก</option>
                    <option value="medium" <?php echo $filter_urgency == 'medium' ? 'selected' : ''; ?>>🟠 ปานกลาง</option>
                    <option value="normal" <?php echo $filter_urgency == 'normal' ? 'selected' : ''; ?>>🟢 ทั่วไป</option>
                </select>
            </div>
            
            <div class="search-box">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 ค้นหา (เลขที่, รายละเอียด, ผู้แจ้ง)" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <?php if (!empty($filter_date)): ?>
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <?php endif; ?>
            <?php if (!empty($filter_category)): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($filter_category); ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
            
            <?php if ($filter_status != 'all' || $filter_urgency != 'all' || !empty($search) || !empty($filter_date) || !empty($filter_category)): ?>
                <a href="issue_list.php" class="btn btn-danger">❌ ล้างตัวกรอง</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Issue Table -->
    <div class="issue-table-container">
        <?php if (empty($issues)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔭</div>
                <h3>ไม่พบรายการปัญหา</h3>
                <p>ลองเปลี่ยนตัวกรอง หรือ <a href="report_issue.php">แจ้งปัญหาใหม่</a></p>
            </div>
        <?php else: ?>
            <table class="issue-table">
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>ผู้แจ้ง</th>
                        <th>รายละเอียด</th>
                        <th>ความเร่งด่วน</th>
                        <th>สถานะ</th>
                        <th>วันที่แจ้ง</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue): 
                        $status_text = [
                            'pending' => 'รอดำเนินการ',
                            'in_progress' => 'กำลังดำเนินการ',
                            'resolved' => 'แก้ไขแล้ว',
                            'closed' => 'ปิดงาน',
                            'rejected' => 'ปฏิเสธ'
                        ];
                        
                        $urgency_text = [
                            'urgent' => '🔴 เร่งด่วนมาก',
                            'medium' => '🟠 ปานกลาง',
                            'normal' => '🟢 ทั่วไป'
                        ];
                    ?>
                    <tr>
                        <td><span class="issue-number"><?php echo htmlspecialchars($issue['issue_number']); ?></span></td>
                        <td>
                            <strong><?php echo htmlspecialchars($issue['reporter_name']); ?></strong><br>
                            <small style="color: #999;"><?php echo htmlspecialchars($issue['reporter_location']); ?></small>
                        </td>
                        <td>
                            <div class="issue-description"><?php echo htmlspecialchars($issue['issue_description']); ?></div>
                            <?php if ($issue['update_count'] > 0): ?>
                                <small style="color: #667eea; font-weight: 600;">💬 <?php echo $issue['update_count']; ?> อัปเดต</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="urgency-badge urgency-<?php echo $issue['urgency_level']; ?>">
                                <?php echo $urgency_text[$issue['urgency_level']]; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $issue['status']; ?>">
                                <?php echo $status_text[$issue['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <span class="date-text"><?php echo date('d/m/Y H:i', strtotime($issue['created_at'])); ?></span>
                        </td>
                        <td>
                            <a href="issue_detail.php?id=<?php echo $issue['id']; ?>" class="action-btn btn-view">
                                👁️ ดูรายละเอียด
                            </a>
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