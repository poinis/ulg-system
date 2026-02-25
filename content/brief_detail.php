<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$brief_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($brief_id <= 0) {
    die("ไม่พบข้อมูลบรีฟ");
}

// ดึงข้อมูลบรีฟ
$sql = "SELECT * FROM content_brief WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $brief_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$brief = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$brief) {
    die("ไม่พบข้อมูลบรีฟ");
}

// แปลงสถานะเป็นภาษาไทย
$status_thai = array(
    'pending' => 'รอดำเนินการ',
    'in_progress' => 'กำลังดำเนินการ',
    'completed' => 'เสร็จสิ้น',
    'cancelled' => 'ยกเลิก'
);

$status_color = array(
    'pending' => '#ffc107',
    'in_progress' => '#17a2b8',
    'completed' => '#28a745',
    'cancelled' => '#dc3545'
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>รายละเอียดบรีฟ - <?php echo htmlspecialchars($brief['job_title']); ?></title>
    <meta charset="utf-8" />
    <style>
        body { 
            font-family: Tahoma, Arial, sans-serif; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 180px;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px 0 0;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .attachment-link {
            color: #007bff;
            text-decoration: none;
        }
        .attachment-link:hover {
            text-decoration: underline;
        }
        .actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📋 รายละเอียดบรีฟงาน</h2>

        <div class="info-row">
            <div class="info-label">🆔 รหัสบรีฟ:</div>
            <div class="info-value">#<?php echo $brief['id']; ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">📌 ชื่องาน:</div>
            <div class="info-value"><strong><?php echo htmlspecialchars($brief['job_title']); ?></strong></div>
        </div>

        <div class="info-row">
            <div class="info-label">🏢 แบรนด์:</div>
            <div class="info-value"><?php echo htmlspecialchars($brief['brand']); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">📱 แพลตฟอร์ม:</div>
            <div class="info-value"><?php echo htmlspecialchars($brief['platform'] ?: '-'); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">📂 หมวดหมู่:</div>
            <div class="info-value"><?php echo htmlspecialchars($brief['category'] ?: '-'); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">📝 รายละเอียด:</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($brief['content_detail'])); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">🎨 รูปแบบเนื้อหา:</div>
            <div class="info-value"><?php echo htmlspecialchars($brief['content_format']); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">📅 กำหนดส่งงาน:</div>
            <div class="info-value"><?php echo date('d/m/Y', strtotime($brief['due_date'])); ?></div>
        </div>

        <?php if (!empty($brief['attachment'])) { ?>
        <div class="info-row">
            <div class="info-label">📎 ไฟล์แนบ:</div>
            <div class="info-value">
                <a href="<?php echo htmlspecialchars($brief['attachment']); ?>" 
                   class="attachment-link" 
                   target="_blank">ดาวน์โหลดไฟล์</a>
            </div>
        </div>
        <?php } ?>

        <?php if (!empty($brief['remark'])) { ?>
        <div class="info-row">
            <div class="info-label">💬 หมายเหตุ:</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($brief['remark'])); ?></div>
        </div>
        <?php } ?>

        <div class="info-row">
            <div class="info-label">👤 ผู้สร้าง:</div>
            <div class="info-value"><?php echo htmlspecialchars($brief['name']); ?> (<?php echo htmlspecialchars($brief['username']); ?>)</div>
        </div>

        <div class="info-row">
            <div class="info-label">📊 สถานะ:</div>
            <div class="info-value">
                <span class="status-badge" style="background-color: <?php echo $status_color[$brief['status']]; ?>">
                    <?php echo $status_thai[$brief['status']]; ?>
                </span>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">🕐 วันที่สร้าง:</div>
            <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($brief['created_at'])); ?></div>
        </div>

        <?php if ($brief['updated_at']) { ?>
        <div class="info-row">
            <div class="info-label">🔄 อัพเดทล่าสุด:</div>
            <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($brief['updated_at'])); ?></div>
        </div>
        <?php } ?>

        <div class="actions">
            <a href="user_dashboard.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
            <!-- เพิ่มปุ่มอื่นๆ ตามต้องการ เช่น แก้ไข, ลบ -->
        </div>
    </div>
</body>
</html>