<?php
// api/api_daily_sales_raw.php

require_once 'api_init.php'; 

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000; 
$offset    = ($page - 1) * $limit;

try {
    // นับจำนวนทั้งหมด
    $count_sql = "SELECT COUNT(*) as total FROM daily_sales WHERE sale_date BETWEEN ? AND ?";
    $stmt_count = $db->prepare($count_sql);
    $stmt_count->execute([$date_from, $date_to]);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // ✨✨ แก้ไขตรงนี้: สลับเอา stores.* ขึ้นก่อน ✨✨
    // ผลลัพธ์: ถ้าชื่อซ้ำกัน ค่าจาก daily_sales (ตัวหลัง) จะชนะ และไม่เป็น Null
    $sql = "
        SELECT 
            stores.*,       -- เอาข้อมูลร้านค้าขึ้นก่อน (ถ้าหาไม่เจอจะเป็น NULL)
            daily_sales.* -- เอาข้อมูลการขายปิดท้าย (ค่า store_code จากตรงนี้จะไปทับ NULL ตัวบน)
        FROM daily_sales 
        LEFT JOIN stores ON daily_sales.store_code = stores.store_code
        WHERE daily_sales.sale_date BETWEEN ? AND ?
        ORDER BY daily_sales.sale_date ASC, daily_sales.internal_ref ASC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "period" => ["from" => $date_from, "to" => $date_to],
        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_pages" => $total_pages,
            "total_records" => $total_records
        ],
        "data" => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>