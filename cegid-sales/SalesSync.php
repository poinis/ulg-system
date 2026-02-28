<?php
/**
 * Sales Sync Manager - SOAP Version
 * ดึงข้อมูลจาก Cegid SOAP API (SaleDocument) → เก็บลง MySQL
 * ใช้ GetHeaderList + GetByKey เพื่อดึง full documents (header + lines + payments)
 */

require_once 'CegidSOAP.php';

class SalesSync {
    private $db;
    private $soap;
    
    private $customerSoap;
    private $customerCache = [];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->soap = new CegidSOAP();
        
        require_once __DIR__ . '/CegidCustomerSOAP.php';
        $this->customerSoap = new CegidCustomerSOAP();
        
        // Pre-load customer cache from DB
        $stmt = $this->db->query("SELECT customer_code, first_name, last_name FROM customers");
        foreach ($stmt->fetchAll() as $row) {
            $this->customerCache[$row['customer_code']] = $row;
        }
    }
    
    private function getCustomerName($customerId) {
        if (empty($customerId) || strpos($customerId, 'WI00') === 0) {
            return ['first_name' => '', 'last_name' => ''];
        }
        
        if (isset($this->customerCache[$customerId])) {
            return $this->customerCache[$customerId];
        }
        
        $contact = $this->customerSoap->getContact($customerId);
        if ($contact) {
            $firstName = $contact['first_name'] ?? '';
            $lastName = $contact['last_name'] ?? '';
            
            // Cache to DB
            $stmt = $this->db->prepare("
                INSERT INTO customers (customer_code, first_name, last_name, phone)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    first_name = VALUES(first_name), last_name = VALUES(last_name),
                    phone = VALUES(phone)
            ");
            $stmt->execute([
                $customerId, $firstName, $lastName,
                $contact['phone'] ?? ''
            ]);
            
            $this->customerCache[$customerId] = ['first_name' => $firstName, 'last_name' => $lastName];
            usleep(30000); // 30ms delay for API rate limiting
            return $this->customerCache[$customerId];
        }
        
        $this->customerCache[$customerId] = ['first_name' => '', 'last_name' => ''];
        return $this->customerCache[$customerId];
    }
    
    /**
     * Sync sales data for a specific date (all stores)
     */
    public function syncDate($date, $storeCode = null) {
        $logId = $this->createLog($date);
        $result = [
            'success' => false,
            'date' => $date,
            'documents' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'payments' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'transactions' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'errors' => []
        ];
        
        try {
            // Step 1: Get all receipt headers for this date
            $allHeaders = [];
            $pageIndex = 1;
            $pageSize = 200;
            
            do {
                $headers = $this->soap->getHeaderList($date, $date, $storeCode, $pageSize, $pageIndex);
                $allHeaders = array_merge($allHeaders, $headers);
                $pageIndex++;
            } while (count($headers) >= $pageSize);
            
            $result['documents']['total'] = count($allHeaders);
            
            if (empty($allHeaders)) {
                $result['success'] = true;
                $this->updateLog($logId, 'completed', 0, 0, 0);
                return $result;
            }
            
            // Prepare statements
            $paymentStmt = $this->db->prepare("
                INSERT INTO sale_payments (
                    nature_piece, date_piece, store_code, caisse, souche,
                    payment_method, numero, customer_code, customer_first_name,
                    customer_last_name, amount_total, representant, ticket_annule,
                    cb_num_ctrl, ref_interne, hour_creation, hour_creation_hhmmss,
                    hour_creation_combined, date_creation, sync_date, raw_data
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    amount_total = VALUES(amount_total),
                    payment_method = VALUES(payment_method),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $txStmt = $this->db->prepare("
                INSERT INTO sale_transactions (
                    nature_piece, souche, date_piece, store_code, caisse,
                    numero, payment_ref_interne, num_ligne, indice,
                    article_code, article_internal_code, barcode,
                    product_title, product_description, color, brand, category,
                    sub_category, dimension1, dimension2, libdim1, libdim2,
                    customer_code, customer_first_name, customer_last_name,
                    quantity, price_ht, price_ttc, discount_amount,
                    total_ttc, total_ht, bill_total_ttc, bill_discount_sc,
                    net_total_ttc, representant, ticket_annule,
                    hour_creation_combined, creator, char_libre1,
                    char_libre2, char_libre3, sync_date, raw_data
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    quantity = VALUES(quantity),
                    price_ttc = VALUES(price_ttc),
                    total_ttc = VALUES(total_ttc),
                    bill_total_ttc = VALUES(bill_total_ttc),
                    bill_discount_sc = VALUES(bill_discount_sc),
                    net_total_ttc = VALUES(net_total_ttc),
                    color = VALUES(color),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            // Step 2: Get full document for each header
            foreach ($allHeaders as $header) {
                $key = $header['Key'];
                
                try {
                    $doc = $this->soap->getByKey($key['Type'], $key['Stump'], $key['Number']);
                    if (!$doc) {
                        $result['documents']['failed']++;
                        continue;
                    }
                    
                    $result['documents']['success']++;
                    $docHeader = $doc['Header'];
                    $documentDate = date('Y-m-d', strtotime($docHeader['Date']));
                    
                    // Get customer name
                    $custName = $this->getCustomerName($docHeader['CustomerId'] ?? '');
                    
                    // Insert payments
                    foreach ($doc['Payments'] as $payment) {
                        $result['payments']['total']++;
                        try {
                            $paymentStmt->execute([
                                'FFO',                                      // nature_piece
                                $documentDate,                              // date_piece
                                $docHeader['StoreId'],                      // store_code
                                $docHeader['Key']['Stump'],                 // caisse
                                $docHeader['Key']['Stump'],                 // souche
                                $payment['PaymentMethodId'] ?? $payment['Code'] ?? '', // payment_method
                                $docHeader['Key']['Number'],                // numero
                                $docHeader['CustomerId'] ?? '',             // customer_code
                                $custName['first_name'],                    // customer_first_name
                                $custName['last_name'],                     // customer_last_name
                                $payment['Amount'] ?? 0,                    // amount_total
                                $docHeader['SalesPersonId'] ?? '',          // representant
                                $docHeader['Active'] ? '-' : 'X',          // ticket_annule
                                '',                                         // cb_num_ctrl
                                $docHeader['InternalReference'],            // ref_interne
                                null,                                       // hour_creation
                                null,                                       // hour_creation_hhmmss
                                null,                                       // hour_creation_combined
                                $documentDate,                              // date_creation
                                $date,                                      // sync_date
                                json_encode($payment),                      // raw_data
                            ]);
                            $result['payments']['success']++;
                        } catch (Exception $e) {
                            $result['payments']['failed']++;
                            error_log("Payment sync failed [{$docHeader['InternalReference']}]: " . $e->getMessage());
                        }
                    }
                    
                    // Calculate bill-level S/C discount
                    // Exclude OFF items (shopping bags) from ratio calculation — CSV doesn't include them
                    $billTotalTTC = $docHeader['TaxIncludedTotalAmount'] ?? 0;
                    $linesTotalTTC = 0;
                    $offTotalTTC = 0;
                    foreach ($doc['Lines'] as $l) {
                        $q = $l['Quantity'] ?? 0;
                        $net = $l['TaxIncludedNetUnitPrice'] ?? ($l['TaxIncludedUnitPrice'] ?? 0);
                        $lineVal = $net * $q;
                        $linesTotalTTC += $lineVal;
                        $itemCode = $l['ItemCode'] ?? '';
                        if (strpos($itemCode, 'OFF') === 0) {
                            $offTotalTTC += $lineVal;
                        }
                    }
                    // S/C discount applies only to non-OFF items
                    $realItemsTotal = $linesTotalTTC - $offTotalTTC;
                    $billTotalExclOff = $billTotalTTC - $offTotalTTC;
                    $billDiscountSC = $realItemsTotal - $billTotalExclOff;
                    if ($billDiscountSC < 0) $billDiscountSC = 0;
                    $discountRatio = ($realItemsTotal > 0) ? ($billTotalExclOff / $realItemsTotal) : 1;
                    
                    // Insert transaction lines
                    foreach ($doc['Lines'] as $lineNum => $line) {
                        $result['transactions']['total']++;
                        
                        $quantity = $line['Quantity'] ?? 0;
                        $priceTTC = $line['TaxIncludedUnitPrice'] ?? 0;
                        $priceHT = $line['TaxExcludedUnitPrice'] ?? 0;
                        $netPriceTTC = $line['TaxIncludedNetUnitPrice'] ?? 0;
                        $netPriceHT = $line['TaxExcludedNetUnitPrice'] ?? 0;
                        $discountAmount = ($priceTTC - $netPriceTTC) * $quantity;
                        $totalTTC = $netPriceTTC * $quantity;
                        $totalHT = $netPriceHT * $quantity;
                        
                        // Net after bill S/C discount (proportional)
                        // OFF items (shopping bags) keep original price — S/C discount only on real items
                        $itemCode = $line['ItemCode'] ?? '';
                        if (strpos($itemCode, 'OFF') === 0) {
                            $netTotalTTC = $totalTTC;
                            $lineDiscountSC = 0;
                        } else {
                            $netTotalTTC = round($totalTTC * $discountRatio, 2);
                            $lineDiscountSC = round($totalTTC - $netTotalTTC, 2);
                        }
                        
                        try {
                            $txStmt->execute([
                                'FFO',                                      // nature_piece
                                $docHeader['Key']['Stump'],                 // souche
                                $documentDate,                              // date_piece
                                $docHeader['StoreId'],                      // store_code
                                $docHeader['Key']['Stump'],                 // caisse
                                $docHeader['Key']['Number'],                // numero
                                $docHeader['InternalReference'],            // payment_ref_interne
                                $lineNum + 1,                               // num_ligne
                                $line['Rank'] ?? '',                        // indice
                                $line['ItemCode'] ?? '',                    // article_code
                                $line['ItemId'] ?? '',                      // article_internal_code
                                $line['ItemReference'] ?? '',               // barcode
                                $line['Label'] ?? '',                       // product_title
                                $line['ComplementaryDescription'] ?? '',    // product_description
                                $line['ComplementaryDescription'] ?? '',    // color
                                $this->extractBrand($line['ItemCode'] ?? ''), // brand
                                '',                                         // category
                                '',                                         // sub_category
                                '',                                         // dimension1
                                '',                                         // dimension2
                                '',                                         // libdim1
                                '',                                         // libdim2
                                $docHeader['CustomerId'] ?? '',             // customer_code
                                $custName['first_name'],                    // customer_first_name
                                $custName['last_name'],                     // customer_last_name
                                $quantity,                                  // quantity
                                $priceHT,                                   // price_ht
                                $priceTTC,                                  // price_ttc
                                $discountAmount,                            // discount_amount
                                $totalTTC,                                  // total_ttc
                                $totalHT,                                   // total_ht
                                $billTotalTTC,                              // bill_total_ttc
                                $lineDiscountSC,                            // bill_discount_sc
                                $netTotalTTC,                               // net_total_ttc
                                $line['SalesPersonId'] ?? $docHeader['SalesPersonId'] ?? '', // representant
                                $docHeader['Active'] ? '-' : 'X',          // ticket_annule
                                null,                                       // hour_creation_combined
                                '',                                         // creator
                                $line['DiscountTypeId'] ?? '',              // char_libre1
                                $line['CatalogReference'] ?? '',            // char_libre2
                                $line['WarehouseId'] ?? '',                 // char_libre3
                                $date,                                      // sync_date
                                json_encode(['header' => $docHeader, 'line' => $line]), // raw_data
                            ]);
                            $result['transactions']['success']++;
                        } catch (Exception $e) {
                            $result['transactions']['failed']++;
                            error_log("Transaction line sync failed [{$docHeader['InternalReference']}#$lineNum]: " . $e->getMessage());
                        }
                    }
                    
                } catch (Exception $e) {
                    $result['documents']['failed']++;
                    error_log("GetByKey failed for {$key['Stump']}/{$key['Number']}: " . $e->getMessage());
                }
                
                usleep(50000); // 50ms delay
            }
            
            $result['success'] = true;
            $totalProcessed = $result['payments']['total'] + $result['transactions']['total'];
            $totalSuccess = $result['payments']['success'] + $result['transactions']['success'];
            $totalFailed = $result['payments']['failed'] + $result['transactions']['failed'];
            $this->updateLog($logId, 'completed', $totalProcessed, $totalSuccess, $totalFailed);
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->updateLog($logId, 'failed', 0, 0, 0, $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Extract brand code from ItemCode (first 3 chars)
     * CSV uses same mapping: NDI=NUDIE, CRH=CARHARTT, etc.
     */
    private function extractBrand($itemCode) {
        if (strlen($itemCode) >= 3) {
            return substr($itemCode, 0, 3);
        }
        return '';
    }
    
    /**
     * Create sync log
     */
    private function createLog($date) {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (sync_date, sync_type, file_name, status)
            VALUES (?, 'soap_sync', ?, 'processing')
        ");
        $stmt->execute([$date, "SOAP Sync - {$date}"]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update sync log
     */
    private function updateLog($logId, $status, $processed, $success, $failed, $error = null) {
        $stmt = $this->db->prepare("
            UPDATE sync_logs 
            SET status = ?,
                records_processed = ?,
                records_success = ?,
                records_failed = ?,
                error_message = ?,
                completed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $processed, $success, $failed, $error, $logId]);
    }
    
    /**
     * Test SOAP connection
     */
    public function testConnection() {
        return $this->soap->testConnection();
    }
}
?>
