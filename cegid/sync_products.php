<?php
/**
 * Cegid Y2 - Sync Product Master Data (Group, Class, Size)
 * ========================================================
 * Usage: sync_products.php?limit=50
 */
require_once __DIR__ . '/config.php';

class ProductSyncService {
    private $db;
    private $api;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->api = new CegidSoapClient();
    }
    
    public function syncMissingDetails($limit = 50) {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>👕 Sync Product Details (Group, Class, Size)</h2><hr>";
        
        // 1. หาสินค้าที่ยังไม่มีข้อมูล Group หรือ Size
        // เราจะดึงเฉพาะสินค้าที่มีอยู่ในรายการขายแล้ว (จาก sync_sales) เพื่อไม่ให้เสียเวลาดึงทั้งระบบ
        $sql = "SELECT item_id, item_code FROM cegid_products 
                WHERE (group_name IS NULL OR size_name IS NULL) 
                AND item_id != '' 
                LIMIT ?";
        
        $products = $this->db->fetchAll($sql, [$limit]);
        
        if (empty($products)) {
            echo "<p>✅ สินค้าทุกตัวมีข้อมูลครบถ้วนแล้ว (No pending products)</p></div>";
            return;
        }
        
        echo "<p>📦 พบสินค้าที่ต้องอัปเดตข้อมูล: " . count($products) . " รายการ</p>";
        
        $count = 0;
        foreach ($products as $prod) {
            $this->fetchAndUpdateProduct($prod['item_id'], $prod['item_code']);
            $count++;
            usleep(100000); // พัก 0.1 วิ กันเซิร์ฟเวอร์ค้าง
        }
        
        echo "<hr><h3>✅ อัปเดตเสร็จสิ้น {$count} รายการ</h3></div>";
    }
    
    private function fetchAndUpdateProduct($itemId, $itemCode) {
        // ใช้ GetByKey ของ Product Service (ต้องเช็คว่าใน config.php มี CEGID_SERVICE_PRODUCT ถูกต้อง)
        // หมายเหตุ: โค้ดนี้สมมติว่าใช้ GetByKey กับ ProductMerchandiseItemsService
        // ถ้า API จริงใช้ชื่ออื่น ต้องปรับตาม SOAPUI (เช่น GetDetail)
        
        $soapBody = '
        <ns:GetByKey>
            <ns:key>
                <ns:Id>' . htmlspecialchars($itemId) . '</ns:Id>
            </ns:key>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetByKey>';
        
        $result = $this->api->request(CEGID_SERVICE_PRODUCT, 'http://www.cegid.fr/Retail/1.0/IProductMerchandiseItemsService/GetByKey', $soapBody);
        
        if (!$result['success']) {
            echo "<p style='color:orange'>⚠️ ดึงข้อมูลไม่สำเร็จ: {$itemCode} (HTTP {$result['http_code']})</p>";
            return;
        }
        
        $xml = cleanXml($result['response']);
        
        // --- เริ่มแกะข้อมูล ---
        // 1. Group / Family
        $groupCode = ''; $groupName = '';
        if (preg_match('/<FamilyId>(.*?)<\/FamilyId>/', $xml, $m)) $groupCode = $m[1];
        if (preg_match('/<FamilyLabel>(.*?)<\/FamilyLabel>/', $xml, $m)) $groupName = $m[1];
        
        // 2. Class / SubFamily
        $classCode = ''; $className = '';
        if (preg_match('/<SubFamilyId>(.*?)<\/SubFamilyId>/', $xml, $m)) $classCode = $m[1];
        if (preg_match('/<SubFamilyLabel>(.*?)<\/SubFamilyLabel>/', $xml, $m)) $className = $m[1];
        
        // 3. Size / Dimension (Cegid มักเก็บไซส์ใน Dimension1, Dimension2 หรือ Grid)
        $sizeCode = ''; $sizeName = '';
        // ลองหาจาก Dimension 1
        if (preg_match('/<Dimension1Id>(.*?)<\/Dimension1Id>/', $xml, $m)) $sizeCode = $m[1];
        if (preg_match('/<Dimension1Label>(.*?)<\/Dimension1Label>/', $xml, $m)) $sizeName = $m[1];
        
        // ถ้า Dimension 1 ว่าง ให้ลองดู Dimension 2 (เผื่อเป็นสี/ไซส์สลับกัน)
        if (empty($sizeName)) {
             if (preg_match('/<Dimension2Label>(.*?)<\/Dimension2Label>/', $xml, $m)) $sizeName = $m[1];
        }

        // กรณีไม่มี Label ให้ใช้ Code แทน
        if (empty($groupName)) $groupName = $groupCode;
        if (empty($className)) $className = $classCode;
        if (empty($sizeName)) $sizeName = $sizeCode;
        
        // บันทึกลงฐานข้อมูล
        $sql = "UPDATE cegid_products SET 
                group_code = ?, group_name = ?,
                class_code = ?, class_name = ?,
                size_code = ?, size_name = ?
                WHERE item_id = ?";
                
        $this->db->query($sql, [
            $groupCode, $groupName,
            $classCode, $className,
            $sizeCode, $sizeName,
            $itemId
        ]);
        
        // echo "<p>Updated: {$itemCode} -> Grp:{$groupName}, Cls:{$className}, Sz:{$sizeName}</p>";
    }
}

// Run
$sync = new ProductSyncService();
$sync->syncMissingDetails((int)($_GET['limit'] ?? 50));
?>