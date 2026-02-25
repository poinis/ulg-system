<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Update System</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .content {
            padding: 40px;
        }
        .upload-section {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        .upload-section:hover {
            border-color: #764ba2;
            background: #f0f0ff;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin: 20px 0;
        }
        .file-input-wrapper input[type="file"] {
            display: none;
        }
        .file-label {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        .file-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .file-name {
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .btn-success:hover {
            box-shadow: 0 5px 20px rgba(56, 239, 125, 0.4);
        }
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
        }
        .warehouse-info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        .warehouse-info strong {
            color: #1976D2;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Stock Update System</h1>
            <p>อัปเดตสตอกสินค้าจากคลังที่กำหนด</p>
        </div>
        
        <div class="content">
            <div class="warehouse-info">
                <strong>🏢 Warehouse Codes ที่ใช้งาน:</strong> 02030, 02000, 02009 (หรือ 2030, 2000, 2009)
            </div>

            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <div class="upload-section">
                    <h2>📄 อัปโหลดไฟล์ CSV</h2>
                    <p style="color: #666; margin: 10px 0;">เลือกไฟล์ CSV เพื่อนับจำนวนสินค้าตาม barcode (รองรับ UTF-8, UTF-16)</p>
                    
                    <div class="file-input-wrapper">
                        <input type="file" name="csvfile" id="csvfile" accept=".csv" required>
                        <label for="csvfile" class="file-label">
                            📂 เลือกไฟล์ CSV
                        </label>
                    </div>
                    <div class="file-name" id="fileName">ยังไม่ได้เลือกไฟล์</div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" name="upload" class="btn">
                            ⬆️ อัปโหลดและประมวลผล
                        </button>
                        <button type="submit" name="export" class="btn btn-success">
                            💾 Export for Update
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top: 15px; color: #666;">กำลังประมวลผล...</p>
            </div>
            
            <div id="messageArea"></div>
            <div id="statsArea"></div>
        </div>
    </div>

    <script>
        document.getElementById('csvfile').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'ยังไม่ได้เลือกไฟล์';
            document.getElementById('fileName').textContent = fileName;
        });

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('csvfile');
            if (!fileInput.files[0] && e.submitter.name === 'upload') {
                e.preventDefault();
                alert('กรุณาเลือกไฟล์ CSV');
                return;
            }
            document.getElementById('loading').style.display = 'block';
        });
    </script>
</body>
</html>

<?php
// ===== การตั้งค่าฐานข้อมูล =====
$db_host = 'localhost';
$db_name = 'cmbase';
$db_user = 'cmbase';
$db_pass = '#wmIYH3wazaa';

// Warehouse codes ที่อนุญาต (รองรับทั้งแบบมี 0 นำหน้าและไม่มี)
$allowed_warehouses = ['02030', '02000', '02009', '2030', '2000', '2009'];

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("<div class='message error'>เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage() . "</div>");
}

// ===== ฟังก์ชันอ่านไฟล์ CSV (รองรับ UTF-16 และ UTF-8) =====
function parseCSV($file) {
    $data = [];
    
    // อ่านไฟล์ทั้งหมด
    $content = file_get_contents($file);
    if ($content === false) {
        return $data;
    }
    
    // ตรวจสอบ encoding และแปลงเป็น UTF-8
    $encoding = mb_detect_encoding($content, ['UTF-16LE', 'UTF-16BE', 'UTF-8', 'TIS-620', 'ISO-8859-1'], true);
    
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // ลบ UTF-8 BOM ถ้ามี
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    
    // แยกเป็นบรรทัด
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    
    if (empty($lines)) {
        return $data;
    }
    
    // อ่าน headers จากบรรทัดแรก
    $headerLine = array_shift($lines);
    $headers = str_getcsv($headerLine, ',', '"');
    
    // ทำความสะอาด headers
    $headers = array_map(function($h) {
        $h = trim($h);
        $h = str_replace(["\xEF\xBB\xBF", "\r", "\n", '"'], '', $h);
        return strtolower(trim($h));
    }, $headers);
    
    // อ่านข้อมูลแต่ละแถว
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        $row = str_getcsv($line, ',', '"');
        
        // ข้ามถ้าจำนวนคอลัมน์ไม่ตรง
        if (count($row) !== count($headers)) {
            continue;
        }
        
        // ทำความสะอาดข้อมูล
        $row = array_map(function($v) {
            return trim(str_replace(["\r", "\n"], '', $v));
        }, $row);
        
        $data[] = array_combine($headers, $row);
    }
    
    return $data;
}

// ===== ฟังก์ชันประมวลผลและอัปเดต Stock =====
function processAndUpdateStock($pdo, $csvData, $allowed_warehouses) {
    $barcodeSum = [];
    $filtered = 0;
    $processed = 0;
    $debug = [];
    $errors = [];
    
    // เพิ่ม debug: แสดง headers ที่อ่านได้
    if (!empty($csvData)) {
        $debug[] = "Headers พบ: " . implode(', ', array_keys($csvData[0]));
        $debug[] = "ตัวอย่างข้อมูลแถวแรก: " . json_encode($csvData[0], JSON_UNESCAPED_UNICODE);
    }
    
    // กรองและรวมข้อมูลตาม warehouse code และ barcode
    foreach ($csvData as $index => $row) {
        // ลองหา key ทั้งแบบพิมพ์เล็กและแบบปกติ
        $warehouseCode = '';
        $barcode = '';
        $physical = 0;
        
        // หา warehouse code (ลองหลายรูปแบบ)
        foreach (['warehouse code', 'warehouse_code', 'warehousecode', 'warehouse'] as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                $warehouseCode = trim($row[$key]);
                break;
            }
        }
        
        // หา barcode
        foreach (['barcode', 'bar_code', 'code'] as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                $barcode = trim($row[$key]);
                break;
            }
        }
        
        // หา physical
        foreach (['physical', 'qty', 'quantity', 'stock'] as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                $physical = intval($row[$key]);
                break;
            }
        }
        
        // เก็บ debug info สำหรับ 5 แถวแรก
        if ($index < 5) {
            $allKeys = implode(', ', array_keys($row));
            $debug[] = "Row " . ($index + 1) . ": keys=[$allKeys] | warehouse='$warehouseCode', barcode='$barcode', physical='$physical'";
        }
        
        // ตรวจสอบว่าเป็น warehouse ที่อนุญาตหรือไม่
        if (in_array($warehouseCode, $allowed_warehouses) && !empty($barcode)) {
            if (!isset($barcodeSum[$barcode])) {
                $barcodeSum[$barcode] = 0;
            }
            $barcodeSum[$barcode] += $physical;
            $filtered++;
        }
    }
    
    // เพิ่ม debug: จำนวน barcode ที่จะบันทึก
    $debug[] = "จำนวน Barcode ที่จะบันทึก: " . count($barcodeSum);
    
    // ล้างตาราง stock_update (หรือสร้างใหม่)
    try {
        // สร้างตารางถ้ายังไม่มี
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stock_update (
                id INT AUTO_INCREMENT PRIMARY KEY,
                barcode VARCHAR(255) NOT NULL,
                physical INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_barcode (barcode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // ล้างข้อมูลเก่า
        $pdo->exec("TRUNCATE TABLE stock_update");
        
        // แทรกข้อมูลใหม่
        $stmt = $pdo->prepare("INSERT INTO stock_update (barcode, physical) VALUES (:barcode, :physical)");
        
        foreach ($barcodeSum as $barcode => $totalQty) {
            $stmt->execute([
                ':barcode' => $barcode,
                ':physical' => $totalQty
            ]);
            $processed++;
        }
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    return [
        'success' => true,
        'total_rows' => count($csvData),
        'filtered' => $filtered,
        'unique_barcodes' => count($barcodeSum),
        'processed' => $processed,
        'debug' => $debug
    ];
}

// ===== ฟังก์ชัน Export for Update =====
function exportForUpdate($pdo) {
    try {
        $stmt = $pdo->query("SELECT barcode, physical FROM stock_update ORDER BY barcode");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($data)) {
            return false;
        }
        
        // สร้างไฟล์ CSV
        $filename = 'stock_update_export_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // เขียน BOM สำหรับ UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // เขียน headers
        fputcsv($output, ['barcode', 'physical']);
        
        // เขียนข้อมูล
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        return false;
    }
}

// ===== ประมวลผลคำขอ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Export for Update
    if (isset($_POST['export'])) {
        if (exportForUpdate($pdo)) {
            exit;
        } else {
            echo "<script>
                document.getElementById('messageArea').innerHTML = '<div class=\"message error\">❌ ไม่สามารถ Export ข้อมูลได้ (ยังไม่มีข้อมูลในระบบ)</div>';
                document.getElementById('loading').style.display = 'none';
            </script>";
        }
    }
    
    // Upload และประมวลผล
    if (isset($_POST['upload']) && isset($_FILES['csvfile'])) {
        $file = $_FILES['csvfile'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $csvData = parseCSV($file['tmp_name']);
            
            if (empty($csvData)) {
                echo "<script>
                    document.getElementById('messageArea').innerHTML = '<div class=\"message error\">❌ ไม่พบข้อมูลในไฟล์ CSV</div>';
                    document.getElementById('loading').style.display = 'none';
                </script>";
            } else {
                $result = processAndUpdateStock($pdo, $csvData, $allowed_warehouses);
                
                if ($result['success']) {
                    $debugInfo = implode('<br>', $result['debug']);
                    $statsHtml = "
                        <div class='stats'>
                            <div class='stat-card'>
                                <h3>ทั้งหมดใน CSV</h3>
                                <div class='number'>{$result['total_rows']}</div>
                            </div>
                            <div class='stat-card'>
                                <h3>กรองตามคลัง</h3>
                                <div class='number'>{$result['filtered']}</div>
                            </div>
                            <div class='stat-card'>
                                <h3>Barcode ไม่ซ้ำ</h3>
                                <div class='number'>{$result['unique_barcodes']}</div>
                            </div>
                            <div class='stat-card'>
                                <h3>บันทึกสำเร็จ</h3>
                                <div class='number'>{$result['processed']}</div>
                            </div>
                        </div>
                        <div class='warehouse-info'>
                            <strong>🔍 ข้อมูล Debug:</strong><br>
                            $debugInfo
                        </div>
                    ";
                    
                    echo "<script>
                        document.getElementById('messageArea').innerHTML = '<div class=\"message success\">✅ ประมวลผลและบันทึกข้อมูลสำเร็จ!</div>';
                        document.getElementById('statsArea').innerHTML = `$statsHtml`;
                        document.getElementById('loading').style.display = 'none';
                    </script>";
                } else {
                    echo "<script>
                        document.getElementById('messageArea').innerHTML = '<div class=\"message error\">❌ เกิดข้อผิดพลาด: {$result['error']}</div>';
                        document.getElementById('loading').style.display = 'none';
                    </script>";
                }
            }
        } else {
            echo "<script>
                document.getElementById('messageArea').innerHTML = '<div class=\"message error\">❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์</div>';
                document.getElementById('loading').style.display = 'none';
            </script>";
        }
    }
}
?>