<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

$user_role = $_SESSION["role"];
$user_id = $_SESSION["id"];

// สร้าง SQL query ตามสิทธิ์
if (in_array($user_role, ['admin', 'approve'])) {
    // Admin และ Approve เห็นทั้งหมด
    $sql = "SELECT p.*, b.brand_name, u.username as creator_name 
            FROM promotions p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN users u ON p.created_by = u.id
            ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $sql);
} else if (in_array($user_role, ['promotion', 'marketing', 'brand'])) {
    // Promotion, Marketing, Brand เห็นของตัวเองและที่ completed
    $sql = "SELECT p.*, b.brand_name, u.username as creator_name 
            FROM promotions p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.created_by = ? OR p.status = 'completed'
            ORDER BY p.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // Role อื่นๆ เห็นเฉพาะ completed
    $sql = "SELECT p.*, b.brand_name, u.username as creator_name 
            FROM promotions p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.status = 'completed'
            ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $sql);
}

// คำนวณสถิติทั้งหมด
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM promotions";

if (in_array($user_role, ['promotion', 'marketing', 'brand']) && !in_array($user_role, ['admin', 'approve'])) {
    $stats_sql .= " WHERE created_by = $user_id OR status = 'completed'";
}

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// คำนวณสถิติเดือนที่เลือก
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('n');
}
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = date('Y');
}

$month_stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM promotions
    WHERE MONTH(created_at) = $selected_month 
    AND YEAR(created_at) = $selected_year";

if (in_array($user_role, ['promotion', 'marketing', 'brand']) && !in_array($user_role, ['admin', 'approve'])) {
    $month_stats_sql .= " AND (created_by = $user_id OR status = 'completed')";
}

$month_stats_result = mysqli_query($conn, $month_stats_sql);
$month_stats = mysqli_fetch_assoc($month_stats_result);

// เดือนและปีที่เลือก (ภาษาไทย)
$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$current_month_name = $thai_months[$selected_month];
$thai_year = $selected_year + 543;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการโปรโมชั่น | ULG Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 1.8em;
            color: #333;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .content {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* Dashboard Stats */
        .stats-container {
            margin-bottom: 30px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stats-section {
            flex: 1;
        }

        .stats-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .stats-section-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-card.pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffc107 100%);
        }

        .stat-card.approved {
            background: linear-gradient(135deg, #d1ecf1 0%, #17a2b8 100%);
        }

        .stat-card.rejected {
            background: linear-gradient(135deg, #f8d7da 0%, #dc3545 100%);
        }

        .stat-card.completed {
            background: linear-gradient(135deg, #d4edda 0%, #28a745 100%);
        }

        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-icon {
            font-size: 1.3em;
            display: block;
            margin-bottom: 4px;
        }

        .stat-number {
            font-size: 1.6em;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 0.75em;
            opacity: 0.85;
            font-weight: 500;
        }

        /* Month Selector */
        .month-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .month-selector label {
            font-weight: 500;
            color: #555;
            font-size: 0.85em;
            white-space: nowrap;
        }

        .month-selector select {
            padding: 6px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.85em;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .month-selector select:focus {
            outline: none;
            border-color: #667eea;
        }

        .month-selector select:hover {
            border-color: #667eea;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
        }

        .section-subtitle {
            font-size: 0.85em;
            color: #666;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, #667eea 0%, transparent 100%);
            margin: 20px 0;
        }

        .promotions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .promotion-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .promotion-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            flex: 1;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            white-space: nowrap;
            margin-left: 10px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .card-info {
            color: #666;
            font-size: 0.9em;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .card-info-row {
            display: flex;
            gap: 8px;
        }

        .card-info-label {
            font-weight: 600;
            min-width: 80px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .card-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-complete {
            background: #17a2b8;
            color: white;
        }

        .btn-complete:hover {
            background: #138496;
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .empty-state-text {
            font-size: 1.2em;
            font-weight: 500;
        }

        @media screen and (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                font-size: 1.4em;
            }

            .header-buttons {
                width: 100%;
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .content {
                padding: 25px 20px;
            }

            .promotions-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-number {
                font-size: 2em;
            }

            .stat-icon {
                font-size: 2em;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .month-selector {
                flex-direction: column;
                align-items: flex-start;
            }

            .month-selector select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎁 รายการโปรโมชั่น</h1>
            <div class="header-buttons">
                <?php if (in_array($user_role, ['admin', 'promotion'])): ?>
                    <a href="create_promotion.php" class="btn btn-primary">➕ สร้างโปรโมชั่นใหม่</a>
                <?php endif; ?>
                <a href="../dashboard.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
            </div>
        </div>

        <div class="content">
            <!-- Dashboard Stats -->
            <div class="stats-container">
                <!-- บรรทัดที่ 1: สถิติทั้งหมด -->
                <div class="stats-row">
                    <div class="stats-section">
                        <div class="stats-section-header">
                            <span class="stats-section-title">📊 สถิติทั้งหมด</span>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <span class="stat-icon">📦</span>
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">ทั้งหมด</div>
                            </div>
                            <div class="stat-card pending">
                                <span class="stat-icon">⏳</span>
                                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">รออนุมัติ</div>
                            </div>
                            <div class="stat-card approved">
                                <span class="stat-icon">✅</span>
                                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                                <div class="stat-label">อนุมัติแล้ว</div>
                            </div>
                            <div class="stat-card rejected">
                                <span class="stat-icon">❌</span>
                                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                                <div class="stat-label">ไม่อนุมัติ</div>
                            </div>
                            <div class="stat-card completed">
                                <span class="stat-icon">🎉</span>
                                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                                <div class="stat-label">เสร็จสิ้น</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- บรรทัดที่ 2: สถิติรายเดือน -->
                <div class="stats-row">
                    <div class="stats-section">
                        <div class="stats-section-header">
                            <span class="stats-section-title">📅 สถิติรายเดือน</span>
                            <div class="month-selector">
                                <label for="monthSelect">เลือก:</label>
                                <select id="monthSelect" onchange="changeMonth()">
                                    <?php
                                    $current_time = time();
                                    for ($i = 0; $i < 12; $i++) {
                                        $month_timestamp = strtotime("-$i months", $current_time);
                                        $month_num = date('n', $month_timestamp);
                                        $year_num = date('Y', $month_timestamp);
                                        $thai_year_display = $year_num + 543;
                                        $month_name = $thai_months[$month_num];
                                        
                                        $selected = ($month_num == $selected_month && $year_num == $selected_year) ? 'selected' : '';
                                        echo "<option value='{$month_num}|{$year_num}' {$selected}>{$month_name} {$thai_year_display}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <span class="stat-icon">📦</span>
                                <div class="stat-number"><?php echo $month_stats['total']; ?></div>
                                <div class="stat-label">ทั้งหมด</div>
                            </div>
                            <div class="stat-card pending">
                                <span class="stat-icon">⏳</span>
                                <div class="stat-number"><?php echo $month_stats['pending']; ?></div>
                                <div class="stat-label">รออนุมัติ</div>
                            </div>
                            <div class="stat-card approved">
                                <span class="stat-icon">✅</span>
                                <div class="stat-number"><?php echo $month_stats['approved']; ?></div>
                                <div class="stat-label">อนุมัติแล้ว</div>
                            </div>
                            <div class="stat-card rejected">
                                <span class="stat-icon">❌</span>
                                <div class="stat-number"><?php echo $month_stats['rejected']; ?></div>
                                <div class="stat-label">ไม่อนุมัติ</div>
                            </div>
                            <div class="stat-card completed">
                                <span class="stat-icon">🎉</span>
                                <div class="stat-number"><?php echo $month_stats['completed']; ?></div>
                                <div class="stat-label">เสร็จสิ้น</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- รายการโปรโมชั่น -->
            <div class="section-header">
                <div>
                    <h2 class="section-title">📋 รายการโปรโมชั่น</h2>
                </div>
                <?php if (in_array($user_role, ['admin', 'promotion'])): ?>
                <a href="create_promotion.php" class="btn btn-primary" style="font-size: 0.9em; padding: 8px 16px;">➕ สร้างใหม่</a>
                <?php endif; ?>
            </div>

            <div class="promotions-grid" id="promotionsGrid">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): 
                        $status_class = "status-" . $row['status'];
                        $status_text = [
                            'pending' => '⏳ รออนุมัติ',
                            'approved' => '✅ อนุมัติแล้ว',
                            'rejected' => '❌ ไม่อนุมัติ',
                            'completed' => '🎉 เสร็จสิ้น'
                        ][$row['status']];
                        
                        $store_ids = json_decode($row['store_ids'], true);
                        $store_count = count($store_ids);
                    ?>
                    <div class="promotion-card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($row['promotion_name']); ?></div>
                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>

                        <div class="card-info">
                            <div class="card-info-row">
                                <span class="card-info-label">แบรนด์:</span>
                                <span><?php echo htmlspecialchars($row['brand_name']); ?></span>
                            </div>
                            <div class="card-info-row">
                                <span class="card-info-label">สาขา:</span>
                                <span><?php echo $store_count; ?> สาขา</span>
                            </div>
                            <div class="card-info-row">
                                <span class="card-info-label">ระยะเวลา:</span>
                                <span>
                                    <?php echo date('d/m/Y', strtotime($row['start_date'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($row['end_date'])); ?>
                                </span>
                            </div>
                            <div class="card-info-row">
                                <span class="card-info-label">ผู้สร้าง:</span>
                                <span><?php echo htmlspecialchars($row['creator_name']); ?></span>
                            </div>
                            <div class="card-info-row">
                                <span class="card-info-label">สร้างเมื่อ:</span>
                                <span><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></span>
                            </div>

                            <?php if ($row['status'] == 'rejected' && !empty($row['rejection_reason'])): ?>
                            <div class="card-info-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f8d7da;">
                                <span class="card-info-label" style="color: #721c24;">เหตุผล:</span>
                                <span style="color: #721c24;"><?php echo htmlspecialchars($row['rejection_reason']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <a href="view_promotion.php?id=<?php echo $row['id']; ?>" class="card-btn btn-view">
                                👁️ ดูรายละเอียด
                            </a>

                            <?php if (in_array($user_role, ['admin', 'approve']) && $row['status'] == 'pending'): ?>
                                <a href="approve_promotion.php?id=<?php echo $row['id']; ?>" class="card-btn btn-approve">
                                    ✅ อนุมัติ
                                </a>
                            <?php endif; ?>

                            <?php if (in_array($user_role, ['admin', 'promotion']) && $row['status'] == 'approved'): ?>
                                <a href="complete_promotion.php?id=<?php echo $row['id']; ?>" class="card-btn btn-complete">
                                    🎯 บันทึกเสร็จสิ้น
                                </a>
                            <?php endif; ?>

                            <?php if ($row['created_by'] == $user_id && $row['status'] == 'rejected'): ?>
                                <a href="edit_promotion.php?id=<?php echo $row['id']; ?>" class="card-btn btn-edit">
                                    ✏️ แก้ไข
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-text">ยังไม่มีโปรโมชั่นในระบบ</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function changeMonth() {
            const select = document.getElementById('monthSelect');
            const value = select.value;
            const [month, year] = value.split('|');
            
            // Reload page with selected month and year
            window.location.href = `promotion_list.php?month=${month}&year=${year}`;
        }
    </script>
</body>
</html>