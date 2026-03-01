<?php
/**
 * Cegid Customer SOAP API Client
 * Uses CustomerWcfService (richer data) + CustomerService (contact data)
 */
class CegidCustomerSOAP {
    private $wcfEndpoint;
    private $contactEndpoint;
    private $username;
    private $password;
    private $databaseId;
    
    public function __construct() {
        $baseHost = 'https://90643827-retail-ondemand.cegid.cloud';
        $this->wcfEndpoint = $baseHost . '/Y2/CustomerWcfService.svc';
        $this->contactEndpoint = $baseHost . '/Y2/CustomerService.svc';
        $this->username = CEGID_USERNAME;
        $this->password = CEGID_PASSWORD;
        $this->databaseId = CEGID_FOLDER_ID;
    }
    
    private function soapRequest($endpoint, $soapAction, $body) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://www.cegid.fr/Retail/1.0">
  <s:Body>' . $body . '</s:Body>
</s:Envelope>';
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
            ],
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception("Customer SOAP error: HTTP $httpCode");
        }
        
        return $response;
    }
    
    /**
     * GetCustomerDetail via CustomerWcfService
     * Returns full customer info: name, phone, email, birthday, usual store, member type
     */
    public function getCustomerDetail($customerId) {
        $body = '
    <tns:GetCustomerDetail>
      <tns:customerId>' . htmlspecialchars($customerId) . '</tns:customerId>
      <tns:clientContext>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:clientContext>
    </tns:GetCustomerDetail>';
        
        try {
            $response = $this->soapRequest(
                $this->wcfEndpoint,
                'http://www.cegid.fr/Retail/1.0/ICustomerWcfService/GetCustomerDetail',
                $body
            );
            return $this->parseCustomerDetail($response);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Search customer by phone via CustomerWcfService
     */
    public function searchByPhone($phone) {
        $body = '
    <tns:SearchCustomerIds>
      <tns:searchData>
        <tns:PhoneData>
          <tns:CellularPhoneNumber>' . htmlspecialchars($phone) . '</tns:CellularPhoneNumber>
        </tns:PhoneData>
        <tns:MaxNumberOfCustomers>5</tns:MaxNumberOfCustomers>
      </tns:searchData>
      <tns:clientContext>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:clientContext>
    </tns:SearchCustomerIds>';
        
        try {
            $response = $this->soapRequest(
                $this->wcfEndpoint,
                'http://www.cegid.fr/Retail/1.0/ICustomerWcfService/SearchCustomerIds',
                $body
            );
            $ids = [];
            if (preg_match_all('/<CustomerId>(.*?)<\/CustomerId>/s', $response, $m)) {
                $ids = $m[1];
            }
            return $ids;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Legacy: Get customer contact via CustomerService (backward compat)
     */
    public function getContact($customerId) {
        // Try CustomerWcfService first (richer data)
        $detail = $this->getCustomerDetail($customerId);
        if ($detail) {
            return [
                'first_name' => $detail['first_name'] ?? '',
                'last_name' => $detail['last_name'] ?? '',
                'phone' => $detail['phone'] ?? '',
                'email' => $detail['email'] ?? '',
            ];
        }
        return null;
    }
    
    private function parseCustomerDetail($xml) {
        $result = [];
        
        if (preg_match('/<FirstName>(.*?)<\/FirstName>/s', $xml, $m)) $result['first_name'] = trim($m[1]);
        if (preg_match('/<LastName>(.*?)<\/LastName>/s', $xml, $m)) $result['last_name'] = trim($m[1]);
        if (preg_match('/<CellularPhoneNumber>(.*?)<\/CellularPhoneNumber>/s', $xml, $m)) $result['phone'] = trim($m[1]);
        if (preg_match('/<IsCompany>(.*?)<\/IsCompany>/s', $xml, $m)) $result['is_company'] = trim($m[1]) === 'true';
        
        // Email
        if (preg_match('/<EmailData>.*?<Email>(.*?)<\/Email>.*?<\/EmailData>/s', $xml, $m)) $result['email'] = trim($m[1]);
        
        // Birthday
        if (preg_match('/<BirthDateDay>(.*?)<\/BirthDateDay>/s', $xml, $m)) $bDay = trim($m[1]);
        if (preg_match('/<BirthDateMonth>(.*?)<\/BirthDateMonth>/s', $xml, $m)) $bMonth = trim($m[1]);
        if (preg_match('/<BirthDateYear>(.*?)<\/BirthDateYear>/s', $xml, $m)) $bYear = trim($m[1]);
        if (isset($bDay, $bMonth, $bYear) && $bYear > 0) {
            $result['birthday'] = sprintf('%04d-%02d-%02d', $bYear, $bMonth, $bDay);
        }
        
        // Usual Store
        if (preg_match('/<UsualStoreId>(.*?)<\/UsualStoreId>/s', $xml, $m)) $result['usual_store'] = trim($m[1]);
        
        // Member Type (UserDefinedTable1Value)
        if (preg_match('/<UserDefinedTable1Value>(.*?)<\/UserDefinedTable1Value>/s', $xml, $m)) $result['member_type'] = trim($m[1]);
        
        return !empty($result) ? $result : null;
    }
}
