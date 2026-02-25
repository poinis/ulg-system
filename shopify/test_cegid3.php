<?php
/**
 * FIELD EXPLORER TEST: ดูว่า Cegid ส่งเลขช่องไหนมาให้เราบ้าง
 * สำหรับบาร์โค้ด 9000200931853
 */

header('Content-Type: text/plain; charset=utf-8');

$wsdl_url = 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl';
$username = '90643827_001_PROD\\frt';
$password = 'adgjm';
$database_id = '90643827_001_PROD';

$barcode_to_test = '9000201023878'; 

echo "--- CEGID FIELD EXPLORER START ---" . PHP_EOL;
echo "Testing Barcode: $barcode_to_test" . PHP_EOL . PHP_EOL;

try {
    $client = new SoapClient($wsdl_url, [
        'login' => $username, 'password' => $password,
        'trace' => 1, 'exceptions' => 1
    ]);

    // ใช้ Method Discovery เพื่อดูโครงสร้างทั้งหมด
    $params = [
        'inventoryStoreItemDetailRequest' => [

            'DetailSku' => true,
            'ItemIdentifiers' => [['Reference' => $barcode_to_test]],
            'OnlyAvailableStock' => false, 
            'StoreIds' => null,
            'WithStoreName' => true
        ],
        'clientContext' => ['DatabaseId' => $database_id]
    ];

    $response = $client->GetListItemInventoryDetailByStore($params);
    
    // 🛑 สั่ง Debug ดูโครงสร้างข้อมูลดิบที่ Cegid ส่งกลับมา
    echo "=== RAW DATA STRUCTURE FROM CEGID ===" . PHP_EOL;
    print_r($response->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore);

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
}