<?php
/**
 * SYNC MASTER V2.6.6: Precise Mapping + Pagination + Sync List Only
 * - Shopify: ดึงเฉพาะ status=active
 * - Cegid: ปรับการแมตช์ข้อมูลแบบ 1 ต่อ 1 (แก้ปัญหาเลข 0 ทั้งที่มีของ)
 * - UI: แสดงเฉพาะรายการที่ยอดไม่ตรง หน้าละ 100 รายการ
 */

// 1. Config ระบบ
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); 
ini_set('memory_limit', '512M');
ignore_user_abort(true);
header('Content-Type: text/html; charset=utf-8');

// ================= CONFIGURATION =================
$db_file = 'inventory_sync1.db';
$BLACKLIST_BARCODES = ['2990000006684'];

$STORE_LIST = [
    '10000' => 'ULG DC OFFICE',
    '12010' => 'PRONTO CENTRAL RAMA 9',
    '17020' => 'SOUP EMSPHERE',
    '77000' => 'ULG ONLINE',
    '77001' => 'PRONTO ONLINE'
];

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
    'location_id' => '92684255512', 
];

// ================= DATABASE SETUP =================
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, variant_id TEXT UNIQUE, product_title TEXT, sku TEXT, barcode TEXT, inventory_item_id TEXT, shopify_qty INTEGER DEFAULT 0, cegid_qty INTEGER DEFAULT NULL, status TEXT DEFAULT 'pending', error_msg TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
} catch (PDOException $e) { die("❌ DB Error: " . $e->getMessage()); }

// ================= HELPER FUNCTIONS =================
function shopifyRequest($url_or_endpoint, $method = 'GET', $data = []) {
    global $SHOP_CONFIG;
    $url = (strpos($url_or_endpoint, 'http') === 0) ? $url_or_endpoint : "https://{$SHOP_CONFIG['shop']}/admin/api/{$SHOP_CONFIG['version']}/$url_or_endpoint";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Shopify-Access-Token: {$SHOP_CONFIG['token']}", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HEADER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($method == 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size); $body = substr($response, $header_size);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $httpCode, 'header' => $header, 'data' => json_decode($body, true)];
}

// ✅ ฟังก์ชันดึงสต็อกรายตัว (เพื่อให้มั่นใจว่ายอดที่ได้ตรงกับ Barcode แน่นอน)
function getCegidStockSingle($client, $storeIds, $barcode, $databaseId) {
    try {
        $params = [
            'inventoryStoreItemDetailRequest' => [
                'AllAvailableWarehouse' => false, 
                'DetailSku' => true,
                'ItemIdentifiers' => [['Reference' => trim((string)$barcode), 'Id' => null]],
                'OnlyAvailableStock' => false, 
                'StoreIds' => $storeIds,
                'WithStoreName' => false
            ],
            'clientContext' => ['DatabaseId' => $databaseId]
        ];
        $response = $client->GetListItemInventoryDetailByStore($params);
        $details = $response->GetListItemInventoryDetailByStoreResult->InventoryDetailsByStore->AvailableQtyByItemByStore ?? null;
        if (!$details) return 0;
        
        $totalQty = 0;
        $stores = $details->StoresAvailableQty->StoreAvailableQty ?? [];
        if (!is_array($stores)) $stores = [$stores];
        foreach ($stores as $store) $totalQty += (float)($store->AvailableQuantity ?? 0);
        return $totalQty;
    } catch (Exception $e) { return null; }
}

// ================= AJAX HANDLERS =================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    if ($action === 'step1_init' || $action === 'step1_continue') {
        $nextUrl = ($action === 'step1_init') ? "products.json?status=active&limit=250&fields=id,title,variants" : ($_POST['nextUrl'] ?? '');
        $res = shopifyRequest($nextUrl);
        if ($res['code'] !== 200) { echo json_encode(['success' => false, 'error' => "Shopify Error"]); exit; }
        if ($action === 'step1_init') $pdo->exec("DELETE FROM products; DELETE FROM sqlite_sequence WHERE name='products';");
        $stmt = $pdo->prepare("INSERT INTO products (variant_id, product_title, sku, barcode, inventory_item_id, shopify_qty, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $count = 0;
        foreach ($res['data']['products'] as $product) {
            foreach ($product['variants'] as $v) {
                $b = trim((string)$v['barcode']);
                if (empty($b) || in_array($b, $BLACKLIST_BARCODES)) continue;
                try { $stmt->execute([$v['id'], $product['title'], $v['sku'], $b, $v['inventory_item_id'], $v['inventory_quantity']]); $count++; } catch (Exception $e) {}
            }
        }
        $nextPage = ''; if (preg_match('/<([^>]+)>; rel="next"/', $res['header'], $m)) $nextPage = $m[1];
        echo json_encode(['success' => true, 'count' => (int)($_POST['totalCount'] ?? 0) + $count, 'hasMore' => !empty($nextPage), 'nextUrl' => $nextPage, 'page' => (int)($_POST['page'] ?? 1) + 1]);
        exit;
    }

    if ($action === 'step2_init') {
        $stores = json_decode($_POST['stores'] ?? '[]', true);
        $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('temp_stores', ?)")->execute([json_encode($stores)]);
        $total = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn();
        echo json_encode(['success' => true, 'total' => $total, 'processed' => 0, 'offset' => 0, 'batchSize' => 10]);
        exit;
    }

    if ($action === 'step2_batch') {
        $offset = (int)$_POST['offset'];
        $stmt = $pdo->prepare("SELECT id, barcode FROM products WHERE status = 'pending' LIMIT 10 OFFSET ?"); 
        $stmt->execute([$offset]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) { echo json_encode(['success' => true, 'done' => true]); exit; }
        
        $stmtS = $pdo->query("SELECT value FROM settings WHERE key = 'temp_stores'"); 
        $stores = json_decode($stmtS->fetchColumn(), true);
        
        try {
            $client = new SoapClient($CEGID_CONFIG['wsdl_url'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password'], 'connection_timeout' => 30]);
            foreach ($items as $item) {
                $stock = getCegidStockSingle($client, $stores, $item['barcode'], $CEGID_CONFIG['database_id']);
                if ($stock === null) {
                    $pdo->prepare("UPDATE products SET status = 'error', error_msg = 'API Timeout' WHERE id = ?")->execute([$item['id']]);
                } else {
                    $pdo->prepare("UPDATE products SET cegid_qty = ?, status = 'ok' WHERE id = ?")->execute([(int)$stock, $item['id']]);
                }
            }
            echo json_encode(['success' => true, 'done' => false, 'processed' => $offset + count($items), 'total' => (int)$_POST['total'], 'offset' => $offset + 10]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'step3_batch') {
        $offset = (int)$_POST['offset'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE status = 'ok' AND shopify_qty != cegid_qty LIMIT 10"); 
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) { echo json_encode(['success' => true, 'done' => true]); exit; }
        $success = 0;
        foreach ($items as $item) {
            $res = shopifyRequest("inventory_levels/set.json", "POST", ["location_id" => (int)$SHOP_CONFIG['location_id'], "inventory_item_id" => (int)$item['inventory_item_id'], "available" => (int)$item['cegid_qty']]);
            if ($res['code'] === 200) { $pdo->prepare("UPDATE products SET shopify_qty = ? WHERE id = ?")->execute([$item['cegid_qty'], $item['id']]); $success++; }
            usleep(200000);
        }
        echo json_encode(['success' => true, 'done' => false, 'processed' => $offset + count($items), 'total' => (int)$_POST['total'], 'batchSuccess' => $success, 'offset' => $offset + 10]);
        exit;
    }
    exit;
}

// ================= PAGINATION & VIEW LOGIC =================
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 100;
$offset = ($page - 1) * $limit;

// แสดงเฉพาะรายการที่ยอดไม่ตรง
$where = "WHERE status = 'ok' AND shopify_qty != cegid_qty";
$total_diff = $pdo->query("SELECT COUNT(*) FROM products $where")->fetchColumn();
$total_pages = ceil($total_diff / $limit);
$products_list = $pdo->query("SELECT * FROM products $where LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

$products_in_db = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Sync Master V2.6.6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>.progress-bar { transition: width 0.3s ease; } .spinner { animation: spin 1s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } }</style>
</head>
<body class="bg-slate-100 min-h-screen p-4 md:p-8 font-sans">
    <div class="max-w-6xl mx-auto">
        
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6 flex justify-between items-center border-t-4 border-indigo-500">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-sync-alt text-indigo-500 mr-2"></i>Stock Sync Master</h1>
                <p class="text-slate-500 text-sm">V2.6.6: ตัวดึงแม่นยำสูง | แสดงเฉพาะรายการที่ยอดไม่ตรง</p>
            </div>
            <div class="text-right">
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-tight">Physical Match Mode</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
                <h3 class="font-bold text-indigo-600 mb-4 text-xs uppercase">1. Fetch Shopify (Active)</h3>
                <div class="text-3xl font-black mb-4"><?= number_format($products_in_db) ?></div>
                <div id="step1-progress" class="hidden h-1.5 bg-slate-100 rounded-full mb-4"><div id="step1-bar" class="bg-indigo-600 h-1.5 rounded-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep1()" id="btn-step1" class="w-full bg-indigo-600 text-white py-2 rounded-lg font-bold hover:bg-indigo-700 transition shadow-sm">Get Products</button>
            </div>
            <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
                <h3 class="font-bold text-blue-600 mb-4 text-xs uppercase">2. Check Cegid (Physical)</h3>
                <div class="h-24 overflow-y-auto border rounded-lg p-2 mb-3 bg-slate-50 text-[10px]">
                    <?php foreach ($STORE_LIST as $code => $name): ?>
                    <label class="flex items-center gap-2 mb-1 p-1 hover:bg-white rounded cursor-pointer">
                        <input type="checkbox" class="store-checkbox" value="<?= $code ?>" checked><span><?= $code ?> - <?= $name ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="step2-progress" class="hidden h-1.5 bg-slate-100 rounded-full mb-4"><div id="step2-bar" class="bg-blue-600 h-1.5 rounded-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep2()" id="btn-step2" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700 transition shadow-sm" <?= $products_in_db==0?'disabled':'' ?>>Check Stock</button>
            </div>
            <div class="bg-white rounded-xl shadow p-5 border border-slate-200">
                <h3 class="font-bold text-green-600 mb-4 text-xs uppercase">3. Sync กลับ Shopify</h3>
                <div class="text-3xl font-black text-yellow-500 mb-4"><?= number_format($total_diff) ?></div>
                <div id="step3-progress" class="hidden h-1.5 bg-slate-100 rounded-full mb-4"><div id="step3-bar" class="bg-green-600 h-1.5 rounded-full progress-bar" style="width:0%"></div></div>
                <button onclick="runStep3()" id="btn-step3" class="w-full bg-green-600 text-white py-2 rounded-lg font-bold hover:bg-green-700 transition shadow-sm" <?= $total_diff==0?'disabled':'' ?>>Start Sync</button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-slate-200">
            <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center">
                <h2 class="font-bold text-slate-700">📋 รายการที่ยอดไม่ตรง (หน้า <?= $page ?> / <?= $total_pages ?: 1 ?>)</h2>
                <div class="flex items-center gap-2">
                    <?php if($page > 1): ?>
                        <a href="?p=<?= $page-1 ?>" class="px-3 py-1 bg-white border rounded text-xs hover:bg-slate-100 font-bold">ก่อนหน้า</a>
                    <?php endif; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?p=<?= $page+1 ?>" class="px-3 py-1 bg-white border rounded text-xs hover:bg-slate-100 font-bold">ถัดไป</a>
                    <?php endif; ?>
                </div>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100 text-slate-500 text-[10px] uppercase font-bold tracking-wider">
                    <tr><th class="px-6 py-4">Barcode</th><th class="px-6 py-4">Product Name</th><th class="px-6 py-4 text-center">Shopify</th><th class="px-6 py-4 text-center">Cegid (Physical)</th><th class="px-6 py-4 text-center">Diff</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($products_list)): ?>
                        <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic font-medium">✨ ทุกรายการยอดตรงกันหมดแล้ว ไม่ต้อง Sync ครับ</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products_list as $p): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 font-mono text-xs font-bold text-slate-700"><?= $p['barcode'] ?></td>
                        <td class="px-6 py-4 truncate max-w-xs" title="<?= $p['product_title'] ?>"><?= $p['product_title'] ?></td>
                        <td class="px-6 py-4 text-center text-slate-400 font-medium"><?= $p['shopify_qty'] ?></td>
                        <td class="px-6 py-4 text-center text-blue-600 font-black italic"><?= $p['cegid_qty'] ?></td>
                        <td class="px-6 py-4 text-center text-red-500 font-black bg-red-50"><?= $p['cegid_qty'] - $p['shopify_qty'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function updateBar(s, p) { document.getElementById(`step${s}-bar`).style.width = p+'%'; }
    async function runStep1() {
        if(!confirm('ดึงสินค้าใหม่? (ข้ามรายการ Draft)')) return;
        document.getElementById('btn-step1').disabled = true;
        document.getElementById('step1-progress').classList.remove('hidden');
        let res = await fetch('?ajax=step1_init'); let data = await res.json();
        while(data.success && data.hasMore) {
            updateBar(1, 50);
            let fd = new FormData(); fd.append('nextUrl', data.nextUrl); fd.append('totalCount', data.count); fd.append('page', data.page);
            res = await fetch('?ajax=step1_continue', {method:'POST', body:fd}); data = await res.json();
        }
        location.reload();
    }
    async function runStep2() {
        const stores = Array.from(document.querySelectorAll('.store-checkbox:checked')).map(c => c.value);
        if(!stores.length) return alert('กรุณาเลือกสาขา');
        document.getElementById('btn-step2').disabled = true;
        document.getElementById('step2-progress').classList.remove('hidden');
        let fd = new FormData(); fd.append('stores', JSON.stringify(stores));
        let res = await fetch('?ajax=step2_init', {method:'POST', body:fd}); let data = await res.json();
        let offset = 0;
        while(data.success && !data.done) {
            let bfd = new FormData(); bfd.append('offset', offset); bfd.append('total', data.total);
            res = await fetch('?ajax=step2_batch', {method:'POST', body:bfd}); let bdata = await res.json();
            if(bdata.done) break; offset = bdata.offset; updateBar(2, (offset/data.total)*100);
        }
        location.reload();
    }
    async function runStep3() {
        if(!confirm('ยืนยันการ Sync ยอดกลับ Shopify?')) return;
        document.getElementById('btn-step3').disabled = true;
        document.getElementById('step3-progress').classList.remove('hidden');
        let res = await fetch('?ajax=step3_init'); let data = await res.json();
        let offset = 0;
        while(data.success && !data.done) {
            let bfd = new FormData(); bfd.append('offset', offset); bfd.append('total', data.total);
            res = await fetch('?ajax=step3_batch', {method:'POST', body:bfd}); let bdata = await res.json();
            if(bdata.done) break; offset = bdata.offset; updateBar(3, (offset/data.total)*100);
        }
        location.reload();
    }
    </script>
</body>
</html>