<?php
/**
 * Cegid Y2 - Sync Sales Data
 * ==========================
 * Usage: sync_sales.php?date_from=2025-12-01&date_to=2025-12-31
 *        sync_sales.php?date_from=2025-12-01&date_to=2025-12-31&headers_only=1
 */
require_once __DIR__ . '/config.php';

class SalesSyncService {
    private $db;
    private $api;
    private $logId;
    private $brandCache = [];
    private $stats = [
        'headers_found' => 0, 'headers_inserted' => 0, 'headers_updated' => 0,
        'lines_synced' => 0, 'payments_synced' => 0, 'products_updated' => 0, 'errors' => 0
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->api = new CegidSoapClient();
        
        // Load brand cache
        $brands = $this->db->fetchAll("SELECT category_code, category_name FROM cegid_categories WHERE category_type_id = 1");
        foreach ($brands as $b) $this->brandCache[$b['category_code']] = $b['category_name'];
    }
    
    public function sync($dateFrom, $dateTo, $syncLines = true) {
        $start = microtime(true);
        $this->startLog('full_sync', $dateFrom, $dateTo);
        
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>🔄 Cegid Sales Sync</h2>";
        echo "<p><strong>Period:</strong> {$dateFrom} to {$dateTo}</p><hr>";
        
        try {
            // Step 1: Headers
            echo "<h3>Step 1: Sync Headers</h3>";
            $this->syncHeaders($dateFrom, $dateTo);
            
            // Step 2: Lines & Payments
            if ($syncLines) {
                echo "<h3>Step 2: Sync Lines & Payments</h3>";
                $this->syncLinesAndPayments();
            }
            
            $this->completeLog($start);
            echo "<hr><h3>✅ Complete!</h3>";
            $this->printStats();
            
        } catch (Exception $e) {
            $this->failLog($e->getMessage());
            echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        echo "</div>";
    }
    
    private function syncHeaders($dateFrom, $dateTo) {
        $page = 1;
        while (true) {
            echo "<p>📥 Page {$page}...</p>"; flush();
            
            $result = $this->api->getSaleHeaders($dateFrom, $dateTo, $page, SYNC_PAGE_SIZE);
            if (!$result['success']) throw new Exception("API Error: HTTP {$result['http_code']}");
            
            $xml = cleanXml($result['response']);
            preg_match_all('/<Get_Header>(.*?)<\/Get_Header>/s', $xml, $matches);
            
            echo "<p>   Found " . count($matches[1]) . " documents</p>";
            
            foreach ($matches[1] as $headerXml) {
                $this->saveHeader($headerXml);
            }
            
            $this->stats['headers_found'] += count($matches[1]);
            
            if (count($matches[1]) < SYNC_PAGE_SIZE) break;
            $page++;
        }
        echo "<p>✅ Total: {$this->stats['headers_found']} headers</p>";
    }
    
    private function saveHeader($xml) {
        // Parse Key
        $type = $stump = $number = '';
        if (preg_match('/<Key>(.*?)<\/Key>/s', $xml, $keyMatch)) {
            $type = extractXmlValue($keyMatch[1], 'Type');
            $stump = extractXmlValue($keyMatch[1], 'Stump');
            $number = extractXmlValue($keyMatch[1], 'Number');
        }
        if (empty($type) || empty($number)) return;
        
        $docKey = "{$type}|{$stump}|{$number}";
        $docDate = extractXmlValue($xml, 'Date');
        // ใช้ strtotime เพื่อแปลงวันที่ให้ถูกต้องตามปฏิทิน (Y-m-d)
        if ($docDate && strtotime($docDate)) {
            $docDate = date('Y-m-d', strtotime($docDate));
        } else {
            // กรณีวันที่มาผิด หรือเป็นค่าว่าง ให้ใช้วันที่ปัจจุบัน (หรือจะแก้เป็น null ก็ได้)
            $docDate = date('Y-m-d');
        }
        
        $sql = "INSERT INTO cegid_sale_documents 
                (doc_key, doc_type, doc_number, doc_stump, store_id, doc_date, customer_id, salesperson_id,
                 currency_id, tax_excluded_amount, tax_included_amount, total_quantity, origin, status, is_active, warehouse_id, synced_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE tax_included_amount=VALUES(tax_included_amount), status=VALUES(status), synced_at=NOW()";
        
        $stmt = $this->db->query($sql, [
            $docKey, $type, $number, $stump,
            extractXmlValue($xml, 'StoreId'),
            $docDate,
            extractXmlValue($xml, 'CustomerId'),
            extractXmlValue($xml, 'SalesPersonId'),
            extractXmlValue($xml, 'CurrencyId') ?: 'THB',
            extractXmlValue($xml, 'TaxExcludedTotalAmount') ?: 0,
            extractXmlValue($xml, 'TaxIncludedTotalAmount') ?: 0,
            extractXmlValue($xml, 'TotalQuantity') ?: 0,
            extractXmlValue($xml, 'Origin'),
            extractXmlValue($xml, 'Status'),
            extractXmlValue($xml, 'Active') === 'true' ? 1 : 0,
            extractXmlValue($xml, 'WarehouseId')
        ]);
        
        if ($stmt->rowCount() === 1) $this->stats['headers_inserted']++;
        else $this->stats['headers_updated']++;
    }
    
    private function syncLinesAndPayments($limit = 100) {
        $docs = $this->db->fetchAll("SELECT doc_key, doc_type, doc_number, doc_stump FROM cegid_sale_documents WHERE lines_synced = 0 ORDER BY doc_date DESC LIMIT ?", [$limit]);
        
        if (empty($docs)) { echo "<p>📋 No pending documents</p>"; return; }
        echo "<p>📋 Syncing " . count($docs) . " documents...</p>";
        
        foreach ($docs as $i => $doc) {
            echo "<p>   [" . ($i+1) . "/" . count($docs) . "] {$doc['doc_key']}...</p>"; flush();
            
            try {
                $result = $this->api->getSaleDetail($doc['doc_type'], $doc['doc_stump'], $doc['doc_number']);
                if (!$result['success'] || strpos($result['response'], 'Fault') !== false) throw new Exception("API Error");
                
                $xml = cleanXml($result['response']);
                
                // Lines
                preg_match_all('/<Get_Line>(.*?)<\/Get_Line>/s', $xml, $lines);
                foreach ($lines[1] as $lineXml) {
                    $this->saveLine($doc['doc_key'], $lineXml);
                    $this->stats['lines_synced']++;
                }
                
                // Payments
                preg_match_all('/<Get_Payment>(.*?)<\/Get_Payment>/s', $xml, $payments);
                foreach ($payments[1] as $payXml) {
                    $this->savePayment($doc['doc_key'], $payXml);
                    $this->stats['payments_synced']++;
                }
                
                $this->db->query("UPDATE cegid_sale_documents SET lines_synced = 1 WHERE doc_key = ?", [$doc['doc_key']]);
                
            } catch (Exception $e) {
                $this->stats['errors']++;
            }
            usleep(100000);
        }
        echo "<p>✅ Lines: {$this->stats['lines_synced']}, Payments: {$this->stats['payments_synced']}</p>";
    }
    
    private function saveLine($docKey, $xml) {
        $itemCode = extractXmlValue($xml, 'ItemCode');
        $brandCode = extractBrandCode($itemCode);
        $qty = (float)(extractXmlValue($xml, 'Quantity') ?: 0);
        $price = (float)(extractXmlValue($xml, 'TaxIncludedNetUnitPrice') ?: 0);
        
        $this->db->query("INSERT INTO cegid_sale_lines 
            (doc_key, line_number, item_id, item_code, item_reference, item_label, complementary_desc, brand_code, quantity, unit_price, net_unit_price, line_total, salesperson_id, origin)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
            $docKey,
            extractXmlValue($xml, 'Rank'),
            extractXmlValue($xml, 'ItemId'),
            $itemCode,
            extractXmlValue($xml, 'ItemReference'),
            extractXmlValue($xml, 'Label'),
            extractXmlValue($xml, 'ComplementaryDescription'),
            $brandCode,
            $qty,
            extractXmlValue($xml, 'TaxIncludedUnitPrice') ?: 0,
            $price,
            $qty * $price,
            extractXmlValue($xml, 'SalesPersonId'),
            extractXmlValue($xml, 'Origin')
        ]);
        
        // Update product master
        $itemId = extractXmlValue($xml, 'ItemId');
        if ($itemId) {
            $this->db->query("INSERT INTO cegid_products (item_id, item_code, item_reference, item_name, brand_code, brand_name, color, unit_price)
                VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE item_name=COALESCE(VALUES(item_name),item_name)", [
                $itemId, $itemCode, extractXmlValue($xml, 'ItemReference'), extractXmlValue($xml, 'Label'),
                $brandCode, $this->brandCache[$brandCode] ?? null, extractXmlValue($xml, 'ComplementaryDescription'), $price
            ]);
            $this->stats['products_updated']++;
        }
    }
    
    private function savePayment($docKey, $xml) {
        $this->db->query("INSERT INTO cegid_sale_payments (doc_key, payment_code, payment_label, amount, cash_amount, currency_id) VALUES (?,?,?,?,?,?)", [
            $docKey,
            extractXmlValue($xml, 'Code'),
            extractXmlValue($xml, 'Label'),
            extractXmlValue($xml, 'Amount') ?: 0,
            extractXmlValue($xml, 'CashAmount') ?: 0,
            extractXmlValue($xml, 'Currency') ?: 'THB'
        ]);
    }
    
    private function startLog($type, $from, $to) {
        $this->db->query("INSERT INTO cegid_sync_logs (sync_type, sync_date_from, sync_date_to, started_at, status) VALUES (?,?,?,NOW(),'running')", [$type, $from, $to]);
        $this->logId = $this->db->lastInsertId();
    }
    
    private function completeLog($start) {
        $this->db->query("UPDATE cegid_sync_logs SET completed_at=NOW(), records_found=?, records_inserted=?, records_updated=?, status='completed', execution_time=? WHERE id=?",
            [$this->stats['headers_found'], $this->stats['headers_inserted'], $this->stats['lines_synced'], round(microtime(true) - $start, 2), $this->logId]);
    }
    
    private function failLog($error) {
        $this->db->query("UPDATE cegid_sync_logs SET completed_at=NOW(), status='failed', error_message=? WHERE id=?", [$error, $this->logId]);
    }
    
    private function printStats() {
        echo "<table border='1' cellpadding='8'>";
        foreach ($this->stats as $k => $v) echo "<tr><td>" . ucwords(str_replace('_', ' ', $k)) . "</td><td>{$v}</td></tr>";
        echo "</table>";
    }
}

// Main
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$headersOnly = isset($_GET['headers_only']);

$sync = new SalesSyncService();
$sync->sync($dateFrom, $dateTo, !$headersOnly);
