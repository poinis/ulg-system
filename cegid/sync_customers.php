<?php
/**
 * Cegid Y2 - Sync Customers & Loyalty (Regex Fix)
 * ===============================================
 * - Fixes XML parsing issue (handles attributes/spaces in tags)
 * - Robust BirthDate parsing
 */
require_once __DIR__ . '/config.php';

class CustomerSyncService {
    private $db;
    private $api;
    private $stats = ['found' => 0, 'synced' => 0, 'loyalty' => 0, 'cards' => 0, 'errors' => 0];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->api = new CegidSoapClient();
    }
    
    public function syncFromSales($limit = 100) {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>👥 Sync Customers from Sales</h2><hr>";
        
        $start = microtime(true);
        
        // Get customer IDs from sales not yet in customers table
        $sql = "SELECT DISTINCT d.customer_id FROM cegid_sale_documents d 
                LEFT JOIN cegid_customers c ON d.customer_id = c.customer_id
                WHERE d.customer_id IS NOT NULL AND d.customer_id != '' AND c.customer_id IS NULL LIMIT ?";
        
        $customerIds = array_column($this->db->fetchAll($sql, [$limit]), 'customer_id');
        
        if (empty($customerIds)) {
            echo "<p>✅ No new customers to sync.</p></div>";
            return;
        }
        
        echo "<p>📋 Found " . count($customerIds) . " new customers. Starting sync...</p>";
        
        $this->fetchAndSaveCustomers($customerIds);
        
        echo "<hr><h3>✅ Complete! (" . round(microtime(true) - $start, 2) . "s)</h3>";
        $this->printStats();
        echo "</div>";
    }
    
    private function fetchAndSaveCustomers($customerIds) {
        $count = 0;
        foreach ($customerIds as $id) {
            // 1. Call API
            $result = $this->api->getCustomerDetail($id);
            
            // 2. Handle API Errors
            if (!$result['success']) {
                echo "<p style='color:orange'>⚠️ Failed to fetch customer <strong>{$id}</strong>: HTTP {$result['http_code']}</p>";
                $this->stats['errors']++;
                continue;
            }
            
            $xml = cleanXml($result['response']);
            
            // 3. Parse Result (Robust Regex for attributes/spaces)
            // 🔥 แก้ไข: ใช้ [^>]* เพื่อดักจับ Tag ที่อาจมี Attribute หรือช่องว่าง
            if (preg_match('/<GetCustomerDetailResult[^>]*>(.*?)<\/GetCustomerDetailResult>/s', $xml, $match)) {
                $this->saveCustomer($match[1]);
                $this->stats['synced']++;
                $count++;
            } else {
                echo "<p style='color:red'>❌ XML Parsed but tag not found for ID: $id</p>";
                // Debug: Show what the XML actually looks like if it fails
                echo "<textarea style='width:100%;height:60px;font-size:10px;'>" . htmlspecialchars(substr($xml, 0, 1000)) . "</textarea>";
            }
            
            usleep(50000); 
        }
        echo "<p>   ✅ Successfully synced: $count customers</p>";
        $this->stats['found'] += count($customerIds);
    }
    
    private function saveCustomer($xml) {
        $customerId = extractXmlValue($xml, 'CustomerId');
        // Fallback: Try getting ID from <Id> tag if CustomerId is empty
        if (empty($customerId)) $customerId = extractXmlValue($xml, 'Id');
        
        if (empty($customerId)) return;
        
        $safeVal = function($tag) use ($xml) { return extractXmlValue($xml, $tag) ?? ''; };

        // Parse Phone Data
        $phone = ''; $mobile = '';
        if (preg_match('/<PhoneData[^>]*>(.*?)<\/PhoneData>/s', $xml, $pMatch)) {
            $phone = extractXmlValue($pMatch[1], 'HomePhoneNumber') ?? '';
            $mobile = extractXmlValue($pMatch[1], 'CellularPhoneNumber') ?? '';
        }
        
        // Parse Email Data
        $email = '';
        if (preg_match('/<EmailData[^>]*>(.*?)<\/EmailData>/s', $xml, $eMatch)) {
            $email = extractXmlValue($eMatch[1], 'Email') ?? '';
        }
        
        // Parse Address
        $address = ''; $city = ''; $postalCode = ''; $country = '';
        if (preg_match('/<AddressData[^>]*>(.*?)<\/AddressData>/s', $xml, $aMatch)) {
            $line1 = extractXmlValue($aMatch[1], 'AddressLine1') ?? '';
            $line2 = extractXmlValue($aMatch[1], 'AddressLine2') ?? '';
            $address = trim("$line1 $line2");
            $city = extractXmlValue($aMatch[1], 'City') ?? '';
            $postalCode = extractXmlValue($aMatch[1], 'ZipCode') ?? '';
            $country = extractXmlValue($aMatch[1], 'CountryId') ?? '';
        }
        
        // Parse BirthDate (Handles split fields)
        $birthDate = null;
        if (preg_match('/<BirthDateData[^>]*>(.*?)<\/BirthDateData>/s', $xml, $bMatch)) {
            $d = extractXmlValue($bMatch[1], 'BirthDateDay');
            $m = extractXmlValue($bMatch[1], 'BirthDateMonth');
            $y = extractXmlValue($bMatch[1], 'BirthDateYear');
            if ($d && $m && $y && $y != '0') {
                $birthDate = sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
        
        $firstName = $safeVal('FirstName');
        $lastName = $safeVal('LastName');

        $sql = "INSERT INTO cegid_customers 
                (customer_id, customer_code, title, first_name, last_name, full_name, email, phone, mobile, birth_date, gender, address, city, postal_code, country, is_closed, synced_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), email=VALUES(email), mobile=VALUES(mobile), synced_at=NOW()";
        
        $this->db->query($sql, [
            $customerId,
            $safeVal('Code'), 
            $safeVal('TitleId'),
            $firstName, 
            $lastName, 
            trim("{$firstName} {$lastName}"),
            $email,
            $phone, 
            $mobile, 
            $birthDate,
            $safeVal('Sex'),
            $address, 
            $city, 
            $postalCode, 
            $country,
            $safeVal('Closed') === 'true' ? 1 : 0
        ]);
    }
    
    public function searchByPhone($phone) {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>🔍 Search: {$phone}</h2><hr>";
        
        $result = $this->api->searchCustomers(['phone' => $phone, 'MaxNumberOfCustomers' => 10]);
        if (!$result['success']) {
            echo "<p style='color:red'>❌ API Error</p></div>";
            return;
        }
        
        $xml = cleanXml($result['response']);
        preg_match_all('/<string>([^<]+)<\/string>/s', $xml, $matches);
        
        if (empty($matches[1])) {
            echo "<p>No customers found</p></div>";
            return;
        }
        
        echo "<p>Found " . count($matches[1]) . " customer(s)</p>";
        $this->fetchAndSaveCustomers($matches[1]);
        
        foreach ($matches[1] as $customerId) {
            $this->syncLoyaltyForCustomer($customerId);
        }
        echo "<hr><h3>✅ Complete!</h3>";
        $this->printStats();
        echo "</div>";
    }
    
    public function syncAllLoyalty($limit = 100) {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>🎯 Sync Loyalty Points</h2><hr>";
        
        $customers = $this->db->fetchAll("SELECT customer_id FROM cegid_customers WHERE is_closed = 0 ORDER BY synced_at DESC LIMIT ?", [$limit]);
        
        if (empty($customers)) {
            echo "<p>No customers to sync</p></div>";
            return;
        }
        
        echo "<p>📋 Syncing " . count($customers) . " customers...</p>";
        foreach ($customers as $i => $c) {
            if (($i + 1) % 10 === 0) { echo "<p>Progress: " . ($i + 1) . "/" . count($customers) . "</p>"; flush(); }
            $this->syncLoyaltyForCustomer($c['customer_id']);
            usleep(100000);
        }
        
        echo "<hr><h3>✅ Complete!</h3>";
        $this->printStats();
        echo "</div>";
    }
    
    private function syncLoyaltyForCustomer($customerId) {
        $loyalty = $this->api->getLoyaltyInfo($customerId);
        
        $points = 0;
        if ($loyalty['points_response']['success']) {
            $xml = cleanXml($loyalty['points_response']['response']);
            if (preg_match('/<GetCustomerAvailableLoyaltyPointsResult[^>]*>([^<]+)<\/GetCustomerAvailableLoyaltyPointsResult>/', $xml, $m)) {
                $points = (float)$m[1];
            } elseif (preg_match('/<AvailablePoints[^>]*>([^<]+)<\/AvailablePoints>/', $xml, $m)) {
                $points = (float)$m[1];
            }
        }
        
        $tier = $cardNumber = null;
        if ($loyalty['cards_response']['success']) {
            $xml = cleanXml($loyalty['cards_response']['response']);
            if (preg_match('/<CardHeaderGet[^>]*>(.*?)<\/CardHeaderGet>/s', $xml, $cardMatch)) {
                $cardNumber = extractXmlValue($cardMatch[1], 'Id');
                
                $programXml = '';
                if (preg_match('/<Program[^>]*>(.*?)<\/Program>/s', $cardMatch[1], $progMatch)) {
                    $programXml = $progMatch[1];
                    $tier = extractXmlValue($programXml, 'Label');
                }

                $startDate = null; $expiryDate = null;
                $startDateRaw = extractXmlValue($cardMatch[1], 'ActivationDate');
                $expiryDateRaw = extractXmlValue($cardMatch[1], 'ExpiracyDate');
                
                if ($startDateRaw && substr($startDateRaw, 0, 4) !== '0001') $startDate = date('Y-m-d', strtotime($startDateRaw));
                if ($expiryDateRaw && substr($expiryDateRaw, 0, 4) !== '0001') $expiryDate = date('Y-m-d', strtotime($expiryDateRaw));
                
                $this->db->query("INSERT INTO cegid_customer_cards (customer_id, card_number, card_type, tier_name, start_date, expiry_date, synced_at)
                    VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE tier_name=VALUES(tier_name), synced_at=NOW()", [
                    $customerId, $cardNumber, 'Loyalty', $tier, $startDate, $expiryDate
                ]);
                $this->stats['cards']++;
            }
        }
        
        $this->db->query("UPDATE cegid_customers SET loyalty_points = ?, tier = ?, card_number = ? WHERE customer_id = ?",
            [$points, $tier, $cardNumber, $customerId]);
        $this->stats['loyalty']++;
    }
    
    public function updatePurchaseStats() {
        echo "<div style='font-family:monospace;padding:20px'>";
        echo "<h2>📊 Update Purchase Stats</h2><hr>";
        $sql = "UPDATE cegid_customers c SET 
                total_purchases = COALESCE((SELECT SUM(tax_included_amount) FROM cegid_sale_documents WHERE customer_id = c.customer_id AND is_active = 1), 0),
                visit_count = COALESCE((SELECT COUNT(DISTINCT doc_key) FROM cegid_sale_documents WHERE customer_id = c.customer_id AND is_active = 1), 0),
                first_purchase_date = (SELECT MIN(doc_date) FROM cegid_sale_documents WHERE customer_id = c.customer_id AND is_active = 1),
                last_purchase_date = (SELECT MAX(doc_date) FROM cegid_sale_documents WHERE customer_id = c.customer_id AND is_active = 1)";
        $stmt = $this->db->query($sql);
        echo "<p>✅ Updated " . $stmt->rowCount() . " customers</p></div>";
    }
    
    private function printStats() {
        echo "<table border='1' cellpadding='8'>";
        foreach ($this->stats as $k => $v) echo "<tr><td>{$k}</td><td>{$v}</td></tr>";
        echo "</table>";
    }
}

// Main Execution
$sync = new CustomerSyncService();
if (isset($_GET['phone'])) {
    $sync->searchByPhone($_GET['phone']);
} elseif (isset($_GET['loyalty'])) {
    $sync->syncAllLoyalty((int)($_GET['limit'] ?? 100));
} elseif (isset($_GET['stats'])) {
    $sync->updatePurchaseStats();
} else {
    $sync->syncFromSales((int)($_GET['limit'] ?? 100));
}
?>