<?php
/**
 * Sales Sync Manager
 * ดึงข้อมูลจาก Cegid API → เก็บลง MySQL
 */

require_once 'CegidAPI.php';
require_once 'CegidSOAP.php';

class SalesSync {
    private $db;
    private $api;
    private $soap;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->api = new CegidAPI();
        $this->soap = new CegidSOAP();
    }
    
    /**
     * Sync sales data for a specific date
     * 
     * @param string $date Format: YYYY-MM-DD
     * @param string $storeCode Optional
     * @return array Result summary
     */
    public function syncDate($date, $storeCode = null) {
        $logId = $this->createLog($date);
        $result = [
            'success' => false,
            'date' => $date,
            'payments' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'transactions' => ['total' => 0, 'success' => 0, 'failed' => 0],
            'errors' => []
        ];
        
        try {
            $this->db->beginTransaction();
            
            // 1. Sync Payments
            $payments = $this->syncPayments($date, $storeCode);
            $result['payments'] = $payments;
            
            // 2. Sync Transactions
            $transactions = $this->syncTransactions($date, $storeCode);
            $result['transactions'] = $transactions;
            
            $this->db->commit();
            
            $result['success'] = true;
            $this->updateLog($logId, 'completed', 
                $payments['total'] + $transactions['total'],
                $payments['success'] + $transactions['success'],
                $payments['failed'] + $transactions['failed']
            );
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $result['errors'][] = $e->getMessage();
            $this->updateLog($logId, 'failed', 0, 0, 0, $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Sync Payment data
     */
    private function syncPayments($date, $storeCode = null) {
        $result = ['total' => 0, 'success' => 0, 'failed' => 0];
        
        try {
            // Get data from API
            $data = $this->api->getReceipts($date, $date, $storeCode);
            
            if (empty($data)) {
                return $result;
            }
            
            // Prepare statement
            $stmt = $this->db->prepare("
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
            
            // Process each record
            foreach ($data as $record) {
                $result['total']++;
                
                try {
                    $mapped = $this->mapPaymentData($record, $date);
                    $stmt->execute($mapped);
                    $result['success']++;
                } catch (Exception $e) {
                    $result['failed']++;
                    error_log("Payment sync failed: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Payment sync error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Sync Transaction data via SOAP API (SaleDocument GetByKey)
     */
    private function syncTransactions($date, $storeCode = null) {
        $result = ['total' => 0, 'success' => 0, 'failed' => 0];
        
        try {
            // Step 1: Get headers via SOAP
            $headers = $this->soap->getHeaderList($date, $date, $storeCode);
            
            if (empty($headers)) {
                return $result;
            }
            
            // Prepare statement
            $stmt = $this->db->prepare("
                INSERT INTO sale_transactions (
                    nature_piece, souche, date_piece, store_code, caisse,
                    numero, payment_ref_interne, num_ligne, indice,
                    article_code, article_internal_code, barcode,
                    product_title, product_description, brand, category,
                    sub_category, dimension1, dimension2, libdim1, libdim2,
                    customer_code, customer_first_name, customer_last_name,
                    quantity, price_ht, price_ttc, discount_amount,
                    total_ttc, total_ht, representant, ticket_annule,
                    hour_creation_combined, creator, char_libre1,
                    char_libre2, char_libre3, sync_date, raw_data
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    quantity = VALUES(quantity),
                    price_ttc = VALUES(price_ttc),
                    total_ttc = VALUES(total_ttc),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            // Step 2: For each header, get full document via GetByKey
            foreach ($headers as $header) {
                $key = $header['Key'];
                
                try {
                    $doc = $this->soap->getByKey($key['Type'], $key['Stump'], $key['Number']);
                    if (!$doc || empty($doc['Lines'])) continue;
                    
                    $docHeader = $doc['Header'];
                    
                    foreach ($doc['Lines'] as $lineNum => $line) {
                        $result['total']++;
                        
                        try {
                            $mapped = $this->mapSOAPTransactionData($docHeader, $line, $lineNum + 1, $date);
                            $stmt->execute($mapped);
                            $result['success']++;
                        } catch (Exception $e) {
                            $result['failed']++;
                            error_log("Transaction line sync failed [{$docHeader['InternalReference']}#$lineNum]: " . $e->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    error_log("GetByKey failed for {$key['Stump']}/{$key['Number']}: " . $e->getMessage());
                }
                
                usleep(100000); // 100ms delay between requests
            }
            
        } catch (Exception $e) {
            throw new Exception("Transaction sync error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Map API payment data to database format
     */
    private function mapPaymentData($apiData, $syncDate) {
        // Extract data from nested structure
        $header = $apiData['header'] ?? [];
        $customer = $apiData['customerIdentifier'] ?? [];
        $payment = $apiData['payment'] ?? [];
        $lines = $apiData['lines'] ?? [];
        
        // Calculate total from lines if not provided
        $totalAmount = 0;
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $totalAmount += ($line['totalIncludingTax'] ?? 0);
            }
        } else {
            $totalAmount = $header['totalAmount'] ?? 0;
        }
        
        // Parse dates
        $documentDate = null;
        if (!empty($header['documentDate'])) {
            $documentDate = date('Y-m-d', strtotime($header['documentDate']));
        }
        
        $createdDateTime = null;
        if (!empty($header['createdDateTime'])) {
            $createdDateTime = date('Y-m-d H:i:s', strtotime($header['createdDateTime']));
        }
        
        return [
            'FFO', // nature_piece
            $documentDate ?? $syncDate, // date_piece
            $header['storeIdentifier']['id'] ?? '', // store_code
            $header['registerId'] ?? '', // caisse
            $header['warehouseIdentifier']['id'] ?? '', // souche
            $payment['method'] ?? $payment['type'] ?? '', // payment_method
            $header['registerOpeningNumber'] ?? null, // numero
            $customer['id'] ?? $customer['code'] ?? '', // customer_code
            $customer['firstName'] ?? '', // customer_first_name
            $customer['lastName'] ?? $customer['name'] ?? '', // customer_last_name
            $totalAmount, // amount_total
            $header['salespersonId'] ?? '', // representant
            ($header['cancelled'] ?? false) ? 'X' : '-', // ticket_annule
            '', // cb_num_ctrl
            $header['references']['internal'] ?? $header['documentNumber'] ?? uniqid('REC'), // ref_interne
            $createdDateTime ? date('H:i:s', strtotime($createdDateTime)) : null, // hour_creation
            $createdDateTime ? date('H:i:s', strtotime($createdDateTime)) : null, // hour_creation_hhmmss
            $createdDateTime, // hour_creation_combined
            $documentDate ?? $syncDate, // date_creation
            $syncDate, // sync_date
            json_encode($apiData) // raw_data
        ];
    }
    
    /**
     * Map SOAP transaction line data to database format
     */
    private function mapSOAPTransactionData($header, $line, $lineNum, $syncDate) {
        $documentDate = null;
        if (!empty($header['Date'])) {
            $documentDate = date('Y-m-d', strtotime($header['Date']));
        }
        
        $quantity = $line['Quantity'] ?? 0;
        $priceTTC = $line['TaxIncludedUnitPrice'] ?? 0;
        $priceHT = $line['TaxExcludedUnitPrice'] ?? 0;
        $netPriceTTC = $line['TaxIncludedNetUnitPrice'] ?? 0;
        $netPriceHT = $line['TaxExcludedNetUnitPrice'] ?? 0;
        $discountAmount = ($priceTTC - $netPriceTTC) * $quantity;
        $totalTTC = $netPriceTTC * $quantity;
        $totalHT = $netPriceHT * $quantity;
        
        return [
            'FFO',                                          // nature_piece
            $header['Key']['Stump'] ?? '',                  // souche
            $documentDate ?? $syncDate,                     // date_piece
            $header['StoreId'] ?? '',                       // store_code
            $header['Key']['Stump'] ?? '',                  // caisse
            $header['Key']['Number'] ?? null,               // numero
            $header['InternalReference'] ?? '',             // payment_ref_interne
            $lineNum,                                       // num_ligne
            $line['Rank'] ?? '',                            // indice
            $line['ItemCode'] ?? '',                        // article_code
            $line['ItemId'] ?? '',                          // article_internal_code
            $line['ItemReference'] ?? '',                   // barcode
            $line['Label'] ?? '',                           // product_title
            $line['ComplementaryDescription'] ?? '',        // product_description
            '',                                             // brand
            '',                                             // category
            '',                                             // sub_category
            '',                                             // dimension1
            '',                                             // dimension2
            '',                                             // libdim1
            '',                                             // libdim2
            $header['CustomerId'] ?? '',                    // customer_code
            '',                                             // customer_first_name
            '',                                             // customer_last_name
            $quantity,                                      // quantity
            $priceHT,                                       // price_ht
            $priceTTC,                                      // price_ttc
            $discountAmount,                                // discount_amount
            $totalTTC,                                      // total_ttc
            $totalHT,                                       // total_ht
            $line['SalesPersonId'] ?? $header['SalesPersonId'] ?? '', // representant
            $header['Active'] ? '-' : 'X',                  // ticket_annule
            null,                                           // hour_creation_combined
            '',                                             // creator
            $line['DiscountTypeId'] ?? '',                  // char_libre1
            $line['CatalogReference'] ?? '',                // char_libre2
            $line['WarehouseId'] ?? '',                     // char_libre3
            $syncDate,                                      // sync_date
            json_encode(['header' => $header, 'line' => $line]) // raw_data
        ];
    }
    
    /**
     * Map API transaction data to database format (legacy REST)
     */
    private function mapTransactionData($apiData, $syncDate) {
        // Check if this is from receipt lines
        $receipt = $apiData['receipt'] ?? [];
        $line = empty($receipt) ? $apiData : $apiData;
        
        // Product info
        $product = $line['product'] ?? $line['productIdentifier'] ?? [];
        $item = $line['item'] ?? [];
        
        // Parse dates
        $documentDate = null;
        if (!empty($receipt['documentDate'])) {
            $documentDate = date('Y-m-d', strtotime($receipt['documentDate']));
        } elseif (!empty($apiData['date'])) {
            $documentDate = date('Y-m-d', strtotime($apiData['date']));
        }
        
        $createdDateTime = null;
        if (!empty($receipt['createdDateTime'])) {
            $createdDateTime = date('Y-m-d H:i:s', strtotime($receipt['createdDateTime']));
        } elseif (!empty($apiData['createdAt'])) {
            $createdDateTime = date('Y-m-d H:i:s', strtotime($apiData['createdAt']));
        }
        
        return [
            'FFO', // nature_piece
            $receipt['warehouseIdentifier']['id'] ?? '', // souche
            $documentDate ?? $syncDate, // date_piece
            $receipt['storeIdentifier']['id'] ?? $apiData['storeId'] ?? '', // store_code
            $receipt['registerId'] ?? '', // caisse
            $receipt['registerOpeningNumber'] ?? null, // numero
            $receipt['references']['internal'] ?? $apiData['receiptNumber'] ?? '', // payment_ref_interne
            $line['lineNumber'] ?? $line['number'] ?? null, // num_ligne
            '', // indice
            $product['id'] ?? $product['code'] ?? $item['code'] ?? '', // article_code
            $product['internalCode'] ?? '', // article_internal_code
            $product['barcode'] ?? $product['ean'] ?? $item['ean'] ?? '', // barcode
            $product['name'] ?? $product['label'] ?? $item['label'] ?? '', // product_title
            $product['description'] ?? '', // product_description
            $product['brand'] ?? $item['brand'] ?? '', // brand
            $product['category'] ?? '', // category
            $product['subCategory'] ?? '', // sub_category
            $line['size'] ?? $product['size'] ?? '', // dimension1
            $line['color'] ?? $product['color'] ?? '', // dimension2
            '', // libdim1
            '', // libdim2
            $receipt['customerIdentifier']['id'] ?? '', // customer_code
            '', // customer_first_name
            '', // customer_last_name
            $line['quantity'] ?? 0, // quantity
            $line['unitPriceExcludingTax'] ?? 0, // price_ht
            $line['unitPriceIncludingTax'] ?? $line['unitPrice'] ?? 0, // price_ttc
            $line['discountAmount'] ?? 0, // discount_amount
            $line['totalIncludingTax'] ?? $line['totalAmount'] ?? 0, // total_ttc
            $line['totalExcludingTax'] ?? 0, // total_ht
            $receipt['salespersonId'] ?? '', // representant
            ($receipt['cancelled'] ?? false) ? 'X' : '-', // ticket_annule
            $createdDateTime, // hour_creation_combined
            '', // creator
            '', // char_libre1
            '', // char_libre2
            '', // char_libre3
            $syncDate, // sync_date
            json_encode($apiData) // raw_data
        ];
    }
    
    /**
     * Create sync log
     */
    private function createLog($date) {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (sync_date, sync_type, file_name, status)
            VALUES (?, 'api_sync', ?, 'processing')
        ");
        $stmt->execute([$date, "API Sync - {$date}"]);
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
     * Test API connection
     */
    public function testConnection() {
        return $this->api->testConnection();
    }
}
?>
