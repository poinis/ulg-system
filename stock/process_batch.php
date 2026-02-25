<?php
header('Content-Type: application/json');
ini_set('max_execution_time', 60);
ini_set('memory_limit', '256M');

// Database Configuration
$host = 'localhost';
$dbname = 'cmbase';
$username = 'cmbase';
$password = '#wmIYH3wazaa';
$table_name = 'variant_inventory';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // รับข้อมูล JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['batch']) || !is_array($data['batch'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $batch = $data['batch'];
    $updated = 0;
    $notFound = 0;
    $errors = 0;
    
    // Prepare statement - ไม่เช็ค warehouse
    $sql = "UPDATE `{$table_name}` 
            SET `Variant Inventory Qty` = :physical 
            WHERE `Variant Barcode` = :barcode";
    $stmt = $pdo->prepare($sql);
    
    $pdo->beginTransaction();
    
    foreach ($batch as $item) {
        if (!isset($item['barcode']) || !isset($item['physical'])) {
            continue;
        }
        
        try {
            $stmt->execute([
                ':physical' => $item['physical'],
                ':barcode' => $item['barcode']
            ]);
            
            if ($stmt->rowCount() > 0) {
                $updated++;
            } else {
                $notFound++;
            }
        } catch (PDOException $e) {
            $errors++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'not_found' => $notFound,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>