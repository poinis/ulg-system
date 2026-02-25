<?php
/**
 * CEGID ITEM HUNTER V1.2 - CATEGORY DISCOVERY
 * - Feature: ค้นหาตามหมวดหมู่ (Categories) หรือ คอลเลกชัน (Collections)
 * - Logic: ค้นหา ID ของหมวดหมู่ "Lean Dean" และดึงสินค้าทั้งหมดในหมวดนั้น
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

// ================= 1. CONFIGURATION =================
$db_file = 'item_hunter.db';
$CEGID_CONFIG = [
    'category_wsdl' => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ProductCategoriesService.svc?wsdl',
    'search_wsdl'   => 'https://90643827-retail-ondemand.cegid.cloud/Y2/ProductSearchService.svc?wsdl',
    'username'      => '90643827_001_PROD\\frt',
    'password'      => 'adgjm',
    'database_id'   => '90643827_001_PROD',
];

// ================= 2. DATABASE SETUP =================
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS hunter_items (id INTEGER PRIMARY KEY AUTOINCREMENT, barcode TEXT UNIQUE, style_name TEXT, color TEXT, size TEXT, category_found TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
} catch (PDOException $e) { die("❌ DB Error: " . $e->getMessage()); }

// ================= 3. DISCOVERY LOGIC =================
$keyword = isset($_POST['q']) ? trim($_POST['q']) : '';
$categories = [];

try {
    $catClient = new SoapClient($CEGID_CONFIG['category_wsdl'], ['login' => $CEGID_CONFIG['username'], 'password' => $CEGID_CONFIG['password']]);
    
    // 🔍 STEP 1: ดึงรายชื่อหมวดหมู่ทั้งหมดมาดูว่าอันไหนคือ "Lean Dean"
    $res = $catClient->GetList(['Context' => ['DatabaseId' => $CEGID_CONFIG['database_id']]]);
    $all_cats = $res->GetListResult->Categories->Category ?? [];
    if (!is_array($all_cats)) $all_cats = [$all_cats];

    if ($keyword) {
        // กรองหาหมวดหมู่ที่ตรงกับ Keyword
        foreach($all_cats as $cat) {
            if (stripos($cat->Description, $keyword) !== false) {
                $categories[] = $cat;
            }
        }
    }
} catch (Exception $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><title>Item Hunter V1.2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 p-8 font-sans">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-t-8 border-indigo-600">
            <h1 class="text-3xl font-black text-slate-800 mb-2 uppercase italic tracking-tighter">
                <i class="fas fa-sitemap text-indigo-600 mr-2"></i> Category Hunter
            </h1>
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-8">ค้นหาสินค้าตามหมวดหมู่ (Family Search)</p>
            
            <form method="POST" class="flex gap-4">
                <input type="text" name="q" placeholder="เช่น Lean Dean หรือ Nudie..." value="<?= htmlspecialchars($keyword) ?>"
                       class="flex-1 p-5 border-2 rounded-2xl outline-none focus:border-indigo-500 text-xl font-bold uppercase shadow-inner transition-all">
                <button type="submit" class="bg-indigo-600 text-white px-12 py-5 rounded-2xl font-black hover:bg-indigo-700 shadow-lg active:scale-95 transition">ค้นหาหมวดหมู่</button>
            </form>
        </div>

        <?php if(!empty($categories)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php foreach($categories as $c): ?>
            <div class="bg-white p-6 rounded-2xl shadow-md border border-slate-100 flex justify-between items-center">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Category ID: <?= $c->Id ?></span>
                    <h3 class="text-xl font-black text-slate-800 uppercase"><?= $c->Description ?></h3>
                </div>
                <button onclick="alert('กำลังพัฒนา: ดึงสินค้าจากหมวดนี้')" class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-indigo-600 hover:text-white transition">ดึงสินค้า</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif($keyword): ?>
        <div class="p-10 bg-amber-50 text-amber-700 rounded-3xl text-center border-2 border-dashed border-amber-200 font-bold uppercase italic">
            ไม่พบหมวดหมู่ที่มีชื่อว่า "<?= htmlspecialchars($keyword) ?>" ในระบบ
        </div>
        <?php endif; ?>
    </div>
</body>
</html>