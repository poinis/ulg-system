<?php
session_start();
require_once "../config.php";
require_once "promotion_functions.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// ตรวจสอบสิทธิ์ (เฉพาะ admin และ approve)
if (!in_array($_SESSION["role"], ['admin', 'approve'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

$promotion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = $error = "";

// ดึงข้อมูลโปรโมชั่น
$sql = "SELECT p.*, b.brand_name, u.username as creator_name 
        FROM promotions p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ? AND p.status = 'pending'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $promotion_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$promotion = mysqli_fetch_assoc($result);

if (!$promotion) {
    die("ไม่พบข้อมูลโปรโมชั่น หรือโปรโมชั่นนี้ได้รับการอนุมัติแล้ว");
}

// ดึงข้อมูลสาขา
$store_ids = json_decode($promotion['store_ids'], true);
$stores_sql = "SELECT store_name FROM stores WHERE id IN (" . implode(',', array_map('intval', $store_ids)) . ")";
$stores_result = mysqli_query($conn, $stores_sql);
$stores = [];
while ($row = mysqli_fetch_assoc($stores_result)) {
    $stores[] = $row['store_name'];
}

// จัดการการอนุมัติ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $approver_id = $_SESSION["id"];
    
    if ($action == 'approve') {
        // อนุมัติ
        $update_sql = "UPDATE promotions SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ii", $approver_id, $promotion_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // บันทึก log
            $log_sql = "INSERT INTO promotion_logs (promotion_id, action, user_id, notes) VALUES (?, 'approved', ?, 'อนุมัติโปรโมชั่น')";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "ii", $promotion_id, $approver_id);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
            
            // แจ้งเตือนผู้สร้าง
            notifyCreatorApproved($conn, $promotion_id, $promotion['promotion_name'], $promotion['created_by']);
            
            $success = "อนุมัติโปรโมชั่นเรียบร้อยแล้ว! ระบบได้แจ้งเตือนผู้สร้างโปรโมชั่นแล้ว";
            header("refresh:2;url=promotion_list.php");
        } else {
            $error = "เกิดข้อผิดพลาดในการอนุมัติ: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($update_stmt);
        
    } elseif ($action == 'reject') {
        // ไม่อนุมัติ
        $rejection_reason = trim($_POST['rejection_reason']);
        
        if (empty($rejection_reason)) {
            $error = "กรุณาระบุเหตุผลในการไม่อนุมัติ";
        } else {
            $update_sql = "UPDATE promotions SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sii", $rejection_reason, $approver_id, $promotion_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // บันทึก log
                $log_sql = "INSERT INTO promotion_logs (promotion_id, action, user_id, notes) VALUES (?, 'rejected', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                mysqli_stmt_bind_param($log_stmt, "iis", $promotion_id, $approver_id, $rejection_reason);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
                // แจ้งเตือนผู้สร้าง
                notifyCreatorRejected($conn, $promotion_id, $promotion['promotion_name'], $promotion['created_by'], $rejection_reason);
                
                $success = "บันทึกการไม่อนุมัติเรียบร้อยแล้ว! ระบบได้แจ้งเตือนผู้สร้างโปรโมชั่นแล้ว";
                header("refresh:2;url=promotion_list.php");
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึก: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($update_stmt);
        }
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติโปรโมชั่น | ULG Portal</title>
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
            max-width: 900px;
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
        }

        .header h1 {
            font-size: 1.8em;
            color: #333;
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
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .stores-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .store-badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .attachment-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .reject-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fff3cd;
            border-radius: 8px;
            border: 2px solid #ffc107;
        }

        .reject-form.active {
            display: block;
        }

        .reject-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ffc107;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }

        .reject-form-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-confirm-reject {
            background: #dc3545;
            color: white;
        }

        @media screen and (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .content {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ อนุมัติโปรโมชั่น</h1>
            <a href="promotion_list.php" class="back-btn">← กลับ</a>
        </div>

        <div class="content">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">ชื่อโปรโมชั่น:</div>
                    <div class="info-value"><?php echo htmlspecialchars($promotion['promotion_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">แบรนด์:</div>
                    <div class="info-value"><?php echo htmlspecialchars($promotion['brand_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">สาขา:</div>
                    <div class="info-value">
                        <div class="stores-list">
                            <?php foreach ($stores as $store): ?>
                                <span class="store-badge"><?php echo htmlspecialchars($store); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">ระยะเวลา:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y', strtotime($promotion['start_date'])); ?> 
                        ถึง 
                        <?php echo date('d/m/Y', strtotime($promotion['end_date'])); ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">รายละเอียด:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($promotion['description'])); ?></div>
                </div>

                <?php if ($promotion['attachment_path']): ?>
                <div class="info-row">
                    <div class="info-label">ไฟล์แนบ:</div>
                    <div class="info-value">
                        <a href="<?php echo htmlspecialchars($promotion['attachment_path']); ?>" 
                           target="_blank" class="attachment-link">
                            📎 ดาวน์โหลดไฟล์แนบ
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <div class="info-label">ผู้สร้าง:</div>
                    <div class="info-value"><?php echo htmlspecialchars($promotion['creator_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">วันที่สร้าง:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($promotion['created_at'])); ?> น.
                    </div>
                </div>
            </div>

            <form method="POST" action="" id="approveForm">
                <input type="hidden" name="action" id="action" value="">
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-approve" onclick="approvePromotion()">
                        ✅ อนุมัติ
                    </button>
                    <button type="button" class="btn btn-reject" onclick="toggleRejectForm()">
                        ❌ ไม่อนุมัติ
                    </button>
                </div>

                <div class="reject-form" id="rejectForm">
                    <h3 style="margin-bottom: 15px; color: #856404;">เหตุผลที่ไม่อนุมัติ</h3>
                    <textarea name="rejection_reason" placeholder="กรุณาระบุเหตุผลที่ไม่อนุมัติโปรโมชั่นนี้"></textarea>
                    
                    <div class="reject-form-buttons">
                        <button type="button" class="btn-small btn-cancel" onclick="toggleRejectForm()">
                            ยกเลิก
                        </button>
                        <button type="button" class="btn-small btn-confirm-reject" onclick="rejectPromotion()">
                            ยืนยันไม่อนุมัติ
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function approvePromotion() {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการอนุมัติโปรโมชั่นนี้?')) {
                document.getElementById('action').value = 'approve';
                document.getElementById('approveForm').submit();
            }
        }

        function toggleRejectForm() {
            const form = document.getElementById('rejectForm');
            form.classList.toggle('active');
        }

        function rejectPromotion() {
            const reason = document.querySelector('textarea[name="rejection_reason"]').value.trim();
            
            if (!reason) {
                alert('กรุณาระบุเหตุผลในการไม่อนุมัติ');
                return;
            }

            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการไม่อนุมัติโปรโมชั่นนี้?')) {
                document.getElementById('action').value = 'reject';
                document.getElementById('approveForm').submit();
            }
        }
    </script>
</body>
</html>