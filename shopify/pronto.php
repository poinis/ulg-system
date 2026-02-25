<?php
/**
 * SYNC MASTER V3.1.7: SELECTIVE SYNC EDITION
 * - Feature: เพิ่ม Checkbox เลือกรายการที่จะซิงค์ (ติ๊กถูกไว้เป็นค่าเริ่มต้น)
 * - Logic: Single Request Mode (Stable)
 * - Filter: Freitag Brand Blacklist
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); 
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');

// ================= 1. CONFIGURATION =================
$db_file = 'inventory_sync.db';
$WAREHOUSE_LIST = ['10000' => 'ULG DC OFFICE', '12010' => 'PRONTO CENTRAL RAMA 9', '77010' => 'PRONTO WEB'];
$TARGET_STORES = array_keys($WAREHOUSE_LIST);

$CEGID_CONFIG = [
    'wsdl_url' => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ItemInventoryWcfService.svc?wsdl',
    'username' => '90643827_001_PROD\\frt',
    'password' => 'adgjm',
    'database_id' => '90643827_001_PROD',
];

$SHOP_CONFIG = [
    'shop' => 'newpronto.myshopify.com',
    'token' => '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',
    'version' => '2025-01',
    'location_id' => '106930012440', 
];

// ================= 2. DATABASE & CORE FUNCTIONS =================
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // เพิ่ม Column is_selected (เริ่มต้นเป็น 1)
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, variant_id TEXT UNIQUE, product_title TEXT, sku TEXT, barcode TEXT, inventory_item_id TEXT, shopify_qty INTEGER DEFAULT 0, cegid_qty INTEGER DEFAULT NULL, is_selected INTEGER DEFAULT 1, status TEXT DEFAULT 'pending', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
} catch (PDOException $e) { die("❌ DB Error: " . $e->getMessage()); }

function shopifyRequest($endpoint, $method = 'GET', $data = []) {
    global $SHOP_CONFIG;
    $url = (strpos($endpoint, 'http') === 0) ? $endpoint : "https://{$SHOP_CONFIG['shop']}/admin/api/{$SHOP_CONFIG['version']}/$endpoint";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Shopify-Access-Token: {$SHOP_CONFIG['token']}", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HEADER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($method == 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $response = curl_exec($ch); $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size); $body = substr($response, $header_size);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'header' => $header, 'data' => json_decode($body, true)];
}

function getCegidStockStable($client, $barcode, $databaseId) {
    global $TARGET_STORES;
    try {
        $params = ['inventoryStoreItemDetailRequest' => ['AllAvailableWarehouse' => false, 'ItemIdentifiers' => [['Reference' => trim((string)$barcode)]], 'StoreIds' => $TARGET_STORES, 'WithStoreName' => false], 'clientContext' => ['DatabaseId' => $databaseId]];
        $res = $client->GetListItemInventoryDetailByStore($params);
        $details = $res->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore ?? null;
        if (!$details) return 0;
        $total = 0;
        $stores = $details->StoresAvailableQty->StoreAvailableQty ?? [];
        if (!is_array($stores)) $stores = [$stores];
        foreach ($stores as $s) { if (in_array($s->StoreId, $TARGET_STORES)) $total += (float)($s->AvailableQuantity ?? 0); }
        return (int)$total;
    } catch (Exception $e) { return 0; }
}

// ================= 3. AJAX HANDLERS =================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    // ✅ Toggle Selection (ใช้ตอนกดติ๊กถูก/ออก ในตาราง)
    if ($action === 'toggle_select') {
        $id = (int)$_POST['id'];
        $val = (int)$_POST['selected'];
        $pdo->prepare("UPDATE products SET is_selected = ? WHERE id = ?")->execute([$val, $id]);
        echo json_encode(['success' => true]); exit;
    }

    if ($action === 'step1_init' || $action === 'step1_continue') {
        $nextUrl = ($action === 'step1_init') ? "products.json?status=active&limit=250&fields=id,title,vendor,variants,tags" : ($_POST['nextUrl'] ?? '');
        $res = shopifyRequest($nextUrl);
        if ($action === 'step1_init') $pdo->exec("DELETE FROM products; DELETE FROM sqlite_sequence WHERE name='products';");
        $stmt = $pdo->prepare("INSERT INTO products (variant_id, product_title, sku, barcode, inventory_item_id, shopify_qty, is_selected, status) VALUES (?, ?, ?, ?, ?, ?, 1, 'pending')");
        $count = 0;
        foreach ($res['data']['products'] as $product) {
            if (stripos($product['title'], 'freitag') !== false || stripos($product['vendor'], 'freitag') !== false) continue;
            foreach ($product['variants'] as $v) {
                $b = trim((string)$v['barcode'] ?? ''); if (empty($b)) continue;
                try { $stmt->execute([$v['id'], $product['title'], $v['sku'], $b, $v['inventory_item_id'], $v['inventory_quantity']]); $count++; } catch (Exception $e) {}
            }
        }
        $nextPage = ''; if (preg_match('/<([^>]+)>; rel="next"/', $res['header'], $m)) $nextPage = $m[1];
        echo json_encode(['success' => true, 'count' => (int)($_POST['totalCount'] ?? 0) + $count, 'hasMore' => !empty($nextPage), 'nextUrl' => $nextPage]);
        exit;
    }

    if ($action === 'step2_batch') {
        $stmt = $pdo->prepare("SELECT id, barcode FROM products WHERE status = 'pending' LIMIT 10");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) { echo json_encode(['done' => true]); exit; }
        try {
            $client = new SoapClient($CEGID_CONFIG['wsdl_url'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password']]);
            foreach ($items as $item) {
                $qty = getCegidStockStable($client, $item['barcode'], $CEGID_CONFIG['database_id']);
                $pdo->prepare("UPDATE products SET cegid_qty = ?, status = 'ok' WHERE id = ?")->execute([$qty, $item['id']]);
            }
            echo json_encode(['done' => false, 'processed' => (int)$_POST['offset'] + count($items)]);
        } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'step3_batch') {
        // ✅ ซิงค์เฉพาะรายการที่ is_selected = 1 เท่านั้น
        $stmt = $pdo->prepare("SELECT * FROM products WHERE status = 'ok' AND is_selected = 1 AND shopify_qty != cegid_qty LIMIT 10"); 
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) { echo json_encode(['done' => true]); exit; }
        foreach ($items as $item) {
            $res = shopifyRequest("inventory_levels/set.json", "POST", ["location_id" => (int)$SHOP_CONFIG['location_id'], "inventory_item_id" => (int)$item['inventory_item_id'], "available" => (int)$item['cegid_qty']]);
            if ($res['code'] === 200) { $pdo->prepare("UPDATE products SET shopify_qty = ? WHERE id = ?")->execute([$item['cegid_qty'], $item['id']]); }
            usleep(250000);
        }
        echo json_encode(['done' => false]); exit;
    }
    exit;
}

// ================= 4. VIEW LOGIC =================
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1; $limit = 100; $offset = ($page - 1) * $limit;
$where = "WHERE status = 'ok' AND shopify_qty != cegid_qty";
$total_diff = $pdo->query("SELECT COUNT(*) FROM products $where")->fetchColumn();
$total_pages = ceil($total_diff / $limit);
$products = $pdo->query("SELECT * FROM products $where LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$all_in_db = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$processed_count = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'ok'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>Sync Master V3.1.7</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>.progress-bar { transition: width 0.3s ease; } input[type='checkbox'] { width: 1.2rem; height: 1.2rem; cursor: pointer; }</style>
</head>
<body class="bg-slate-50 p-8 font-sans min-h-screen">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-3xl shadow-lg p-6 mb-8 flex justify-between items-center border-t-4 border-indigo-600">
            <div><h1 class="text-2xl font-black text-slate-800 tracking-tighter italic uppercase">PRONTO Sync V3.1.7</h1><p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mt-1">Selective Sync Mode | 100 Items/Page</p></div>
            <a href="index.php" class="bg-slate-800 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-black transition flex items-center gap-2"><i class="fas fa-home"></i> HOME</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-3xl shadow-md p-6 border border-slate-100 text-center">
                <h3 class="font-bold text-indigo-600 mb-4 text-xs uppercase tracking-widest">1. Fetch Shopify</h3>
                <div class="text-4xl font-black mb-4"><?= number_format($all_in_db) ?></div>
                <div class="h-2 bg-slate-100 rounded-full mb-4 overflow-hidden"><div id="bar-step1" class="bg-indigo-600 h-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep1()" id="btn-step1" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg">Get Products</button>
            </div>
            <div class="bg-white rounded-3xl shadow-md p-6 border border-slate-100 text-center">
                <h3 class="font-bold text-blue-600 mb-4 text-xs uppercase tracking-widest">2. Check Cegid</h3>
                <div class="text-4xl font-black mb-4 text-slate-800" id="txt-step2"><?= number_format($processed_count) ?></div>
                <div class="h-2 bg-slate-100 rounded-full mb-4 overflow-hidden"><div id="bar-step2" class="bg-blue-600 h-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep2()" id="btn-step2" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 shadow-lg" <?= $all_in_db==0?'disabled':'' ?>>Update Inventory</button>
            </div>
            <div class="bg-white rounded-3xl shadow-md p-6 border border-slate-100 text-center">
                <h3 class="font-bold text-green-600 mb-4 text-xs uppercase tracking-widest">3. Sync Back</h3>
                <div class="text-4xl font-black text-yellow-500 mb-4"><?= number_format($total_diff) ?></div>
                <div class="h-2 bg-slate-100 rounded-full mb-4 overflow-hidden"><div id="bar-step3" class="bg-green-600 h-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep3()" id="btn-step3" class="w-full bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 shadow-lg" <?= $total_diff==0?'disabled':'' ?>>Start Sync</button>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200">
            <div class="p-6 border-b bg-slate-50 flex justify-between items-center text-slate-700">
                <h2 class="font-black uppercase text-xs italic tracking-tighter">📋 ยอดไม่ตรงกัน (<?= number_format($total_diff) ?> รายการ)</h2>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-slate-400 mr-4 font-mono">Page <?= $page ?>/<?= $total_pages ?: 1 ?></span>
                    <?php if($page > 1): ?><a href="?p=<?= $page-1 ?>" class="bg-white border-2 px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-slate-100 transition shadow-sm"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                    <?php if($page < $total_pages): ?><a href="?p=<?= $page+1 ?>" class="bg-white border-2 px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-slate-100 transition shadow-sm"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
                </div>
            </div>
            <table class="w-full text-left text-sm font-medium">
                <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                    <tr>
                        <th class="px-8 py-4 text-center w-20 border-r"><input type="checkbox" id="check-all" checked></th>
                        <th class="px-8 py-4">Barcode</th>
                        <th class="px-8 py-4">Product Name</th>
                        <th class="px-8 py-4 text-center">Shopify</th>
                        <th class="px-8 py-4 text-center bg-indigo-50 text-indigo-600">Cegid (Physical)</th>
                        <th class="px-8 py-4 text-center">Diff</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php foreach ($products as $p): ?>
                    <tr class="hover:bg-slate-50 transition <?= $p['is_selected'] ? '' : 'bg-slate-50 opacity-50' ?>">
                        <td class="px-8 py-5 text-center border-r"><input type="checkbox" class="row-check" data-id="<?= $p['id'] ?>" <?= $p['is_selected'] ? 'checked' : '' ?>></td>
                        <td class="px-8 py-5 font-mono text-xs font-bold text-slate-600"><?= $p['barcode'] ?></td>
                        <td class="px-8 py-5 font-bold truncate max-w-xs"><?= $p['product_title'] ?></td>
                        <td class="px-8 py-5 text-center text-slate-400"><?= $p['shopify_qty'] ?></td>
                        <td class="px-8 py-5 text-center font-black bg-indigo-50 text-indigo-600 italic"><?= $p['cegid_qty'] ?></td>
                        <td class="px-8 py-5 text-center font-black text-red-500 font-mono"><?= $p['cegid_qty'] - $p['shopify_qty'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    const setBar = (s, p) => document.getElementById(`bar-step${s}`).style.width = p + '%';
    
    // ✅ ฟังก์ชันติ๊กถูก/ออก
    document.querySelectorAll('.row-check').forEach(chk => {
        chk.onclick = async function() {
            const id = this.dataset.id;
            const selected = this.checked ? 1 : 0;
            this.closest('tr').classList.toggle('opacity-50', !this.checked);
            this.closest('tr').classList.toggle('bg-slate-50', !this.checked);
            let fd = new FormData(); fd.append('id', id); fd.append('selected', selected);
            await fetch('?ajax=toggle_select', {method: 'POST', body: fd});
        };
    });

    // ✅ ฟังก์ชันติ๊กถูกทั้งหมด
    document.getElementById('check-all').onclick = function() {
        document.querySelectorAll('.row-check').forEach(c => { if(c.checked !== this.checked) c.click(); });
    };

    async function runStep1() {
        if(!confirm('ดึงสินค้าใหม่? (Freitag จะกรองออกอัตโนมัติ)')) return;
        setBar(1, 10); let res = await fetch('?ajax=step1_init'); let data = await res.json();
        while(data.success && data.hasMore) {
            setBar(1, 50); let fd = new FormData(); fd.append('nextUrl', data.nextUrl); fd.append('totalCount', data.count);
            res = await fetch('?ajax=step1_continue', {method:'POST', body:fd}); data = await res.json();
        }
        location.reload();
    }
    async function runStep2() {
        setBar(2, 5); let offset = 0;
        while(true) {
            let fd = new FormData(); fd.append('offset', offset);
            let res = await fetch('?ajax=step2_batch', {method:'POST', body:fd}); let data = await res.json();
            if(data.done || data.error) break; offset = data.processed; setBar(2, (offset/<?= $all_in_db ?: 1 ?>)*100);
            document.getElementById('txt-step2').innerText = offset;
        }
        location.reload();
    }
    async function runStep3() {
        if(!confirm('เริ่ม Sync เฉพาะรายการที่ติ๊กถูกกลับ Shopify?')) return;
        setBar(3, 10);
        while(true) {
            let res = await fetch('?ajax=step3_batch'); let data = await res.json();
            if(data.done) break; setBar(3, 50);
        }
        alert('Sync สำเร็จ!'); location.reload();
    }
    </script>
</body>
</html>