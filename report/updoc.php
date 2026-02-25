<?php
// upload_sales_documents.php
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
                    
                    // Prepare insert statement with all fields matching CSV columns
                    $stmt = $db->prepare("
                        INSERT INTO sales_documents 
                        (type_of_document, gl_souche, date, store, store_code, register_of_doc, number, 
                         internal_reference_document, register_of_the_line, line_no, index_field, 
                         item_identifier, sales_division, item_code, line_barcode, compl_desc, 
                         gl_libelle, item_description, color, customer, first_name, last_name, 
                         brand_code, brand_name, season, style_type, class, size, qty, 
                         vat_base_price, base_price, discount_sc, tax_incl_total, tax_excl_total, 
                         receipt_canceled, creation_time, original_document_line, 
                         doc_line_sales_representative, t_fax, t_telephone, t_telex, t_telephone2, 
                         line_discount_type, warehouse_document, original_user, gp_createur, 
                         internal_barcode, ext_barcode, text_reference2)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                                ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                            if (count($data) < 48) {
                                throw new Exception("Insufficient columns: " . count($data) . " found, need 48+");
                            }
                            
                            // Parse วันที่ - แก้ไขให้รองรับ d/m/Y format
                            $date_str = trim($data[2]); // Column 2: Date
                            
                            // ลบ BOM ถ้ามีติดมา
                            $date_str = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $date_str);
                            $date_str = trim($date_str);
                            
                            if (empty($date_str)) {
                                throw new Exception("Empty date");
                            }
                            
                            // แปลงวันที่จาก d/m/Y เป็น Y-m-d
                            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_str, $matches)) {
                                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                $year = $matches[3];
                                $date = "$year-$month-$day";
                                
                                if (!checkdate((int)$month, (int)$day, (int)$year)) {
                                    throw new Exception("Invalid date: $date_str");
                                }
                            } else {
                                $timestamp = strtotime($date_str);
                                if ($timestamp === false) {
                                    throw new Exception("Cannot parse date: '$date_str'");
                                }
                                $date = date('Y-m-d', $timestamp);
                            }
                            
                            // Parse creation_time
                            $creation_time = null;
                            if (!empty($data[37])) {
                                $time_str = trim($data[37]);
                                $timestamp = strtotime($date . ' ' . $time_str);
                                if ($timestamp !== false) {
                                    $creation_time = date('Y-m-d H:i:s', $timestamp);
                                }
                            }
                            
                            // ดึงข้อมูลจากแต่ละคอลัมน์
                            $type_of_document = trim($data[0] ?? '');
                            $gl_souche = trim($data[1] ?? '');
                            $store = trim($data[3] ?? '');
                            $store_code = trim($data[4] ?? '');
                            $register_of_doc = trim($data[5] ?? '');
                            $number = trim($data[6] ?? '');
                            $internal_ref = trim($data[7] ?? '');
                            $register_of_line = trim($data[8] ?? '');
                            $line_no = intval($data[9] ?? 0);
                            $index_field = trim($data[10] ?? '');
                            $item_identifier = trim($data[11] ?? '');
                            $sales_division = trim($data[12] ?? '');
                            $item_code = trim($data[13] ?? '');
                            $line_barcode = trim($data[14] ?? '');
                            $compl_desc = trim($data[15] ?? '');
                            $gl_libelle = trim($data[16] ?? '');
                            $item_description = trim($data[17] ?? '');
                            $color = trim($data[18] ?? '');
                            $customer = trim($data[19] ?? '');
                            $first_name = trim($data[20] ?? '');
                            $last_name = trim($data[21] ?? '');
                            $brand_code = trim($data[22] ?? '');
                            $brand_name = trim($data[23] ?? '');
                            $season = trim($data[24] ?? '');
                            $style_type = trim($data[25] ?? '');
                            $class = trim($data[26] ?? '');
                            $size = trim($data[27] ?? '');
                            
                            // แปลงตัวเลข - ลบ comma ออกก่อน
                            $qty = floatval(str_replace(',', '', trim($data[28] ?? '0')));
                            $vat_base_price = floatval(str_replace(',', '', trim($data[29] ?? '0')));
                            $base_price = floatval(str_replace(',', '', trim($data[30] ?? '0')));
                            $discount_sc = floatval(str_replace(',', '', trim($data[31] ?? '0')));
                            $tax_incl_total = floatval(str_replace(',', '', trim($data[32] ?? '0')));
                            $tax_excl_total = floatval(str_replace(',', '', trim($data[33] ?? '0')));
                            
                            // Boolean field
                            $receipt_canceled = !empty($data[34]) && strtolower(trim($data[34])) == 'yes' ? 1 : 0;
                            
                            // Fields ที่เหลือ
                            $original_doc_line = trim($data[38] ?? '');
                            $doc_line_sales_rep = trim($data[39] ?? '');
                            $t_fax = trim($data[40] ?? '');
                            $t_telephone = trim($data[41] ?? '');
                            $t_telex = trim($data[42] ?? '');
                            $t_telephone2 = trim($data[43] ?? '');
                            $line_discount_type = trim($data[44] ?? '');
                            $warehouse_document = trim($data[45] ?? '');
                            $original_user = trim($data[46] ?? '');
                            $gp_createur = trim($data[47] ?? '');
                            $internal_barcode = trim($data[48] ?? '');
                            $ext_barcode = trim($data[49] ?? '');
                            $text_reference2 = trim($data[50] ?? '');
                            
                            // Execute insert
                            $stmt->execute([
                                $type_of_document, $gl_souche, $date, $store, $store_code, 
                                $register_of_doc, $number, $internal_ref, $register_of_line, 
                                $line_no, $index_field, $item_identifier, $sales_division, 
                                $item_code, $line_barcode, $compl_desc, $gl_libelle, 
                                $item_description, $color, $customer, $first_name, $last_name, 
                                $brand_code, $brand_name, $season, $style_type, $class, $size, 
                                $qty, $vat_base_price, $base_price, $discount_sc, 
                                $tax_incl_total, $tax_excl_total, $receipt_canceled, 
                                $creation_time, $original_doc_line, $doc_line_sales_rep, 
                                $t_fax, $t_telephone, $t_telex, $t_telephone2, 
                                $line_discount_type, $warehouse_document, $original_user, 
                                $gp_createur, $internal_barcode, $ext_barcode, $text_reference2
                            ]);
                            
                            $uploaded_records++;
                            
                        } catch (Exception $e) {
                            $errors[] = "Row $row_num: " . $e->getMessage();
                        }
                    }
                    
                    fclose($handle);
                    $db->commit();
                    
                    // Send email notification
                    sendEmailNotification($uploaded_records, $file['name'], date('Y-m-d'));
                    
                    $message = "อัพโหลดสำเร็จ! จำนวน $uploaded_records รายการ";
                    
                    if (!empty($errors)) {
                        $message .= "<br><small>พบข้อผิดพลาด " . count($errors) . " รายการ</small>";
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
    <title>อัพโหลดข้อมูลยอดขาย</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Sarabun', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px; 
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
        }
        h1 { 
            color: #333; 
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 600;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .upload-form { margin-top: 30px; }
        .form-group { margin-bottom: 25px; }
        label { 
            display: block; 
            margin-bottom: 10px; 
            font-weight: 500; 
            color: #555;
            font-size: 16px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        input[type="file"] { 
            width: 100%; 
            padding: 15px; 
            border: 3px dashed #667eea; 
            border-radius: 8px;
            background: #f8f9ff;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        input[type="file"]:hover {
            background: #eef1ff;
            border-color: #764ba2;
        }
        .btn { 
            padding: 15px 40px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 18px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .message { 
            padding: 20px; 
            margin-bottom: 25px; 
            border-radius: 8px;
            font-size: 16px;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545;
        }
        .info-box { 
            background: linear-gradient(135deg, #e7f3ff 0%, #f0e7ff 100%);
            padding: 25px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
            border-left: 5px solid #667eea;
        }
        .info-box h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-box ul { 
            margin-left: 20px; 
            margin-top: 10px;
            line-height: 1.8;
        }
        .info-box li { 
            margin-bottom: 8px;
            color: #555;
        }
        .back-link { 
            display: inline-block; 
            margin-top: 25px; 
            color: #667eea; 
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
        }
        .back-link:hover { 
            color: #764ba2;
            transform: translateX(-5px);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 600;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 อัพโหลดข้อมูลยอดขาย</h1>
        <div class="subtitle">นำเข้าข้อมูลจากไฟล์ CSV เข้าสู่ระบบ</div>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📋 ข้อมูลที่ต้องมีในไฟล์ CSV (ตามลำดับคอลัมน์)</h3>
            <ul>
                <li><strong>คอลัมน์ 0-10:</strong> Type of document, GL_SOUCHE, Date, Store, Store code, Register of doc., Number, Internal reference, Register of line, Line no., Index</li>
                <li><strong>คอลัมน์ 11-20:</strong> Item identifier, Sales division, Item code, Line barcode, COMPL.DESC, GL_LIBELLE, Item description, Color, Customer, First name</li>
                <li><strong>คอลัมน์ 21-30:</strong> Last name, Brand code, Brand name, Season, Style type, Class, Size, Qty, Vat base price, Base price</li>
                <li><strong>คอลัมน์ 31-40:</strong> Discount S/C, Tax incl. total, Tax excl. total, Receipt canceled, Creation time, Original document line, Doc. line sales rep., T_FAX, T_TELEPHONE, T_TELEX</li>
                <li><strong>คอลัมน์ 41-50:</strong> T_TELEPHONE2, Line discount type, Warehouse document, Original user, GP_CREATEUR, Internal Barcode, Ext. Barcode, Text Reference2</li>
            </ul>
            <br>
            <strong>ℹ️ รูปแบบวันที่:</strong> dd/mm/yyyy (เช่น 01/12/2024)<br>
            <strong>⚠️ ข้อควรระวัง:</strong> ไฟล์ CSV ควรมีอย่างน้อย 48 คอลัมน์
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="csv_file">📁 เลือกไฟล์ CSV:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            
            <button type="submit" class="btn">🚀 อัพโหลดข้อมูล</button>
        </form>
        
        <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
    </div>
</body>
</html>