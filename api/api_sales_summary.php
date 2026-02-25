<?php
// api/api_sales_summary.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ✨ บรรทัดเดียวจบ! เรียกใช้ยามเฝ้าประตู
require_once 'api_init.php'; 

// (ไม่ต้อง require config, ไม่ต้อง check api key, ไม่ต้อง connect db เองแล้ว)

// --- ส่วน Logic ของไฟล์นี้ ---
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    // $db พร้อมใช้ทันทีจาก api_init.php
    $sql = "
        SELECT 
            ds.store_code,
            s.store_name,
            COUNT(DISTINCT ds.internal_ref) as bill_count,
            SUM(ds.qty) as total_qty,
            SUM(ds.tax_incl_total) as total_sales
        FROM daily_sales ds
        LEFT JOIN stores s ON ds.store_code = s.store_code
        WHERE ds.sale_date BETWEEN ? AND ?
            AND ds.internal_ref IS NOT NULL
        GROUP BY ds.store_code, s.store_name
        ORDER BY total_sales DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "period" => ["from" => $date_from, "to" => $date_to],
        "count" => count($results),
        "data" => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>