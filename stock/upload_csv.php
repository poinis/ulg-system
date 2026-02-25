<?php
// การตั้งค่าฐานข้อมูล
$host = 'localhost';
$dbname = 'cmbase';
$username = 'cmbase';
$password = '#wmIYH3wazaa';
$table = 'stock_update'; // ชื่อตารางที่ต้องการบันทึก

// ตรวจสอบว่าเป็นการเรียกผ่าน API หรือไม่
$isAPI = (
    isset($_SERVER['HTTP_ACCEPT']) && 
    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || (
    isset($_POST['api']) && $_POST['api'] == '1'
) || (
    !isset($_SERVER['HTTP_ACCEPT']) || 
    strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false
);

// เชื่อมต่อฐานข้อมูล
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    if ($isAPI) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
    die("Connection failed: " . $e->getMessage());
}

// สร้างตารางถ้ายังไม่มี
$createTable = "CREATE TABLE IF NOT EXISTS $table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse VARCHAR(100),
    barcode VARCHAR(100),
    physical INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_barcode (warehouse, barcode)
)";
$pdo->exec($createTable);

// ฟังก์ชันตรวจสอบและแปลง encoding
function detectAndConvertEncoding($filePath) {
    $content = file_get_contents($filePath);
    
    // ตรวจสอบ BOM และ encoding
    if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
        // UTF-16
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
    } elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        // UTF-8 with BOM
        $content = substr($content, 3);
    } else {
        // ลองตรวจสอบ encoding อื่นๆ
        $encoding = mb_detect_encoding($content, ['UTF-8', 'UTF-16', 'TIS-620', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }
    
    // บันทึกไฟล์ชั่วคราว
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tempFile, $content);
    
    return $tempFile;
}

// ฟังก์ชันประมวลผล CSV (ปรับปรุงให้ประหยัด memory)
function processCSV($filePath) {
    $data = [];
    
    // เพิ่ม memory limit
    ini_set('memory_limit', '512M');
    
    // แปลง encoding ก่อน
    $convertedFile = detectAndConvertEncoding($filePath);
    
    if (($handle = fopen($convertedFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle); // อ่านหัวตาราง
        
        // ทำความสะอาดหัวตาราง (ลบ BOM และช่องว่าง)
        $headers = array_map(function($header) {
            return trim(preg_replace('/\x{FEFF}/u', '', strtolower($header)));
        }, $headers);
        
        // หา index ของคอลัมน์ที่ต้องการ
        $barcodeIndex = array_search('barcode', $headers);
        $physicalIndex = array_search('physical', $headers);
        
        if ($barcodeIndex === false || $physicalIndex === false) {
            fclose($handle);
            unlink($convertedFile);
            return ['success' => false, 'message' => 'ไม่พบคอลัมน์ barcode หรือ physical'];
        }
        
        $lineCount = 0;
        
        // อ่านข้อมูลและรวมตาม barcode
        while (($row = fgetcsv($handle)) !== FALSE) {
            $lineCount++;
            $barcode = trim($row[$barcodeIndex]);
            $physical = trim($row[$physicalIndex]);
            
            // ข้ามแถวที่ไม่มีข้อมูล
            if (empty($barcode) || $physical === '' || $physical === 'N/A') {
                continue;
            }
            
            // แปลงค่า physical เป็นตัวเลข
            $physical = is_numeric($physical) ? (int)$physical : 0;
            
            // รวมจำนวนถ้า barcode ซ้ำ
            if (isset($data[$barcode])) {
                $data[$barcode] += $physical;
            } else {
                $data[$barcode] = $physical;
            }
            
            // ล้าง memory ทุกๆ 100 แถว
            if ($lineCount % 100 == 0) {
                gc_collect_cycles();
            }
        }
        fclose($handle);
        
        // ลบไฟล์ชั่วคราว
        unlink($convertedFile);
    }
    
    return ['success' => true, 'data' => $data, 'lines_read' => $lineCount];
}

// ฟังก์ชันบันทึกลงฐานข้อมูล
function saveToDatabase($pdo, $table, $data) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO $table (warehouse, barcode, physical) 
            VALUES (:warehouse, :barcode, :physical)
            ON DUPLICATE KEY UPDATE physical = :physical
        ");
        
        $count = 0;
        foreach ($data as $barcode => $physical) {
            $stmt->execute([
                ':warehouse' => 'online',
                ':barcode' => $barcode,
                ':physical' => $physical
            ]);
            $count++;
            
            // ล้าง memory ทุกๆ 50 records
            if ($count % 50 == 0) {
                gc_collect_cycles();
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'count' => $count];
    } catch(Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// จัดการอัพโหลดไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // ตรวจสอบข้อผิดพลาดในการอัพโหลด
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "เกิดข้อผิดพลาดในการอัพโหลดไฟล์";
        $result = ['success' => false, 'message' => $error];
    }
    // ตรวจสอบนามสกุลไฟล์ (รองรับทั้งตัวพิมพ์เล็กและใหญ่)
    elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = "กรุณาอัพโหลดไฟล์ CSV เท่านั้น";
        $result = ['success' => false, 'message' => $error];
    }
    else {
        // ประมวลผล CSV
        $processResult = processCSV($file['tmp_name']);
        
        if ($processResult['success']) {
            // บันทึกลงฐานข้อมูล
            $saveResult = saveToDatabase($pdo, $table, $processResult['data']);
            
            if ($saveResult['success']) {
                $success = "อัพโหลดและบันทึกข้อมูลสำเร็จ จำนวน {$saveResult['count']} รายการ";
                $result = [
                    'success' => true,
                    'message' => $success,
                    'count' => $saveResult['count'],
                    'lines_read' => $processResult['lines_read'],
                    'table' => $table
                ];
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $saveResult['message'];
                $result = ['success' => false, 'message' => $error];
            }
        } else {
            $error = $processResult['message'];
            $result = ['success' => false, 'message' => $error];
        }
    }
    
    // ถ้าเป็น API ให้ return JSON
    if ($isAPI) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

// ถ้าไม่ใช่ API และไม่มีการ POST ให้แสดง HTML
if ($isAPI && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

// นับจำนวนข้อมูลในตาราง (สำหรับแสดง HTML)
$stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพโหลด CSV และบันทึกลง MySQL</title>
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
        .upload-form {
            margin-top: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>อัพโหลด CSV และบันทึกลง MySQL</h1>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <h2><?php echo number_format($totalRecords); ?></h2>
            <p>รายการในตาราง <?php echo $table; ?></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="csv_file">เลือกไฟล์ CSV:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            
            <button type="submit">อัพโหลดและประมวลผล</button>
        </form>
        
        <div class="info">
            <strong>คำแนะนำ:</strong>
            <ul>
                <li>ไฟล์ CSV ต้องมีคอลัมน์ "barcode" และ "physical"</li>
                <li>ระบบจะรวมจำนวนสินค้าที่มี barcode เหมือนกัน</li>
                <li>ข้อมูลจะถูกบันทึกลงตาราง "<?php echo $table; ?>" ด้วย warehouse = "online"</li>
                <li>ถ้า barcode ซ้ำในฐานข้อมูล จะอัพเดทจำนวนใหม่</li>
                <li>รองรับการเรียกผ่าน API (จะ return JSON)</li>
            </ul>
        </div>
    </div>
</body>
</html>