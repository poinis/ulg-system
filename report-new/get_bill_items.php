<?php
// get_bill_items.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$internal_ref = $_GET['ref'] ?? '';
$store_code = $_GET['store'] ?? '';

if (!$internal_ref) {
    echo json_encode(['success' => false, 'error' => 'No reference provided']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Get bill items
    $stmt = $db->prepare("
        SELECT 
            line_barcode,
            item_description,
            brand,
            sales_division,
            group_name,
            class_name,
            size,
            qty,
            base_price,
            tax_incl_total
        FROM daily_sales
        WHERE internal_ref = ?
        ORDER BY created_at
    ");
    
    $stmt->execute([$internal_ref]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}