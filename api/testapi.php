<?php
/**
 * TEST - Customer IDs without THA
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Bangkok');

$CEGID_CONFIG = [
    'sales_wsdl'     => 'http://90643827-retail-ondemand.cegid.cloud/Y2/SalesExternalEngineService.svc?wsdl',
    'search_wsdl'    => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ProductSearchService.svc?wsdl',
    'username'       => '90643827_001_PROD\\frt',
    'password'       => 'adgjm',
    'database_id'    => '90643827_001_PROD',
];

// 🔥 Customer IDs ที่น่าจะไม่มี THA
$CUSTOMERS = [
    'WI0000TH',   // เดิม (มี THA)
    'WI0000',     // ลองไม่มี TH
    'WALKIN',     
    'WALK-IN',    
    'GUEST',      
    'CASH',       
    'POS',        
    'ONLINE',     
    'ECOM',       
    'WEB',        
];

$TEST_CUSTOMER = isset($_GET['c']) ? $_GET['c'] : 'WI0000TH';

$STORE_ID     = '77000';
$REGISTER_ID  = '77001';
$WAREHOUSE_ID = '10000';

$TEST_BARCODE = '9000200906646';
$TEST_QTY     = 1;
$TEST_PRICE   = 550;

echo "<h1>🛒 Test Customer ID</h1>";

// ปุ่มเลือก
echo "<div style='margin:15px 0; display:flex; flex-wrap:wrap; gap:5px;'>";
foreach ($CUSTOMERS as $c) {
    $bg = ($c == $TEST_CUSTOMER) ? '#22c55e' : '#e5e7eb';
    $color = ($c == $TEST_CUSTOMER) ? 'white' : 'black';
    echo "<a href='?c=$c' style='padding:8px 12px; background:$bg; color:$color; text-decoration:none; border-radius:5px;'>$c</a>";
}
echo "</div>";

// Custom input
echo "<form method='get' style='margin:10px 0;'>";
echo "<input type='text' name='c' placeholder='ใส่ Customer ID อื่น' style='padding:8px; width:200px;'>";
echo "<button type='submit' style='padding:8px 15px; background:#3b82f6; color:white; border:none; border-radius:5px; cursor:pointer;'>ทดสอบ</button>";
echo "</form>";

echo "<div style='background:#e0f2fe; padding:15px; border-radius:8px;'>";
echo "Store: $STORE_ID | Register: $REGISTER_ID | <strong>Customer: $TEST_CUSTOMER</strong>";
echo "</div><hr>";

try {
    $client_search = new SoapClient($CEGID_CONFIG['search_wsdl'], [
        'login' => $CEGID_CONFIG['username'],
        'password' => $CEGID_CONFIG['password']
    ]);
    
    $searchRes = $client_search->GetListDetail([
        'Request' => ['Barcodes' => [$TEST_BARCODE]],
        'Context' => ['DatabaseId' => $CEGID_CONFIG['database_id']]
    ]);
    
    $item = $searchRes->GetListDetailResult->Items->Item ?? null;
    if (!$item) die("❌ ไม่พบสินค้า");
    
    $internalId = $item->Identifier->Id;
    $itemName   = $item->Description ?? 'Item';
    echo "✅ พบสินค้า: <strong>$itemName</strong><br><hr>";

    $request = [
        'Header' => [
            'StoreId'             => $STORE_ID,
            'RegisterId'          => $REGISTER_ID,
            'WarehouseId'         => $WAREHOUSE_ID,
            'SalespersonId'       => 'ADMIN',
            'DocumentDate'        => date('Y-m-d'),
            'CreatedDateTime'     => date('Y-m-d\TH:i:s'),
            'DocumentNature'      => 'Receipt',
            'TaxExcluded'         => false,
            'CurrencyCode'        => 'THB',
            'CanGenerateDocument' => true,
            'ExternalReference'   => 'TEST-' . time()
        ],
        'Customer' => ['Id' => $TEST_CUSTOMER],
        'Lines' => [
            'Line' => [[
                'Product' => [
                    'ItemId'      => $internalId,
                    'WarehouseId' => $WAREHOUSE_ID,
                    'Quantity'    => $TEST_QTY,
                    'UnitPrice'   => $TEST_PRICE,
                    'NetAmount'   => $TEST_PRICE * $TEST_QTY,
                    'Description' => $itemName
                ]
            ]]
        ],
        'Payments' => [
            'Payment' => [[
                'PaymentMethodId' => '100',
                'Amount'          => $TEST_PRICE * $TEST_QTY,
                'CurrencyCode'    => 'THB'
            ]]
        ]
    ];
    
    $client_sales = new SoapClient($CEGID_CONFIG['sales_wsdl'], [
        'login' => $CEGID_CONFIG['username'],
        'password' => $CEGID_CONFIG['password'],
        'trace' => true
    ]);

    $res = $client_sales->SanityCheck([
        'Request' => $request,
        'Context' => ['DatabaseId' => $CEGID_CONFIG['database_id']]
    ]);
    $result = $res->SanityCheckResult;

    echo "<h3>📋 ผลลัพธ์ Customer '$TEST_CUSTOMER':</h3>";
    
    if ($result->Result === 'Success' || $result->Result === 'Warning') {
        echo "<div style='background:#dcfce7; padding:20px; border:2px solid #22c55e; border-radius:10px;'>";
        echo "<h1>✅ SUCCESS!</h1>";
        echo "<h2>Customer ID ที่ใช้ได้: <code>$TEST_CUSTOMER</code></h2>";
        echo "</div>";
    } else {
        echo "<div style='background:#fee2e2; padding:20px; border:2px solid #ef4444; border-radius:10px;'>";
        echo "<h1>❌ FAILED</h1>";
        if (isset($result->Messages->Message)) {
            $msgs = is_array($result->Messages->Message) ? $result->Messages->Message : [$result->Messages->Message];
            foreach ($msgs as $msg) {
                $code = $msg->Code ?? '';
                $summary = $msg->Summary ?? $msg->Details ?? '';
                echo "<p><strong>$code</strong> - $summary</p>";
                
                // แนะนำ
                if (strpos($summary, 'THA') !== false) {
                    echo "<p style='color:orange;'>⚠️ Customer นี้มี Country=THA ที่ไม่ได้ setup → ลอง Customer อื่น</p>";
                }
                if (strpos($summary, 'CustomerId') !== false || strpos($code, '0150') !== false) {
                    echo "<p style='color:orange;'>⚠️ Customer ID ไม่มีในระบบ → ลอง ID อื่น</p>";
                }
            }
        }
        echo "</div>";
    }
    
    echo "<details><summary>🔍 Full Response</summary><pre>" . print_r($result, true) . "</pre></details>";

} catch (Exception $e) {
    echo "<div style='background:#fee2e2; padding:20px;'><h2>❌</h2><p>" . $e->getMessage() . "</p></div>";
}
?>