<?php
// ── debug.php — ลบทิ้งหลังใช้งาน ──
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace;font-size:13px;background:#0d1117;color:#e6edf3;padding:20px'>";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// 1. ตรวจ config
echo "=== 1. Config ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "✅ config.php OK\n";
    echo "   DB_HOST=" . DB_HOST . "  DB_NAME=" . DB_NAME . "  DB_USER=" . DB_USER . "\n";
} catch(Throwable $e) { echo "❌ config.php: " . $e->getMessage() . "\n"; }

// 2. ตรวจ DB connection
echo "\n=== 2. DB Connection ===\n";
try {
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    echo "✅ MySQL connected\n";
    echo "   Server version: " . $db->query("SELECT VERSION()")->fetchColumn() . "\n";
} catch(Throwable $e) { echo "❌ DB Error: " . $e->getMessage() . "\n"; die(); }

// 3. ตรวจตาราง
echo "\n=== 3. Tables ===\n";
$tables = ['stores','daily_sales','replenish_uploads','replenish_products','replenish_stock','replenish_top_sellers','replenish_plans'];
foreach ($tables as $t) {
    try {
        $cnt = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "✅ $t — $cnt rows\n";
    } catch(Throwable $e) {
        echo "❌ $t — MISSING (". $e->getMessage() .")\n";
    }
}

// 4. ตรวจ columns ใน stores
echo "\n=== 4. stores columns ===\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM stores")->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(', ', $cols) . "\n";
    $need = ['store_type','latitude','longitude','region','open_date'];
    foreach ($need as $c) {
        echo (in_array($c,$cols) ? "✅" : "❌ MISSING") . " $c\n";
    }
} catch(Throwable $e) { echo "❌ " . $e->getMessage() . "\n"; }

// 5. ตรวจ stores.store_code_new
echo "\n=== 5. stores with store_code_new ===\n";
try {
    $rows = $db->query("SELECT store_code, store_code_new, store_name FROM stores WHERE store_code_new IS NOT NULL LIMIT 10")->fetchAll();
    echo count($rows) . " stores have store_code_new:\n";
    foreach ($rows as $r) echo "  {$r['store_code']} → {$r['store_code_new']}  ({$r['store_name']})\n";
} catch(Throwable $e) { echo "❌ " . $e->getMessage() . "\n"; }

// 6. ตรวจ SalesAnalytics
echo "\n=== 6. SalesAnalytics ===\n";
try {
    require_once __DIR__ . '/classes/SalesAnalytics.php';
    $sa = new SalesAnalytics();
    echo "✅ SalesAnalytics created\n";
    echo "   uploadId=" . ($sa->uploadId ?? 'null') . "\n";
    $up = $sa->getUploadInfo();
    if ($up) echo "   Upload: date={$up['upload_date']} rate_days={$up['rate_days']}\n";
    else echo "   No uploads found\n";
} catch(Throwable $e) { echo "❌ SalesAnalytics: " . $e->getMessage() . "\n"; echo $e->getTraceAsString() . "\n"; }

// 7. ตรวจ daily_sales sample
echo "\n=== 7. daily_sales sample ===\n";
try {
    $r = $db->query("SELECT MIN(sale_date) AS min_d, MAX(sale_date) AS max_d, COUNT(*) AS cnt, COUNT(DISTINCT store_code) AS stores FROM daily_sales WHERE qty > 0 AND brand='TOPOLOGIE'")->fetch();
    echo "TOPOLOGIE rows: {$r['cnt']}\n";
    echo "Date range: {$r['min_d']} → {$r['max_d']}\n";
    echo "Stores: {$r['stores']}\n";
} catch(Throwable $e) { echo "❌ " . $e->getMessage() . "\n"; }

echo "\n=== Done ===\n";
echo "</pre>";
