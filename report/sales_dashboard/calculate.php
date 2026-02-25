<?php
/**
 * Calculate Daily Sales Summary
 */
require_once 'config.php';

function calculateDailySummary($pdo, $saleDate) {
    try {
        $r = [];
        
        // Helper: Sum Payment
        $sumPayment = function($stores, $method = null) use ($pdo, $saleDate) {
            if (!is_array($stores)) $stores = [$stores];
            $placeholders = implode(',', array_fill(0, count($stores), '?'));
            
            $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE sale_date = ? AND store IN ($placeholders)";
            $params = array_merge([$saleDate], $stores);
            
            if ($method) {
                $sql .= " AND payment_method = ?";
                $params[] = $method;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return floatval($stmt->fetch()['total']);
        };
        
        // Helper: Count Payment
        $countPayment = function($stores, $method) use ($pdo, $saleDate) {
            if (!is_array($stores)) $stores = [$stores];
            $placeholders = implode(',', array_fill(0, count($stores), '?'));
            
            $sql = "SELECT COUNT(*) as cnt FROM payments WHERE sale_date = ? AND store IN ($placeholders) AND payment_method = ?";
            $params = array_merge([$saleDate], $stores, [$method]);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return intval($stmt->fetch()['cnt']);
        };
        
        // Helper: Sum Sales
        $sumSales = function($warehouses, $brand = null) use ($pdo, $saleDate) {
            if (!is_array($warehouses)) $warehouses = [$warehouses];
            $placeholders = implode(',', array_fill(0, count($warehouses), '?'));
            
            $sql = "SELECT COALESCE(SUM(total_incl_tax), 0) as total FROM sales 
                    WHERE sale_date = ? AND warehouse IN ($placeholders) AND total_incl_tax != 0";
            $params = array_merge([$saleDate], $warehouses);
            
            if ($brand) {
                $sql .= " AND brand = ?";
                $params[] = $brand;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return floatval($stmt->fetch()['total']);
        };
        
        // Helper: Sum Qty
        $sumQty = function($warehouses, $brand = null) use ($pdo, $saleDate) {
            if (!is_array($warehouses)) $warehouses = [$warehouses];
            $placeholders = implode(',', array_fill(0, count($warehouses), '?'));
            
            $sql = "SELECT COALESCE(SUM(qty), 0) as total FROM sales 
                    WHERE sale_date = ? AND warehouse IN ($placeholders) AND total_incl_tax != 0";
            $params = array_merge([$saleDate], $warehouses);
            
            if ($brand) {
                $sql .= " AND brand = ?";
                $params[] = $brand;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return intval($stmt->fetch()['total']);
        };
        
        // TOPOLOGIE
        $r['topo_ladprao_pcs'] = $sumQty(6030, 'TOPOLOGIE');
        $r['topo_ladprao_amt'] = $sumPayment(6030);
        $r['topo_paragon_pcs'] = $sumQty(8010, 'TOPOLOGIE');
        $r['topo_paragon_amt'] = $sumSales(8010, 'TOPOLOGIE');
        $r['topo_ctw_pcs'] = $sumQty(3020, 'TOPOLOGIE');
        $r['topo_ctw_amt'] = $sumSales(3020, 'TOPOLOGIE');
        $r['topo_mega_pcs'] = $sumQty(6050, 'TOPOLOGIE');
        $r['topo_mega_amt'] = $sumPayment(6050);
        $r['topo_dusit_pcs'] = $sumQty(6060, 'TOPOLOGIE') - $countPayment(6060, 'Top-up Marketing');
        $r['topo_dusit_amt'] = $sumPayment(6060) - $sumPayment(6060, 'Top-up Marketing');
        $r['topo_online_pcs'] = $sumQty(2009, 'TOPOLOGIE');
        $r['topo_online_amt'] = $sumSales(2009, 'TOPOLOGIE');
        
        $r['topo_offline'] = $r['topo_ladprao_amt'] + $r['topo_paragon_amt'] + $r['topo_ctw_amt'] + $r['topo_mega_amt'] + $r['topo_dusit_amt'];
        $r['topo_online'] = $r['topo_online_amt'];
        
        // SUPERDRY
        $r['spd_paragon_amt'] = $sumPayment(8010) - $r['topo_paragon_amt'];
        $r['spd_ctw_amt'] = $sumPayment(9030);
        $r['spd_t21_amt'] = $sumPayment(9110);
        $r['spd_pkt_amt'] = $sumPayment(4120);
        $r['spd_pattaya_amt'] = $sumPayment(9130);
        $r['spd_jungceylon_amt'] = $sumPayment(9100);
        $r['spd_mega_amt'] = $sumPayment(9160);
        $r['spd_village_amt'] = $sumPayment(9140);
        
        $r['spd_offline'] = $r['spd_paragon_amt'] + $r['spd_ctw_amt'] + $r['spd_t21_amt'] + 
                            $r['spd_pkt_amt'] + $r['spd_pattaya_amt'] + $r['spd_jungceylon_amt'] + 
                            $r['spd_mega_amt'] + $r['spd_village_amt'];
        
        // PRONTO
        $prontoStores = [2010, 2020, 2030, 2080, 2090, 7020, 7030];
        $r['pronto_offline'] = $sumPayment($prontoStores);
        $r['pronto_online'] = $sumPayment(2009, 'OMISE');
        
        // FREITAG
        $r['freitag_bkk_total'] = $sumPayment(3010);
        $r['freitag_cm_total'] = $sumPayment(3030);
        $r['freitag_silom_total'] = $sumPayment(3060);
        $r['freitag'] = $r['freitag_bkk_total'] + $r['freitag_cm_total'] + $r['freitag_silom_total'];
        
        // IZIPIZI
        $r['izipizi'] = $sumSales(3020, 'IZIPIZI');
        
        // HOOGA
        $r['hooga'] = $sumPayment(10010);
        
        // SOUP
        $r['soup'] = $sumPayment(6010);
        
        // SW19
        $r['sw19'] = $sumPayment(13010);
        $r['sw19_lazada'] = $sumSales(2009, 'SW19');
        
        // PAVEMENT
        $r['pavement_online'] = $sumPayment(5020);
        
        // TOTALS
        $r['total_offline'] = $r['spd_offline'] + $r['pronto_offline'] + $r['freitag'] + 
                              $r['topo_offline'] + $r['izipizi'] + $r['hooga'] + $r['soup'] + $r['sw19'];
        $r['total_online'] = $r['pronto_online'] + $r['topo_online'] + $r['sw19_lazada'] + $r['pavement_online'];
        $r['grand_total'] = $r['total_offline'] + $r['total_online'];
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO daily_summary 
            (sale_date, spd_offline, pronto_offline, pronto_online, freitag, pavement_online,
             topo_offline, topo_online, izipizi, hooga, soup, sw19, sw19_lazada,
             total_offline, total_online, grand_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            spd_offline = VALUES(spd_offline),
            pronto_offline = VALUES(pronto_offline),
            pronto_online = VALUES(pronto_online),
            freitag = VALUES(freitag),
            pavement_online = VALUES(pavement_online),
            topo_offline = VALUES(topo_offline),
            topo_online = VALUES(topo_online),
            izipizi = VALUES(izipizi),
            hooga = VALUES(hooga),
            soup = VALUES(soup),
            sw19 = VALUES(sw19),
            sw19_lazada = VALUES(sw19_lazada),
            total_offline = VALUES(total_offline),
            total_online = VALUES(total_online),
            grand_total = VALUES(grand_total),
            calculated_at = NOW()
        ");
        
        $stmt->execute([
            $saleDate,
            $r['spd_offline'],
            $r['pronto_offline'],
            $r['pronto_online'],
            $r['freitag'],
            $r['pavement_online'],
            $r['topo_offline'],
            $r['topo_online'],
            $r['izipizi'],
            $r['hooga'],
            $r['soup'],
            $r['sw19'],
            $r['sw19_lazada'],
            $r['total_offline'],
            $r['total_online'],
            $r['grand_total'],
        ]);
        
        return ['success' => true, 'data' => $r, 'grand_total' => $r['grand_total']];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}