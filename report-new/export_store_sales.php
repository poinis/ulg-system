<?php
// export_store_sales.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// 1. รับค่า Filter (เหมือน multi_filter_report.php)
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_brands = $_GET['brands'] ?? [];
$selected_classes = $_GET['classes'] ?? [];
$selected_stores = $_GET['stores'] ?? [];
$selected_customer_types = $_GET['customer_types'] ?? [];

// 2. สร้าง SQL Conditions (แบบ Clean ไม่ Map)
$conditions = [];
$params = [];

$conditions[] = "ds.sale_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

if (!empty($selected_brands)) {
    $ph = implode(',', array_fill(0, count($selected_brands), '?'));
    $conditions[] = "ds.brand IN ($ph)";
    foreach ($selected_brands as $b) $params[] = $b;
}

if (!empty($selected_classes)) {
    $ph = implode(',', array_fill(0, count($selected_classes), '?'));
    $conditions[] = "ds.class_name IN ($ph)";
    foreach ($selected_classes as $c) $params[] = $c;
}

if (!empty($selected_stores)) {
    $ph = implode(',', array_fill(0, count($selected_stores), '?'));
    $conditions[] = "ds.store_code IN ($ph)";
    foreach ($selected_stores as $s) $params[] = $s;
}

if (!empty($selected_customer_types)) {
    $ct_conds = [];
    foreach ($selected_customer_types as $type) {
        if ($type === 'MEMBER') $ct_conds[] = "ds.customer LIKE '99%'";
        elseif ($type === 'WALKIN') $ct_conds[] = "ds.customer LIKE 'WI%TH'";
        elseif ($type === 'FOREIGNER') $ct_conds[] = "(ds.customer LIKE 'WI%' AND ds.customer NOT LIKE 'WI%TH')";
    }
    if (!empty($ct_conds)) $conditions[] = "(" . implode(' OR ', $ct_conds) . ")";
}

$where_clause = implode(' AND ', $conditions);

// 3. Query ข้อมูลดิบ
$sql = "
    SELECT 
        ds.sale_date,
        ds.store_code,
        s.store_name,
        ds.internal_ref,
        ds.line_barcode,
        ds.item_description,
        ds.brand,
        ds.sales_division,
        ds.qty,
        ds.tax_incl_total
    FROM daily_sales ds
    LEFT JOIN stores s ON ds.store_code = s.store_code
    WHERE $where_clause
    ORDER BY ds.sale_date, ds.store_code, ds.internal_ref
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. ตั้งค่า Header ให้เป็นไฟล์ Excel
$filename = "sales_report_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 5. แสดงผลตาราง (Excel จะอ่าน HTML Table นี้เป็นตาราง)
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
xmlns:x="urn:schemas-microsoft-com:office:excel"
xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
<table border="1">
    <thead>
        <tr style="background-color: #f0f0f0;">
            <th>วันที่</th>
            <th>รหัสสาขา</th>
            <th>ชื่อสาขา</th>
            <th>เลขที่บิล</th>
            <th>บาร์โค้ด</th>
            <th>ชื่อสินค้า</th>
            <th>แบรนด์</th>
            <th>แผนก</th>
            <th>จำนวน</th>
            <th>ยอดขาย (บาท)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= $row['sale_date'] ?></td>
            <td><?= $row['store_code'] ?></td>
            <td><?= $row['store_name'] ?></td>
            <td style="mso-number-format:'@'"><?= $row['internal_ref'] ?></td>
            <td style="mso-number-format:'@'"><?= $row['line_barcode'] ?></td>
            <td><?= $row['item_description'] ?></td>
            <td><?= $row['brand'] ?></td>
            <td><?= $row['sales_division'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['tax_incl_total'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>