<?php
/**
 * CEGID SYNC V2.0 - AUTO RESUME
 * - Feature: ตรวจสอบรายการที่ทำเสร็จแล้ว (item_name ไม่ว่าง) แล้วข้ามไปทำต่อทันที
 * - Logic: Start Offset = จำนวนรายการที่มีชื่อแล้ว
 * - Optimization: เพิ่ม ORDER BY id เพื่อให้ลำดับการข้ามแม่นยำ 100%
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// ================= CONFIGURATION =================
$MYSQL_CONFIG = [
    'host' => 'localhost', 'user' => 'cmbase', 'pass' => '#wmIYH3wazaa', 'db' => 'cmbase', 'port' => 3306
];
$CEGID_CONFIG = [
    'inventory_wsdl' => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl',
    'search_wsdl'    => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ProductSearchService.svc?wsdl',
    'username'       => '90643827_001_PROD\\frt', 'password' => 'adgjm', 'database_id' => '90643827_001_PROD',
];

try {
    $pdo = new PDO("mysql:host={$MYSQL_CONFIG['host']};dbname={$MYSQL_CONFIG['db']};port={$MYSQL_CONFIG['port']};charset=utf8mb4", $MYSQL_CONFIG['user'], $MYSQL_CONFIG['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { die(json_encode(['error' => "MySQL Error: " . $e->getMessage()])); }

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];
    try {
        // 🔄 เช็คจำนวนงานทั้งหมด + งานที่เสร็จแล้ว
        if ($action === 'check_progress') {
            $total = $pdo->query("SELECT COUNT(*) FROM stock_today WHERE Barcode IS NOT NULL AND Barcode != ''")->fetchColumn();
            $done = $pdo->query("SELECT COUNT(*) FROM stock_today WHERE Barcode IS NOT NULL AND Barcode != '' AND item_name IS NOT NULL")->fetchColumn();
            echo json_encode(['success' => true, 'total' => (int)$total, 'done' => (int)$done]); exit;
        }

        if ($action === 'sync_batch') {
            $offset = (int)$_POST['offset'];
            $limit = 10;
            $sync_names = ($_POST['sync_names'] === 'true');

            // 🎯 เพิ่ม ORDER BY id เพื่อให้มั่นใจว่าการข้าม (Offset) จะตรงกับลำดับที่ทำไปแล้ว
            $stmt = $pdo->prepare("SELECT id, Store, Barcode FROM stock_today WHERE Barcode IS NOT NULL AND Barcode != '' ORDER BY id ASC LIMIT $limit OFFSET $offset");
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) { echo json_encode(['done' => true]); exit; }

            $client_inv = new SoapClient($CEGID_CONFIG['inventory_wsdl'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password'], 'connection_timeout' => 30]);
            $client_search = $sync_names ? new SoapClient($CEGID_CONFIG['search_wsdl'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password']]) : null;

            $updated_count = 0;
            foreach ($items as $item) {
                $bc = trim($item['Barcode']);
                $store = trim($item['Store']);
                
                try {
                    $invRes = $client_inv->GetListItemInventoryDetailByStore([
                        'inventoryStoreItemDetailRequest' => [
                            'AllAvailableWarehouse' => false,
                            'ItemIdentifiers' => [['Reference' => $bc]],
                            'StoreIds' => [$store],
                            'WithStoreName' => false
                        ],
                        'clientContext' => ['DatabaseId' => $CEGID_CONFIG['database_id']]
                    ]);

                    $qty = 0;
                    $storeData = $invRes->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore->StoresAvailableQty->StoreAvailableQty ?? [];
                    if (!is_array($storeData)) $storeData = [$storeData];
                    foreach ($storeData as $s) { if ($s->StoreId == $store) $qty = (int)$s->AvailableQuantity; }

                    // ถ้า Cegid ตอบกลับมา ให้ Update (แม้จะเป็น 0 ก็ตาม)
                    $rawRes = $invRes->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore ?? null;
                    if ($rawRes) {
                        $name = null;
                        if ($sync_names) {
                            try {
                                $sRes = $client_search->GetListDetail(['Request' => ['Barcodes' => [$bc]], 'Context' => ['DatabaseId' => $CEGID_CONFIG['database_id']]]);
                                $name = $sRes->GetListDetailResult->Items->Item->Description ?? null;
                            } catch (Exception $e) {}
                        }

                        // ถ้าหาชื่อไม่เจอจริงๆ ให้ใส่เป็น "-" ไว้ก่อน เพื่อให้รู้ว่า "เช็คแล้วนะ" (รอบหน้าจะได้ข้ามได้)
                        // แต่ถ้า User ไม่ได้ติ๊ก Sync Name ก็ปล่อย NULL ไว้ตามเดิม
                        $update_name_val = $name;
                        if ($sync_names && $name === null) $update_name_val = "Not Found"; 

                        $sql = "UPDATE stock_today SET Physical = ?";
                        $params = [$qty];
                        if ($sync_names) { 
                            $sql .= ", item_name = ?"; 
                            $params[] = $update_name_val; 
                        }
                        $sql .= " WHERE id = ?"; 
                        $params[] = $item['id'];
                        
                        $pdo->prepare($sql)->execute($params);
                        $updated_count++;
                    }
                } catch (Exception $e) { continue; }
            }

            echo json_encode(['done' => false, 'processed' => $offset + count($items), 'updated' => $updated_count]);
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => "SERVER ERROR: " . $e->getMessage()]); exit; }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>Sync V2.0 Resume</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>.progress-bar { transition: width 0.3s ease; }</style>
</head>
<body class="bg-slate-50 p-8 font-sans h-screen flex items-center justify-center">
    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-2xl p-10 border-t-8 border-purple-500">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-800 italic uppercase tracking-tighter">Sync V2.0 Resume</h1>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1">Auto-Skip Completed Items</p>
            </div>
            <span id="status-badge" class="bg-slate-100 text-slate-500 px-4 py-1 rounded-full text-xs font-black uppercase">Ready</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <label class="bg-purple-50 p-6 rounded-2xl border-2 border-purple-100 flex items-center gap-4 cursor-pointer hover:bg-purple-100 transition">
                <input type="checkbox" id="sync_names" class="w-6 h-6 rounded text-purple-600 focus:ring-purple-500" checked>
                <span class="font-black text-purple-900 text-sm uppercase">Update Names</span>
            </label>
            <button onclick="checkAndStart()" id="btn-sync" class="w-full bg-purple-600 text-white py-5 rounded-2xl font-black text-xl hover:bg-purple-700 shadow-xl active:scale-95 transition-all">
                <i class="fas fa-forward mr-2"></i> CHECK & RESUME
            </button>
        </div>

        <div id="progress-box" class="hidden space-y-4">
            <div class="flex justify-between text-xs font-black text-slate-400 uppercase tracking-widest">
                <span>Progress</span>
                <span id="percent-text" class="text-purple-600">0%</span>
            </div>
            <div class="h-4 bg-slate-100 rounded-full overflow-hidden shadow-inner flex">
                <div id="skipped-bar" class="h-full bg-slate-300 progress-bar" style="width:0%"></div>
                <div id="progress-bar" class="h-full bg-purple-500 progress-bar" style="width:0%"></div>
            </div>
            <div class="flex justify-between items-center text-[10px] font-bold text-slate-400 font-mono uppercase">
                <span id="count-text">Calculated...</span>
                <span id="updated-text" class="text-green-500">Active...</span>
            </div>
            <div class="bg-slate-900 rounded-xl p-4 font-mono text-xs text-green-400 shadow-inner overflow-hidden relative">
                <div class="absolute top-2 right-2 text-[10px] text-slate-500 font-bold uppercase tracking-widest">Live Console</div>
                <div id="console-feed" class="h-24 overflow-y-auto space-y-1"><span class="text-slate-500 italic">Checking database...</span></div>
            </div>
        </div>
    </div>

    <script>
    let totalItems = 0;
    
    async function checkAndStart() {
        const btn = document.getElementById('btn-sync');
        const consoleFeed = document.getElementById('console-feed');
        btn.disabled = true; btn.classList.add('opacity-50');
        document.getElementById('progress-box').classList.remove('hidden');

        // 1. เช็คว่าทำไปถึงไหนแล้ว
        const resCheck = await fetch('?ajax=check_progress');
        const dataCheck = await resCheck.json();
        totalItems = dataCheck.total;
        let doneItems = dataCheck.done;

        // คำนวณ % ที่เสร็จแล้ว
        const skippedPercent = Math.round((doneItems / totalItems) * 100);
        document.getElementById('skipped-bar').style.width = skippedPercent + '%';
        document.getElementById('count-text').innerText = `Skipping first ${doneItems.toLocaleString()} items...`;
        
        const log = document.createElement('div');
        log.innerHTML = `<span class="text-purple-400">[Resume]</span> Found ${doneItems.toLocaleString()} completed items. Jumping to offset...`;
        consoleFeed.insertBefore(log, consoleFeed.firstChild);

        if(!confirm(`พบรายการที่เสร็จแล้ว ${doneItems.toLocaleString()} รายการ\nต้องการ "ข้าม" ไปทำต่อเลยไหม?`)) {
            btn.disabled = false; btn.classList.remove('opacity-50');
            return;
        }

        startSync(doneItems); // เริ่ม Offset ที่จำนวนที่เสร็จแล้ว
    }

    async function startSync(startOffset) {
        let offset = startOffset;
        const syncNames = document.getElementById('sync_names').checked;
        const consoleFeed = document.getElementById('console-feed');
        const statusBadge = document.getElementById('status-badge');

        statusBadge.innerText = "Running..."; statusBadge.className = "bg-blue-100 text-blue-600 px-4 py-1 rounded-full text-xs font-black uppercase";

        try {
            while(true) {
                let fd = new FormData();
                fd.append('offset', offset);
                fd.append('sync_names', syncNames);
                
                const res = await fetch('?ajax=sync_batch', {method: 'POST', body: fd});
                const data = await res.json();

                if (data.error) throw new Error(data.error);
                if (data.done) break;

                // Update UI
                offset = data.processed;
                
                // คำนวณ Progress (Skipped + Current)
                const totalPercent = Math.min(100, Math.round((offset / totalItems) * 100));
                // Adjust bars: Skipped bar stays static, Progress bar grows
                const skippedPercent = parseFloat(document.getElementById('skipped-bar').style.width);
                document.getElementById('progress-bar').style.width = (totalPercent - skippedPercent) + '%';
                
                document.getElementById('percent-text').innerText = totalPercent + '%';
                document.getElementById('count-text').innerText = `${offset.toLocaleString()} / ${totalItems.toLocaleString()}`;

                const log = document.createElement('div');
                log.innerHTML = `<span class="text-slate-500">[Batch]</span> Offset ${offset} | Updated: <span class="text-white">${data.updated}</span>`;
                consoleFeed.insertBefore(log, consoleFeed.firstChild);
            }

            statusBadge.innerText = "Completed"; statusBadge.className = "bg-emerald-100 text-emerald-600 px-4 py-1 rounded-full text-xs font-black uppercase";
            alert('ซิงค์เสร็จสิ้น 100% แล้วครับ!');

        } catch (e) {
            alert('Error: ' + e.message);
        }
    }
    </script>
</body>
</html>