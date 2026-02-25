<?php
/**
 * CSV Exporter Class
 * Handles exporting data from MySQL to CSV files
 */

class CSVExporter {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Export Sale Payments to CSV
     */
    public function exportPayments($dateFrom = null, $dateTo = null, $storeCode = null) {
        // Build query
        $sql = "SELECT 
            nature_piece as GPE_NATUREPIECEG,
            DATE_FORMAT(date_piece, '%d/%m/%Y') as GPE_DATEPIECE,
            store_code as GP_ETABLISSEMENT,
            caisse as GPE_CAISSE,
            souche as GPE_SOUCHE,
            payment_method as C5,
            numero as GPE_NUMERO,
            customer_code as GPE_TIERS,
            customer_first_name as T_PRENOM,
            customer_last_name as T_LIBELLE,
            amount_total as GPE_MONTANTECHE,
            representant as GP_REPRESENTANT,
            ticket_annule as GP_TICKETANNULE,
            cb_num_ctrl as GPE_CBNUMCTRL,
            '' as C17,
            ref_interne as GP_REFINTERNE,
            DATE_FORMAT(hour_creation_combined, '%d/%m/%Y') as GPE_HEURECREATION,
            TIME_FORMAT(hour_creation_hhmmss, '%H:%i:%s') as GPE_HEURECREATION_HHMMSS,
            DATE_FORMAT(hour_creation_combined, '%d/%m/%Y %H:%i:%s') as GPE_HEURECREATION_COMBINED,
            DATE_FORMAT(date_creation, '%d/%m/%Y') as GPE_DATECREATION
        FROM sale_payments
        WHERE 1=1";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND date_piece >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND date_piece <= ?";
            $params[] = $dateTo;
        }
        
        if ($storeCode) {
            $sql .= " AND store_code = ?";
            $params[] = $storeCode;
        }
        
        $sql .= " ORDER BY date_piece DESC, hour_creation_combined DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate filename
        $filename = 'sale_payment_daily_' . date('Ymd_His') . '.csv';
        $filepath = EXPORT_DIR . $filename;
        
        // Write to CSV
        $handle = fopen($filepath, 'w');
        
        // Write BOM for UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header
        if (!empty($data)) {
            fputcsv($handle, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }
        
        fclose($handle);
        
        // Log export
        $this->logExport('payment', $dateFrom, $dateTo, $filename, count($data));
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'records' => count($data)
        ];
    }
    
    /**
     * Export Sale Transactions to CSV
     */
    public function exportTransactions($dateFrom = null, $dateTo = null, $storeCode = null) {
        $sql = "SELECT 
            nature_piece as GL_NATUREPIECEG,
            souche as GL_SOUCHE,
            DATE_FORMAT(date_piece, '%d/%m/%Y') as GL_DATEPIECE,
            '' as C3,
            store_code as GL_ETABLISSEMENT,
            caisse as GP_CAISSE,
            numero as GL_NUMERO,
            payment_ref_interne as GP_REFINTERNE,
            caisse as GL_CAISSE,
            num_ligne as GL_NUMLIGNE,
            indice as GL_INDICEG,
            article_code as GL_ARTICLE,
            '' as C11,
            article_internal_code as GL_CODEARTICLE,
            barcode as GL_REFARTBARRE,
            product_title as GL_LIBCOMPL,
            product_description as GL_LIBELLE,
            '' as GA_LIBELLE,
            libdim1 as LIBDIM1,
            customer_code as GL_TIERS,
            customer_first_name as T_PRENOM,
            customer_last_name as T_LIBELLE,
            brand as C21,
            '' as C22,
            '' as C23,
            category as C24,
            sub_category as C25,
            libdim2 as LIBDIM2,
            quantity as GL_QTEFACT,
            price_ht as GL_PUHTBASE,
            price_ttc as GL_PUTTCBASE,
            discount_amount as GL_REMISELIBRE2,
            total_ttc as GL_TOTALTTC,
            total_ht as GL_TOTALHT,
            ticket_annule as GP_TICKETANNULE,
            DATE_FORMAT(hour_creation_combined, '%d/%m/%Y') as GP_HEURECREATION,
            TIME_FORMAT(hour_creation_combined, '%H:%i:%s') as GP_HEURECREATION_HHMMSS,
            '' as GL_PIECEORIGINE,
            representant as GL_REPRESENTANT,
            '' as T_FAX,
            '' as T_TELEPHONE,
            '' as T_TELEX,
            '' as T_TELEPHONE2,
            '' as C41,
            '' as C42,
            '' as C43,
            creator as GP_CREATEUR,
            char_libre1 as GA_CHARLIBRE1,
            char_libre2 as GA_CHARLIBRE2,
            char_libre3 as GA_CHARLIBRE3,
            DATE_FORMAT(hour_creation_combined, '%d/%m/%Y %H:%i:%s') as GP_HEURECREATION_COMBINED
        FROM sale_transactions
        WHERE 1=1";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " AND date_piece >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND date_piece <= ?";
            $params[] = $dateTo;
        }
        
        if ($storeCode) {
            $sql .= " AND store_code = ?";
            $params[] = $storeCode;
        }
        
        $sql .= " ORDER BY date_piece DESC, payment_ref_interne, num_ligne";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate filename
        $filename = 'sale_transaction_daily_' . date('Ymd_His') . '.csv';
        $filepath = EXPORT_DIR . $filename;
        
        // Write to CSV
        $handle = fopen($filepath, 'w');
        
        // Write BOM
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header
        if (!empty($data)) {
            fputcsv($handle, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }
        
        fclose($handle);
        
        // Log export
        $this->logExport('transaction', $dateFrom, $dateTo, $filename, count($data));
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'records' => count($data)
        ];
    }
    
    /**
     * Export both Payments and Transactions
     */
    public function exportBoth($dateFrom = null, $dateTo = null, $storeCode = null) {
        $payments = $this->exportPayments($dateFrom, $dateTo, $storeCode);
        $transactions = $this->exportTransactions($dateFrom, $dateTo, $storeCode);
        
        return [
            'success' => true,
            'payments' => $payments,
            'transactions' => $transactions
        ];
    }
    
    /**
     * Log export activity
     */
    private function logExport($type, $dateFrom, $dateTo, $filename, $recordCount) {
        $stmt = $this->db->prepare("
            INSERT INTO export_history 
            (export_type, date_from, date_to, file_name, records_count, exported_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $type,
            $dateFrom,
            $dateTo,
            $filename,
            $recordCount,
            $_SERVER['REMOTE_ADDR'] ?? 'system'
        ]);
    }
    
    /**
     * Get available export files
     */
    public function getExportFiles() {
        $files = [];
        
        if (is_dir(EXPORT_DIR)) {
            $items = scandir(EXPORT_DIR, SCANDIR_SORT_DESCENDING);
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $filepath = EXPORT_DIR . $item;
                if (is_file($filepath)) {
                    $files[] = [
                        'name' => $item,
                        'size' => filesize($filepath),
                        'date' => date('Y-m-d H:i:s', filemtime($filepath)),
                        'download_url' => 'download.php?file=' . urlencode($item)
                    ];
                }
            }
        }
        
        return $files;
    }
}
?>
