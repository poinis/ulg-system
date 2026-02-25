<?php
session_start();
require_once "../config.php";
require_once "promotion_functions.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

$promotion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = $error = "";

// ดึงข้อมูลโปรโมชั่น
$sql = "SELECT p.*, b.brand_name 
        FROM promotions p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ? AND p.status = 'rejected' AND p.created_by = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $promotion_id, $_SESSION["id"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$promotion = mysqli_fetch_assoc($result);

if (!$promotion) {
    die("ไม่พบข้อมูลโปรโมชั่น หรือคุณไม่มีสิทธิ์แก้ไข");
}

// ดึงข้อมูล brands และ stores
$brands = mysqli_query($conn, "SELECT * FROM brands WHERE is_active = 1 ORDER BY brand_name");
$stores = mysqli_query($conn, "SELECT * FROM stores WHERE is_active = 1 ORDER BY store_name");

$selected_stores = json_decode($promotion['store_ids'], true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $promotion_name = trim($_POST["promotion_name"]);
    $brand_id = intval($_POST["brand_id"]);
    $store_ids = isset($_POST["store_ids"]) ? json_encode($_POST["store_ids"]) : json_encode([]);
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];
    $description = trim($_POST["description"]);
    
    // จัดการไฟล์แนบใหม่
    $attachment_path = $promotion['attachment_path'];
    if (isset($_FILES["attachment"]) && $_FILES["attachment"]["error"] == 0) {
        $target_dir = "uploads/promotions/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION);
        $file_name = uniqid() . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            // ลบไฟล์เก่า
            if (!empty($attachment_path) && file_exists($attachment_path)) {
                unlink($attachment_path);
            }
            $attachment_path = $target_file;
        }
    }
    
    // อัพเดทข้อมูล
    $update_sql = "UPDATE promotions 
                   SET promotion_name = ?, brand_id = ?, store_ids = ?, start_date = ?, 
                       end_date = ?, description = ?, attachment_path = ?, status = 'pending',
                       rejection_reason = NULL
                   WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "sisssssi", $promotion_name, $brand_id, $store_ids, 
                              $start_date, $end_date, $description, $attachment_path, $promotion_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // บันทึก log
            $log_sql = "INSERT INTO promotion_logs (promotion_id, action, user_id, notes) 
                       VALUES (?, 'updated', ?, 'แก้ไขโปรโมชั่นและส่งขออนุมัติใหม่')";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "ii", $promotion_id, $_SESSION["id"]);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
            
            // แจ้งเตือนผู้อนุมัติ
            notifyApprovers($conn, $promotion_id, $promotion_name, $_SESSION["username"]);
            
            $success = "แก้ไขโปรโมชั่นสำเร็จ! ส่งขออนุมัติใหม่แล้ว";
            header("refresh:2;url=promotion_list.php");
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($update_stmt);
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรโมชั่น | ULG Portal</title>
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

        .form-container {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .rejection-notice {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .rejection-title {
            font-weight: 600;
            color: #721c24;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .rejection-text {
            color: #721c24;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }

        label.required::after {
            content: " *";
            color: #e74c3c;
        }

        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input[type="text"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .store-selection {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            max-height: 250px;
            overflow-y: auto;
        }

        .store-item {
            padding: 8px 0;
            display: flex;
            align-items: center;
        }

        .store-item input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .store-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
        }

        .file-upload {
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9ff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload:hover {
            background: #e8ebff;
            border-color: #764ba2;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-info {
            margin-top: 10px;
            color: #666;
            font-size: 0.9em;
        }

        .current-file {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .current-file a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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

        .date-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 0;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .form-container {
                padding: 25px 20px;
            }

            .date-range {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ แก้ไขโปรโมชั่น</h1>
            <a href="promotion_list.php" class="back-btn">← กลับ</a>
        </div>

        <div class="form-container">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="rejection-notice">
                <div class="rejection-title">❌ โปรโมชั่นนี้ไม่ผ่านการอนุมัติ</div>
                <div class="rejection-text">
                    <strong>เหตุผล:</strong> <?php echo nl2br(htmlspecialchars($promotion['rejection_reason'])); ?>
                </div>
                <div style="margin-top: 10px; font-size: 0.9em; color: #721c24;">
                    กรุณาแก้ไขข้อมูลตามเหตุผลข้างต้น และส่งขออนุมัติใหม่
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="promotion_name" class="required">ชื่อโปรโมชั่น</label>
                    <input type="text" id="promotion_name" name="promotion_name" required 
                           value="<?php echo htmlspecialchars($promotion['promotion_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="brand_id" class="required">แบรนด์</label>
                    <select id="brand_id" name="brand_id" required>
                        <option value="">-- เลือกแบรนด์ --</option>
                        <?php 
                        mysqli_data_seek($brands, 0);
                        while ($brand = mysqli_fetch_assoc($brands)): 
                        ?>
                            <option value="<?php echo $brand['id']; ?>" 
                                    <?php echo ($brand['id'] == $promotion['brand_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">สาขา</label>
                    <div class="store-selection">
                        <?php 
                        mysqli_data_seek($stores, 0);
                        while ($store = mysqli_fetch_assoc($stores)): 
                            $checked = in_array($store['id'], $selected_stores) ? 'checked' : '';
                        ?>
                            <div class="store-item">
                                <input type="checkbox" id="store_<?php echo $store['id']; ?>" 
                                       name="store_ids[]" value="<?php echo $store['id']; ?>" <?php echo $checked; ?>>
                                <label for="store_<?php echo $store['id']; ?>">
                                    <?php echo htmlspecialchars($store['store_name']); ?> 
                                    (<?php echo htmlspecialchars($store['location']); ?>)
                                </label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required">วันที่โปรโมชั่น</label>
                    <div class="date-range">
                        <div>
                            <label for="start_date" style="font-weight: 400; margin-bottom: 5px;">วันเริ่มต้น</label>
                            <input type="date" id="start_date" name="start_date" required 
                                   value="<?php echo $promotion['start_date']; ?>">
                        </div>
                        <div>
                            <label for="end_date" style="font-weight: 400; margin-bottom: 5px;">วันสิ้นสุด</label>
                            <input type="date" id="end_date" name="end_date" required 
                                   value="<?php echo $promotion['end_date']; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="required">รายละเอียดโปรโมชั่น</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($promotion['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="attachment">ไฟล์แนบ (ถ้ามี)</label>
                    
                    <?php if (!empty($promotion['attachment_path'])): ?>
                    <div class="current-file">
                        📄 <a href="<?php echo htmlspecialchars($promotion['attachment_path']); ?>" target="_blank">
                            ไฟล์แนบปัจจุบัน
                        </a>
                        <span style="color: #666; font-size: 0.9em;">(อัพโหลดใหม่เพื่อแทนที่)</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="file-upload" onclick="document.getElementById('attachment').click()">
                        <input type="file" id="attachment" name="attachment" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                               onchange="displayFileName(this)">
                        <div>📎 คลิกเพื่อเลือกไฟล์ใหม่</div>
                        <div class="file-info">รองรับไฟล์: PDF, JPG, PNG, DOC, DOCX (ขนาดไม่เกิน 10MB)</div>
                        <div id="file-name" style="margin-top: 10px; font-weight: 600; color: #667eea;"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">🔄 แก้ไขและส่งขออนุมัติใหม่</button>
            </form>
        </div>
    </div>

    <script>
        function displayFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('file-name').textContent = fileName ? '✓ ' + fileName : '';
        }

        // ตรวจสอบวันที่
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });

        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = document.getElementById('start_date').value;
            if (startDate && this.value < startDate) {
                alert('วันสิ้นสุดต้องไม่น้อยกว่าวันเริ่มต้น');
                this.value = '';
            }
        });
    </script>
</body>
</html>