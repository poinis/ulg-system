<?php
// api/api_stock_today.php
// Created: 2026-01-30
// Description: API สำหรับดึงข้อมูล Stock คงเหลือปัจจุบัน (รองรับการค้นหาและแบ่งหน้า)

// 1. เรียกใช้ระบบความปลอดภัยและเชื่อมต่อฐานข้อมูล
require_once 'api_init.php';

// 2. รับค่าพารามิเตอร์สำหรับ Pagination
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit     = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 1000; 
$offset    = ($page - 1) * $limit;

// 3. รับค่าตัวกรอง (Filters)
$store_code = $_GET['store_code'] ?? ''; // กรองตามรหัสร้าน
$search     = $_GET['search'] ?? '';     // ค้นหา (Barcode หรือ ชื่อสินค้า)

try {
    // 4. สร้างเงื่อนไข WHERE (Dynamic Where Clause)
    $conditions = ["1=1"];
    $params = [];

    // กรองสาขา (ใช้ชื่อคอลัมน์ 'Store' ตาม Database จริง)
    if (!empty($store_code)) {
        $conditions[] = "stock_today.Store = ?";
        $params[] = $store_code;
    }

    // ค้นหา Barcode หรือ ชื่อสินค้า
    if (!empty($search)) {
        $conditions[] = "(stock_today.Barcode LIKE ? OR stock_today.item_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_sql = implode(" AND ", $conditions);

    // 5. นับจำนวนรายการทั้งหมด (สำหรับทำ Pagination)
    $count_sql = "SELECT COUNT(*) FROM stock_today WHERE $where_sql";
    $stmt_count = $db->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // 6. ดึงข้อมูลจริง (Main Query)
    // ใช้ AS เปลี่ยนชื่อคอลัมน์จาก DB ให้เป็น JSON key ที่อ่านง่าย (Snake Case)
    $sql = "
        SELECT 
            -- ข้อมูลร้านค้า (จาก Table stores)
            stores.store_name,
            
            -- ข้อมูลสต็อก (จาก Table stock_today) พร้อมเปลี่ยนชื่อ
            stock_today.Store          AS store_code,
            stock_today.Barcode        AS barcode,
            stock_today.item_name      AS item_name,
            stock_today.GQ_ARTICLE     AS article_code,
            stock_today.GQ_CLOTURE     AS closure_status,
            stock_today.GA_STATUTART   AS status_art,
            stock_today.GA_FAMILLENIV1 AS family_group,
            
            -- ตัวเลขจำนวนสต็อก
            stock_today.Physical       AS qty_physical,
            stock_today.Sale           AS qty_sale,
            stock_today.Discrepancy    AS qty_diff,
            stock_today.Transfer       AS qty_transfer,
            stock_today.Notice         AS qty_notice,
            
            -- คอลัมน์ชื่อพิเศษ (มีเว้นวรรคหรือสัญลักษณ์ ต้องใส่ Backtick `` ครอบ)
            stock_today.`Sup Reci`     AS qty_sup_reci,
            stock_today.`Input/Output` AS qty_io,
            
            stock_today.GQ_DATECLOTURE AS date_closure

        FROM stock_today
        LEFT JOIN stores ON stock_today.Store = stores.store_code
        WHERE $where_sql
        ORDER BY stock_today.Store ASC, stock_today.id ASC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. ส่งค่ากลับเป็น JSON
    echo json_encode([
        "status" => "success",
        "timestamp" => date('Y-m-d H:i:s'),
        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_pages" => $total_pages,
            "total_records" => $total_records
        ],
        "filters" => [
            "store_code" => $store_code,
            "search" => $search
        ],
        "count" => count($results),
        "data" => $results
    ]);

} catch (Exception $e) {
    // กรณีเกิด Error 500
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>