<?php
// upload_sales.php
require_once 'config.php';

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $error = 'กรุณาอัพโหลดไฟล์ CSV เท่านั้น';
        } else {
            $uploaded_records = 0;
            $errors = [];
            
            try {
                $db->beginTransaction();
                
                // Open CSV file with UTF-16 LE support
                $handle = fopen($file['tmp_name'], 'r');
                
                if ($handle !== false) {
                    // ตรวจสอบและข้าม UTF-16 LE BOM
                    $bom = fread($handle, 2);
                    if ($bom !== "\xFF\xFE") {
                        rewind($handle);
                    }
                    
                    // เพิ่ม filter แปลง UTF-16LE เป็น UTF-8
                    stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
                    
                    // อ่าน header
                    $header = fgetcsv($handle, 0, ',');
                    
                    // ลบ BOM character ออกจาก header แรก (ถ้ามี)
                    if (!empty($header[0])) {
                        $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);
                        $header[0] = trim($header[0], "\xEF\xBB\xBF\xFE\xFF");
                    }
                    
                    // Prepare insert statement with new fields
                    $stmt = $db->prepare("
                        INSERT INTO daily_sales 
                        (sale_date, store_code, internal_ref, sales_division, brand, group_name, class_name, 
                         line_barcode, item_description, customer, member, first_name, last_name, size, 
                         qty, base_price, tax_incl_total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $row_num = 1;
                    while (($data = fgetcsv($handle, 0, ',')) !== false) {
                        $row_num++;
                        
                        // ข้ามแถวว่าง
                        if (empty($data) || (count($data) == 1 && empty($data[0]))) {
                            continue;
                        }
                        
                        try {
                            // ตรวจสอบว่ามีข้อมูลเพียงพอหรือไม่
                            if (count($data) < 27) {
                                throw new Exception("Insufficient columns: " . count($data) . " found, need 27+");
                            }
                            
                            // Parse วันที่ - แก้ไขให้รองรับ d/m/Y format
                            $date_str = trim($data[0]);
                            
                            // ลบ BOM ถ้ามีติดมา
                            $date_str = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $date_str);
                            $date_str = trim($date_str);
                            
                            if (empty($date_str)) {
                                throw new Exception("Empty date");
                            }
                            
                            // แปลงวันที่จาก d/m/Y เป็น Y-m-d
                            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_str, $matches)) {
                                // Format: d/m/Y (Thai format)
                                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                $year = $matches[3];
                                $date = "$year-$month-$day";
                                
                                // ตรวจสอบว่าวันที่ถูกต้องหรือไม่
                                if (!checkdate((int)$month, (int)$day, (int)$year)) {
                                    throw new Exception("Invalid date: $date_str (parsed as $date)");
                                }
                            } else {
                                // ลอง parse ด้วย strtotime (fallback)
                                $timestamp = strtotime($date_str);
                                if ($timestamp === false) {
                                    throw new Exception("Cannot parse date: '$date_str'");
                                }
                                $date = date('Y-m-d', $timestamp);
                            }
                            
                            // ดึงข้อมูลจากคอลัมน์ที่ถูกต้อง
                            $store = trim($data[1]) ?: ''; // Column 1: Store
                            $internal_ref = trim($data[4] ?? ''); // Column 4: Internal reference - document
                            $sales_division = trim($data[6] ?? ''); // Column 6: Sales division
                            $brand = trim($data[17] ?? ''); // Column 17: Brand
                            $group_name = trim($data[18] ?? ''); // Column 18: Group
                            $class_name = trim($data[19] ?? ''); // Column 19: Class
                            
                            // ฟิลด์ใหม่ (ตำแหน่งคอลัมน์ที่ถูกต้อง)
                            $line_barcode = trim($data[8] ?? ''); // Column 8: Line barcode
                            $item_description = trim($data[10] ?? ''); // Column 10: Item description
                            $customer = trim($data[12] ?? ''); // Column 12: Customer
                            $first_name = trim($data[14] ?? ''); // Column 14: First name
                            $last_name = trim($data[15] ?? ''); // Column 15: Last name
                            $size = trim($data[21] ?? ''); // Column 21: Size
                            
                            // คำนวณ member จาก customer
                            $member = '';
                            if (!empty($customer)) {
                                // ตรวจสอบว่าเป็นตัวเลขอย่างเดียวหรือไม่
                                if (preg_match('/^\d+$/', $customer)) {
                                    $member = 'ULG Member';
                                } else {
                                    $member = $customer;
                                }
                            }
                            
                            // แปลงตัวเลข - ลบ comma ออกก่อน
                            $qty_str = str_replace(',', '', trim($data[22] ?? '0')); // Column 22: Qty
                            $base_price_str = str_replace(',', '', trim($data[24] ?? '0')); // Column 24: Base price
                            $tax_str = str_replace(',', '', trim($data[26] ?? '0')); // Column 26: Tax incl. total
                            
                            $qty = intval($qty_str);
                            $base_price = floatval($base_price_str);
                            $tax_incl_total = floatval($tax_str);
                            
                            // ตรวจสอบข้อมูลที่จำเป็น
                            if (empty($store)) {
                                throw new Exception("Store code is empty");
                            }
                            
                            $stmt->execute([
                                $date, $store, $internal_ref, $sales_division, $brand, 
                                $group_name, $class_name, $line_barcode,
                                $item_description, $customer, $member, $first_name, 
                                $last_name, $size, $qty, $base_price, $tax_incl_total
                            ]);
                            
                            $uploaded_records++;
                            
                        } catch (Exception $e) {
                            $errors[] = "Row $row_num: " . $e->getMessage();
                        }
                    }
                    
                    fclose($handle);
                    
                    $db->commit();
                    
                    // Send email notification only
                    sendEmailNotification($uploaded_records, $file['name'], date('Y-m-d'));
                    
                    $message = "อัพโหลดสำเร็จ! จำนวน $uploaded_records รายการ";
                    
                    if (!empty($errors)) {
                        $message .= "<br><small>พบข้อผิดพลาด " . count($errors) . " รายการ</small>";
                        // แสดง error 5 รายการแรก
                        $message .= "<br><pre style='font-size:12px; background:#f5f5f5; padding:10px; margin-top:10px;'>";
                        foreach (array_slice($errors, 0, 5) as $err) {
                            $message .= htmlspecialchars($err) . "\n";
                        }
                        if (count($errors) > 5) {
                            $message .= "... และอีก " . (count($errors) - 5) . " รายการ\n";
                        }
                        $message .= "</pre>";
                    }
                    
                } else {
                    throw new Exception('ไม่สามารถเปิดไฟล์ได้');
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
    }
}

function sendEmailNotification($records, $filename, $date) {
    $to = ADMIN_EMAIL;
    $subject = "แจ้งเตือน: อัพเดทยอดขายรายวัน - " . date('d/m/Y', strtotime($date));
    $message = "
        <html>
        <body>
            <h2>อัพเดทยอดขายรายวันสำเร็จ</h2>
            <p><strong>วันที่:</strong> " . date('d/m/Y', strtotime($date)) . "</p>
            <p><strong>ไฟล์:</strong> $filename</p>
            <p><strong>จำนวนรายการ:</strong> " . number_format($records) . "</p>
            <p><strong>เวลา:</strong> " . date('H:i:s') . "</p>
        </body>
        </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Sales Report System <noreply@example.com>" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพโหลดยอดขาย</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .upload-form { margin-top: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #ddd; border-radius: 5px; }
        .btn { padding: 12px 30px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #45a049; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3; }
        .info-box ul { margin-left: 20px; margin-top: 10px; }
        .info-box li { margin-bottom: 5px; }
        .back-link { display: inline-block; margin-top: 20px; color: #2196F3; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 อัพโหลดยอดขายรายวัน</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>📋 ข้อมูลที่ต้องมีในไฟล์ CSV:</strong>
            <ul>
                <li>Date (d/m/Y), Store, <strong>Internal reference - document</strong></li>
                <li>Sales division, Brand, Group, Class</li>
                <li><strong>Line barcode, Item description, Customer</strong></li>
                <li><strong>First name, Last name</strong></li>
                <li>Qty, <strong>Size</strong>, Base price, Tax incl. total</li>
            </ul>
            <br>
            <strong>ℹ️ หมายเหตุ:</strong> ระบบจะสร้างฟิลด์ <strong>Member</strong> อัตโนมัติ<br>
            - ถ้า Customer เป็นตัวเลขอย่างเดียว → บันทึกเป็น "ULG Member"<br>
            - ถ้าไม่ใช่ตัวเลข → บันทึกข้อมูลตามที่ระบุใน Customer
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="csv_file">เลือกไฟล์ CSV:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            
            <button type="submit" class="btn">🚀 อัพโหลด</button>
        </form>
        
        <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
    </div>
</body>
</html>