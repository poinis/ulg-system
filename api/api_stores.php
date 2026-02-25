<?php
// api/api_stores.php

// 1. เรียกใช้ระบบความปลอดภัย
require_once 'api_init.php';

// 2. รับค่าพารามิเตอร์ (เผื่ออยากกรอง)
$active_only = $_GET['active'] ?? ''; // ส่ง active=1 เพื่อดึงเฉพาะร้านที่เปิดอยู่
$code        = $_GET['code']   ?? ''; // ส่ง code=... เพื่อดึงเฉพาะร้านที่ต้องการ

try {
    // 3. เตรียม Query
    $sql = "SELECT * FROM stores WHERE 1=1";
    $params = [];

    // เงื่อนไข: กรองเฉพาะร้านที่ Active
    if ($active_only === '1') {
        $sql .= " AND is_active = 1";
    }

    // เงื่อนไข: ค้นหารายร้าน (รองรับทั้งรหัสเก่าและใหม่)
    if (!empty($code)) {
        $sql .= " AND (store_code = ? OR store_code_new = ?)";
        $params[] = $code;
        $params[] = $code;
    }

    $sql .= " ORDER BY store_code ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. ส่งค่ากลับ (JSON)
    echo json_encode([
        "status" => "success",
        "timestamp" => date('Y-m-d H:i:s'),
        "count" => count($results),
        "data" => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>