<?php
/**
 * BARCODE CHECKER V2: ตรวจสอบยอดสต็อก 3 คลัง
 * สำหรับบาร์โค้ด: 9000201012698
 * คลังที่ตรวจสอบ: 10000, 12010, 77010
 */

header('Content-Type: text/html; charset=utf-8');

// 1. CONFIGURATION
$wsdl_url = 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl';
$username = '90643827_001_PROD\\frt';
$password = 'adgjm';
$database_id = '90643827_001_PROD';

$barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '9000201012698';
$target_warehouses = ['10000', '12010', '77010']; // คลังตามไฟล์ pronto.php ล่าสุด

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <title>Stock Checker - PRONTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 p-10">
    <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-2xl p-8 border border-slate-200">
        <h1 class="text-3xl font-black text-slate-800 mb-8 flex items-center gap-3 italic uppercase">
            <i class="fas fa-search-location text-indigo-600"></i> Stock Deep Checker
        </h1>

        <form method="POST" class="bg-slate-50 p-6 rounded-2xl flex gap-4 mb-10 border-2 border-dashed border-slate-200">
            <div class="flex-1">
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">สแกนหรือกรอกบาร์โค้ด</label>
                <input type="text" name="barcode" value="<?= htmlspecialchars($barcode) ?>" autofocus
                       class="w-full p-4 border-2 border-white rounded-xl focus:border-indigo-500 outline-none transition font-mono text-xl shadow-inner uppercase">
            </div>
            <button type="submit" class="self-end bg-indigo-600 text-white px-10 py-4 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg active:scale-95">
                ตรวจสอบยอด
            </button>
        </form>

        <?php
        try {
            $client = new SoapClient($wsdl_url, ['login' => $username, 'password' => $password, 'trace' => 1]);

            // --- ฟังก์ชันดึงยอดแยกตามโหมด ---
            function getStock($client, $barcode, $dbId, $stores, $isInventory) {
                $params = [
                    'inventoryStoreItemDetailRequest' => [
                        'AllAvailableWarehouse' => $isInventory,
                        'ItemIdentifiers' => [['Reference' => $barcode]],
                        'StoreIds' => $stores,
                        'WithStoreName' => true
                    ],
                    'clientContext' => ['DatabaseId' => $dbId]
                ];
                $res = $client->GetListItemInventoryDetailByStore($params);
                $data = $res->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore->StoresAvailableQty->StoreAvailableQty ?? [];
                if (!is_array($data)) $data = [$data];
                return $data;
            }

            // ดึงข้อมูล 2 แบบเพื่อเปรียบเทียบ
            $physical_data = getStock($client, $barcode, $database_id, $target_warehouses, false);
            $inventory_data = getStock($client, $barcode, $database_id, $target_warehouses, true);

            echo "<div class='space-y-6'>";
            
            foreach ($target_warehouses as $id) {
                $p_qty = 0; $i_qty = 0; $name = "Unknown Store";
                
                foreach ($physical_data as $s) { if ($s->StoreId == $id) { $p_qty = (float)$s->AvailableQuantity; $name = $s->StoreName; } }
                foreach ($inventory_data as $s) { if ($s->StoreId == $id) { $i_qty = (float)$s->AvailableQuantity; } }

                echo "
                <div class='border-2 border-slate-100 rounded-2xl p-6 hover:border-indigo-100 transition'>
                    <div class='flex justify-between items-center mb-4'>
                        <div>
                            <span class='text-[10px] font-black text-slate-400 uppercase'>Store ID: $id</span>
                            <div class='text-xl font-black text-slate-800 uppercase tracking-tighter'>$name</div>
                        </div>
                    </div>
                    <div class='grid grid-cols-2 gap-4'>
                        <div class='bg-slate-50 p-4 rounded-xl text-center'>
                            <p class='text-[10px] font-bold text-slate-400 uppercase mb-1'>Physical (พร้อมขาย)</p>
                            <p class='text-3xl font-black text-slate-400'>$p_qty</p>
                            <p class='text-[9px] mt-1 italic'>(นี่คือค่าที่ V3.1.2 เห็น)</p>
                        </div>
                        <div class='bg-indigo-50 p-4 rounded-xl text-center border-2 border-indigo-100'>
                            <p class='text-[10px] font-bold text-indigo-400 uppercase mb-1'>Inventory (ยอดรวม)</p>
                            <p class='text-3xl font-black text-indigo-600'>$i_qty</p>
                            <p class='text-[9px] mt-1 italic'>(รวมยอดที่กำลังโอน/รับเข้า)</p>
                        </div>
                    </div>";

                if ($i_qty > $p_qty) {
                    echo "<div class='mt-4 p-3 bg-amber-50 text-amber-700 text-[11px] font-bold rounded-lg border border-amber-100 flex items-center gap-2'>
                            <i class='fas fa-info-circle'></i> ตรวจพบสินค้า " . ($i_qty - $p_qty) . " ชิ้น ติดสถานะ Transfer หรือ Reserved อยู่
                          </div>";
                }
                echo "</div>";
            }
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='p-6 bg-red-50 text-red-600 rounded-2xl font-mono text-sm'>Error: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
</body>
</html>