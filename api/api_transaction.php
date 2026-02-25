<?php
require_once 'api_init.php'; 
// Params
$ref = $_GET['ref'] ?? ''; // ระบุเลขบิลเจาะจง
$date = $_GET['date'] ?? ''; // หรือระบุวันที่

try {
    $results = [];
    
    if ($ref) {
        // กรณีระบุเลขบิล (ดึงบิลเดียว + สินค้า)
        $stmt = $db->prepare("SELECT * FROM daily_sales WHERE internal_ref = ?");
        $stmt->execute([$ref]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    elseif ($date) {
        // กรณีระบุวันที่ (ดึงทุกบิลในวันนั้น - เฉพาะ Header)
        $stmt = $db->prepare("
            SELECT internal_ref, store_code, sale_date, SUM(tax_incl_total) as total 
            FROM daily_sales 
            WHERE sale_date = ? 
            GROUP BY internal_ref
        ");
        $stmt->execute([$date]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("Please provide 'ref' or 'date' parameter");
    }

    echo json_encode([
        "status" => "success",
        "data" => $results
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>