<?php
/**
 * TransferOptimizer.php  v3.0
 *
 * TF Best Rules (จาก หลักการ_Recall):
 *  1. Daily Rate จาก daily_sales  (ไม่ใช่ CSV)
 *  2. เป้า 10 วัน
 *  3. Receiving store ต้องมียอดขาย SKU นั้น (qty_sold > 0)
 *  4. WC < 4w → โอนออกได้หมด ไม่ต้องเก็บขั้นต่ำ
 *  5. Company holding < 30d → โอนออกหมดได้
 *  6. WC > 2.5w (excess) → โอนส่วนเกิน (Top Seller เก็บ 2w ขั้นต่ำ)
 *  7. ห้ามโอนออกจาก DC / DISPLAY
 *  8. ห้ามโอนเข้า DC
 *  9. ใช้ต้นทางน้อยที่สุด — เลือก excess มากสุดก่อน
 * 10. Receiver เรียงตาม sell rate สูงสุดก่อน
 * 11. คำนึงระยะทาง (Haversine km)
 * 12. แสดง Diff เกิน/ขาด/พอดี
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SalesAnalytics.php';

class TransferOptimizer {

    protected PDO            $db;
    protected SalesAnalytics $sa;
    public    ?int           $uploadId;

    public function __construct(?int $uploadId = null) {
        $this->db       = Database::getInstance();
        $this->sa       = new SalesAnalytics($uploadId);
        $this->uploadId = $this->sa->uploadId;
    }

    // ════════════════════════════════════════════════════════
    // TF Best  (store-to-store, no DC stock, target 10d)
    // ════════════════════════════════════════════════════════
    public function generateTFBest(string $barcode): array {
        $ranking = $this->sa->getStoreRanking($barcode, TF_TARGET_DAYS);
        if (!$ranking) return [];

        $cHold   = $ranking[0]['company_holding'];
        $topInfo = $this->sa->getTopSellerInfo($barcode);

        $donors    = [];
        $receivers = [];

        foreach ($ranking as $s) {
            // ── Exclude ──────────────────────────────────────
            if (in_array($s['store_type'], ['DC','DISPLAY'])) continue;

            // ── Donor logic ──────────────────────────────────
            $avail = 0;
            if ($cHold['can_xfer_all'] && $s['stock'] > 0 && $s['daily_rate'] > 0) {
                // Company holding < 30d → โอนออกหมดได้
                $avail = $s['stock'];
            } elseif ($s['week_cover'] < WC_TRANSFER_ALL && $s['week_cover'] > 0 && $s['stock'] > 0) {
                // WC < 4w → โอนออกหมดได้ (ไม่ต้องเก็บขั้นต่ำ)
                $avail = $s['stock'];
            } elseif ($s['week_cover'] > WC_STOP && $s['week_cover'] < 999) {
                // Excess → โอนส่วนที่เกิน keep
                $keepWeeks = ($topInfo && $s['week_cover'] > 2) ? 2.0 : 1.0;
                $keep      = (int)ceil($s['weekly_rate'] * $keepWeeks);
                $avail     = max(0, $s['stock'] - $keep);
            }

            if ($avail > 0) {
                $donors[] = ['available' => $avail] + $s;
            }

            // ── Receiver: ขาด + ต้องเคยขาย ──────────────────
            if ($s['diff'] < 0 && $s['qty_sold'] > 0) {
                $receivers[] = ['need' => abs($s['diff'])] + $s;
            }
        }

        if (!$donors || !$receivers) return [];

        // Donors: excess มากสุดก่อน
        usort($donors,    fn($a,$b) => $b['available'] <=> $a['available']);
        // Receivers: ขายเร็วสุดก่อน
        usort($receivers, fn($a,$b) => $b['daily_rate'] <=> $a['daily_rate']);

        $plans = [];
        foreach ($receivers as $recv) {
            $need = $recv['need'];
            foreach ($donors as &$donor) {
                if ($donor['available'] <= 0 || $need <= 0) continue;
                if ($donor['store_code_new'] === $recv['store_code_new']) continue;

                $qty  = min($donor['available'], $need);
                $dist = $this->haversine($donor['lat'], $donor['lng'], $recv['lat'], $recv['lng']);
                $dArr = $this->sa->diffLabel($recv['stock'], $recv['daily_rate'], TF_TARGET_DAYS);

                $plans[] = [
                    'plan_type'       => 'TF_BEST',
                    'barcode'         => $barcode,
                    'from_store_code' => $donor['store_code'],
                    'from_stock'      => $donor['stock'],
                    'from_week_cover' => $donor['week_cover'],
                    'from_holding'    => $donor['holding_days'],
                    'from_excess'     => $donor['available'],
                    'from_diff_label' => $donor['diff_label'],
                    'from_short'      => $donor['store_short'],
                    'from_name'       => $donor['store_name'],
                    'to_store_code'   => $recv['store_code'],
                    'to_stock'        => $recv['stock'],
                    'to_week_cover'   => $recv['week_cover'],
                    'to_daily_rate'   => $recv['daily_rate'],
                    'to_need_qty'     => (int)round($recv['daily_rate'] * TF_TARGET_DAYS - $recv['stock']),
                    'to_diff_label'   => $dArr['label'],
                    'to_short'        => $recv['store_short'],
                    'to_name'         => $recv['store_name'],
                    'qty'             => $qty,
                    'priority'        => $recv['rank'],
                    'distance_km'     => round($dist, 2),
                    'company_holding' => $cHold['holding_days'],
                    'can_xfer_all'    => (int)$cHold['can_xfer_all'],
                    'note'            => "{$donor['store_short']} → {$recv['store_short']} | {$dArr['label']}",
                ];

                $donor['available'] -= $qty;
                $need               -= $qty;
            }
        }
        return $plans;
    }

    // ════════════════════════════════════════════════════════
    // Refill  (DC → สาขา, target 14d)
    // ════════════════════════════════════════════════════════
    public function generateRefill(string $barcode): array {
        $dcStock = $this->sa->getDCStock($barcode);
        if ($dcStock <= 0) return ['dc_stock'=>0,'items'=>[]];

        $ranking  = $this->sa->getStoreRanking($barcode, REFILL_TARGET_DAYS);
        $remain   = $dcStock;
        $plans    = [];

        foreach ($ranking as $s) {
            if ($remain <= 0) break;
            if ($s['daily_rate'] <= 0) continue;     // ไม่เคยขาย → ไม่เติม
            if ($s['diff'] >= 0)       continue;     // stock พอ/เกิน → ข้าม

            $need    = abs($s['diff']);
            $give    = min($need, $remain);
            $projWC  = $s['weekly_rate'] > 0
                ? round(($s['stock'] + $give) / $s['weekly_rate'], 1)
                : 999.0;
            $priority = match(true) {
                $s['week_cover'] < WC_CRITICAL => 3,
                $s['week_cover'] < WC_WARNING  => 2,
                default                        => 1,
            };

            $plans[] = [
                'plan_type'       => 'REFILL',
                'barcode'         => $barcode,
                'from_store_code' => DC_DS_CODES[0],
                'from_stock'      => $remain,
                'from_short'      => 'DC',
                'from_name'       => 'Pronto Dc Office',
                'to_store_code'   => $s['store_code'],
                'to_stock'        => $s['stock'],
                'to_week_cover'   => $s['week_cover'],
                'to_daily_rate'   => $s['daily_rate'],
                'to_need_qty'     => $need,
                'to_diff_label'   => $s['diff_label'],
                'to_short'        => $s['store_short'],
                'to_name'         => $s['store_name'],
                'qty'             => $give,
                'priority'        => $priority,
                'distance_km'     => null,
                'company_holding' => null,
                'can_xfer_all'    => 0,
                'projected_wc'    => $projWC,
                'note'            => $give < $need
                    ? "⚠️ DC มีแค่ {$give}/{$need} ชิ้น"
                    : "✅ เติม {$give} → WC {$projWC}w",
            ];

            $remain -= $give;
        }

        usort($plans, fn($a,$b) => $b['priority'] <=> $a['priority']);
        return ['dc_stock' => $dcStock, 'dc_remain' => $remain, 'items' => $plans];
    }

    // ════════════════════════════════════════════════════════
    // Generate & Save All Plans
    // ════════════════════════════════════════════════════════
    public function generateAndSaveAll(): array {
        $this->db->prepare("DELETE FROM replenish_plans WHERE upload_id=:uid AND status='PENDING'")
                 ->execute([':uid'=>$this->uploadId]);

        $barcodes = $this->sa->getBarcodes();
        $summary  = ['tf_best'=>0,'refill'=>0,'total_qty'=>0];

        foreach ($barcodes as $bc) {
            $dcStock = $this->sa->getDCStock($bc);
            if ($dcStock <= 0) {
                foreach ($this->generateTFBest($bc) as $p) {
                    $this->savePlan($p);
                    $summary['tf_best']++;
                    $summary['total_qty'] += $p['qty'];
                }
            } else {
                $res = $this->generateRefill($bc);
                foreach ($res['items'] as $p) {
                    $this->savePlan($p);
                    $summary['refill']++;
                    $summary['total_qty'] += $p['qty'];
                }
            }
        }
        return $summary;
    }

    // ════════════════════════════════════════════════════════
    // Get Plans from DB
    // ════════════════════════════════════════════════════════
    public function getPlans(string $type = '', string $status = 'PENDING'): array {
        $sql = "SELECT rp.*, p.product_name, p.price,
                       COALESCE(fs.store_name, fs2.store_name, rp.from_store_code) AS from_store_name,
                       COALESCE(fs.store_type, fs2.store_type, '?') AS from_store_type,
                       COALESCE(ts.store_name, ts2.store_name, rp.to_store_code) AS to_store_name,
                       COALESCE(ts.store_type, ts2.store_type, '?') AS to_store_type
                FROM replenish_plans rp
                LEFT JOIN replenish_products p ON rp.barcode = p.barcode
                LEFT JOIN stores fs ON rp.from_store_code = fs.store_code
                LEFT JOIN stores fs2 ON rp.from_store_code = fs2.store_code_new
                LEFT JOIN stores ts ON rp.to_store_code = ts.store_code
                LEFT JOIN stores ts2 ON rp.to_store_code = ts2.store_code_new
                WHERE rp.upload_id = :uid";
        $params = [':uid' => $this->uploadId];
        if ($status) { $sql .= " AND rp.status=:st"; $params[':st'] = $status; }
        if ($type)   { $sql .= " AND rp.plan_type=:pt"; $params[':pt'] = $type; }
        $sql .= " ORDER BY rp.priority DESC, rp.to_week_cover ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ════════════════════════════════════════════════════════
    // Approve / Reject
    // ════════════════════════════════════════════════════════
    public function updateStatus(int $planId, string $status, ?string $reason = null, string $by = 'system'): bool {
        $stmt = $this->db->prepare(
            "UPDATE replenish_plans SET status=:st,reject_reason=:rr,approved_by=:by,action_at=NOW()
             WHERE id=:id AND upload_id=:uid"
        );
        return $stmt->execute([':st'=>$status,':rr'=>$reason,':by'=>$by,':id'=>$planId,':uid'=>$this->uploadId]);
    }

    // ════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════
    private function savePlan(array $p): void {
        $sql = "INSERT INTO replenish_plans
                (upload_id,plan_type,barcode,
                 from_store_code,from_stock,from_week_cover,from_holding_days,from_excess_qty,from_diff_label,
                 to_store_code,to_stock,to_week_cover,to_daily_rate,to_need_qty,to_diff_label,
                 qty,priority,distance_km,company_holding,can_xfer_all,status)
                VALUES
                (:uid,:type,:bc,
                 :fsc,:fst,:fwc,:fhd,:feq,:fdl,
                 :tsc,:tst,:twc,:tdr,:tnq,:tdl,
                 :qty,:pri,:dist,:ch,:cxa,'PENDING')";
        $this->db->prepare($sql)->execute([
            ':uid'  => $this->uploadId,
            ':type' => $p['plan_type'],
            ':bc'   => $p['barcode'],
            ':fsc'  => $p['from_store_code'],
            ':fst'  => $p['from_stock'],
            ':fwc'  => $p['from_week_cover'] ?? null,
            ':fhd'  => $p['from_holding'] ?? null,
            ':feq'  => $p['from_excess'] ?? 0,
            ':fdl'  => $p['from_diff_label'] ?? null,
            ':tsc'  => $p['to_store_code'],
            ':tst'  => $p['to_stock'],
            ':twc'  => $p['to_week_cover'] ?? null,
            ':tdr'  => $p['to_daily_rate'] ?? null,
            ':tnq'  => $p['to_need_qty'] ?? 0,
            ':tdl'  => $p['to_diff_label'] ?? null,
            ':qty'  => $p['qty'],
            ':pri'  => $p['priority'] ?? 0,
            ':dist' => $p['distance_km'] ?? null,
            ':ch'   => $p['company_holding'] ?? null,
            ':cxa'  => $p['can_xfer_all'] ?? 0,
        ]);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
        if ($lat1==0 && $lon1==0) return 0;
        if ($lat2==0 && $lon2==0) return 0;
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}