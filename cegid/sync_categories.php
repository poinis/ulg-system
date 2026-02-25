<?php
/**
 * Cegid Y2 - Sync Categories
 * ==========================
 * Sync Brand, Collection, Class, Style, etc.
 * 
 * Usage: sync_categories.php OR sync_categories.php?type=1 (Brand only)
 */
require_once __DIR__ . '/config.php';

class CategorySyncService {
    private $db;
    private $api;
    private $stats = ['found' => 0, 'inserted' => 0, 'updated' => 0];
    
    private $categoryTypes = [
        1 => 'Brand',
        2 => 'Collection',
        3 => 'Class',
        4 => 'SubClass',
        5 => 'Style',
        7 => 'Origin',
        8 => 'Department'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->api = new CegidSoapClient();
    }
    
    public function syncAll() {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>🏷️ Sync Product Categories</h2><hr>";
        
        $start = microtime(true);
        foreach ($this->categoryTypes as $id => $name) {
            $this->syncCategoryType($id, $name);
        }
        
        echo "<hr><h3>✅ Complete! (" . round(microtime(true) - $start, 2) . "s)</h3>";
        echo "<table border='1' cellpadding='8'>";
        foreach ($this->stats as $k => $v) echo "<tr><td>{$k}</td><td>{$v}</td></tr>";
        echo "</table></div>";
    }
    
    public function syncCategoryType($typeId, $typeName = null) {
        $typeName = $typeName ?? ($this->categoryTypes[$typeId] ?? "Type_{$typeId}");
        echo "<h3>📂 {$typeName}...</h3>";
        
        $page = 1;
        $total = 0;
        
        while (true) {
            $result = $this->api->getCategoryValues($typeId, $page, 500);
            if (!$result['success']) { echo "<p style='color:red'>❌ API Error</p>"; break; }
            
            $xml = cleanXml($result['response']);
            preg_match_all('/<Value>(.*?)<\/Value>/s', $xml, $matches);
            
            if (empty($matches[1])) break;
            
            foreach ($matches[1] as $valueXml) {
                $code = extractXmlValue($valueXml, 'Id');
                $name = extractXmlValue($valueXml, 'Description') ?? ''; 
                $short = extractXmlValue($valueXml, 'ShortDescription') ?? '';
                if ($code) {
                    $this->db->query("INSERT INTO cegid_categories (category_type_id, category_type_name, category_code, category_name, short_name, synced_at)
                        VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE category_name=VALUES(category_name), synced_at=NOW()",
                        [$typeId, $typeName, $code, $name, $short]);
                    $total++;
                }
            }
            
            $this->stats['found'] += count($matches[1]);
            
            // Check pagination
            if (preg_match('/<To>(\d+)<\/To>.*?<Count>(\d+)<\/Count>/s', $xml, $m)) {
                if ((int)$m[1] >= (int)$m[2]) break;
            } else break;
            
            $page++;
        }
        
        echo "<p>✅ {$total} values synced</p>";
    }
}

// Main
$sync = new CategorySyncService();
if (isset($_GET['type'])) {
    echo "<div style='font-family:monospace;padding:20px'>";
    $sync->syncCategoryType((int)$_GET['type']);
    echo "</div>";
} else {
    $sync->syncAll();
}
