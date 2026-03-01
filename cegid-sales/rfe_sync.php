<?php
/**
 * RFE Auto-Sync: Download CSVs from Azure Blob Storage and import
 * Usage: php rfe_sync.php "SAS_URL"
 * 
 * SAS_URL example: https://dat842901sta003cfe.blob.core.windows.net/90643827-90643827-001-prod-th?sv=...&sig=...
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

date_default_timezone_set('Asia/Bangkok');

$sas_url = $argv[1] ?? '';
if (empty($sas_url)) {
    die("Usage: php rfe_sync.php \"SAS_URL\"\n");
}

// Parse SAS URL
if (!preg_match('/^(https:\/\/[^?]+)\?(.+)$/', $sas_url, $m)) {
    die("Invalid SAS URL format\n");
}
$base_url = $m[1];
$sas_params = $m[2];

// Save SAS key for cron use
$sas_file = __DIR__ . '/.rfe_sas_key';
file_put_contents($sas_file, json_encode([
    'url' => $sas_url,
    'base_url' => $base_url,
    'sas_params' => $sas_params,
    'saved_at' => date('Y-m-d H:i:s'),
    'expires' => extractExpiry($sas_params),
]));
chmod($sas_file, 0600);

log_msg("=== RFE Sync Started ===");
log_msg("SAS expires: " . extractExpiry($sas_params));

// List blobs
$list_url = $base_url . "?restype=container&comp=list&" . $sas_params;
$xml = @file_get_contents($list_url);
if (!$xml) {
    die("ERROR: Cannot list blobs. SAS key may be expired.\n");
}

// Parse blob names
preg_match_all('/<Name>(.*?)<\/Name>/', $xml, $matches);
$blobs = $matches[1] ?? [];
log_msg("Found " . count($blobs) . " blobs in container");

// Get today's date prefix (YYYYMMDD)
$today = date('Ymd');
$yesterday = date('Ymd', strtotime('-1 day'));

// Files to download
$target_files = [
    'sale by item' => 'import_sale_by_item',
    'daily sale palexy' => 'import_palexy', 
    'Stock All' => null, // download only, no import yet
    'sale payment daily' => null,
    'loyalty today' => null,
];

$downloaded = 0;
$imported = 0;
$download_dir = '/tmp/rfe_downloads';
@mkdir($download_dir, 0755, true);

// Download latest files (today or yesterday)
foreach ($blobs as $blob) {
    if (!preg_match('/^out\/(\d{8})(.+)\.CSV$/', $blob, $bm)) continue;
    
    $file_date = $bm[1];
    $file_type = trim($bm[2]);
    
    // Only download today or yesterday
    if ($file_date !== $today && $file_date !== $yesterday) continue;
    
    // Check if it's a file we care about
    $import_func = null;
    foreach ($target_files as $pattern => $func) {
        if (stripos($file_type, $pattern) !== false || $file_type === $pattern) {
            $import_func = $func;
            break;
        }
    }
    
    $local_file = "$download_dir/{$file_date}_{$file_type}.csv";
    $blob_url = $base_url . "/" . rawurlencode("out/{$file_date}{$file_type}.CSV") . "?" . $sas_params;
    // Fix double encoding issue
    $blob_url = str_replace('%2F', '/', $blob_url);
    
    log_msg("Downloading: {$file_date}{$file_type}.CSV");
    
    $content = @file_get_contents($blob_url);
    if ($content === false) {
        // Try with space encoding
        $encoded_name = str_replace(' ', '%20', "out/{$file_date}{$file_type}.CSV");
        $blob_url = $base_url . "/" . $encoded_name . "?" . $sas_params;
        $content = @file_get_contents($blob_url);
    }
    
    if ($content === false) {
        log_msg("  FAILED to download");
        continue;
    }
    
    file_put_contents($local_file, $content);
    $size = round(strlen($content) / 1024 / 1024, 1);
    log_msg("  Downloaded: {$size}MB → $local_file");
    $downloaded++;
    
    // Import if handler exists
    if ($import_func && function_exists($import_func)) {
        $count = $import_func($local_file, $file_date);
        log_msg("  Imported: $count records");
        $imported += $count;
    }
}

log_msg("=== Done: Downloaded $downloaded files, Imported $imported records ===");

// ============ Import Functions ============

function import_sale_by_item($file, $date_prefix) {
    try {
        $db = new PDO(
            'mysql:host=localhost;dbname=ulgcegid;charset=utf8mb4',
            DB_USER_CEGID ?? 'ulgcegid', DB_PASS_CEGID ?? '#wmIYH3wazaa',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $handle = fopen($file, 'r');
        if (!$handle) return 0;
        
        // Skip BOM
        $bom = fread($handle, 2);
        if ($bom !== "\xFF\xFE") rewind($handle);
        stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
        
        // Skip header
        fgetcsv($handle, 0, ',');
        
        $bmStmt = $db->prepare('REPLACE INTO barcode_mapping (barcode, brand, group_name, class_name, size_name) VALUES (?, ?, ?, ?, ?)');
        $imStmt = $db->prepare('
            INSERT INTO item_master (item_code, brand, item_group, class_name, size_name, item_description, color, barcode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                brand = IF(VALUES(brand) != "", VALUES(brand), brand),
                item_group = IF(VALUES(item_group) != "", VALUES(item_group), item_group),
                class_name = IF(VALUES(class_name) != "", VALUES(class_name), class_name)
        ');
        
        $seen_bc = $seen_ic = [];
        $count = 0;
        $db->beginTransaction();
        
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count($data) < 28) continue;
            
            $itemcode = trim($data[13] ?? '');
            $barcode = trim($data[14] ?? '');
            $desc = trim($data[16] ?? '');
            $color = trim($data[18] ?? '');
            $brand = trim($data[23] ?? '');
            $group = trim($data[25] ?? '');
            $class = trim($data[26] ?? '');
            $size = trim($data[27] ?? '');
            
            if (!empty($barcode) && !isset($seen_bc[$barcode])) {
                $bmStmt->execute([$barcode, $brand, $group, $class, $size]);
                $seen_bc[$barcode] = 1;
                $count++;
            }
            if (!empty($itemcode) && !isset($seen_ic[$itemcode])) {
                $imStmt->execute([$itemcode, $brand, $group, $class, $size, $desc, $color, $barcode]);
                $seen_ic[$itemcode] = 1;
            }
        }
        
        $db->commit();
        fclose($handle);
        return $count;
    } catch (Exception $e) {
        log_msg("  ERROR import_sale_by_item: " . $e->getMessage());
        return 0;
    }
}

function import_palexy($file, $date_prefix) {
    try {
        $db = new PDO(
            'mysql:host=localhost;dbname=cmbase;charset=utf8mb4',
            'cmbase', '#wmIYH3wazaa',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $handle = fopen($file, 'r');
        if (!$handle) return 0;
        
        $bom = fread($handle, 2);
        if ($bom !== "\xFF\xFE") rewind($handle);
        stream_filter_append($handle, 'convert.iconv.UTF-16LE/UTF-8');
        fgetcsv($handle, 0, ','); // skip header
        
        $stmt = $db->prepare("
            INSERT INTO daily_sales 
            (sale_date, store_code, internal_ref, sales_division, brand, group_name, class_name, 
             line_barcode, item_description, customer, member, first_name, last_name, size, 
             qty, base_price, tax_incl_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $count = 0;
        $db->beginTransaction();
        
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count($data) < 27) continue;
            
            $date_str = trim($data[0]);
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_str, $dm)) {
                $date = sprintf('%04d-%02d-%02d', $dm[3], $dm[2], $dm[1]);
            } else {
                continue;
            }
            
            $store = trim($data[1]);
            if (empty($store)) continue;
            
            $customer = trim($data[12] ?? '');
            $member = '';
            if (!empty($customer)) {
                $member = preg_match('/^\d+$/', $customer) ? 'ULG Member' : $customer;
            }
            
            try {
                $stmt->execute([
                    $date, $store, trim($data[4] ?? ''), trim($data[6] ?? ''),
                    trim($data[17] ?? ''), trim($data[18] ?? ''), trim($data[19] ?? ''),
                    trim($data[8] ?? ''), trim($data[10] ?? ''), $customer, $member,
                    trim($data[15] ?? ''), trim($data[14] ?? ''), trim($data[21] ?? ''),
                    intval(str_replace(',', '', $data[22] ?? '0')),
                    floatval(str_replace(',', '', $data[24] ?? '0')),
                    floatval(str_replace(',', '', $data[26] ?? '0'))
                ]);
                $count++;
            } catch (Exception $e) {
                // Skip duplicate rows
            }
        }
        
        $db->commit();
        fclose($handle);
        
        // Also sync to ulgcegid barcode_mapping
        syncFromCmbase($date, $db);
        
        return $count;
    } catch (Exception $e) {
        log_msg("  ERROR import_palexy: " . $e->getMessage());
        return 0;
    }
}

function syncFromCmbase($date, $cmDb) {
    try {
        $cegidDb = new PDO(
            'mysql:host=localhost;dbname=ulgcegid;charset=utf8mb4',
            'ulgcegid', '#wmIYH3wazaa',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $items = $cmDb->prepare('
            SELECT DISTINCT line_barcode, brand, group_name, class_name, size
            FROM daily_sales WHERE sale_date = ? AND line_barcode IS NOT NULL AND line_barcode != ""
        ');
        $items->execute([$date]);
        
        $bmStmt = $cegidDb->prepare('REPLACE INTO barcode_mapping (barcode, brand, group_name, class_name, size_name) VALUES (?, ?, ?, ?, ?)');
        
        foreach ($items->fetchAll() as $r) {
            $bmStmt->execute([$r['line_barcode'], $r['brand'], $r['group_name'], $r['class_name'], $r['size']]);
        }
    } catch (Exception $e) {
        log_msg("  syncFromCmbase error: " . $e->getMessage());
    }
}

// ============ Helpers ============

function extractExpiry($params) {
    parse_str($params, $p);
    return urldecode($p['se'] ?? 'unknown');
}

function log_msg($msg) {
    $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
    echo $line;
    @file_put_contents('/var/log/rfe-sync.log', $line, FILE_APPEND);
}
