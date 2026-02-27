<?php
/**
 * Cegid SOAP API Client
 * ใช้เรียก SaleDocument service เพื่อดึง line-level transaction data
 */

class CegidSOAP {
    private $wsdlUrl;
    private $username;
    private $password;
    private $databaseId;
    
    public function __construct() {
        $this->wsdlUrl = CEGID_BASE_URL . '/Doc/WebService/SaleDocument.svc?wsdl';
        $this->username = CEGID_USERNAME;
        $this->password = CEGID_PASSWORD;
        $this->databaseId = CEGID_FOLDER_ID;
    }
    
    /**
     * Create SOAP client
     */
    private function createClient() {
        $options = [
            'login' => $this->username,
            'password' => $this->password,
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'timeout' => 60,
                ]
            ]),
        ];
        
        return new SoapClient($this->wsdlUrl, $options);
    }
    
    /**
     * Build RetailContext
     */
    private function getContext() {
        return ['DatabaseId' => $this->databaseId];
    }
    
    /**
     * GetHeaderList - ดึงรายการ headers ตามวันที่
     * 
     * @param string $dateFrom YYYY-MM-DD
     * @param string $dateTo YYYY-MM-DD
     * @param string|null $storeId
     * @param int $pageSize
     * @param int $pageIndex
     * @return array
     */
    public function getHeaderList($dateFrom, $dateTo = null, $storeId = null, $pageSize = 100, $pageIndex = 1) {
        if ($dateTo === null) $dateTo = $dateFrom;
        
        $client = $this->createClient();
        
        $request = [
            'BeginDate' => $dateFrom . 'T00:00:00',
            'EndDate' => $dateTo . 'T23:59:59',
            'DocumentTypes' => [
                'SaleDocumentType' => ['Receipt']
            ],
            'Pager' => [
                'PageSize' => $pageSize,
                'PageIndex' => $pageIndex,
            ],
        ];
        
        if ($storeId) {
            $request['StoreIds'] = ['string' => [$storeId]];
        }
        
        try {
            $response = $client->GetHeaderList([
                'searchRequest' => $request,
                'clientContext' => $this->getContext(),
            ]);
            
            $headers = $response->GetHeaderListResult->Headers->Get_Header ?? [];
            
            // Ensure array
            if (!is_array($headers)) {
                $headers = [$headers];
            }
            
            return $headers;
            
        } catch (SoapFault $e) {
            error_log("SOAP GetHeaderList Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * GetByKey - ดึงรายละเอียดเต็ม (header + lines + payments)
     * 
     * @param string $type SaleDocumentType (e.g. 'Receipt')
     * @param string $stump Souche
     * @param int $number Numero
     * @return object
     */
    public function getByKey($type, $stump, $number) {
        $client = $this->createClient();
        
        $request = [
            'Key' => [
                'Type' => $type,
                'Stump' => $stump,
                'Number' => $number,
            ],
        ];
        
        try {
            $response = $client->GetByKey([
                'searchRequest' => $request,
                'clientContext' => $this->getContext(),
            ]);
            
            return $response->GetByKeyResult ?? null;
            
        } catch (SoapFault $e) {
            error_log("SOAP GetByKey Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * ดึง full documents (header + lines) สำหรับวันที่กำหนด
     * 
     * @param string $date YYYY-MM-DD
     * @param string|null $storeId
     * @return array Array of full documents
     */
    public function getFullDocuments($date, $storeId = null) {
        $documents = [];
        $pageIndex = 1;
        $pageSize = 50;
        
        do {
            $headers = $this->getHeaderList($date, $date, $storeId, $pageSize, $pageIndex);
            
            if (empty($headers)) break;
            
            foreach ($headers as $header) {
                $key = $header->Key ?? null;
                if (!$key) continue;
                
                try {
                    $doc = $this->getByKey(
                        $key->Type ?? 'Receipt',
                        $key->Stump ?? '',
                        $key->Number ?? 0
                    );
                    
                    if ($doc) {
                        $documents[] = $doc;
                    }
                } catch (Exception $e) {
                    error_log("Failed to get document {$key->Stump}/{$key->Number}: " . $e->getMessage());
                }
                
                // Small delay to avoid rate limiting
                usleep(100000); // 100ms
            }
            
            $pageIndex++;
            
        } while (count($headers) >= $pageSize);
        
        return $documents;
    }
    
    /**
     * Test connection
     */
    public function testConnection() {
        try {
            $client = $this->createClient();
            $response = $client->HelloWorld([
                'text' => 'test',
                'clientContext' => $this->getContext(),
            ]);
            return [
                'success' => true,
                'message' => $response->HelloWorldResult ?? 'Connected',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
?>
