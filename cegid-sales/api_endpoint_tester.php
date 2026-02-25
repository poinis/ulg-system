<?php
/**
 * Cegid REST API Endpoint Tester
 * ทดสอบหา API endpoints ที่ใช้ดึงข้อมูลยอดขาย
 */

// Configuration
$CEGID_CONFIG = [
    'base_url' => 'https://90643827-retail-ondemand.cegid.cloud/Y2',
    'username' => '90643827_001_PROD\\frt',
    'password' => 'adgjm',
    'folder_id' => '90643827_001_PROD',
];

// Test date (จากไฟล์ตัวอย่าง)
$testDate = '2026-01-31';

/**
 * Call Cegid REST API
 */
function callCegidAPI($endpoint, $params = []) {
    global $CEGID_CONFIG;
    
    $url = "{$CEGID_CONFIG['base_url']}/{$CEGID_CONFIG['folder_id']}/api/{$endpoint}";
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$CEGID_CONFIG['username']}:{$CEGID_CONFIG['password']}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'url' => $url,
        'http_code' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

/**
 * Display test result
 */
function displayResult($name, $result) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Test: {$name}\n";
    echo str_repeat("=", 80) . "\n";
    echo "URL: {$result['url']}\n";
    echo "HTTP Code: {$result['http_code']}\n";
    
    if ($result['success']) {
        echo "Status: ✅ SUCCESS\n";
        echo "\nResponse Preview:\n";
        echo substr(json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 1000);
        echo "\n\n";
        
        // Show structure
        if (is_array($result['data'])) {
            echo "Data Structure:\n";
            if (!empty($result['data'])) {
                $firstItem = is_array($result['data']) ? reset($result['data']) : $result['data'];
                if (is_array($firstItem)) {
                    echo "  Fields: " . implode(", ", array_keys($firstItem)) . "\n";
                }
            }
        }
    } else {
        echo "Status: ❌ FAILED\n";
        if ($result['error']) {
            echo "Error: {$result['error']}\n";
        }
        echo "Response: " . substr($result['raw'], 0, 500) . "\n";
    }
    echo "\n";
}

// ============================================================================
// PRIORITY 1: API Receipts (v2)
// ============================================================================

echo "\n" . str_repeat("#", 80) . "\n";
echo "# PRIORITY 1: API Receipts (v2) - สำหรับข้อมูล Payment\n";
echo str_repeat("#", 80) . "\n";

// Test 1.1: Get all receipts
$result = callCegidAPI('receipts/v2');
displayResult('Receipts v2 - All', $result);

// Test 1.2: With date filter
$result = callCegidAPI('receipts/v2', ['date' => $testDate]);
displayResult('Receipts v2 - With Date', $result);

// Test 1.3: With date range
$result = callCegidAPI('receipts/v2', [
    'dateFrom' => $testDate,
    'dateTo' => $testDate
]);
displayResult('Receipts v2 - Date Range', $result);

// Test 1.4: With store filter
$result = callCegidAPI('receipts/v2', [
    'date' => $testDate,
    'storeCode' => '11010'
]);
displayResult('Receipts v2 - With Store', $result);

// Test 1.5: Try different parameter names
$paramVariations = [
    ['receiptDate' => $testDate],
    ['creationDate' => $testDate],
    ['startDate' => $testDate, 'endDate' => $testDate],
    ['fromDate' => $testDate, 'toDate' => $testDate],
];

foreach ($paramVariations as $i => $params) {
    $result = callCegidAPI('receipts/v2', $params);
    displayResult("Receipts v2 - Param Variation " . ($i + 1), $result);
}

// ============================================================================
// PRIORITY 2: Sales External Report2 (Beta)
// ============================================================================

echo "\n" . str_repeat("#", 80) . "\n";
echo "# PRIORITY 2: Sales External Report2 - สำหรับข้อมูล Transaction\n";
echo str_repeat("#", 80) . "\n";

// Test 2.1: Get report
$result = callCegidAPI('sales-external-report2');
displayResult('Sales External Report2 - All', $result);

// Test 2.2: With date
$result = callCegidAPI('sales-external-report2', ['date' => $testDate]);
displayResult('Sales External Report2 - With Date', $result);

// Test 2.3: Try different paths
$reportPaths = [
    'sales-external-report2/v1',
    'sales/external-report2',
    'reports/sales-external2',
];

foreach ($reportPaths as $path) {
    $result = callCegidAPI($path, ['date' => $testDate]);
    displayResult("Sales Report - Path: {$path}", $result);
}

// ============================================================================
// PRIORITY 3: Sales External (v1)
// ============================================================================

echo "\n" . str_repeat("#", 80) . "\n";
echo "# PRIORITY 3: Sales External (v1)\n";
echo str_repeat("#", 80) . "\n";

$result = callCegidAPI('sales-external/v1', ['date' => $testDate]);
displayResult('Sales External v1', $result);

// ============================================================================
// ALTERNATIVE: Try other variations
// ============================================================================

echo "\n" . str_repeat("#", 80) . "\n";
echo "# ALTERNATIVE ENDPOINTS\n";
echo str_repeat("#", 80) . "\n";

$alternatives = [
    'receipts/v1',
    'sales/receipts',
    'sales/transactions',
    'sales/daily',
    'pos-sessions',
    'sales-external',
    'sales-external-report',
];

foreach ($alternatives as $endpoint) {
    $result = callCegidAPI($endpoint, ['date' => $testDate]);
    displayResult("Alternative: {$endpoint}", $result);
}

// ============================================================================
// SUMMARY
// ============================================================================

echo "\n" . str_repeat("=", 80) . "\n";
echo "TESTING COMPLETED\n";
echo str_repeat("=", 80) . "\n";
echo "\nNext Steps:\n";
echo "1. ดู endpoint ไหนที่ return HTTP 200 และมีข้อมูล\n";
echo "2. ตรวจสอบ structure ของข้อมูลว่าตรงกับที่ต้องการหรือไม่\n";
echo "3. ถ้าไม่เจอ endpoint ที่ต้องการ:\n";
echo "   - ลองเข้า Swagger UI: {$CEGID_CONFIG['base_url']}/swagger\n";
echo "   - หรือใช้ SOAP API แทน\n";
echo "\n";
?>
