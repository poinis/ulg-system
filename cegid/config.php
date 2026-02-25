<?php
/**
 * Cegid Y2 Integration - Configuration (FULL VERSION)
 * ===================================================
 * Updated: Support Sales, Products, Customers, Loyalty
 */

// =====================================================
// DATABASE SETTINGS
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ulgcegid');
define('DB_USER', 'ulgcegid');
define('DB_PASS', '#wmIYH3wazaa');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// CEGID API SETTINGS
// =====================================================
define('CEGID_BASE_URL', 'https://90643827-test-retail-ondemand.cegid.cloud');
define('CEGID_DOMAIN', '90643827_002_TEST');
define('CEGID_USERNAME', 'frt');
define('CEGID_PASSWORD', 'adgjm');
define('CEGID_FULL_USERNAME', CEGID_DOMAIN . '\\' . CEGID_USERNAME);

// Service URLs
define('CEGID_SERVICE_SALE', '/Y2/SaleDocumentService.svc');
define('CEGID_SERVICE_PRODUCT', '/Y2/ProductMerchandiseItemsService.svc');
define('CEGID_SERVICE_CUSTOMER', '/Y2/CustomerWcfService.svc');
define('CEGID_SERVICE_LOYALTY', '/Y2/LoyaltyWcfService.svc');
define('CEGID_SERVICE_CATEGORIES', '/Y2/ProductCategoriesService.svc');

// =====================================================
// SYNC SETTINGS
// =====================================================
define('SYNC_PAGE_SIZE', 500);
define('SYNC_TIMEOUT', 120);
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);

// =====================================================
// DATABASE CLASS
// =====================================================
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    
    public function getConnection() { return $this->pdo; }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) { return $this->query($sql, $params)->fetchAll(); }
    public function fetchOne($sql, $params = []) { return $this->query($sql, $params)->fetch(); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }
}

// =====================================================
// CEGID SOAP CLIENT CLASS
// =====================================================
class CegidSoapClient {
    private $baseUrl;
    private $credentials;
    
    public function __construct() {
        $this->baseUrl = CEGID_BASE_URL;
        $this->credentials = CEGID_FULL_USERNAME . ':' . CEGID_PASSWORD;
    }
    
    public function request($endpoint, $soapAction, $soapBody) {
        $url = $this->baseUrl . $endpoint;
        $soapEnvelope = '<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.cegid.fr/Retail/1.0" xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
    <soapenv:Header/>
    <soapenv:Body>' . $soapBody . '</soapenv:Body>
</soapenv:Envelope>';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
                'Accept: text/xml'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_TIMEOUT => SYNC_TIMEOUT,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->credentials,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }

    // --- SALES ---
    public function getSaleHeaders($dateFrom, $dateTo, $pageIndex = 1, $pageSize = 500) {
        $soapBody = '
        <ns:GetHeaderList>
            <ns:searchRequest>
                <ns:BeginDate>' . $dateFrom . '</ns:BeginDate>
                <ns:EndDate>' . $dateTo . '</ns:EndDate>
                <ns:DocumentTypes><ns:SaleDocumentType>Receipt</ns:SaleDocumentType></ns:DocumentTypes>
                <ns:Pager>
                    <ns:PageIndex>' . $pageIndex . '</ns:PageIndex>
                    <ns:PageSize>' . $pageSize . '</ns:PageSize>
                </ns:Pager>
            </ns:searchRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetHeaderList>';
        return $this->request(CEGID_SERVICE_SALE, 'http://www.cegid.fr/Retail/1.0/ISaleDocumentService/GetHeaderList', $soapBody);
    }
    
    public function getSaleDetail($type, $stump, $number) {
        $soapBody = '
        <ns:GetByKey>
            <ns:searchRequest>
                <ns:Key>
                    <ns:Number>' . htmlspecialchars($number) . '</ns:Number>
                    <ns:Stump>' . htmlspecialchars($stump) . '</ns:Stump>
                    <ns:Type>' . htmlspecialchars($type) . '</ns:Type>
                </ns:Key>
            </ns:searchRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetByKey>';
        return $this->request(CEGID_SERVICE_SALE, 'http://www.cegid.fr/Retail/1.0/ISaleDocumentService/GetByKey', $soapBody);
    }

    // --- CATEGORIES ---
    public function getCategoryValues($categoryId, $pageIndex = 1, $pageSize = 500) {
        $soapBody = '
        <ns:GetValues>
            <ns:Request>
                <ns:CategoryId>' . $categoryId . '</ns:CategoryId>
                <ns:Paging><ns:PageIndex>' . $pageIndex . '</ns:PageIndex><ns:PageSize>' . $pageSize . '</ns:PageSize></ns:Paging>
            </ns:Request>
            <ns:Context><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:Context>
        </ns:GetValues>';
        return $this->request(CEGID_SERVICE_CATEGORIES, 'http://www.cegid.fr/Retail/1.0/ICategoriesWebService/GetValues', $soapBody);
    }

    // --- CUSTOMERS ---
    public function searchCustomers($criteria = []) {
        $searchXML = '';
        if (isset($criteria['MaxNumberOfCustomers'])) $searchXML .= '<ns:MaxNumberOfCustomers>' . $criteria['MaxNumberOfCustomers'] . '</ns:MaxNumberOfCustomers>';
        else $searchXML .= '<ns:MaxNumberOfCustomers>500</ns:MaxNumberOfCustomers>';

        if (isset($criteria['phone'])) {
            $searchXML .= '<ns:PhoneData><ns:CellularPhoneNumber>' . htmlspecialchars($criteria['phone']) . '</ns:CellularPhoneNumber></ns:PhoneData>';
        }
        $searchXML .= '<ns:Closed>false</ns:Closed>';

        $soapBody = '
        <ns:SearchCustomerIds>
            <ns:searchData>' . $searchXML . '</ns:searchData>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:SearchCustomerIds>';
        return $this->request(CEGID_SERVICE_CUSTOMER, 'http://www.cegid.fr/Retail/1.0/ICustomerWcfService/SearchCustomerIds', $soapBody);
    }
    
    public function getCustomerDetails($customerIds) {
        $idsXml = '';
        foreach ($customerIds as $id) {
            $idsXml .= '<arr:string>' . htmlspecialchars($id) . '</arr:string>';
        }
        
        $soapBody = '
        <ns:GetCustomers>
            <ns:getCustomersData>
                <ns:CustomerIds>' . $idsXml . '</ns:CustomerIds>
            </ns:getCustomersData>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetCustomers>';
        return $this->request(CEGID_SERVICE_CUSTOMER, 'http://www.cegid.fr/Retail/1.0/ICustomerWcfService/GetCustomers', $soapBody);
    }

    // --- LOYALTY (POINTS & CARDS) ---
    public function getLoyaltyInfo($customerId) {
        // 1. Get Points
        $soapBodyPoints = '
        <ns:GetCustomerAvailableLoyaltyPoints>
            <ns:customerReference>' . htmlspecialchars($customerId) . '</ns:customerReference>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetCustomerAvailableLoyaltyPoints>';
        
        $resPoints = $this->request(CEGID_SERVICE_LOYALTY, 'http://www.cegid.fr/Retail/1.0/ILoyaltyWcfService/GetCustomerAvailableLoyaltyPoints', $soapBodyPoints);

        // 2. Get Cards (For Member Level)
        $soapBodyCards = '
        <ns:GetCustomerCards>
            <ns:customerCardsRequest>
                <ns:CustomerId>' . htmlspecialchars($customerId) . '</ns:CustomerId>
                <ns:ActiveCards>true</ns:ActiveCards> 
            </ns:customerCardsRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetCustomerCards>';

        $resCards = $this->request(CEGID_SERVICE_LOYALTY, 'http://www.cegid.fr/Retail/1.0/ILoyaltyWcfService/GetCustomerCards', $soapBodyCards);

        return ['points_response' => $resPoints, 'cards_response' => $resCards];
    }
    
    public function getLoyaltyPoints($customerId) {
        $soapBody = '
        <ns:GetCustomerAvailableLoyaltyPoints>
            <ns:customerReference>' . htmlspecialchars($customerId) . '</ns:customerReference>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetCustomerAvailableLoyaltyPoints>';
        return $this->request(CEGID_SERVICE_LOYALTY, 'http://www.cegid.fr/Retail/1.0/ILoyaltyWcfService/GetCustomerAvailableLoyaltyPoints', $soapBody);
    }
    
    public function getCustomerCards($customerId) {
        $soapBody = '
        <ns:GetCustomerCards>
            <ns:customerCardsRequest>
                <ns:CustomerId>' . htmlspecialchars($customerId) . '</ns:CustomerId>
                <ns:ActiveCards>true</ns:ActiveCards>
            </ns:customerCardsRequest>
            <ns:clientContext><ns:DatabaseId>' . CEGID_DOMAIN . '</ns:DatabaseId></ns:clientContext>
        </ns:GetCustomerCards>';
        return $this->request(CEGID_SERVICE_LOYALTY, 'http://www.cegid.fr/Retail/1.0/ILoyaltyWcfService/GetCustomerCards', $soapBody);
    }
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function formatNumber($num, $decimals = 2) { return number_format((float)$num, $decimals); }
function formatDate($date, $format = 'd/m/Y') { return $date ? date($format, strtotime($date)) : '-'; }
function formatDateTime($dt) { return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

function cleanXml($xml) {
    $xml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml);
    return preg_replace('/[a-zA-Z0-9]+:([a-zA-Z0-9]+)/', '$1', $xml);
}

function extractXmlValue($xml, $tag) {
    if (preg_match('/<(?:[^:>]*:)?' . $tag . '[^>]*>([^<]*)<\/(?:[^:>]*:)?' . $tag . '>/s', $xml, $m)) {
        return trim($m[1]);
    }
    return null;
}

function extractBrandCode($itemCode) {
    if (empty($itemCode)) return null;
    return strtoupper(substr($itemCode, 0, 3));
}

function logMessage($message, $type = 'INFO') {
    $logFile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] [{$type}] {$message}\n", FILE_APPEND);
}