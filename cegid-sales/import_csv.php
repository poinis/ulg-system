<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();
$message = '';
$imported = 0;
$skipped = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (!$file) {
        $message = '❌ กรุณาเลือกไฟล์';
    } else {
        // Read file - handle UTF-16LE (Cegid export format)
        $raw = file_get_contents($file);
        
        // Detect encoding
        if (substr($raw, 0, 2) === "\xFF\xFE") {
            $content = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        } elseif (substr($raw, 0, 2) === "\xFE\xFF") {
            $content = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        } else {
            $content = $raw;
        }
        
        $lines = explode("\n", $content);
        $header = str_getcsv(array_shift($lines));
        
        // Map CSV columns to indices
        $colMap = [];
        foreach ($header as $i => $h) {
            $colMap[trim($h)] = $i;
        }
        
        // Required columns check
        $required = ['Date', 'Store', 'Number', 'Internal reference - document.', 'Item code', 'Qty', 'Tax incl. total'];
        $missing = array_diff($required, array_keys($colMap));
        if (!empty($missing)) {
            $message = '❌ ไม่พบ columns: ' . implode(', ', $missing);
        } else {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    INSERT INTO sale_transactions (
                        nature_piece, souche, date_piece, store_code, caisse,
                        numero, payment_ref_interne, num_ligne, indice,
                        article_code, article_internal_code, barcode,
                        product_title, product_description, color, brand, category,
                        sub_category, dimension1, dimension2, libdim1, libdim2,
                        customer_code, customer_first_name, customer_last_name,
                        quantity, price_ht, price_ttc, discount_amount,
                        total_ttc, total_ht, bill_total_ttc, bill_discount_sc,
                        net_total_ttc, representant, ticket_annule,
                        hour_creation_combined, creator, char_libre1,
                        char_libre2, char_libre3, sync_date, raw_data
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                    ON DUPLICATE KEY UPDATE
                        quantity = VALUES(quantity),
                        price_ttc = VALUES(price_ttc),
                        total_ttc = VALUES(total_ttc),
                        net_total_ttc = VALUES(net_total_ttc),
                        bill_discount_sc = VALUES(bill_discount_sc),
                        brand = VALUES(brand),
                        category = VALUES(category),
                        sub_category = VALUES(sub_category),
                        color = VALUES(color),
                        dimension1 = VALUES(dimension1),
                        customer_first_name = VALUES(customer_first_name),
                        customer_last_name = VALUES(customer_last_name),
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $lineNum = 0;
                $receiptLines = []; // track line numbers per receipt
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $row = str_getcsv($line);
                    if (count($row) < count($required)) continue;
                    
                    $col = function($name, $default = '') use ($row, $colMap) {
                        return isset($colMap[$name]) && isset($row[$colMap[$name]]) ? trim($row[$colMap[$name]]) : $default;
                    };
                    
                    $date = date('Y-m-d', strtotime($col('Date')));
                    $store = $col('Store');
                    $numero = (int)$col('Number');
                    $ref = $col('Internal reference - document.');
                    $itemCode = $col('Item code');
                    $qty = (float)$col('Qty', 0);
                    $basePrice = (float)$col('Base price', 0);
                    $vatBasePrice = (float)$col('Vat base price', 0);
                    $discountSC = (float)$col('Discount S/C', 0);
                    $ttc = (float)$col('Tax incl. total', 0);
                    $ht = (float)$col('Tax excl. total', 0);
                    $cancelled = $col('Receipt canceled', '-');
                    $creationTime = $col('Creation time');
                    
                    // Track line number per receipt
                    $receiptKey = "$ref";
                    if (!isset($receiptLines[$receiptKey])) $receiptLines[$receiptKey] = 0;
                    $receiptLines[$receiptKey]++;
                    $numLigne = $receiptLines[$receiptKey];
                    
                    try {
                        $stmt->execute([
                            'FFO',                          // nature_piece
                            $store,                         // souche
                            $date,                          // date_piece
                            $store,                         // store_code
                            $col('Register of the document'), // caisse
                            $numero,                        // numero
                            $ref,                           // payment_ref_interne
                            $numLigne,                      // num_ligne
                            '',                             // indice
                            $itemCode,                      // article_code
                            '',                             // article_internal_code
                            $col('Line barcode'),           // barcode
                            $col('Item description'),       // product_title
                            '',                             // product_description
                            $col('Color'),                  // color
                            $col('Brand'),                  // brand
                            $col('Group'),                  // category (=Group in Cegid)
                            $col('Class'),                  // sub_category (=Class)
                            $col('Season'),                 // dimension1 (=Season)
                            $col('Size'),                   // dimension2 (=Size)
                            '',                             // libdim1
                            '',                             // libdim2
                            $col('Customer'),               // customer_code
                            $col('first name'),             // customer_first_name
                            $col('last name'),              // customer_last_name
                            $qty,                           // quantity
                            $vatBasePrice,                  // price_ht
                            $basePrice,                     // price_ttc
                            $discountSC,                    // discount_amount (S/C discount)
                            $basePrice * $qty,              // total_ttc (base price × qty)
                            $vatBasePrice * $qty,           // total_ht
                            null,                           // bill_total_ttc
                            $discountSC,                    // bill_discount_sc
                            $ttc,                           // net_total_ttc (= Tax incl. total = after S/C discount)
                            $col('Doc. line sales representative.'), // representant
                            $cancelled === 'A' ? 'X' : '-', // ticket_annule
                            $creationTime ? date('Y-m-d H:i:s', strtotime($creationTime)) : null, // hour_creation_combined
                            $col('Creation method'),        // creator
                            $col('Formula'),                // char_libre1 (=Formula/cust type)
                            $col('Internal Barcode'),       // char_libre2
                            $col('Style uk'),               // char_libre3
                            $date,                          // sync_date
                            null,                           // raw_data
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $skipped++;
                        if (count($errors) < 10) {
                            $errors[] = "Line $numLigne [$ref]: " . $e->getMessage();
                        }
                    }
                }
                
                $db->commit();
                $message = "✅ Import สำเร็จ: {$imported} รายการ" . ($skipped ? " (ข้าม {$skipped})" : '');
            } catch (Exception $e) {
                $db->rollBack();
                $message = '❌ Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📥 Import CSV</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd, #f5f5f5); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; }
        .header-content { max-width: 1400px; margin: 0 auto; }
        .header-title { color: white; font-size: 32px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; }
        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .card { background: white; padding: 35px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px; }
        .card-title { font-size: 22px; font-weight: 800; margin-bottom: 20px; color: #2c3e50; }
        .upload-area { border: 3px dashed #0288d1; border-radius: 15px; padding: 40px; text-align: center; margin: 20px 0; background: #f8f9fa; }
        .upload-area:hover { background: #e1f5fe; }
        input[type="file"] { margin: 15px 0; font-family: 'Sarabun'; font-size: 16px; }
        .btn-import { padding: 15px 40px; background: linear-gradient(135deg, #0288d1, #0097a7); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 16px; }
        .btn-import:hover { transform: translateY(-2px); }
        .msg-ok { background: #e8f5e9; color: #2e7d32; padding: 15px 20px; border-radius: 10px; margin: 15px 0; font-weight: 600; }
        .msg-err { background: #fbe9e7; color: #c62828; padding: 15px 20px; border-radius: 10px; margin: 15px 0; font-weight: 600; }
        .info { background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 15px 0; font-size: 14px; line-height: 1.8; }
        .error-list { margin-top: 10px; font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <div class="header"><div class="header-content"><div class="header-title"><span class="header-icon">📥</span> Import CSV</div></div></div>
    <div class="nav-menu"><div class="nav-content">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
        <a href="import_csv.php" class="nav-btn active">📥 Import CSV</a>
    </div></div>
    <div class="container">
        <?php if ($message): ?>
        <div class="<?= strpos($message, '✅') !== false ? 'msg-ok' : 'msg-err' ?>"><?= $message ?></div>
        <?php if (!empty($errors)): ?>
        <div class="error-list"><?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title">📥 อัพโหลดไฟล์ CSV จาก Cegid</h2>
            <div class="info">
                <strong>รองรับ:</strong> ไฟล์ CSV จาก Cegid (UTF-16LE หรือ UTF-8)<br>
                <strong>Columns ที่ต้องมี:</strong> Date, Store, Number, Internal reference, Item code, Qty, Tax incl. total<br>
                <strong>เพิ่มเติม:</strong> Brand, Group, Class, Season, Size, Color, Customer, Discount S/C ฯลฯ<br>
                <strong>หมายเหตุ:</strong> ถ้ามี record ซ้ำจะอัพเดทข้อมูลใหม่ทับ (ไม่ซ้ำ)
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area">
                    <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                    <div style="font-size: 18px; font-weight: 600; color: #333;">เลือกไฟล์ CSV</div>
                    <input type="file" name="csv_file" accept=".csv,.txt,.mp3" required>
                    <div style="color: #999; font-size: 13px; margin-top: 5px;">* รองรับไฟล์ .csv (และ .mp3 ที่จริงๆ เป็น CSV 😄)</div>
                </div>
                <button type="submit" class="btn-import">📥 Import เข้าระบบ</button>
            </form>
        </div>
    </div>
</body></html>
