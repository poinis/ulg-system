<?php
/**
 * Cegid Y2 - API Test
 */
require_once __DIR__ . '/config.php';

echo "<pre style='font-family:monospace;padding:20px;background:#1e1e1e;color:#0f0'>";
echo "=== Cegid API Test ===\n\n";
echo "URL: " . CEGID_BASE_URL . "\n";
echo "User: " . CEGID_FULL_USERNAME . "\n\n";

$api = new CegidSoapClient();

// Test 1: Sale Headers
echo "1. SaleDocumentService (GetHeaderList)...\n";
$result = $api->getSaleHeaders('2025-01-01', '2026-12-31', 1, 5);
echo "   HTTP: {$result['http_code']} - ";
if ($result['success'] && preg_match_all('/<Get_Header>/', $result['response'], $m)) {
    echo "✓ Found " . count($m[0]) . " documents\n";
    
    // Get first doc for GetByKey test
    $xml = cleanXml($result['response']);
    if (preg_match('/<Key>(.*?)<\/Key>/s', $xml, $keyMatch)) {
        $type = extractXmlValue($keyMatch[1], 'Type');
        $stump = extractXmlValue($keyMatch[1], 'Stump');
        $number = extractXmlValue($keyMatch[1], 'Number');
        
        echo "\n2. SaleDocumentService (GetByKey)...\n";
        $result2 = $api->getSaleDetail($type, $stump, $number);
        echo "   HTTP: {$result2['http_code']} - ";
        if ($result2['success'] && strpos($result2['response'], 'Fault') === false) {
            preg_match_all('/<Get_Line>/', $result2['response'], $lines);
            preg_match_all('/<Get_Payment>/', $result2['response'], $payments);
            echo "✓ Lines: " . count($lines[0]) . ", Payments: " . count($payments[0]) . "\n";
        } else echo "✗ Failed\n";
    }
} else echo "✗ Failed\n";

// Test 3: Categories
echo "\n3. ProductCategoriesService (GetValues - Brand)...\n";
$result = $api->getCategoryValues(1, 1, 10);
echo "   HTTP: {$result['http_code']} - ";
if ($result['success'] && preg_match('/<Count>(\d+)<\/Count>/', $result['response'], $m)) {
    echo "✓ Total brands: {$m[1]}\n";
} else echo "✗ Failed\n";

// Test 4: Customer Search
echo "\n4. CustomerWcfService (SearchCustomerIds)...\n";
$result = $api->searchCustomers(['MaxNumberOfCustomers' => 5]);
echo "   HTTP: {$result['http_code']} - ";
if ($result['success']) {
    $xml = cleanXml($result['response']);
    if (preg_match_all('/<string>([^<]+)<\/string>/', $xml, $m)) {
        echo "✓ Found " . count($m[1]) . " customers\n";
        
        // Test Loyalty
        if (!empty($m[1][0])) {
            echo "\n5. LoyaltyWcfService (GetCustomerAvailableLoyaltyPoints)...\n";
            $loyaltyResult = $api->getLoyaltyInfo($m[1][0]);
            echo "   HTTP: {$loyaltyResult['points_response']['http_code']} - ";
            if ($loyaltyResult['points_response']['success']) {
                if (preg_match('/<AvailablePoints>([^<]+)<\/AvailablePoints>/', $loyaltyResult['points_response']['response'], $pts)) {
                    echo "✓ Points: {$pts[1]}\n";
                } else echo "✓ No points\n";
            } else echo "✗ Failed\n";
            
            echo "\n6. LoyaltyWcfService (GetCustomerCards)...\n";
            echo "   HTTP: {$loyaltyResult['cards_response']['http_code']} - ";
            if ($loyaltyResult['cards_response']['success']) {
                if (preg_match('/<Card>/', $loyaltyResult['cards_response']['response'])) {
                    echo "✓ Has card\n";
                } else echo "✓ No cards\n";
            } else echo "✗ Failed\n";
        }
    } else echo "? No customers found\n";
} else echo "✗ Failed\n";

echo "\n=== Test Complete ===\n";
echo "</pre>";
echo "<hr><p><a href='sync_manager.php'>→ Go to Sync Manager</a></p>";
