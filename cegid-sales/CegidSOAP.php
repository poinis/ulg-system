<?php
/**
 * Cegid SOAP API Client (No-WSDL mode)
 * ใช้เรียก SaleDocument service เพื่อดึง line-level transaction data
 * ใช้ raw CURL SOAP requests เนื่องจาก WSDL ต้อง login ผ่าน browser ก่อน
 */

class CegidSOAP {
    private $endpoint;
    private $username;
    private $password;
    private $databaseId;
    private $namespace = 'http://www.cegid.fr/Retail/1.0';
    private $arraysNs = 'http://schemas.microsoft.com/2003/10/Serialization/Arrays';
    
    public function __construct() {
        $this->endpoint = CEGID_BASE_URL . '/Doc/WebService/SaleDocument.svc';
        $this->username = CEGID_USERNAME;
        $this->password = CEGID_PASSWORD;
        $this->databaseId = CEGID_FOLDER_ID;
    }
    
    /**
     * Send raw SOAP request via CURL
     */
    private function soapRequest($action, $body) {
        $soapAction = $this->namespace . '/ISaleDocumentService/' . $action;
        
        $envelope = '<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:tns="' . $this->namespace . '"
            xmlns:arr="' . $this->arraysNs . '">
  <s:Body>' . $body . '</s:Body>
</s:Envelope>';
        
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            // Try to extract fault message
            if (preg_match('/<faultstring[^>]*>(.+?)<\/faultstring>/s', $response, $m)) {
                throw new Exception("SOAP Fault: " . html_entity_decode($m[1]));
            }
            throw new Exception("SOAP Error HTTP {$httpCode}: " . substr($response, 0, 500));
        }
        
        return $response;
    }
    
    /**
     * Parse XML response, stripping namespaces for easy access
     */
    private function parseResponse($xml) {
        // Remove namespace prefixes for easier parsing
        $xml = preg_replace('/(<\/?)[\w]+:/', '$1', $xml);
        $xml = preg_replace('/\s+xmlns[^=]*="[^"]*"/', '', $xml);
        
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if (!$doc) {
            throw new Exception("XML Parse Error: " . implode('; ', array_map(fn($e) => $e->message, libxml_get_errors())));
        }
        return $doc;
    }
    
    /**
     * HelloWorld - test connection
     */
    public function testConnection() {
        $body = '
    <tns:HelloWorld>
      <tns:text>test</tns:text>
      <tns:clientContext>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:clientContext>
    </tns:HelloWorld>';
        
        try {
            $response = $this->soapRequest('HelloWorld', $body);
            $doc = $this->parseResponse($response);
            $result = (string)($doc->Body->HelloWorldResponse->HelloWorldResult ?? '');
            return ['success' => true, 'message' => $result ?: 'Connected'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * GetHeaderList - ดึงรายการ headers ตามวันที่
     */
    public function getHeaderList($dateFrom, $dateTo = null, $storeId = null, $pageSize = 100, $pageIndex = 1) {
        if ($dateTo === null) $dateTo = $dateFrom;
        
        $storeXml = '';
        if ($storeId) {
            $storeXml = '
          <tns:StoreIds>
            <arr:string>' . htmlspecialchars($storeId) . '</arr:string>
          </tns:StoreIds>';
        }
        
        $body = '
    <tns:GetHeaderList>
      <tns:searchRequest>
        <tns:BeginDate>' . $dateFrom . 'T00:00:00</tns:BeginDate>
        <tns:DocumentTypes>
          <tns:SaleDocumentType>Receipt</tns:SaleDocumentType>
        </tns:DocumentTypes>
        <tns:EndDate>' . $dateTo . 'T23:59:59</tns:EndDate>
        <tns:Pager>
          <tns:PageIndex>' . $pageIndex . '</tns:PageIndex>
          <tns:PageSize>' . $pageSize . '</tns:PageSize>
        </tns:Pager>' . $storeXml . '
      </tns:searchRequest>
      <tns:clientContext>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:clientContext>
    </tns:GetHeaderList>';
        
        $response = $this->soapRequest('GetHeaderList', $body);
        $doc = $this->parseResponse($response);
        
        $headers = [];
        $headerNodes = $doc->Body->GetHeaderListResponse->GetHeaderListResult->Headers->Get_Header ?? [];
        
        foreach ($headerNodes as $node) {
            $headers[] = [
                'InternalReference' => (string)($node->InternalReference ?? ''),
                'Date' => (string)($node->Date ?? ''),
                'StoreId' => (string)($node->StoreId ?? ''),
                'CustomerId' => (string)($node->CustomerId ?? ''),
                'SalesPersonId' => (string)($node->SalesPersonId ?? ''),
                'TaxIncludedTotalAmount' => (float)($node->TaxIncludedTotalAmount ?? 0),
                'TaxExcludedTotalAmount' => (float)($node->TaxExcludedTotalAmount ?? 0),
                'TotalQuantity' => (float)($node->TotalQuantity ?? 0),
                'CurrencyId' => (string)($node->CurrencyId ?? ''),
                'Active' => ((string)($node->Active ?? 'true')) === 'true',
                'Key' => [
                    'Type' => (string)($node->Key->Type ?? ''),
                    'Stump' => (string)($node->Key->Stump ?? ''),
                    'Number' => (int)($node->Key->Number ?? 0),
                ],
            ];
        }
        
        return $headers;
    }
    
    /**
     * GetByKey - ดึงเอกสารเต็ม (header + lines + payments)
     */
    public function getByKey($type, $stump, $number) {
        $body = '
    <tns:GetByKey>
      <tns:searchRequest>
        <tns:Key>
          <tns:Number>' . (int)$number . '</tns:Number>
          <tns:Stump>' . htmlspecialchars($stump) . '</tns:Stump>
          <tns:Type>' . htmlspecialchars($type) . '</tns:Type>
        </tns:Key>
      </tns:searchRequest>
      <tns:clientContext>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:clientContext>
    </tns:GetByKey>';
        
        $response = $this->soapRequest('GetByKey', $body);
        $doc = $this->parseResponse($response);
        
        $result = $doc->Body->GetByKeyResponse->GetByKeyResult ?? null;
        if (!$result) return null;
        
        // Parse header
        $h = $result->Header;
        $header = [
            'InternalReference' => (string)($h->InternalReference ?? ''),
            'Date' => (string)($h->Date ?? ''),
            'StoreId' => (string)($h->StoreId ?? ''),
            'CustomerId' => (string)($h->CustomerId ?? ''),
            'SalesPersonId' => (string)($h->SalesPersonId ?? ''),
            'TaxIncludedTotalAmount' => (float)($h->TaxIncludedTotalAmount ?? 0),
            'TaxExcludedTotalAmount' => (float)($h->TaxExcludedTotalAmount ?? 0),
            'TotalQuantity' => (float)($h->TotalQuantity ?? 0),
            'CurrencyId' => (string)($h->CurrencyId ?? ''),
            'Active' => ((string)($h->Active ?? 'true')) === 'true',
            'WarehouseId' => (string)($h->WarehouseId ?? ''),
            'Key' => [
                'Type' => (string)($h->Key->Type ?? ''),
                'Stump' => (string)($h->Key->Stump ?? ''),
                'Number' => (int)($h->Key->Number ?? 0),
            ],
        ];
        
        // Parse lines
        $lines = [];
        $lineNodes = $result->Lines->Get_Line ?? [];
        if (!is_array($lineNodes) && !($lineNodes instanceof \Traversable)) {
            $lineNodes = [$lineNodes];
        }
        foreach ($lineNodes as $ln) {
            $lines[] = [
                'ItemId' => (string)($ln->ItemId ?? ''),
                'ItemCode' => (string)($ln->ItemCode ?? ''),
                'ItemReference' => (string)($ln->ItemReference ?? ''),
                'Label' => (string)($ln->Label ?? ''),
                'Quantity' => (float)($ln->Quantity ?? 0),
                'TaxIncludedUnitPrice' => (float)($ln->TaxIncludedUnitPrice ?? 0),
                'TaxIncludedNetUnitPrice' => (float)($ln->TaxIncludedNetUnitPrice ?? 0),
                'TaxExcludedUnitPrice' => (float)($ln->TaxExcludedUnitPrice ?? 0),
                'TaxExcludedNetUnitPrice' => (float)($ln->TaxExcludedNetUnitPrice ?? 0),
                'SalesPersonId' => (string)($ln->SalesPersonId ?? ''),
                'DiscountTypeId' => (string)($ln->DiscountTypeId ?? ''),
                'Rank' => (int)($ln->Rank ?? 0),
                'ComplementaryDescription' => (string)($ln->ComplementaryDescription ?? ''),
                'WarehouseId' => (string)($ln->WarehouseId ?? ''),
                'CatalogReference' => (string)($ln->CatalogReference ?? ''),
            ];
        }
        
        // Parse payments
        $payments = [];
        $payNodes = $result->Payments->Get_Payment ?? [];
        if (!is_array($payNodes) && !($payNodes instanceof \Traversable)) {
            $payNodes = [$payNodes];
        }
        foreach ($payNodes as $pn) {
            $payments[] = [
                'Amount' => (float)($pn->Amount ?? 0),
                'CurrencyId' => (string)($pn->CurrencyId ?? ''),
                'PaymentMethodId' => (string)($pn->PaymentMethodId ?? ''),
            ];
        }
        
        return [
            'Header' => $header,
            'Lines' => $lines,
            'Payments' => $payments,
        ];
    }
    
    /**
     * ดึง full documents สำหรับวันที่กำหนด
     */
    public function getFullDocuments($date, $storeId = null) {
        $documents = [];
        $pageIndex = 1;
        $pageSize = 50;
        
        do {
            $headers = $this->getHeaderList($date, $date, $storeId, $pageSize, $pageIndex);
            
            if (empty($headers)) break;
            
            foreach ($headers as $header) {
                $key = $header['Key'];
                if (empty($key['Stump']) && empty($key['Number'])) continue;
                
                try {
                    $doc = $this->getByKey($key['Type'], $key['Stump'], $key['Number']);
                    if ($doc) {
                        $documents[] = $doc;
                    }
                } catch (Exception $e) {
                    error_log("Failed to get doc {$key['Stump']}/{$key['Number']}: " . $e->getMessage());
                }
                
                usleep(100000); // 100ms delay
            }
            
            $pageIndex++;
        } while (count($headers) >= $pageSize);
        
        return $documents;
    }
}
?>
