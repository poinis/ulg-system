<?php
/**
 * Customer Purchase Report
 * รายงานยอดซื้อรวมของลูกค้า (customer ขึ้นต้นด้วย 99)
 * Optimized Version
 */

require_once 'config.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Filter settings
$filterInactive = isset($_GET['inactive']) && $_GET['inactive'] == '1';
$filterHigh = isset($_GET['high']) && $_GET['high'] == '1';
$filterMid = isset($_GET['mid']) && $_GET['mid'] == '1';
$filterLow = isset($_GET['low']) && $_GET['low'] == '1';
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));

// Build HAVING clauses
$havingConditions = [];
if ($filterInactive) $havingConditions[] = "MAX(sale_date) < '$sixMonthsAgo'";
if ($filterHigh) $havingConditions[] = "SUM(tax_incl_total) >= 50000";
if ($filterMid) $havingConditions[] = "SUM(tax_incl_total) BETWEEN 10001 AND 49999";
if ($filterLow) $havingConditions[] = "SUM(tax_incl_total) <= 10000";
$havingClause = !empty($havingConditions) ? "HAVING " . implode(" AND ", $havingConditions) : "";

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $exportSql = "
        SELECT 
            customer,
            MAX(first_name) as first_name,
            MAX(last_name) as last_name,
            COUNT(DISTINCT sale_date) as visit_count,
            SUM(qty) as total_qty,
            SUM(tax_incl_total) as total_amount,
            MIN(sale_date) as first_purchase_date,
            MAX(sale_date) as last_purchase_date
        FROM daily_sales
        WHERE customer LIKE '99%'
        GROUP BY customer
        $havingClause
        ORDER BY SUM(tax_incl_total) DESC
    ";
    
    $exportStmt = $conn->query($exportSql);
    $exportData = $exportStmt->fetchAll();
    
    if (!empty($exportData)) {
        $customerList = array_map(function($c) use ($conn) {
            return $conn->quote($c['customer']);
        }, $exportData);
        
        $latestSql = "
            SELECT d1.customer, d1.store_code, d1.item_description, d1.brand
            FROM daily_sales d1
            INNER JOIN (
                SELECT customer, MAX(id) as max_id
                FROM daily_sales
                WHERE customer IN (" . implode(',', $customerList) . ")
                GROUP BY customer
            ) d2 ON d1.customer = d2.customer AND d1.id = d2.max_id
        ";
        $latestStmt = $conn->query($latestSql);
        $latestData = [];
        while ($row = $latestStmt->fetch()) {
            $latestData[$row['customer']] = $row;
        }
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer_report_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['รหัสลูกค้า', 'ชื่อ', 'นามสกุล', 'จำนวนครั้งที่ซื้อ', 'จำนวนชิ้น', 'ยอดซื้อรวม', 'ซื้อครั้งแรก', 'ซื้อล่าสุด', 'สาขาล่าสุด', 'แบรนด์ล่าสุด', 'สินค้าล่าสุด']);
    
    foreach ($exportData as $row) {
        $latest = $latestData[$row['customer']] ?? [];
        fputcsv($output, [
            $row['customer'],
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['visit_count'],
            $row['total_qty'],
            $row['total_amount'],
            $row['first_purchase_date'],
            $row['last_purchase_date'],
            $latest['store_code'] ?? '',
            $latest['brand'] ?? '',
            $latest['item_description'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Pagination
$perPage = 100;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Count
$countSql = "
    SELECT COUNT(*) as total FROM (
        SELECT customer FROM daily_sales
        WHERE customer LIKE '99%'
        GROUP BY customer $havingClause
    ) as subquery
";
try {
    $totalRecords = $conn->query($countSql)->fetch()['total'];
} catch (PDOException $e) {
    $totalRecords = 0;
}
$totalPages = ceil($totalRecords / $perPage);

// Main query
$sql = "
    SELECT 
        customer,
        MAX(first_name) as first_name,
        MAX(last_name) as last_name,
        COUNT(DISTINCT sale_date) as visit_count,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as total_amount,
        MAX(sale_date) as last_purchase_date,
        MIN(sale_date) as first_purchase_date
    FROM daily_sales
    WHERE customer LIKE '99%'
    GROUP BY customer
    $havingClause
    ORDER BY total_amount DESC
    LIMIT $offset, $perPage
";

try {
    $stmt = $conn->query($sql);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}

// ดึงข้อมูลล่าสุดเฉพาะลูกค้าในหน้านี้
$latestData = [];
if (!empty($customers)) {
    $customerList = array_map(function($c) use ($conn) {
        return $conn->quote($c['customer']);
    }, $customers);
    
    $latestSql = "
        SELECT d1.customer, d1.store_code, d1.item_description, d1.brand
        FROM daily_sales d1
        INNER JOIN (
            SELECT customer, MAX(id) as max_id
            FROM daily_sales
            WHERE customer IN (" . implode(',', $customerList) . ")
            GROUP BY customer
        ) d2 ON d1.customer = d2.customer AND d1.id = d2.max_id
    ";
    
    try {
        $latestStmt = $conn->query($latestSql);
        while ($row = $latestStmt->fetch()) {
            $latestData[$row['customer']] = $row;
        }
    } catch (PDOException $e) {}
}

// Summary
$summarySql = "SELECT COUNT(DISTINCT customer) as total_customers, SUM(tax_incl_total) as grand_total FROM daily_sales WHERE customer LIKE '99%'";
try {
    $summary = $conn->query($summarySql)->fetch();
} catch (PDOException $e) {
    $summary = ['total_customers' => 0, 'grand_total' => 0];
}

function buildQueryString($params = []) {
    $current = [
        'inactive' => $_GET['inactive'] ?? '',
        'high' => $_GET['high'] ?? '',
        'mid' => $_GET['mid'] ?? '',
        'low' => $_GET['low'] ?? '',
    ];
    $merged = array_merge($current, $params);
    return http_build_query(array_filter($merged, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานยอดซื้อรวมของลูกค้า</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1700px; margin: 0 auto; }
        .header { background: white; border-radius: 16px; padding: 24px 32px; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .header-left h1 { color: #1a1a2e; font-size: 28px; margin-bottom: 8px; }
        .header-left p { color: #666; font-size: 14px; }
        .export-btn { display: inline-flex; align-items: center; gap: 8px; background: #10b981; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .export-btn:hover { background: #059669; transform: translateY(-2px); }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .card-label { font-size: 13px; color: #888; margin-bottom: 8px; }
        .card-value { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        .card-value.purple { color: #667eea; }
        .card-value.green { color: #10b981; }
        .card-value.orange { color: #f59e0b; }
        .filter-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .filter-section h3 { font-size: 16px; color: #1a1a2e; margin-bottom: 16px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .filter-group { padding: 12px 16px; background: #f8fafc; border-radius: 8px; }
        .filter-group-title { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-weight: 600; }
        .filter-checkbox { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; }
        .filter-checkbox input[type="checkbox"] { width: 18px; height: 18px; accent-color: #667eea; cursor: pointer; }
        .filter-checkbox label { font-size: 14px; color: #444; cursor: pointer; }
        .hint { font-size: 12px; color: #94a3b8; margin-top: 8px; }
        .table-container { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .table-header { padding: 20px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 18px; color: #1a1a2e; }
        .record-count { font-size: 14px; color: #666; background: #f3f4f6; padding: 8px 16px; border-radius: 20px; }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1300px; }
        thead { background: #f8fafc; }
        th { padding: 14px 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        th.text-right, td.text-right { text-align: right; }
        th.text-center, td.text-center { text-align: center; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
        tr:hover { background: #f8fafc; }
        .customer-code { font-family: 'Monaco', 'Consolas', monospace; font-weight: 600; color: #667eea; font-size: 12px; }
        .customer-name { color: #1a1a2e; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .amount { font-weight: 600; color: #10b981; }
        .amount.high { color: #7c3aed; }
        .amount.mid { color: #0891b2; }
        .amount.low { color: #64748b; }
        .date { color: #64748b; font-size: 12px; white-space: nowrap; }
        .date.warning { color: #f59e0b; font-weight: 500; }
        .date.danger { color: #ef4444; font-weight: 500; }
        .visit-badge { display: inline-block; background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .store-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .brand-badge { display: inline-block; background: #fce7f3; color: #9d174d; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .last-item { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #64748b; }
        .last-item:hover { white-space: normal; word-break: break-word; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 24px; background: #f8fafc; flex-wrap: wrap; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 12px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .pagination a { background: white; color: #64748b; border: 1px solid #e2e8f0; }
        .pagination a:hover { background: #667eea; color: white; border-color: #667eea; }
        .pagination span.current { background: #667eea; color: white; }
        .pagination span.dots { border: none; background: transparent; color: #94a3b8; }
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        @media (max-width: 768px) { .summary-cards { grid-template-columns: 1fr 1fr; } .header { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>📊 รายงานยอดซื้อรวมของลูกค้า</h1>
                <p>แสดงเฉพาะลูกค้าที่รหัสขึ้นต้นด้วย 99 | ข้อมูล ณ <?= date('d/m/Y H:i') ?></p>
            </div>
            <a href="?<?= buildQueryString(['export' => '1']) ?>" class="export-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Excel
            </a>
        </div>
        
        <div class="summary-cards">
            <div class="card">
                <div class="card-label">จำนวนลูกค้าทั้งหมด</div>
                <div class="card-value purple"><?= number_format($summary['total_customers'] ?? 0) ?></div>
            </div>
            <div class="card">
                <div class="card-label">ยอดขายรวมทั้งหมด</div>
                <div class="card-value green">฿<?= number_format($summary['grand_total'] ?? 0, 2) ?></div>
            </div>
            <div class="card">
                <div class="card-label">ตามเงื่อนไขที่เลือก</div>
                <div class="card-value orange"><?= number_format($totalRecords) ?> ราย</div>
            </div>
            <div class="card">
                <div class="card-label">หน้าที่</div>
                <div class="card-value"><?= $page ?> / <?= max(1, $totalPages) ?></div>
            </div>
        </div>
        
        <div class="filter-section">
            <h3>🔍 ตัวกรอง</h3>
            <div class="filter-grid">
                <div class="filter-group">
                    <div class="filter-group-title">📅 ความถี่การซื้อ</div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="filterInactive" <?= $filterInactive ? 'checked' : '' ?> onchange="applyFilter()">
                        <label for="filterInactive">ไม่มาซื้อนานกว่า 6 เดือน</label>
                    </div>
                    <p class="hint">ลูกค้าที่ไม่มีการซื้อตั้งแต่ <?= date('d/m/Y', strtotime($sixMonthsAgo)) ?></p>
                </div>
                <div class="filter-group">
                    <div class="filter-group-title">💰 ยอดซื้อรวม</div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="filterHigh" <?= $filterHigh ? 'checked' : '' ?> onchange="applyFilter()">
                        <label for="filterHigh">🟣 ยอดซื้อ 50,000 บาทขึ้นไป</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="filterMid" <?= $filterMid ? 'checked' : '' ?> onchange="applyFilter()">
                        <label for="filterMid">🔵 ยอดซื้อ 10,001 - 49,999 บาท</label>
                    </div>
                    <div class="filter-checkbox">
                        <input type="checkbox" id="filterLow" <?= $filterLow ? 'checked' : '' ?> onchange="applyFilter()">
                        <label for="filterLow">⚪ ยอดซื้อต่ำกว่า 10,000 บาท</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h2>รายชื่อลูกค้า</h2>
                <span class="record-count">พบ <?= number_format($totalRecords) ?> รายการ</span>
            </div>
            
            <?php if (count($customers) > 0): ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>รหัสลูกค้า</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th class="text-center">ครั้ง</th>
                            <th class="text-right">ชิ้น</th>
                            <th class="text-right">ยอดซื้อรวม</th>
                            <th class="text-center">ซื้อแรก</th>
                            <th class="text-center">ซื้อล่าสุด</th>
                            <th class="text-center">สาขา</th>
                            <th class="text-center">แบรนด์</th>
                            <th>สินค้าล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $index => $customer): 
                            $lastPurchase = strtotime($customer['last_purchase_date']);
                            $daysSince = (time() - $lastPurchase) / 86400;
                            $dateClass = $daysSince > 180 ? 'danger' : ($daysSince > 90 ? 'warning' : '');
                            $amt = $customer['total_amount'];
                            $amtClass = $amt >= 50000 ? 'high' : ($amt >= 10001 ? 'mid' : 'low');
                            $latest = $latestData[$customer['customer']] ?? [];
                        ?>
                        <tr>
                            <td class="text-center"><?= $offset + $index + 1 ?></td>
                            <td><span class="customer-code"><?= htmlspecialchars($customer['customer']) ?></span></td>
                            <td class="customer-name" title="<?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?>">
                                <?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?: '-' ?>
                            </td>
                            <td class="text-center"><span class="visit-badge"><?= number_format($customer['visit_count']) ?></span></td>
                            <td class="text-right"><?= number_format($customer['total_qty']) ?></td>
                            <td class="text-right amount <?= $amtClass ?>">฿<?= number_format($customer['total_amount'], 2) ?></td>
                            <td class="text-center date"><?= date('d/m/Y', strtotime($customer['first_purchase_date'])) ?></td>
                            <td class="text-center date <?= $dateClass ?>"><?= date('d/m/Y', strtotime($customer['last_purchase_date'])) ?></td>
                            <td class="text-center"><span class="store-badge"><?= htmlspecialchars($latest['store_code'] ?? '-') ?></span></td>
                            <td class="text-center"><span class="brand-badge" title="<?= htmlspecialchars($latest['brand'] ?? '-') ?>"><?= htmlspecialchars($latest['brand'] ?? '-') ?></span></td>
                            <td class="last-item" title="<?= htmlspecialchars($latest['item_description'] ?? '-') ?>"><?= htmlspecialchars($latest['item_description'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php 
                $qs = buildQueryString(['page' => '']);
                $qp = $qs ? '&' : '';
                if ($page > 1): ?>
                    <a href="?page=1<?= $qp . $qs ?>">« แรก</a>
                    <a href="?page=<?= $page - 1 ?><?= $qp . $qs ?>">‹ ก่อนหน้า</a>
                <?php endif; 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                if ($start > 1): ?>
                    <a href="?page=1<?= $qp . $qs ?>">1</a>
                    <?php if ($start > 2): ?><span class="dots">...</span><?php endif; ?>
                <?php endif;
                for ($i = $start; $i <= $end; $i++):
                    if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= $qp . $qs ?>"><?= $i ?></a>
                    <?php endif;
                endfor;
                if ($end < $totalPages): 
                    if ($end < $totalPages - 1): ?><span class="dots">...</span><?php endif; ?>
                    <a href="?page=<?= $totalPages ?><?= $qp . $qs ?>"><?= $totalPages ?></a>
                <?php endif;
                if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qp . $qs ?>">ถัดไป ›</a>
                    <a href="?page=<?= $totalPages ?><?= $qp . $qs ?>">สุดท้าย »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <h3>ไม่พบข้อมูล</h3>
                <p>ไม่พบข้อมูลลูกค้าตามเงื่อนไขที่เลือก</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function applyFilter() {
            const url = new URL(window.location.href);
            ['inactive', 'high', 'mid', 'low'].forEach(f => {
                const el = document.getElementById('filter' + f.charAt(0).toUpperCase() + f.slice(1));
                if (el && el.checked) url.searchParams.set(f, '1');
                else url.searchParams.delete(f);
            });
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>