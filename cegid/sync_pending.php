<?php
/**
 * Cegid Y2 - Sync Pending Lines
 * ==============================
 * Usage: sync_pending.php?limit=100
 */
require_once __DIR__ . '/config.php';

$db = Database::getInstance();
$api = new CegidSoapClient();
$stats = ['processed' => 0, 'lines' => 0, 'payments' => 0, 'errors' => 0];
$limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;

echo "<div style='font-family:monospace;padding:20px'>";
echo "<h2>🔄 Sync Pending Lines</h2>";

$docs = $db->fetchAll("SELECT doc_key, doc_type, doc_number, doc_stump FROM cegid_sale_documents WHERE lines_synced = 0 ORDER BY doc_date DESC LIMIT ?", [$limit]);

if (empty($docs)) {
    echo "<p>✅ No pending documents!</p></div>";
    exit;
}

echo "<p>📋 Found " . count($docs) . " pending</p><hr>";
$start = microtime(true);

// Load brand cache
$brands = [];
foreach ($db->fetchAll("SELECT category_code, category_name FROM cegid_categories WHERE category_type_id = 1") as $b) {
    $brands[$b['category_code']] = $b['category_name'];
}

foreach ($docs as $i => $doc) {
    echo "<p>[" . ($i+1) . "/" . count($docs) . "] {$doc['doc_key']}... ";
    flush();
    
    try {
        $result = $api->getSaleDetail($doc['doc_type'], $doc['doc_stump'], $doc['doc_number']);
        
        if (!$result['success'] || strpos($result['response'], 'Fault') !== false) {
            echo "<span style='color:red'>❌</span></p>";
            $stats['errors']++;
            continue;
        }
        
        $xml = cleanXml($result['response']);
        
        // Lines
        preg_match_all('/<Get_Line>(.*?)<\/Get_Line>/s', $xml, $lines);
        foreach ($lines[1] as $lineXml) {
            $itemCode = extractXmlValue($lineXml, 'ItemCode');
            $brandCode = extractBrandCode($itemCode);
            $qty = (float)(extractXmlValue($lineXml, 'Quantity') ?: 0);
            $price = (float)(extractXmlValue($lineXml, 'TaxIncludedNetUnitPrice') ?: 0);
            
            $db->query("INSERT INTO cegid_sale_lines (doc_key, line_number, item_id, item_code, item_reference, item_label, complementary_desc, brand_code, quantity, net_unit_price, line_total, salesperson_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", [
                $doc['doc_key'], extractXmlValue($lineXml, 'Rank'), extractXmlValue($lineXml, 'ItemId'), $itemCode,
                extractXmlValue($lineXml, 'ItemReference'), extractXmlValue($lineXml, 'Label'),
                extractXmlValue($lineXml, 'ComplementaryDescription'), $brandCode, $qty, $price, $qty * $price,
                extractXmlValue($lineXml, 'SalesPersonId')
            ]);
            $stats['lines']++;
            
            // Update product
            $itemId = extractXmlValue($lineXml, 'ItemId');
            if ($itemId) {
                $db->query("INSERT INTO cegid_products (item_id, item_code, item_name, brand_code, brand_name, color, unit_price)
                    VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE item_name=COALESCE(VALUES(item_name),item_name)", [
                    $itemId, $itemCode, extractXmlValue($lineXml, 'Label'), $brandCode, $brands[$brandCode] ?? null,
                    extractXmlValue($lineXml, 'ComplementaryDescription'), $price
                ]);
            }
        }
        
        // Payments
        preg_match_all('/<Get_Payment>(.*?)<\/Get_Payment>/s', $xml, $payments);
        foreach ($payments[1] as $payXml) {
            $db->query("INSERT INTO cegid_sale_payments (doc_key, payment_code, payment_label, amount, currency_id) VALUES (?,?,?,?,?)", [
                $doc['doc_key'], extractXmlValue($payXml, 'Code'), extractXmlValue($payXml, 'Label'),
                extractXmlValue($payXml, 'Amount') ?: 0, extractXmlValue($payXml, 'Currency') ?: 'THB'
            ]);
            $stats['payments']++;
        }
        
        $db->query("UPDATE cegid_sale_documents SET lines_synced = 1 WHERE doc_key = ?", [$doc['doc_key']]);
        $stats['processed']++;
        echo "✅</p>";
        
    } catch (Exception $e) {
        echo "<span style='color:red'>❌</span></p>";
        $stats['errors']++;
    }
    usleep(100000);
}

$elapsed = round(microtime(true) - $start, 2);

echo "<hr><h3>✅ Complete!</h3>";
echo "<table border='1' cellpadding='8'>";
foreach ($stats as $k => $v) echo "<tr><td>{$k}</td><td>{$v}</td></tr>";
echo "<tr><td>Time</td><td>{$elapsed}s</td></tr></table>";

$remaining = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_documents WHERE lines_synced = 0")['cnt'];
if ($remaining > 0) {
    echo "<p><br>⏳ {$remaining} remaining. <a href='?limit={$limit}'>Continue →</a></p>";
}
echo "</div>";
