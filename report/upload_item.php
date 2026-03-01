<?php
/**
 * Upload "sale by item.CSV" from RFE
 * Syncs item data to ulgcegid.item_master + barcode_mapping
 */
require_once __DIR__ . '/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'กรุณาอัพโหลดไฟล์ CSV เท่านั้น';
    } else {
        try {
            $cegidDb = new PDO(
                'mysql:host=localhost;dbname=ulgcegid;charset=utf8mb4',
                'ulgcegid', '#wmIYH3wazaa',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) throw new Exception('ไม่สามารถเปิดไฟล์ได้');
            
            // Skip UTF-16 LE BOM
            $bom = fread($handle, 2);
            if ($bom !== "\xFF\xFE") rewind($handle);
            stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
            
            // Read header
            $header = fgetcsv($handle, 0, ',');
            
            // sale by item columns:
            // 14=Itemcode, 15=barcode, 17=GL_LIBELLE(description), 19=Color
            // 24=Brand Name, 26=C24(Group), 27=C25(Class), 28=Size
            
            $bmStmt = $cegidDb->prepare('
                REPLACE INTO barcode_mapping (barcode, brand, group_name, class_name, size_name)
                VALUES (?, ?, ?, ?, ?)
            ');
            
            $imStmt = $cegidDb->prepare('
                INSERT INTO item_master (item_code, brand, item_group, class_name, size_name, item_description, color, barcode)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    brand = IF(VALUES(brand) != "", VALUES(brand), brand),
                    item_group = IF(VALUES(item_group) != "", VALUES(item_group), item_group),
                    class_name = IF(VALUES(class_name) != "", VALUES(class_name), class_name),
                    size_name = IF(VALUES(size_name) != "", VALUES(size_name), size_name),
                    item_description = IF(VALUES(item_description) != "", VALUES(item_description), item_description),
                    color = IF(VALUES(color) != "", VALUES(color), color),
                    barcode = IF(VALUES(barcode) != "", VALUES(barcode), barcode)
            ');
            
            $cegidDb->beginTransaction();
            $synced_bm = 0;
            $synced_im = 0;
            $seen_barcodes = [];
            $seen_items = [];
            $errors = [];
            $row_num = 1;
            
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                $row_num++;
                if (empty($data) || count($data) < 28) continue;
                
                try {
                    $itemcode = trim($data[13] ?? '');  // col 14 (0-indexed: 13)
                    $barcode = trim($data[14] ?? '');    // col 15
                    $description = trim($data[16] ?? ''); // col 17: GL_LIBELLE
                    $color = trim($data[18] ?? '');      // col 19
                    $brand = trim($data[23] ?? '');      // col 24: Brand Name
                    $group = trim($data[25] ?? '');      // col 26: C24 (Group)
                    $class = trim($data[26] ?? '');      // col 27: C25 (Class)
                    $size = trim($data[27] ?? '');       // col 28: Size
                    
                    // Sync barcode_mapping (unique barcodes only)
                    if (!empty($barcode) && !isset($seen_barcodes[$barcode])) {
                        $bmStmt->execute([$barcode, $brand, $group, $class, $size]);
                        $seen_barcodes[$barcode] = true;
                        $synced_bm++;
                    }
                    
                    // Sync item_master (unique item codes only)
                    if (!empty($itemcode) && !isset($seen_items[$itemcode])) {
                        $imStmt->execute([$itemcode, $brand, $group, $class, $size, $description, $color, $barcode]);
                        $seen_items[$itemcode] = true;
                        $synced_im++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Row $row_num: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            $cegidDb->commit();
            
            $message = "✅ อัพโหลดสำเร็จ!<br>";
            $message .= "🏷️ Barcode mapping: <strong>" . number_format($synced_bm) . "</strong> รายการ<br>";
            $message .= "📦 Item master: <strong>" . number_format($synced_im) . "</strong> รายการ";
            
            // Show totals
            $totals = $cegidDb->query('SELECT COUNT(*) as bm FROM barcode_mapping')->fetch();
            $totals2 = $cegidDb->query('SELECT COUNT(*) as im, COUNT(DISTINCT brand) as brands FROM item_master')->fetch();
            $message .= "<br><br>📊 รวมทั้งหมด: Barcodes=" . number_format($totals['bm']) . 
                        " | Items=" . number_format($totals2['im']) . 
                        " | Brands=" . number_format($totals2['brands']);
            
            if (!empty($errors)) {
                $message .= "<br><small>⚠️ พบข้อผิดพลาด " . count($errors) . " รายการ</small>";
            }
            
        } catch (Exception $e) {
            if (isset($cegidDb)) $cegidDb->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏷️ Upload Sale By Item</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #ddd; border-radius: 5px; }
        .btn { padding: 12px 30px; background: #0288d1; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0277bd; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #0288d1; }
        .info-box ul { margin-left: 20px; margin-top: 10px; }
        .info-box li { margin-bottom: 5px; }
        .back-link { display: inline-block; margin-top: 20px; color: #0288d1; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏷️ Upload Sale By Item (RFE)</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>📋 ไฟล์ที่รองรับ:</strong> <code>sale by item.CSV</code> จาก RFE
            <ul>
                <li>ระบบจะ extract: <strong>Itemcode, Barcode, Brand, Group, Class, Size, Color</strong></li>
                <li>Sync ไปยัง <code>ulgcegid.item_master</code> + <code>barcode_mapping</code></li>
                <li>ใช้กับ Brand Report ใน Cegid Sales Dashboard</li>
            </ul>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="csv_file">เลือกไฟล์ sale by item.CSV:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            <button type="submit" class="btn">🚀 อัพโหลด & Sync</button>
        </form>
        
        <a href="upload.php" class="back-link">← กลับหน้าอัพโหลดยอดขาย</a>
        &nbsp;|&nbsp;
        <a href="/cegid-sales/brand_report.php" class="back-link">🏷️ Brand Report</a>
    </div>
</body>
</html>
