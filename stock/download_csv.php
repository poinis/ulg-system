<?php
// การตั้งค่าฐานข้อมูล
$host = 'localhost';
$dbname = 'cmbase';
$username = 'cmbase';
$password = '#wmIYH3wazaa';
$table = 'stock_update'; // ชื่อตารางที่ต้องการบันทึก

// เชื่อมต่อฐานข้อมูล
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ฟังก์ชันดาวน์โหลด CSV
function downloadCSV($pdo, $table) {
    try {
        // ดึงข้อมูลจากฐานข้อมูล
        $stmt = $pdo->query("SELECT warehouse, barcode, physical FROM $table ORDER BY id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) === 0) {
            return ['success' => false, 'message' => 'ไม่มีข้อมูลในตาราง'];
        }
        
        // สร้างชื่อไฟล์พร้อมวันที่
        $filename = 'inventory_export_' . date('Ymd_His') . '.csv';
        
        // ตั้งค่า header สำหรับดาวน์โหลด
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // เปิด output stream
        $output = fopen('php://output', 'w');
        
        // เพิ่ม BOM สำหรับ UTF-8 (ให้ Excel เปิดได้ถูกต้อง)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // เขียนหัวตาราง
        fputcsv($output, array('warehouse', 'barcode', 'physical'));
        
        // เขียนข้อมูล
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        return ['success' => true, 'count' => count($rows)];
        
    } catch(Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ฟังก์ชันลบข้อมูลในตาราง
function emptyTable($pdo, $table) {
    try {
        $pdo->exec("TRUNCATE TABLE $table");
        return ['success' => true];
    } catch(Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// จัดการคำขอดาวน์โหลด
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    
    // ตรวจสอบว่ามีข้อมูลในตารางหรือไม่
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $error = "ไม่มีข้อมูลในตารางให้ดาวน์โหลด";
    } else {
        // ดาวน์โหลด CSV
        $downloadResult = downloadCSV($pdo, $table);
        
        if ($downloadResult['success']) {
            // ลบข้อมูลในตารางหลังดาวน์โหลดสำเร็จ
            $emptyResult = emptyTable($pdo, $table);
            
            // หยุดการทำงานหลังส่งไฟล์
            exit();
        } else {
            $error = "เกิดข้อผิดพลาดในการดาวน์โหลด: " . $downloadResult['message'];
        }
    }
}

// จัดการคำขอลบข้อมูล (ไม่ดาวน์โหลด)
if (isset($_GET['action']) && $_GET['action'] === 'empty') {
    $emptyResult = emptyTable($pdo, $table);
    
    if ($emptyResult['success']) {
        $success = "ลบข้อมูลในตารางเรียบร้อยแล้ว";
    } else {
        $error = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $emptyResult['message'];
    }
}

// นับจำนวนข้อมูลในตาราง
$stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ดาวน์โหลด CSV จาก MySQL</title>
    <style>
        body {
            font-family: 'Sarabun', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
        }
        .stats h2 {
            margin: 0;
            color: #007bff;
            font-size: 48px;
        }
        .stats p {
            margin: 10px 0 0 0;
            color: #666;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        button, .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-primary:disabled,
        .btn-danger:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            margin-top: 20px;
        }
        .info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .table-preview {
            margin-top: 30px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ดาวน์โหลด CSV จาก MySQL</h1>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <h2><?php echo number_format($totalRecords); ?></h2>
            <p>รายการในฐานข้อมูล</p>
        </div>
        
        <div class="button-group">
            <a href="?action=download" 
               class="btn btn-primary <?php echo $totalRecords == 0 ? 'disabled' : ''; ?>"
               <?php echo $totalRecords == 0 ? 'onclick="return false;"' : ''; ?>>
                📥 ดาวน์โหลด CSV และลบข้อมูล
            </a>
            
            <a href="?action=empty" 
               class="btn btn-danger <?php echo $totalRecords == 0 ? 'disabled' : ''; ?>"
               <?php echo $totalRecords == 0 ? 'onclick="return false;"' : ''; ?>
               onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลทั้งหมด?');">
                🗑️ ลบข้อมูลทั้งหมด
            </a>
        </div>
        
        <?php if ($totalRecords > 0): ?>
        <div class="warning">
            ⚠️ <strong>คำเตือน:</strong> หลังจากดาวน์โหลดไฟล์ CSV ข้อมูลในตารางจะถูกลบทั้งหมดโดยอัตโนมัติ
        </div>
        <?php endif; ?>
        
        <?php if ($totalRecords > 0): ?>
        <div class="table-preview">
            <h3>ตัวอย่างข้อมูล (10 รายการแรก)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Barcode</th>
                        <th>Physical</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT warehouse, barcode, physical FROM $table LIMIT 10");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['warehouse']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['barcode']) . "</td>";
                        echo "<td>" . number_format($row['physical']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>ข้อมูลในตาราง:</strong>
            <ul>
                <li>ตาราง: <?php echo $table; ?></li>
                <li>โครงสร้าง: warehouse, barcode, physical</li>
                <li>ไฟล์ที่ดาวน์โหลดจะมีชื่อรูปแบบ: inventory_export_YYYYMMDD_HHMMSS.csv</li>
                <li>รองรับ UTF-8 เปิดได้ถูกต้องใน Excel</li>
            </ul>
        </div>
    </div>
</body>
</html>