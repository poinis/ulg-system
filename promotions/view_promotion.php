<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

$promotion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงข้อมูลโปรโมชั่น
$sql = "SELECT p.*, b.brand_name, 
        u1.username as creator_name, u1.email as creator_email,
        u2.username as approver_name,
        u3.username as completer_name
        FROM promotions p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN users u1 ON p.created_by = u1.id
        LEFT JOIN users u2 ON p.approved_by = u2.id
        LEFT JOIN users u3 ON p.completed_by = u3.id
        WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $promotion_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$promotion = mysqli_fetch_assoc($result);

if (!$promotion) {
    die("ไม่พบข้อมูลโปรโมชั่น");
}

// ดึงข้อมูลสาขา
$store_ids = json_decode($promotion['store_ids'], true);
if (!empty($store_ids)) {
    $placeholders = str_repeat('?,', count($store_ids) - 1) . '?';
    $stores_sql = "SELECT * FROM stores WHERE id IN ($placeholders)";
    $stores_stmt = mysqli_prepare($conn, $stores_sql);
    mysqli_stmt_bind_param($stores_stmt, str_repeat('i', count($store_ids)), ...$store_ids);
    mysqli_stmt_execute($stores_stmt);
    $stores_result = mysqli_stmt_get_result($stores_stmt);
    $stores = mysqli_fetch_all($stores_result, MYSQLI_ASSOC);
    mysqli_stmt_close($stores_stmt);
} else {
    $stores = [];
}

// ดึง logs
$logs_sql = "SELECT l.*, u.username 
             FROM promotion_logs l
             LEFT JOIN users u ON l.user_id = u.id
             WHERE l.promotion_id = ?
             ORDER BY l.created_at DESC";
$logs_stmt = mysqli_prepare($conn, $logs_sql);
mysqli_stmt_bind_param($logs_stmt, "i", $promotion_id);
mysqli_stmt_execute($logs_stmt);
$logs_result = mysqli_stmt_get_result($logs_stmt);
$logs = mysqli_fetch_all($logs_result, MYSQLI_ASSOC);
mysqli_stmt_close($logs_stmt);

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดโปรโมชั่น | ULG Portal</title>
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
            max-width: 1100px;
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

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 1.8em;
            color: #333;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
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

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
        }

        .content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-section, .sidebar-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.4em;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #667eea;
        }

        .info-grid {
            display: grid;
            gap: 20px;
        }

        .info-item {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .store-card {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .store-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .store-location {
            font-size: 0.9em;
            color: #666;
        }

        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.8;
            color: #333;
            white-space: pre-wrap;
        }

        .rejection-box {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .rejection-title {
            font-weight: 600;
            color: #721c24;
            margin-bottom: 10px;
        }

        .rejection-text {
            color: #721c24;
            line-height: 1.6;
        }

        .attachment-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attachment-icon {
            font-size: 2em;
        }

        .attachment-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        .meta-item {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .meta-item:last-child {
            border-bottom: none;
        }

        .meta-label {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .meta-value {
            font-weight: 600;
            color: #333;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -26px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
        }

        .timeline-action {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .timeline-meta {
            font-size: 0.85em;
            color: #666;
        }

        .timeline-notes {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 0.9em;
            color: #555;
        }

        @media screen and (max-width: 992px) {
            .content {
                grid-template-columns: 1fr;
            }

            .info-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }

        @media screen and (max-width: 768px) {
            .header {
                flex-direction: column;
            }

            .header-title {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }

            .main-section, .sidebar-section {
                padding: 20px;
            }

            .stores-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                <h1>📋 รายละเอียดโปรโมชั่น</h1>
                <?php
                $status_class = "status-" . $promotion['status'];
                $status_text = [
                    'pending' => '⏳ รออนุมัติ',
                    'approved' => '✅ อนุมัติแล้ว',
                    'rejected' => '❌ ไม่อนุมัติ',
                    'completed' => '🎉 เสร็จสิ้น'
                ][$promotion['status']];
                ?>
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            <a href="promotion_list.php" class="back-btn">← กลับ</a>
        </div>

        <div class="content">
            <div class="main-section">
                <h2 class="section-title">ข้อมูลโปรโมชั่น</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">ชื่อโปรโมชั่น:</div>
                        <div class="info-value" style="font-size: 1.2em; font-weight: 600;">
                            <?php echo htmlspecialchars($promotion['promotion_name']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">แบรนด์:</div>
                        <div class="info-value"><?php echo htmlspecialchars($promotion['brand_name']); ?></div>
                    </div>

                    <div class="info-item">
                        <div class="info-label">ระยะเวลา:</div>
                        <div class="info-value">
                            <strong><?php echo date('d/m/Y', strtotime($promotion['start_date'])); ?></strong>
                            ถึง
                            <strong><?php echo date('d/m/Y', strtotime($promotion['end_date'])); ?></strong>
                            (<?php 
                            $start = new DateTime($promotion['start_date']);
                            $end = new DateTime($promotion['end_date']);
                            $diff = $start->diff($end);
                            echo $diff->days + 1; 
                            ?> วัน)
                        </div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">📍 สาขาที่ร่วมโปรโมชั่น</h3>
                <div class="stores-grid">
                    <?php foreach ($stores as $store): ?>
                        <div class="store-card">
                            <div class="store-name"><?php echo htmlspecialchars($store['store_name']); ?></div>
                            <div class="store-location"><?php echo htmlspecialchars($store['location']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">📝 รายละเอียดโปรโมชั่น</h3>
                <div class="description-box">
                    <?php echo htmlspecialchars($promotion['description']); ?>
                </div>

                <?php if (!empty($promotion['attachment_path'])): ?>
                <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">📎 ไฟล์แนบ</h3>
                <div class="attachment-box">
                    <div class="attachment-icon">📄</div>
                    <div>
                        <a href="<?php echo htmlspecialchars($promotion['attachment_path']); ?>" 
                           target="_blank" class="attachment-link">
                            ดาวน์โหลดไฟล์แนบ
                        </a>
                        <div style="font-size: 0.85em; color: #666; margin-top: 3px;">
                            คลิกเพื่อดาวน์โหลดหรือดูไฟล์
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($promotion['status'] == 'rejected' && !empty($promotion['rejection_reason'])): ?>
                <div class="rejection-box">
                    <div class="rejection-title">❌ เหตุผลที่ไม่อนุมัติ:</div>
                    <div class="rejection-text"><?php echo nl2br(htmlspecialchars($promotion['rejection_reason'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="sidebar-section">
                <h3 class="section-title">ข้อมูลเพิ่มเติม</h3>
                
                <div class="meta-item">
                    <div class="meta-label">ผู้สร้าง</div>
                    <div class="meta-value"><?php echo htmlspecialchars($promotion['creator_name']); ?></div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">วันที่สร้าง</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($promotion['created_at'])); ?> น.</div>
                </div>

                <?php if ($promotion['approved_by']): ?>
                <div class="meta-item">
                    <div class="meta-label">ผู้อนุมัติ</div>
                    <div class="meta-value"><?php echo htmlspecialchars($promotion['approver_name']); ?></div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">วันที่อนุมัติ</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($promotion['approved_at'])); ?> น.</div>
                </div>
                <?php endif; ?>

                <?php if ($promotion['completed_by']): ?>
                <div class="meta-item">
                    <div class="meta-label">ผู้บันทึกเสร็จสิ้น</div>
                    <div class="meta-value"><?php echo htmlspecialchars($promotion['completer_name']); ?></div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">วันที่เสร็จสิ้น</div>
                    <div class="meta-value"><?php echo date('d/m/Y H:i', strtotime($promotion['completed_at'])); ?> น.</div>
                </div>
                <?php endif; ?>

                <?php if (!empty($logs)): ?>
                <h3 class="section-title" style="margin-top: 30px;">📜 ประวัติการดำเนินการ</h3>
                <div class="timeline">
                    <?php foreach ($logs as $log): 
                        $action_text = [
                            'created' => 'สร้างโปรโมชั่น',
                            'approved' => 'อนุมัติโปรโมชั่น',
                            'rejected' => 'ไม่อนุมัติโปรโมชั่น',
                            'completed' => 'บันทึกเสร็จสิ้น'
                        ][$log['action']] ?? $log['action'];
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-action"><?php echo $action_text; ?></div>
                            <div class="timeline-meta">
                                โดย <?php echo htmlspecialchars($log['username']); ?> • 
                                <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?> น.
                            </div>
                            <?php if (!empty($log['notes'])): ?>
                            <div class="timeline-notes"><?php echo htmlspecialchars($log['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>