<?php
/**
 * Process Sales Batch - Batch Insert
 * รับข้อมูลจาก JavaScript และ insert เข้า daily_sales
 */

header('Content-Type: application/json');

ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // รับข้อมูล JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['batch']) || !is_array($data['batch'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $batch = $data['batch'];
    $inserted = 0;
    $errors = 0;
    
    if (empty($batch)) {
        echo json_encode(['success' => true, 'inserted' => 0, 'errors' => 0]);
        exit;
    }
    
    // ==========================================
    // Multi-Insert (เร็วกว่า insert ทีละ row 10-50 เท่า)
    // ==========================================
    $columns = "(sale_date, store_code, internal_ref, sales_division, brand, group_name, class_name, 
                 line_barcode, item_description, customer, member, first_name, last_name, size, 
                 qty, base_price, tax_incl_total)";
    
    $placeholders = [];
    $values = [];
    
    foreach ($batch as $row) {
        // Validate required fields
        if (empty($row['sale_date']) || empty($row['store_code'])) {
            $errors++;
            continue;
        }
        
        $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $values[] = $row['sale_date'];
        $values[] = $row['store_code'];
        $values[] = $row['internal_ref'] ?? '';
        $values[] = $row['sales_division'] ?? '';
        $values[] = $row['brand'] ?? '';
        $values[] = $row['group_name'] ?? '';
        $values[] = $row['class_name'] ?? '';
        $values[] = $row['line_barcode'] ?? '';
        $values[] = $row['item_description'] ?? '';
        $values[] = $row['customer'] ?? '';
        $values[] = $row['member'] ?? '';
        $values[] = $row['first_name'] ?? '';
        $values[] = $row['last_name'] ?? '';
        $values[] = $row['size'] ?? '';
        $values[] = intval($row['qty'] ?? 0);
        $values[] = floatval($row['base_price'] ?? 0);
        $values[] = floatval($row['tax_incl_total'] ?? 0);
    }
    
    if (!empty($placeholders)) {
        $sql = "INSERT INTO daily_sales_copy1 $columns VALUES " . implode(', ', $placeholders);
        
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            $inserted = count($placeholders);
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            
            // Fallback: insert ทีละ row
            $inserted = 0;
            $stmt = $db->prepare("
                INSERT INTO daily_sales_copy1 $columns 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($batch as $row) {
                if (empty($row['sale_date']) || empty($row['store_code'])) {
                    continue;
                }
                
                try {
                    $stmt->execute([
                        $row['sale_date'],
                        $row['store_code'],
                        $row['internal_ref'] ?? '',
                        $row['sales_division'] ?? '',
                        $row['brand'] ?? '',
                        $row['group_name'] ?? '',
                        $row['class_name'] ?? '',
                        $row['line_barcode'] ?? '',
                        $row['item_description'] ?? '',
                        $row['customer'] ?? '',
                        $row['member'] ?? '',
                        $row['first_name'] ?? '',
                        $row['last_name'] ?? '',
                        $row['size'] ?? '',
                        intval($row['qty'] ?? 0),
                        floatval($row['base_price'] ?? 0),
                        floatval($row['tax_incl_total'] ?? 0)
                    ]);
                    $inserted++;
                } catch (PDOException $e2) {
                    $errors++;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'inserted' => 0,
        'errors' => count($data['batch'] ?? [])
    ]);
}
?>