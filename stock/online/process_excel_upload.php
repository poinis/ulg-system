<?php
header('Content-Type: application/json');
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

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
    $requestData = json_decode($input, true);
    
    if (!isset($requestData['data']) || !is_array($requestData['data'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $data = $requestData['data'];
    
    // เริ่ม Transaction
    $pdo->beginTransaction();
    
    // 1. ลบข้อมูลเดิมทั้งหมด
    $deleteStmt = $pdo->prepare("DELETE FROM `{$table_name}`");
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->rowCount();
    
    // 2. ดึงชื่อคอลัมน์จาก Database
    $columnsQuery = "SHOW COLUMNS FROM `{$table_name}`";
    $columnsStmt = $pdo->query($columnsQuery);
    $dbColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. สร้าง mapping ระหว่างคอลัมน์ Excel และ Database
    $firstRow = $data[0];
    $excelColumns = array_keys($firstRow);
    
    // แมปคอลัมน์ที่ตรงกัน (case-insensitive)
    $columnMap = [];
    foreach ($excelColumns as $excelCol) {
        foreach ($dbColumns as $dbCol) {
            // เปรียบเทียบแบบไม่สนใจตัวพิมพ์เล็ก-ใหญ่และช่องว่าง
            $excelColClean = strtolower(str_replace([' ', '_', '-'], '', $excelCol));
            $dbColClean = strtolower(str_replace([' ', '_', '-'], '', $dbCol));
            
            if ($excelColClean === $dbColClean) {
                $columnMap[$excelCol] = $dbCol;
                break;
            }
        }
    }
    
    // 4. สร้าง SQL Insert Statement
    $mappedColumns = array_values($columnMap);
    $placeholders = array_fill(0, count($mappedColumns), '?');
    
    $sql = "INSERT INTO `{$table_name}` (`" . implode('`, `', $mappedColumns) . "`) 
            VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pdo->prepare($sql);
    
    // 5. Insert ข้อมูล
    $inserted = 0;
    $skipped = 0;
    
    foreach ($data as $row) {
        try {
            $values = [];
            foreach ($columnMap as $excelCol => $dbCol) {
                $value = isset($row[$excelCol]) ? $row[$excelCol] : null;
                
                // แปลงค่าว่างเป็น NULL
                if ($value === '' || $value === null) {
                    $values[] = null;
                } else {
                    $values[] = $value;
                }
            }
            
            $insertStmt->execute($values);
            $inserted++;
            
        } catch (PDOException $e) {
            $skipped++;
            // Log error but continue
            error_log("Insert error: " . $e->getMessage());
        }
    }
    
    // Commit Transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'deleted' => $deletedRows,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'columns_mapped' => $columnMap
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