<?php
/**
 * Cegid Customer SOAP API Client
 */
class CegidCustomerSOAP {
    private $endpoint;
    private $username;
    private $password;
    private $databaseId;
    
    public function __construct() {
        $baseHost = 'https://90643827-retail-ondemand.cegid.cloud';
        $this->endpoint = $baseHost . '/Y2/CustomerService.svc';
        $this->username = CEGID_USERNAME;
        $this->password = CEGID_PASSWORD;
        $this->databaseId = CEGID_FOLDER_ID;
    }
    
    private function soapRequest($action, $body) {
        $soapAction = "http://www.cegid.fr/Retail/1.0/ICustomerWebService/$action";
        
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://www.cegid.fr/Retail/1.0">
  <s:Body>' . $body . '</s:Body>
</s:Envelope>';
        
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $soapAction,
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
     * Get customer contact by CustomerId
     * Returns: ['first_name' => ..., 'last_name' => ..., 'phone' => ..., 'email' => ...]
     */
    public function getContact($customerId) {
        $body = '
    <tns:GetContact>
      <tns:Request>
        <tns:Identification>
          <tns:CustomerId>' . htmlspecialchars($customerId) . '</tns:CustomerId>
          <tns:Number>1</tns:Number>
        </tns:Identification>
      </tns:Request>
      <tns:Context>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:Context>
    </tns:GetContact>';
        
        try {
            $response = $this->soapRequest('GetContact', $body);
            return $this->parseContactResponse($response);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get multiple contacts for a customer
     */
    public function getContacts($customerId, $maxResults = 5) {
        $body = '
    <tns:GetContacts>
      <tns:Request>
        <tns:CustomerId>' . htmlspecialchars($customerId) . '</tns:CustomerId>
        <tns:MaxNumberReturnedContacts>' . $maxResults . '</tns:MaxNumberReturnedContacts>
      </tns:Request>
      <tns:Context>
        <tns:DatabaseId>' . htmlspecialchars($this->databaseId) . '</tns:DatabaseId>
      </tns:Context>
    </tns:GetContacts>';
        
        try {
            $response = $this->soapRequest('GetContacts', $body);
            return $this->parseContactsResponse($response);
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function parseContactResponse($xml) {
        $result = [];
        
        if (preg_match('/<FirstName>(.*?)<\/FirstName>/s', $xml, $m)) $result['first_name'] = trim($m[1]);
        if (preg_match('/<LastName>(.*?)<\/LastName>/s', $xml, $m)) $result['last_name'] = trim($m[1]);
        if (preg_match('/<MobilePhoneNumber>(.*?)<\/MobilePhoneNumber>/s', $xml, $m)) $result['phone'] = trim($m[1]);
        if (preg_match('/<Email>(.*?)<\/Email>/s', $xml, $m)) $result['email'] = trim($m[1]);
        if (preg_match('/<Civility>(.*?)<\/Civility>/s', $xml, $m)) $result['civility'] = trim($m[1]);
        
        return !empty($result) ? $result : null;
    }
    
    private function parseContactsResponse($xml) {
        $contacts = [];
        if (preg_match_all('/<Contact>(.*?)<\/Contact>/s', $xml, $matches)) {
            foreach ($matches[1] as $contactXml) {
                $contact = [];
                if (preg_match('/<CustomerId>(.*?)<\/CustomerId>/s', $contactXml, $m)) $contact['customer_id'] = trim($m[1]);
                if (preg_match('/<FirstName>(.*?)<\/FirstName>/s', $contactXml, $m)) $contact['first_name'] = trim($m[1]);
                if (preg_match('/<LastName>(.*?)<\/LastName>/s', $contactXml, $m)) $contact['last_name'] = trim($m[1]);
                if (preg_match('/<MobilePhoneNumber>(.*?)<\/MobilePhoneNumber>/s', $contactXml, $m)) $contact['phone'] = trim($m[1]);
                if (preg_match('/<Email>(.*?)<\/Email>/s', $contactXml, $m)) $contact['email'] = trim($m[1]);
                $contacts[] = $contact;
            }
        }
        return $contacts;
    }
}
