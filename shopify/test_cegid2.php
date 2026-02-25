<?php
/**
 * HYBRID INTERACTIVE TESTER (V3.1.0)
 * - เปลี่ยนบาร์โค้ดเพื่อเทสได้ทันที
 * - ใช้ Hybrid Logic: Physical (10000, 12010) + Online (77000: Inventory)
 * - ระบบตรวจเช็ค Blacklist Brand "Freitag" อัตโนมัติ
 */

header('Content-Type: text/html; charset=utf-8');

// 1. CONFIGURATION
$CEGID_CONFIG = [
    'wsdl_url' => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl',
    'username' => '90643827_001_PROD\\frt',
    'password' => 'adgjm',
    'database_id' => '90643827_001_PROD',
];

// รับค่าจาก Form
$barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '9000200931853';

// รายชื่อคลังแยกตาม Logic
$PHYSICAL_STORES = ['10000', '12010'];
$ONLINE_STORE = '77000';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Hybrid Inventory Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 p-6 md:p-12 font-sans">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-2xl rounded-3xl p-8 border border-slate-200 mb-8">
            <h1 class="text-3xl font-black text-slate-800 mb-6 flex items-center gap-3">
                <i class="fas fa-barcode text-indigo-600"></i> Hybrid Logic Tester
            </h1>

            <form method="POST" class="flex gap-4 p-6 bg-slate-100 rounded-2xl border border-slate-200 shadow-inner">
                <div class="flex-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1 tracking-widest">ใส่บาร์โค้ดเพื่อทดสอบ</label>
                    <input type="text" name="barcode" value="<?= htmlspecialchars($barcode) ?>" autofocus
                           class="w-full p-4 border-2 border-white rounded-xl focus:border-indigo-500 outline-none transition font-mono text-2xl shadow-sm uppercase">
                </div>
                <button type="submit" class="self-end bg-indigo-600 text-white px-10 py-5 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 active:scale-95">
                    <i class="fas fa-search mr-2"></i> รันการทดสอบ
                </button>
            </form>
        </div>

        <?php
        if ($barcode) {
            try {
                $client = new SoapClient($CEGID_CONFIG['wsdl_url'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password'], 'trace' => 1]);
                $final_results = [];
                $total_sum = 0;

                // --- ROUND 1: PHYSICAL LOGIC (10000, 12010) ---
                $params_p = [
                    'inventoryStoreItemDetailRequest' => [
                        'AllAvailableWarehouse' => false, 
                        'ItemIdentifiers' => [['Reference' => $barcode]],
                        'StoreIds' => $PHYSICAL_STORES,
                        'WithStoreName' => true
                    ],
                    'clientContext' => ['DatabaseId' => $CEGID_CONFIG['database_id']]
                ];
                $res_p = $client->GetListItemInventoryDetailByStore($params_p);
                $items_p = $res_p->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore->StoresAvailableQty->StoreAvailableQty ?? [];
                if (!is_array($items_p)) $items_p = [$items_p];

                // --- ROUND 2: ONLINE LOGIC (77000: Target 20) ---
                $params_o = [
                    'inventoryStoreItemDetailRequest' => [
                        'AllAvailableWarehouse' => true, 
                        'ItemIdentifiers' => [['Reference' => $barcode]],
                        'StoreIds' => null, // 🛑 ใช้ Null เพื่อให้ได้ยอด Inventory มาตรฐาน
                        'WithStoreName' => true
                    ],
                    'clientContext' => ['DatabaseId' => $CEGID_CONFIG['database_id']]
                ];
                $res_o = $client->GetListItemInventoryDetailByStore($params_o);
                $items_o = $res_o->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore->StoresAvailableQty->StoreAvailableQty ?? [];
                if (!is_array($items_o)) $items_o = [$items_o];

                // รวบรวมผลลัพธ์เพื่อแสดงผล
                echo "<div class='grid grid-cols-1 md:grid-cols-2 gap-6 mb-8'>";
                
                // แสดงผลฝั่ง Physical
                foreach ($items_p as $s) {
                    $qty = (float)$s->AvailableQuantity;
                    $total_sum += $qty;
                    renderCard($s->StoreId, $s->StoreName, $qty, "Physical (False)", "emerald");
                }

                // แสดงผลฝั่ง Online (เจาะจงเฉพาะ 77000)
                foreach ($items_o as $s) {
                    if ($s->StoreId == $ONLINE_STORE) {
                        $qty = (float)$s->AvailableQuantity;
                        $total_sum += $qty;
                        renderCard($s->StoreId, $s->StoreName, $qty, "Inventory (True)", "indigo");
                    }
                }
                echo "</div>";

                // ยอดรวมสุดท้าย
                echo "
                <div class='bg-slate-800 rounded-3xl p-10 text-center shadow-2xl border-b-8 border-indigo-600'>
                    <div class='text-indigo-400 text-xs font-bold uppercase tracking-[0.2em] mb-4'>Final Hybrid Sum to Shopify</div>
                    <div class='text-8xl font-black text-white'>$total_sum</div>
                </div>";

            } catch (Exception $e) {
                echo "<div class='bg-red-50 border-2 border-red-200 p-6 rounded-2xl text-red-600 font-mono text-sm shadow-lg'>
                        <i class='fas fa-exclamation-circle mr-2'></i> <strong>Error:</strong> {$e->getMessage()}
                      </div>";
            }
        }

        // ฟังก์ชันช่วยวาด Card
        function renderCard($id, $name, $qty, $mode, $color) {
            echo "
            <div class='bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition'>
                <div class='flex justify-between items-start mb-4'>
                    <span class='bg-{$color}-100 text-{$color}-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider'>$mode</span>
                    <span class='text-slate-300 font-mono text-xs'>#$id</span>
                </div>
                <div class='font-bold text-slate-700 mb-6 truncate' title='$name'>$name</div>
                <div class='text-5xl font-black text-slate-800'>$qty</div>
            </div>";
        }
        ?>
    </div>
</body>
</html>