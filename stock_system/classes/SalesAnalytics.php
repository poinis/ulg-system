<?php
/**
 * SalesAnalytics.php  v3.0
 *
 * Join chain สำหรับ Sell Rate:
 *   replenish_stock.store_code_new
 *   → stores.store_code_new  → stores.store_code   (daily_sales)
 *   → daily_sales.store_code + daily_sales.line_barcode + daily_sales.brand
 *
 * ตารางหลักที่อ่าน:
 *   - replenish_stock       (Physical / stock snapshot)
 *   - replenish_uploads     (upload_date, rate_days)
 *   - replenish_products    (product_name, price, family)
 *   - stores                (store mapping + coords + open_date)
 *   - daily_sales           (ยอดขายจริง — source of truth)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';

class SalesAnalytics {

    protected PDO  $db;
    public    ?int $uploadId;

    // cache
    private array $storeMap    = [];   // store_code_new → row from stores
    private array $uploadCache = [];

    public function __construct(?int $uploadId = null) {
        $this->db       = Database::getInstance();
        $this->uploadId = $uploadId ?? $this->getLatestUploadId();
        $this->buildStoreMap();
    }

    // ════════════════════════════════════════════════════════
    // 1.  Sell Rate per barcode × store  (from daily_sales)
    //     เรียก batch query ครั้งเดียวต่อ 1 barcode
    //     Return: [ store_code_new => {daily_rate, weekly_rate, qty_sold, effective_days} ]
    // ════════════════════════════════════════════════════════
    public function getSellRates(string $barcode, ?array $upload = null): array {
        $upload ??= $this->getUploadInfo();
        if (!$upload) return [];

        $uploadDate = $upload['upload_date'];
        $rateDays   = (int)$upload['rate_days'];

        // Join daily_sales → stores (via store_code) → filter by brand + barcode + date range
        $sql = "SELECT
                    st.store_code_new,
                    st.store_code,
                    st.open_date,
                    CASE
                        WHEN st.open_date IS NOT NULL
                          AND DATEDIFF(:ud, st.open_date) BETWEEN 1 AND :rd - 1
                        THEN DATEDIFF(:ud2, st.open_date)
                        ELSE :rd2
                    END                             AS effective_days,
                    COALESCE(SUM(ds.qty), 0)        AS qty_sold
                FROM stores st
                LEFT JOIN daily_sales ds
                    ON  (ds.store_code = st.store_code OR ds.store_code = st.store_code_new)
                    AND ds.line_barcode = :bc
                    AND ds.brand        = :brand
                    AND ds.qty          > 0
                    AND ds.sale_date    > DATE_SUB(:ud3, INTERVAL :rd3 DAY)
                    AND ds.sale_date   <= :ud4
                WHERE st.store_code_new IS NOT NULL
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                GROUP BY st.store_code_new, st.store_code, st.open_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':bc'  => $barcode,    ':brand' => DS_BRAND,
            ':ud'  => $uploadDate, ':ud2'   => $uploadDate,
            ':ud3' => $uploadDate, ':ud4'   => $uploadDate,
            ':rd'  => $rateDays,   ':rd2'   => $rateDays,
            ':rd3' => $rateDays,
        ]);

        $result = [];
        foreach ($stmt->fetchAll() as $r) {
            $effDays = max(1, (int)$r['effective_days']);
            $sold    = (int)$r['qty_sold'];
            $result[$r['store_code_new']] = [
                'store_code'     => $r['store_code'],
                'effective_days' => $effDays,
                'qty_sold'       => $sold,
                'daily_rate'     => $sold > 0 ? round($sold / $effDays, 6) : 0.0,
                'weekly_rate'    => $sold > 0 ? round($sold / $effDays * 7, 4) : 0.0,
            ];
        }
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // 2.  Sell Rates สำหรับ barcode ทั้งหมดในคราวเดียว
    //     (ใช้ใน Dashboard / All Products Summary)
    //     Return: [ barcode => [ store_code_new => {...} ] ]
    // ════════════════════════════════════════════════════════
    public function getAllSellRates(?array $upload = null): array {
        $upload ??= $this->getUploadInfo();
        if (!$upload) return [];

        $uploadDate = $upload['upload_date'];
        $rateDays   = (int)$upload['rate_days'];

        // ดึงยอดขายจาก daily_sales — join stores ผ่าน store_code
        // ไม่ต้อง join replenish_products (ไม่กรองด้วย family อีกแล้ว)
        $sql = "SELECT
                    ds.line_barcode                 AS barcode,
                    st.store_code_new,
                    st.store_code,
                    st.open_date,
                    MAX(ds.item_description)        AS item_description,
                    CASE
                        WHEN st.open_date IS NOT NULL
                          AND DATEDIFF(:ud, st.open_date) BETWEEN 1 AND :rd - 1
                        THEN DATEDIFF(:ud2, st.open_date)
                        ELSE :rd2
                    END                             AS effective_days,
                    SUM(ds.qty)                     AS qty_sold
                FROM daily_sales ds
                JOIN stores st ON (ds.store_code = st.store_code OR ds.store_code = st.store_code_new)
                WHERE ds.brand      = :brand
                  AND ds.qty        > 0
                  AND ds.sale_date  > DATE_SUB(:ud3, INTERVAL :rd3 DAY)
                  AND ds.sale_date <= :ud4
                  AND st.store_code_new IS NOT NULL
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                GROUP BY ds.line_barcode, st.store_code_new, st.store_code, st.open_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':brand'  => DS_BRAND,
            ':ud'     => $uploadDate, ':ud2'   => $uploadDate,
            ':ud3'    => $uploadDate, ':ud4'   => $uploadDate,
            ':rd'     => $rateDays,   ':rd2'   => $rateDays,
            ':rd3'    => $rateDays,
        ]);

        $result      = [];
        $descByBC    = [];  // barcode → item_description (ใช้ค่าแรกที่พบ)
        foreach ($stmt->fetchAll() as $r) {
            $effDays = max(1, (int)$r['effective_days']);
            $sold    = (int)$r['qty_sold'];
            $bc      = $r['barcode'];
            if (!isset($descByBC[$bc]) && !empty($r['item_description'])) {
                $descByBC[$bc] = $r['item_description'];
            }
            $result[$bc][$r['store_code_new']] = [
                'store_code'       => $r['store_code'],
                'effective_days'   => $effDays,
                'qty_sold'         => $sold,
                'daily_rate'       => $sold > 0 ? round($sold / $effDays, 6) : 0.0,
                'weekly_rate'      => $sold > 0 ? round($sold / $effDays * 7, 4) : 0.0,
                'item_description' => $r['item_description'] ?? null,
            ];
        }
        // Sync item_description to replenish_products (อัพเดทชื่อถ้า daily_sales มีข้อมูล)
        if ($descByBC) {
            $upd = $this->db->prepare(
                "UPDATE replenish_products SET product_name=:nm WHERE barcode=:bc AND (product_name IS NULL OR product_name='' OR product_name=barcode)"
            );
            foreach ($descByBC as $bc => $nm) {
                if ($nm) $upd->execute([':nm'=>$nm, ':bc'=>$bc]);
            }
        }
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // 3.  Store Performance Ranking  (เรียงตาม Sell Rate สูง→ต่ำ)
    //     พร้อม Diff / Holding / Status
    // ════════════════════════════════════════════════════════
    public function getStoreRanking(string $barcode, int $targetDays = REFILL_TARGET_DAYS): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return [];

        // Stock snapshot
        $sql = "SELECT rs.store_code_new, rs.physical AS stock
                FROM replenish_stock rs
                JOIN stores st ON rs.store_code_new = st.store_code_new
                WHERE rs.upload_id = :uid
                  AND rs.barcode   = :bc
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $this->uploadId, ':bc' => $barcode]);
        $stockMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);  // [store_code_new => physical]

        // Sell rates from daily_sales
        $rates = $this->getSellRates($barcode, $upload);

        // Company holding
        $cHold = $this->getCompanyHolding($barcode, $stockMap, $rates);

        $rows = [];
        foreach ($stockMap as $scNew => $stock) {
            $storeInfo = $this->storeMap[$scNew] ?? null;
            if (!$storeInfo) continue;

            $r         = $rates[$scNew] ?? ['daily_rate'=>0,'weekly_rate'=>0,'qty_sold'=>0,'effective_days'=>(int)$upload['rate_days']];
            $stock     = (int)$stock;
            $wc        = $r['weekly_rate'] > 0 ? round($stock / $r['weekly_rate'], 2) : 999.0;
            $holding   = $r['daily_rate']  > 0 ? round($stock / $r['daily_rate'],  1) : 999.0;
            $diffArr   = $this->diffLabel($stock, $r['daily_rate'], $targetDays);
            $note      = '';
            if ($storeInfo['open_date'] && $r['effective_days'] < (int)$upload['rate_days']) {
                $note = 'เปิด ' . date('j M', strtotime($storeInfo['open_date'])) . " (ใช้ {$r['effective_days']}d)";
            }

            $rows[] = [
                'store_code_new'  => $scNew,
                'store_code'      => $storeInfo['store_code'],
                'store_name'      => $storeInfo['store_name'],
                'store_type'      => $storeInfo['store_type'],
                'store_short'     => $storeInfo['store_short'],
                'lat'             => (float)($storeInfo['latitude']  ?? 0),
                'lng'             => (float)($storeInfo['longitude'] ?? 0),
                'stock'           => $stock,
                'qty_sold'        => $r['qty_sold'],
                'effective_days'  => $r['effective_days'],
                'daily_rate'      => $r['daily_rate'],
                'weekly_rate'     => $r['weekly_rate'],
                'week_cover'      => $wc,
                'holding_days'    => $holding,
                'need_qty'        => $diffArr['need'],
                'diff'            => $diffArr['diff'],
                'diff_label'      => $diffArr['label'],
                'status'          => $this->wcStatus($wc),
                'note'            => $note,
                'company_holding' => $cHold,
            ];
        }

        usort($rows, fn($a,$b) => $b['daily_rate'] <=> $a['daily_rate']);
        foreach ($rows as $i => &$r) $r['rank'] = $i + 1;
        return $rows;
    }

    // ════════════════════════════════════════════════════════
    // 4.  Monthly Breakdown (Nov | Dec | Jan)
    //     ดึงจาก daily_sales GROUP BY month
    //     Return: [ 'months'=>['Nov','Dec','Jan'], 'by_store'=>[store_code_new=>[m=>qty]] ]
    // ════════════════════════════════════════════════════════
    public function getMonthlyBreakdown(string $barcode, int $numMonths = 3): array {
        $upload  = $this->getUploadInfo();
        $endDate = $upload['upload_date'] ?? date('Y-m-d');

        $sql = "SELECT
                    st.store_code_new,
                    DATE_FORMAT(ds.sale_date,'%Y-%m')   AS ym,
                    DATE_FORMAT(ds.sale_date,'%b')       AS mlabel,
                    SUM(ds.qty)                          AS qty
                FROM daily_sales ds
                JOIN stores st ON (ds.store_code = st.store_code OR ds.store_code = st.store_code_new)
                WHERE ds.line_barcode = :bc
                  AND ds.brand        = :brand
                  AND ds.qty          > 0
                  AND ds.sale_date   >= DATE_FORMAT(DATE_SUB(:ed, INTERVAL :nm MONTH),'%Y-%m-01')
                  AND ds.sale_date   <= :ed2
                  AND st.store_code_new IS NOT NULL
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                GROUP BY st.store_code_new, ym, mlabel
                ORDER BY ym";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':bc'=>$barcode,':brand'=>DS_BRAND,
                        ':ed'=>$endDate,':ed2'=>$endDate,':nm'=>$numMonths]);

        $byStore = [];
        $ymMap   = [];   // ym → label
        foreach ($stmt->fetchAll() as $r) {
            $byStore[$r['store_code_new']][$r['mlabel']] = (int)$r['qty'];
            $ymMap[$r['ym']] = $r['mlabel'];
        }
        ksort($ymMap);
        return ['months' => array_values($ymMap), 'by_store' => $byStore];
    }

    // ════════════════════════════════════════════════════════
    // 5.  Company Holding  = SUM(stock all stores) ÷ SUM(daily rate)
    // ════════════════════════════════════════════════════════
    public function getCompanyHolding(string $barcode, ?array $stockMap = null, ?array $rates = null): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return ['total_stock'=>0,'holding_days'=>999,'can_xfer_all'=>false];

        if ($stockMap === null) {
            $stmt = $this->db->prepare(
                "SELECT rs.store_code_new, rs.physical FROM replenish_stock rs
                 JOIN stores st ON rs.store_code_new=st.store_code_new
                 WHERE rs.upload_id=:uid AND rs.barcode=:bc AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))"
            );
            $stmt->execute([':uid'=>$this->uploadId,':bc'=>$barcode]);
            $stockMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        if ($rates === null) $rates = $this->getSellRates($barcode, $upload);

        $totalStock = array_sum($stockMap);
        $totalRate  = array_sum(array_column($rates, 'daily_rate'));
        $holding    = $totalRate > 0 ? round($totalStock / $totalRate, 1) : 999.0;

        return [
            'total_stock'   => $totalStock,
            'total_rate'    => round($totalRate, 4),
            'holding_days'  => $holding,
            'can_xfer_all'  => ($holding < HOLDING_XFER_ALL),
        ];
    }

    // ════════════════════════════════════════════════════════
    // 6.  DC Stock (stock_code_new ใน DC_CEGID_CODES)
    // ════════════════════════════════════════════════════════
    public function getDCStock(string $barcode): int {
        $in  = implode(',', array_fill(0, count(DC_CEGID_CODES), '?'));
        $sql = "SELECT COALESCE(SUM(physical),0) FROM replenish_stock
                WHERE upload_id=? AND barcode=? AND store_code_new IN ($in)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$this->uploadId, $barcode], DC_CEGID_CODES));
        return (int)$stmt->fetchColumn();
    }

    // ════════════════════════════════════════════════════════
    // 7.  All Products Summary (Dashboard table)
    //     ใช้ getAllSellRates() — 1 big query ทั้งหมด
    // ════════════════════════════════════════════════════════
    public function getAllProductsSummary(?string $storeFilter = null, string $statusFilter = ''): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return [];

        // Stock
        // ดึง stock rows — ไม่กรอง family แล้ว (กรองด้วย brand ใน daily_sales)
        $sql = "SELECT rs.store_code_new, rs.barcode, rs.physical AS stock,
                       COALESCE(rp.product_name, rs.barcode) AS product_name,
                       rp.price,
                       st.store_code AS ds_store_code, st.store_name,
                       COALESCE(st.store_type,'OTHER') AS store_type,
                       st.open_date,
                       CASE
                           WHEN st.open_date IS NOT NULL
                             AND DATEDIFF(:ud, st.open_date) BETWEEN 1 AND :rd - 1
                           THEN DATEDIFF(:ud2, st.open_date)
                           ELSE :rd2
                       END AS effective_days
                FROM replenish_stock rs
                LEFT JOIN replenish_products rp ON rs.barcode = rp.barcode
                JOIN stores st ON rs.store_code_new = st.store_code_new
                WHERE rs.upload_id = :uid
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))";
        $params = [':uid'=>$this->uploadId,
                   ':ud'=>$upload['upload_date'],':ud2'=>$upload['upload_date'],
                   ':rd'=>$upload['rate_days'],':rd2'=>$upload['rate_days']];
        if ($storeFilter) { $sql .= " AND rs.store_code_new=:scn"; $params[':scn']=$storeFilter; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stockRows = $stmt->fetchAll();

        // All sell rates in 1 query
        $allRates = $this->getAllSellRates($upload);

        $result = [];
        foreach ($stockRows as $r) {
            $scNew   = $r['store_code_new'];
            $bc      = $r['barcode'];
            $stock   = (int)$r['stock'];
            $effDays = max(1, (int)$r['effective_days']);
            $rInfo   = $allRates[$bc][$scNew] ?? null;
            $sold    = $rInfo ? $rInfo['qty_sold'] : 0;
            $effDays = $rInfo ? $rInfo['effective_days'] : $effDays;
            $daily   = $rInfo ? $rInfo['daily_rate']  : 0.0;
            $weekly  = $rInfo ? $rInfo['weekly_rate'] : 0.0;
            $wc      = $weekly > 0 ? round($stock / $weekly, 2) : 999.0;
            $holding = $daily  > 0 ? round($stock / $daily,  1) : 999.0;
            $dArr    = $this->diffLabel($stock, $daily, REFILL_TARGET_DAYS);
            $status  = $this->wcStatus($wc);

            if ($statusFilter && $status !== $statusFilter) continue;

            $result[] = [
                'store_code_new' => $scNew,
                'store_code'     => $r['ds_store_code'],
                'store_name'     => $r['store_name'],
                'store_type'     => $r['store_type'],
                'store_short'    => $this->storeMap[$scNew]['store_short'] ?? $scNew,
                'barcode'        => $bc,
                'product_name'   => ($allRates[$bc][$scNew]['item_description'] ?? null) ?: ($r['product_name'] ?: $bc),
                'price'          => $r['price'],
                'stock'          => $stock,
                'qty_sold'       => $sold,
                'effective_days' => $effDays,
                'daily_rate'     => $daily,
                'weekly_rate'    => $weekly,
                'week_cover'     => $wc,
                'holding_days'   => $holding,
                'need_14d'       => $dArr['need'],
                'diff'           => $dArr['diff'],
                'diff_label'     => $dArr['label'],
                'status'         => $status,
            ];
        }
        usort($result, fn($a,$b) => $b['daily_rate'] <=> $a['daily_rate']);
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // 8.  Top Sellers  (คำนวณจาก daily_sales ช่วง rate_days)
    // ════════════════════════════════════════════════════════
    public function calculateTopSellers(): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return [];

        // Top sellers ดูจาก daily_sales — match กับ barcode ใน replenish_stock เท่านั้น
        $sql = "SELECT ds.line_barcode AS barcode, SUM(ds.qty) AS total_sold
                FROM daily_sales ds
                JOIN stores st ON (ds.store_code = st.store_code OR ds.store_code = st.store_code_new)
                WHERE ds.brand     = :brand
                  AND ds.qty       > 0
                  AND ds.sale_date > DATE_SUB(:ud, INTERVAL :rd DAY)
                  AND ds.sale_date <= :ud2
                  AND st.store_code_new IS NOT NULL
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                  AND EXISTS (
                      SELECT 1 FROM replenish_stock rs2
                      WHERE rs2.barcode = ds.line_barcode AND rs2.upload_id = :uid2
                  )
                GROUP BY ds.line_barcode
                ORDER BY total_sold DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':brand'=>DS_BRAND,
                        ':ud'=>$upload['upload_date'],':ud2'=>$upload['upload_date'],
                        ':rd'=>$upload['rate_days'],':uid2'=>$this->uploadId]);
        $rows  = $stmt->fetchAll();
        $total = array_sum(array_column($rows, 'total_sold'));
        if (!$total) return [];

        $this->db->prepare("DELETE FROM replenish_top_sellers WHERE upload_id=:uid")
                 ->execute([':uid'=>$this->uploadId]);

        $ins = $this->db->prepare(
            "INSERT INTO replenish_top_sellers
             (upload_id,barcode,rank_position,tier,total_qty_sold,sales_contrib_pct,target_weeks)
             VALUES(:uid,:bc,:rank,:tier,:qty,:pct,:tw)"
        );
        $results = [];
        foreach ($rows as $i => $r) {
            $rank = $i + 1;
            $pct  = round($r['total_sold'] / $total * 100, 3);
            [$tier,$tw] = match(true) {
                $rank <= 20 => ['TOP1_20',  TOP_1_20_WEEKS],
                $rank <= 40 => ['TOP21_40', TOP_21_40_WEEKS],
                $rank <= 60 => ['TOP41_60', TOP_41_60_WEEKS],
                default     => [null, 4],
            };
            $ins->execute([':uid'=>$this->uploadId,':bc'=>$r['barcode'],':rank'=>$rank,
                           ':tier'=>$tier,':qty'=>$r['total_sold'],':pct'=>$pct,':tw'=>$tw]);
            $results[$r['barcode']] = compact('rank','tier','pct','tw');
        }
        return $results;
    }

    // ════════════════════════════════════════════════════════
    // 9.  Reorder Alerts (Top 1-60 ที่ total WC ต่ำกว่า trigger)
    // ════════════════════════════════════════════════════════
    public function getReorderAlerts(): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return [];

        $sql = "SELECT ts.barcode, rp.product_name, rp.is_markdown,
                       ts.rank_position, ts.tier, ts.target_weeks, ts.total_qty_sold,
                       SUM(rs.physical) AS total_stock
                FROM replenish_top_sellers ts
                JOIN replenish_products rp ON ts.barcode = rp.barcode
                JOIN replenish_stock rs    ON ts.barcode = rs.barcode AND rs.upload_id = :uid2
                JOIN stores st             ON rs.store_code_new = st.store_code_new
                WHERE ts.upload_id = :uid
                  AND ts.rank_position <= 60
                  AND rp.is_markdown = 0
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                GROUP BY ts.barcode, rp.product_name, rp.is_markdown,
                         ts.rank_position, ts.tier, ts.target_weeks, ts.total_qty_sold
                HAVING ts.total_qty_sold > 0
                ORDER BY ts.rank_position";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid'=>$this->uploadId,':uid2'=>$this->uploadId]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $rates   = $this->getSellRates($r['barcode'], $upload);
            $totWR   = array_sum(array_column($rates, 'weekly_rate'));
            $totWC   = $totWR > 0 ? round($r['total_stock'] / $totWR, 2) : 999.0;
            $trigger = match($r['tier']) {
                'TOP1_20'  => NOTICE_1_20,
                'TOP21_40' => NOTICE_21_40,
                'TOP41_60' => NOTICE_41_60,
                default    => 99,
            };
            if ($totWC < $trigger) {
                $result[] = array_merge($r, [
                    'total_weekly_rate' => round($totWR, 4),
                    'total_week_cover'  => $totWC,
                ]);
            }
        }
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // 10. Dashboard Stats
    // ════════════════════════════════════════════════════════
    public function getDashboardStats(): array {
        $upload = $this->getUploadInfo();
        if (!$upload) return [];

        $sql = "SELECT
                    COUNT(DISTINCT rs.barcode)        AS total_skus,
                    COUNT(DISTINCT rs.store_code_new) AS total_stores,
                    COALESCE(SUM(rs.physical),0)      AS total_stock
                FROM replenish_stock rs
                JOIN stores st ON rs.store_code_new = st.store_code_new
                WHERE rs.upload_id = :uid
                  AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid'=>$this->uploadId]);
        $stats = $stmt->fetch() ?: [];

        // Stats จาก daily_sales — match เฉพาะ barcode ที่มีใน replenish_stock
        $sql2 = "SELECT
                     COUNT(DISTINCT ds.line_barcode) AS active_skus,
                     COALESCE(SUM(ds.qty),0)         AS total_sold,
                     COUNT(DISTINCT ds.sale_date)    AS selling_days
                 FROM daily_sales ds
                 JOIN stores st ON (ds.store_code = st.store_code OR ds.store_code = st.store_code_new)
                 WHERE ds.brand     = :brand
                   AND ds.qty       > 0
                   AND ds.sale_date > DATE_SUB(:ud, INTERVAL :rd DAY)
                   AND ds.sale_date <= :ud2
                   AND st.store_code_new IS NOT NULL
                   AND (st.store_type IS NULL OR st.store_type NOT IN ('DC','WEBSITE'))
                   AND EXISTS (
                       SELECT 1 FROM replenish_stock rs2
                       WHERE rs2.barcode = ds.line_barcode AND rs2.upload_id = :uid2
                   )";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([':brand'=>DS_BRAND,
                         ':ud'=>$upload['upload_date'],':ud2'=>$upload['upload_date'],
                         ':rd'=>$upload['rate_days'],':uid2'=>$this->uploadId]);
        return array_merge($stats, $stmt2->fetch() ?: []);
    }

    // ════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════

    public function diffLabel(int $stock, float $daily, int $targetDays): array {
        $need = (int)round($daily * $targetDays);
        $diff = $stock - $need;
        return [
            'need'  => $need,
            'diff'  => $diff,
            'label' => match(true) {
                $diff > 0  => "เกิน {$diff}",
                $diff < 0  => "ขาด " . abs($diff),
                default    => 'พอดี',
            },
        ];
    }

    public function wcStatus(float $wc): string {
        return match(true) {
            $wc >= 999 || $wc > WC_STOP => 'overstock',
            $wc < WC_CRITICAL           => 'critical',
            $wc < WC_WARNING            => 'warning',
            $wc <= 2.0                  => 'ok',
            default                     => 'good',
        };
    }

    public function getLatestUploadId(): ?int {
        $v = $this->db->query("SELECT MAX(id) FROM replenish_uploads")->fetchColumn();
        return $v ? (int)$v : null;
    }

    public function getUploadInfo(): ?array {
        if (!$this->uploadId) return null;
        if (!isset($this->uploadCache[$this->uploadId])) {
            $stmt = $this->db->prepare("SELECT * FROM replenish_uploads WHERE id=:id");
            $stmt->execute([':id'=>$this->uploadId]);
            $this->uploadCache[$this->uploadId] = $stmt->fetch() ?: null;
        }
        return $this->uploadCache[$this->uploadId];
    }

    public function getTopSellerInfo(string $barcode): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM replenish_top_sellers WHERE upload_id=:uid AND barcode=:bc LIMIT 1"
        );
        $stmt->execute([':uid'=>$this->uploadId,':bc'=>$barcode]);
        return $stmt->fetch() ?: null;
    }

    public function getBarcodes(): array {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT barcode FROM replenish_stock WHERE upload_id=:uid"
        );
        $stmt->execute([':uid'=>$this->uploadId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getStoreMap(): array { return $this->storeMap; }

    private function buildStoreMap(): void {
        $rows = $this->db->query(
            "SELECT store_code, store_code_new, store_name,
                    COALESCE(store_type,'OTHER') AS store_type,
                    open_date, latitude, longitude, region
             FROM stores WHERE store_code_new IS NOT NULL"
        )->fetchAll();

        $shortNames = [
            'TOPOLOGIE CENTRAL WORLD'            => 'CW',
            'TOPOLOGIE LADPRAO'                  => 'LDP',
            'TOPOLOGIE Mega Bangna'              => 'MEGA',
            'TOPOLOGIE DUSIT CENTRAL PARK'       => 'DCP',
            'TOPOLOGIE SIAM SOI2'                => 'SIAM',
            'TOPOLOGIE Paragon'                  => 'PGN',
            'SOUP EMSPHERE'                      => 'SOUP-EMS',
            'SOUP PARAGON'                       => 'SOUP-PGN',
            'SOUP PATTAYA'                       => 'SOUP-PTV',
            'SOUP TERMINAL 21'                   => 'SOUP-T21',
            'SOUP JUNGCEYLON'                    => 'SOUP-JCL',
            'SOUP CENTRAL WORLD'                 => 'SOUP-CW',
            'Pronto and Co Festival Chiangmai'   => 'P-CM',
            'Pronto Central Rama 9'              => 'P-RAMA9',
            'Thepopupstore Phuket'               => 'POPUP-HKT',
            'And Co ThinkPark'                   => 'ANDCO-TP',
            'And Co OneBkk'                      => 'ANDCO-1BKK',
            'Pronto Dc Office'                   => 'DC',
            'Pronto Online'                      => 'ONLINE',
            'Pronto Mega Bangna'                 => 'P-MEGA',
            'Pronto Siam Paragon'                => 'P-PGN',
            'Pronto Central Lardprao'            => 'P-LDP',
            'Surplus Central Village'            => 'SURPLUS',
            'SW19 CentralwOrld'                  => 'SW19',
            'SUPERDRY LARDPRAO'                  => 'SD-LDP',
        ];

        foreach ($rows as $r) {
            $this->storeMap[$r['store_code_new']] = array_merge($r, [
                'store_short' => $shortNames[$r['store_name']] ?? strtoupper(substr($r['store_name'],0,10)),
            ]);
        }
    }
}