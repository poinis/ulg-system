<?php
/**
 * Transaction Data Endpoint Tester
 * หา endpoint ที่ส่ง transaction/lines data มา
 */

$CEGID_CONFIG = [
    'base_url' => 'https://90643827-retail-ondemand.cegid.cloud/Y2',
    'username' => '90643827_001_PROD\\frt',
    'password' => 'adgjm',
    'folder_id' => '90643827_001_PROD',
];

$testDate = '2026-02-09';

function callAPI($endpoint, $params = []) {
    global $CEGID_CONFIG;
    
    $url = "{$CEGID_CONFIG['base_url']}/{$CEGID_CONFIG['folder_id']}/api/{$endpoint}";
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$CEGID_CONFIG['username']}:{$CEGID_CONFIG['password']}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'url' => $url,
        'code' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => json_decode($response, true),
        'raw' => $response
    ];
}

echo str_repeat("=", 80) . "\n";
echo "Transaction Data Endpoint Tester\n";
echo str_repeat("=", 80) . "\n\n";

// ============================================================================
// Test 1: receipts/v2 with different parameters
// ============================================================================
echo "Test 1: receipts/v2 + Parameters\n";
echo str_repeat("-", 80) . "\n";

$receiptParams = [
    ['startDate' => $testDate, 'endDate' => $testDate],
    ['startDate' => $testDate, 'endDate' => $testDate, 'includeLines' => 'true'],
    ['startDate' => $testDate, 'endDate' => $testDate, 'includeLines' => true],
    ['startDate' => $testDate, 'endDate' => $testDate, 'withLines' => 'true'],
    ['startDate' => $testDate, 'endDate' => $testDate, 'withDetails' => 'true'],
    ['startDate' => $testDate, 'endDate' => $testDate, 'expand' => 'lines'],
    ['startDate' => $testDate, 'endDate' => $testDate, '$expand' => 'lines'],
];

foreach ($receiptParams as $i => $params) {
    echo "\n" . ($i + 1) . ". Params: " . json_encode($params) . "\n";
    $result = callAPI('receipts/v2', $params);
    echo "   HTTP {$result['code']} | ";
    
    if ($result['success'] && !empty($result['data'])) {
        $first = is_array($result['data']) ? reset($result['data']) : $result['data'];
        $hasLines = isset($first['lines']) && !empty($first['lines']);
        
        if ($hasLines) {
            echo "✅ มี LINES! จำนวน: " . count($first['lines']) . "\n";
            echo "   Line ตัวอย่าง: " . json_encode($first['lines'][0]) . "\n";
        } else {
            echo "❌ ไม่มี lines | Structure: " . implode(", ", array_keys($first)) . "\n";
        }
    } else {
        echo "❌ Failed\n";
    }
}

// ============================================================================
// Test 2: Receipt Detail Endpoints (per receipt)
// ============================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "Test 2: Receipt Detail Endpoints\n";
echo str_repeat("-", 80) . "\n";

$receiptId = '00T1000000257'; // จาก diagnostic

$detailEndpoints = [
    "receipts/v2/{$receiptId}",
    "receipts/v2/{$receiptId}/lines",
    "receipts/v2/{$receiptId}/details",
    "receipts/{$receiptId}",
    "receipts/{$receiptId}/lines",
];

foreach ($detailEndpoints as $endpoint) {
    echo "\n" . $endpoint . "\n";
    $result = callAPI($endpoint);
    echo "   HTTP {$result['code']} | ";
    
    if ($result['success'] && !empty($result['data'])) {
        if (isset($result['data']['lines'])) {
            echo "✅ มี LINES! จำนวน: " . count($result['data']['lines']) . "\n";
        } else {
            $keys = is_array($result['data']) ? array_keys($result['data']) : ['scalar'];
            echo "Structure: " . implode(", ", array_slice($keys, 0, 5)) . "\n";
        }
    } else {
        echo "❌ Failed\n";
    }
}

// ============================================================================
// Test 3: Sales/Transaction Specific Endpoints
// ============================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "Test 3: Sales/Transaction Endpoints\n";
echo str_repeat("-", 80) . "\n";

$transactionEndpoints = [
    ['endpoint' => 'sales-external/v1', 'params' => ['startDate' => $testDate, 'endDate' => $testDate]],
    ['endpoint' => 'sales-external-report/v1', 'params' => ['startDate' => $testDate, 'endDate' => $testDate]],
    ['endpoint' => 'sales-external-report2', 'params' => ['startDate' => $testDate, 'endDate' => $testDate]],
    ['endpoint' => 'sales/transactions', 'params' => ['date' => $testDate]],
    ['endpoint' => 'transactions/v1', 'params' => ['date' => $testDate]],
    ['endpoint' => 'pos-sessions', 'params' => ['date' => $testDate]],
];

foreach ($transactionEndpoints as $config) {
    echo "\n" . $config['endpoint'] . "\n";
    $result = callAPI($config['endpoint'], $config['params']);
    echo "   HTTP {$result['code']} | ";
    
    if ($result['success'] && !empty($result['data'])) {
        echo "✅ Success | ";
        
        $first = is_array($result['data']) ? reset($result['data']) : $result['data'];
        if (is_array($first)) {
            $keys = array_keys($first);
            echo "Fields: " . implode(", ", array_slice($keys, 0, 8)) . "\n";
            
            // Check for product/item data
            $hasProduct = isset($first['product']) || isset($first['item']) || isset($first['productId']);
            if ($hasProduct) {
                echo "   ⭐ มี Product data!\n";
            }
        }
    } else {
        echo "❌ Failed\n";
    }
}

// ============================================================================
// Test 4: Alternative paths
// ============================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "Test 4: Alternative Endpoints\n";
echo str_repeat("-", 80) . "\n";

$alternatives = [
    'receipts/lines',
    'sales/lines',
    'sale-lines/v1',
    'receipt-lines/v1',
    'documents/sales',
    'documents/receipts',
];

foreach ($alternatives as $endpoint) {
    $result = callAPI($endpoint, ['startDate' => $testDate, 'endDate' => $testDate]);
    echo "\n" . $endpoint . " → HTTP {$result['code']}";
    
    if ($result['success'] && !empty($result['data'])) {
        echo " ✅\n";
    } else {
        echo " ❌\n";
    }
}

// ============================================================================
// Summary
// ============================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

echo "หาก endpoint ไหนมี lines หรือ product data:\n";
echo "1. บันทึก endpoint ที่ใช้งานได้\n";
echo "2. บันทึก parameter ที่ต้องส่ง\n";
echo "3. ส่งผลลัพธ์มาให้อัพเดท code\n\n";

echo "หากไม่มี endpoint ไหนมี transaction data:\n";
echo "→ ต้องใช้ SOAP API แทน (มี method สำหรับ transaction)\n\n";

echo "=================================================================\n";
?>