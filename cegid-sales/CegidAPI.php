<?php
/**
 * Cegid REST API Wrapper Class
 * Version: 1.0
 */

class CegidAPI {
    private $baseUrl;
    private $username;
    private $password;
    private $folderId;
    private $timeout = 30;
    
    public function __construct($config = null) {
        if ($config === null) {
            // Use config from config.php
            $this->baseUrl = CEGID_BASE_URL;
            $this->username = CEGID_USERNAME;
            $this->password = CEGID_PASSWORD;
            $this->folderId = CEGID_FOLDER_ID;
        } else {
            $this->baseUrl = $config['base_url'];
            $this->username = $config['username'];
            $this->password = $config['password'];
            $this->folderId = $config['folder_id'];
        }
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $params = [], $data = null) {
        $url = "{$this->baseUrl}/{$this->folderId}/api/{$endpoint}";
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("API Error: HTTP {$httpCode} - {$response}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Parse Error: " . json_last_error_msg());
        }
        
        return [
            'success' => true,
            'http_code' => $httpCode,
            'data' => $decoded,
            'raw' => $response
        ];
    }
    
    /**
     * Get Receipts (Sale Payments)
     * 
     * @param string $dateFrom Format: YYYY-MM-DD
     * @param string $dateTo Format: YYYY-MM-DD
     * @param string $storeCode Optional store filter
     * @return array
     */
    public function getReceipts($dateFrom, $dateTo = null, $storeCode = null) {
        if ($dateTo === null) {
            $dateTo = $dateFrom;
        }
        
        // Use working endpoint from testing
        $params = [
            'startDate' => $dateFrom,
            'endDate' => $dateTo
        ];
        
        if ($storeCode) {
            $params['storeId'] = $storeCode;
        }
        
        try {
            $result = $this->request('receipts/v2', 'GET', $params);
            return $result['data'] ?? [];
        } catch (Exception $e) {
            error_log("Cegid API Error (Receipts): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Sales Transactions
     * 
     * @param string $dateFrom Format: YYYY-MM-DD
     * @param string $dateTo Format: YYYY-MM-DD
     * @param string $storeCode Optional store filter
     * @return array
     */
    public function getSalesTransactions($dateFrom, $dateTo = null, $storeCode = null) {
        if ($dateTo === null) {
            $dateTo = $dateFrom;
        }
        
        // Try different endpoints for transaction data
        $endpoints = [
            ['endpoint' => 'sales-external-report2', 'params' => ['startDate' => $dateFrom, 'endDate' => $dateTo]],
            ['endpoint' => 'sales-external/v1', 'params' => ['dateFrom' => $dateFrom, 'dateTo' => $dateTo]],
            ['endpoint' => 'receipts/v2', 'params' => ['startDate' => $dateFrom, 'endDate' => $dateTo, 'includeLines' => true]],
        ];
        
        foreach ($endpoints as $config) {
            $params = $config['params'];
            
            if ($storeCode) {
                $params['storeId'] = $storeCode;
            }
            
            try {
                $result = $this->request($config['endpoint'], 'GET', $params);
                if (!empty($result['data'])) {
                    return $result['data'];
                }
            } catch (Exception $e) {
                error_log("Trying next endpoint: " . $e->getMessage());
                continue;
            }
        }
        
        return [];
    }
    
    /**
     * Get Stores list
     */
    public function getStores() {
        try {
            $result = $this->request('stores/v1');
            return $result['data'];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get Products
     */
    public function getProducts($params = []) {
        try {
            $result = $this->request('products/search', 'GET', $params);
            return $result['data'];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get Customers
     */
    public function getCustomers($params = []) {
        try {
            $result = $this->request('customers/v2', 'GET', $params);
            return $result['data'];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $result = $this->request('audit-usage/v1/country-packages');
            return [
                'success' => true,
                'message' => 'Connected successfully',
                'data' => $result['data']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generic API call (for testing)
     */
    public function call($endpoint, $params = []) {
        return $this->request($endpoint, 'GET', $params);
    }
}
?>
