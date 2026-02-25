<?php
session_start();
require_once "../config.php";
require_once "promotion_functions.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// ตรวจสอบสิทธิ์ (เฉพาะ admin และ promotion)
if (!in_array($_SESSION["role"], ['admin', 'promotion'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

$promotion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = $error = "";

// ดึงข้อมูลโปรโมชั่น
$sql = "SELECT p.*, b.brand_name, u.username as creator_name 
        FROM promotions p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ? AND p.status = 'approved'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $promotion_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$promotion = mysqli_fetch_assoc($result);

if (!$promotion) {
    die("ไม่พบข้อมูลโปรโมชั่น หรือโปรโมชั่นนี้ยังไม่ได้รับการอนุมัติ");
}

// ดึงข้อมูลสาขา
$store_ids = json_decode($promotion['store_ids'], true);
$stores_sql = "SELECT store_name FROM stores WHERE id IN (" . implode(',', array_map('intval', $store_ids)) . ")";
$stores_result = mysqli_query($conn, $stores_sql);
$stores = [];
while ($row = mysqli_fetch_assoc($stores_result)) {
    $stores[] = $row['store_name'];
}

// จัดการการบันทึกเสร็จสิ้น
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $completed_by = $_SESSION["id"];
    $updated_description = trim($_POST["updated_description"]);
    
    // อัพเดทรายละเอียด (ถ้ามี)
    if (!empty($updated_description)) {
        $update_desc_sql = "UPDATE promotions SET description = ? WHERE id = ?";
        $desc_stmt = mysqli_prepare($conn, $update_desc_sql);
        mysqli_stmt_bind_param($desc_stmt, "si", $updated_description, $promotion_id);
        mysqli_stmt_execute($desc_stmt);
        mysqli_stmt_close($desc_stmt);
    }
    
    // อัพเดทสถานะเป็น completed
    $update_sql = "UPDATE promotions SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $completed_by, $promotion_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        // บันทึก log
        $log_sql = "INSERT INTO promotion_logs (promotion_id, action, user_id, notes) VALUES (?, 'completed', ?, 'บันทึกเสร็จสิ้น')";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($log_stmt, "ii", $promotion_id, $completed_by);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        // แจ้งเตือนทุกคน
        $final_description = !empty($updated_description) ? $updated_description : $promotion['description'];
        notifyAllCompleted($conn, $promotion_id, $promotion['promotion_name'], $final_description, 
                          $promotion['start_date'], $promotion['end_date']);
        
        $success = "บันทึกเสร็จสิ้นเรียบร้อยแล้ว! ระบบได้แจ้งเตือนไปยังทุกคนแล้ว";
        header("refresh:2;url=promotion_list.php");
    } else {
        $error = "เกิดข้อผิดพลาดในการบันทึก: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($update_stmt);
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกเสร็จสิ้น | ULG Portal</title>
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

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            margin-bottom: 20px;
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

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            resize: vertical;
            min-height: 120px;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .note-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .note-box h4 {
            color: #856404;
            margin-bottom: 10px;
        }

        .note-box p {
            color: #856404;
            line-height: 1.6;
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

            .content {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 บันทึกเสร็จสิ้นโปรโมชั่น</h1>
            <a href="promotion_list.php" class="back-btn">← กลับ</a>
        </div>

        <div class="content">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="status-badge">✅ ได้รับการอนุมัติแล้ว</div>

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
                    <div class="info-label">รายละเอียดปัจจุบัน:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($promotion['description'])); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">วันที่อนุมัติ:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($promotion['approved_at'])); ?> น.
                    </div>
                </div>
            </div>

            <div class="note-box">
                <h4>📝 หมายเหตุ</h4>
                <p>เมื่อคุณกดปุ่ม "บันทึกเสร็จสิ้น" ระบบจะส่งการแจ้งเตือนไปยังทุกคนในระบบผ่าน Pumble และ Email</p>
                <p>หากต้องการอัพเดทรายละเอียดก่อนแจ้งเตือน กรุณากรอกในช่องด้านล่าง</p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="updated_description">อัพเดทรายละเอียดเพิ่มเติม (ถ้ามี)</label>
                    <textarea id="updated_description" name="updated_description" 
                              placeholder="กรอกรายละเอียดเพิ่มเติมหรือการแก้ไข (ถ้าไม่มีสามารถเว้นว่างไว้ได้)"><?php echo htmlspecialchars($promotion['description']); ?></textarea>
                </div>

                <button type="submit" class="submit-btn" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการบันทึกเสร็จสิ้น? ระบบจะแจ้งเตือนไปยังทุกคนในระบบ')">
                    🎉 บันทึกเสร็จสิ้นและแจ้งเตือนทุกคน
                </button>
            </form>
        </div>
    </div>
</body>
</html>