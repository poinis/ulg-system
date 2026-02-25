<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: search.php');
    exit;
}

if (empty($_POST['rows'])) {
    die('ไม่ได้เลือกรายการใดจากใบเสร็จ');
}

if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

// ข้อมูลรวม
$commonRetDate = $_POST['common_ret_date'] ?? date('Y-m-d');
$commonNewNumber = $_POST['common_new_number'] ?? '';

// จัดการรูปภาพรวม
$commonImgOld = "";
if (isset($_FILES["common_img_old"]) && $_FILES["common_img_old"]['error'] == 0) {
    $commonImgOld = "OLD_" . time() . "_" . uniqid() . ".jpg";
    move_uploaded_file($_FILES["common_img_old"]['tmp_name'], "uploads/" . $commonImgOld);
}

$commonImgNew = "";
if (isset($_FILES["common_img_new"]) && $_FILES["common_img_new"]['error'] == 0) {
    $commonImgNew = "NEW_" . time() . "_" . uniqid() . ".jpg";
    move_uploaded_file($_FILES["common_img_new"]['tmp_name'], "uploads/" . $commonImgNew);
}

$sql = "INSERT INTO returns 
        (sale_id, sale_date, store_code, brand, old_barcode, item_description,
         old_number, price_return, qty_return, new_number, 
         return_date, return_barcode, img_old, img_new)
        VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$ins = $conn->prepare($sql);
$countSuccess = 0;

foreach ($_POST['rows'] as $billNo => $barcodes) {
    foreach ($barcodes as $barcode => $data) {
        
        if (!isset($data['selected'])) continue;

        // ดึงข้อมูลต้นฉบับจาก sales_return
        $s = $conn->prepare("SELECT GL_DATEPIECE, GL_SOUCHE, C22, GL_REFARTBARRE, GL_LIBELLE 
                             FROM sales_return 
                             WHERE GL_NUMERO = ? AND GL_REFARTBARRE = ?");
        $s->bind_param('ss', $billNo, $barcode);
        $s->execute();
        $sale = $s->get_result()->fetch_assoc();

        if (!$sale) continue;

        // หา Store Code
        $master = $conn->prepare("SELECT store_code 
                                  FROM stores_return 
                                  WHERE ABS(store_code) = ABS(?) 
                                  LIMIT 1");
        $master->bind_param('s', $sale['GL_SOUCHE']);
        $master->execute();
        $res_master = $master->get_result()->fetch_assoc();
        $final_store_code = ($res_master) ? $res_master['store_code'] : $sale['GL_SOUCHE'];

        // 1) INSERT แถวหลัก
        $ins->bind_param(
            'ssssssddsssss',
            $sale['GL_DATEPIECE'],
            $final_store_code,
            $sale['C22'],
            $sale['GL_REFARTBARRE'],
            $sale['GL_LIBELLE'],
            $billNo,
            $data['price_return'],
            $data['qty_return'],
            $commonNewNumber,
            $commonRetDate,
            $data['ret_barcode'],
            $commonImgOld,
            $commonImgNew
        );
        $ins->execute();
        $countSuccess++;

        // 2) INSERT แถวเสริม
        if (isset($_POST['extra_barcode'][$billNo][$barcode])) {
            $extraBar = $_POST['extra_barcode'][$billNo][$barcode];
            $extraPrice = $_POST['extra_price'][$billNo][$barcode] ?? [];

            foreach ($extraBar as $idx => $barc) {
                $barc = trim($barc);
                $priceEx = isset($extraPrice[$idx]) ? (float)$extraPrice[$idx] : 0;

                if ($barc === '') continue;

                $ins->bind_param(
                    'ssssssddsssss',
                    $sale['GL_DATEPIECE'],
                    $final_store_code,
                    $sale['C22'],
                    $sale['GL_REFARTBARRE'],
                    $sale['GL_LIBELLE'],
                    $billNo,
                    $priceEx,
                    $data['qty_return'],
                    $commonNewNumber,
                    $commonRetDate,
                    $barc,
                    $commonImgOld,
                    $commonImgNew
                );
                $ins->execute();
                $countSuccess++;
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>บันทึกสำเร็จ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Sarabun', Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.success-box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    text-align: center;
    max-width: 500px;
}
.success-icon {
    font-size: 64px;
    margin-bottom: 20px;
}
h2 {
    color: #4caf50;
    font-size: 28px;
    margin-bottom: 15px;
}
p {
    font-size: 18px;
    color: #555;
    margin-bottom: 30px;
}
.btn {
    display: inline-block;
    padding: 12px 30px;
    margin: 5px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
</style>
</head>
<body>
<div class="success-box">
    <div class="success-icon">✅</div>
    <h2>บันทึกการเปลี่ยนสินค้าสำเร็จ!</h2>
    <p>บันทึกทั้งหมด <strong><?= $countSuccess ?></strong> รายการ</p>
    <a href="search.php" class="btn btn-primary">🔙 กลับหน้าค้นหา</a>
    
</div>
</body>
</html>
