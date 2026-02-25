<?php
// pos/save_sale.php - Save Sale API
session_start();
header('Content-Type: application/json; charset=utf-8');

// Disable error display to prevent JSON issues
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

require_once "../config.php";

$userId = $_SESSION['id'];

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีรายการสินค้า']);
    exit;
}

$items = $input['items'];
$subtotal = floatval($input['subtotal'] ?? 0);
$discount = floatval($input['discount'] ?? 0);
$total = floatval($input['total'] ?? 0);
$branch = $input['branch'] ?? '';
$store = $input['store'] ?? '';
$note = $input['note'] ?? '';

// Create tables if not exist
$conn->query("
    CREATE TABLE IF NOT EXISTS `pos_sales` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sale_code` varchar(50) NOT NULL,
        `store_name` varchar(100) DEFAULT NULL,
        `branch_name` varchar(100) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
        `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `payment_note` text DEFAULT NULL,
        `sale_date` date NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `sale_code` (`sale_code`),
        KEY `branch_name` (`branch_name`),
        KEY `sale_date` (`sale_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `pos_sale_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sale_id` int(11) NOT NULL,
        `sku` varchar(50) DEFAULT NULL,
        `product_name` varchar(255) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `subtotal` decimal(10,2) NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `sale_id` (`sale_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->begin_transaction();

try {
    // Generate sale code
    $saleCode = 'POS' . date('ymd') . sprintf('%04d', rand(1, 9999));
    
    // Insert sale
    $sql = "INSERT INTO pos_sales (sale_code, store_name, branch_name, user_id, subtotal, discount, total_amount, payment_note, sale_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssiddds", $saleCode, $store, $branch, $userId, $subtotal, $discount, $total, $note);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $saleId = $conn->insert_id;
    
    // Insert items
    $stmtItem = $conn->prepare("INSERT INTO pos_sale_items (sale_id, sku, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmtItem) {
        throw new Exception("Prepare item failed: " . $conn->error);
    }
    
    foreach ($items as $item) {
        $itemSku = $item['sku'] ?? '';
        $itemName = $item['name'] ?? '';
        $itemPrice = floatval($item['price'] ?? 0);
        $itemQty = intval($item['qty'] ?? 1);
        $itemSubtotal = $itemPrice * $itemQty;
        
        $stmtItem->bind_param("issdid", $saleId, $itemSku, $itemName, $itemPrice, $itemQty, $itemSubtotal);
        
        if (!$stmtItem->execute()) {
            throw new Exception("Execute item failed: " . $stmtItem->error);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'บันทึกสำเร็จ', 
        'sale_id' => $saleCode
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
