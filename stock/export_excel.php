<?php
// Database Configuration
$host = 'localhost';
$dbname = 'cmbase';
$username = 'cmbase';
$password = '#wmIYH3wazaa';
$table_name = 'variant_inventory';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ดึงข้อมูล
    $sql = "SELECT * FROM `{$table_name}` ORDER BY ID ASC";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($data) === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ไม่พบข้อมูลในตาราง']);
        exit;
    }
    
    // ดึงชื่อคอลัมน์
    $columns = array_keys($data[0]);
    
    // สร้างไฟล์ Excel
    $filename = "variant_inventory_" . date('Y-m-d_H-i-s') . ".xls";
    
    // Set headers สำหรับดาวน์โหลด
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // สร้าง HTML Table (Excel จะแปลงเป็น .xls ได้เอง)
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th { background-color: #4CAF50; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; text-align: center; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; white-space: nowrap; }';
    echo '.number { mso-number-format:"0"; text-align: right; }';
    echo '.text { mso-number-format:"\@"; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Header
    echo '<tr>';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars($col) . '</th>';
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($columns as $col) {
            $value = $row[$col];
            
            // ตรวจสอบว่าเป็นตัวเลขหรือไม่
            if (is_numeric($value) && $value != '') {
                // แสดงเป็นตัวเลข (ไม่มีทศนิยม)
                echo '<td class="number">' . intval($value) . '</td>';
            } else {
                // แสดงเป็น Text
                echo '<td class="text">' . htmlspecialchars($value) . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
?>