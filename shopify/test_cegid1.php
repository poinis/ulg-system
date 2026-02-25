<?php
/**
 * PHYSICAL DISCOVERY TEST: กวาดทุกคลังแบบ AllAvailableWarehouse = false
 * เพื่อหายอด Physical Stock จริง ๆ ของบาร์โค้ด 9000200931853
 */

header('Content-Type: text/plain; charset=utf-8');

$wsdl_url = 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl';
$username = '90643827_001_PROD\\frt';
$password = 'adgjm';
$database_id = '90643827_001_PROD';

$barcode_to_test = '9000200931853'; 

// ✅ Blacklist: เพิ่มสินค้า Freitag และบาร์โค้ดพิเศษตามสั่ง
$BLACKLIST = [
    '2990000006684', 
    '9000000439252', // Freitag
    '2990000001207'  // Freitag
];

echo "--- CEGID PHYSICAL DISCOVERY MODE ---" . PHP_EOL;
echo "Searching for Barcode: $barcode_to_test" . PHP_EOL;
echo "Mode: Physical Stock Only (AllAvailableWarehouse = false)" . PHP_EOL . PHP_EOL;

if (in_array($barcode_to_test, $BLACKLIST)) {
    die("🚫 บาร์โค้ดนี้อยู่ใน Blacklist ระบบจะไม่ประมวลผลครับ");
}

try {
    $client = new SoapClient($wsdl_url, [
        'login' => $username, 'password' => $password,
        'trace' => 1, 'exceptions' => 1
    ]);

    $params = [
        'inventoryStoreItemDetailRequest' => [
            'AllAvailableWarehouse' => false, // 🛑 บังคับเป็น false เพื่อดูยอดสต็อกจริงหน้าร้าน
            'DetailSku' => false,
            'ItemIdentifiers' => [['Reference' => $barcode_to_test, 'Id' => null]],
            'OnlyAvailableStock' => true,     // กรองเอาเฉพาะที่มีของ
            'StoreIds' => null,               // ไม่ระบุสาขา เพื่อให้กวาดหาทุกที่
            'WithStoreName' => true
        ],
        'clientContext' => ['DatabaseId' => $database_id]
    ];

    $response = $client->GetListItemInventoryDetailByStore($params);
    $details = $response->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore ?? null;

    if (!$details) {
        echo "❌ ไม่พบ Physical Stock ของบาร์โค้ดนี้ในระบบครับ" . PHP_EOL;
    } else {
        $stores = $details->StoresAvailableQty->StoreAvailableQty ?? [];
        if (!is_array($stores)) $stores = [$stores];

        echo "=== PHYSICAL STOCK LOCATIONS FOUND ===" . PHP_EOL;
        foreach ($stores as $s) {
            echo "✅ Found ID: [" . $s->StoreId . "] Name: " . $s->StoreName;
            echo " | Physical Qty: " . (float)($s->AvailableQuantity ?? 0) . PHP_EOL;
        }
    }

} catch (Exception $e) {
    echo "❌ SOAP ERROR: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--- END OF TEST ---";