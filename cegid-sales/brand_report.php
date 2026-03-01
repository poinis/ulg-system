<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('-1 day'));
$store_code = $_GET['store'] ?? '';
$view_by = $_GET['view'] ?? 'brand'; // brand, group, class, detail

// Build WHERE
$where = "WHERE t.date_piece BETWEEN ? AND ? AND t.ticket_annule = '-' " . SALES_FILTER;
$params = [$date_from, $date_to];
if ($store_code) {
    $where .= " AND t.store_code = ?";
    $params[] = $store_code;
}

// Brand summary
if ($view_by === 'brand') {
    $sql = "
        SELECT COALESCE(bm.brand, SUBSTRING(t.article_code, 1, 3)) as brand_name,
               COUNT(DISTINCT CONCAT(t.store_code, t.document_key)) as bill_count,
               SUM(t.quantity) as total_qty,
               SUM(COALESCE(t.net_total_ttc, t.total_ttc)) as total_sales
        FROM sale_transactions t
        LEFT JOIN barcode_mapping bm ON t.barcode = bm.barcode
        $where
        GROUP BY brand_name
        ORDER BY total_sales DESC
    ";
} elseif ($view_by === 'group') {
    $brand_filter = $_GET['brand'] ?? '';
    $where .= $brand_filter ? " AND bm.brand = ?" : "";
    if ($brand_filter) $params[] = $brand_filter;
    
    $sql = "
        SELECT COALESCE(bm.brand, '?') as brand_name,
               COALESCE(bm.group_name, '-') as group_name,
               SUM(t.quantity) as total_qty,
               SUM(COALESCE(t.net_total_ttc, t.total_ttc)) as total_sales,
               COUNT(DISTINCT CONCAT(t.store_code, t.document_key)) as bill_count
        FROM sale_transactions t
        LEFT JOIN barcode_mapping bm ON t.barcode = bm.barcode
        $where
        GROUP BY brand_name, group_name
        ORDER BY brand_name, total_sales DESC
    ";
} elseif ($view_by === 'class') {
    $brand_filter = $_GET['brand'] ?? '';
    $where .= $brand_filter ? " AND bm.brand = ?" : "";
    if ($brand_filter) $params[] = $brand_filter;
    
    $sql = "
        SELECT COALESCE(bm.brand, '?') as brand_name,
               COALESCE(bm.class_name, '-') as class_name,
               SUM(t.quantity) as total_qty,
               SUM(COALESCE(t.net_total_ttc, t.total_ttc)) as total_sales,
               COUNT(DISTINCT CONCAT(t.store_code, t.document_key)) as bill_count
        FROM sale_transactions t
        LEFT JOIN barcode_mapping bm ON t.barcode = bm.barcode
        $where
        GROUP BY brand_name, class_name
        ORDER BY brand_name, total_sales DESC
    ";
} else { // detail
    $brand_filter = $_GET['brand'] ?? '';
    $where .= $brand_filter ? " AND bm.brand = ?" : "";
    if ($brand_filter) $params[] = $brand_filter;
    
    $sql = "
        SELECT t.article_code, t.product_title,
               COALESCE(bm.brand, SUBSTRING(t.article_code, 1, 3)) as brand_name,
               COALESCE(bm.group_name, '-') as group_name,
               COALESCE(bm.class_name, '-') as class_name,
               COALESCE(bm.size_name, '-') as size_name,
               SUM(t.quantity) as total_qty,
               SUM(COALESCE(t.net_total_ttc, t.total_ttc)) as total_sales
        FROM sale_transactions t
        LEFT JOIN barcode_mapping bm ON t.barcode = bm.barcode
        $where
        GROUP BY t.article_code, t.product_title, brand_name, group_name, class_name, size_name
        ORDER BY total_sales DESC
        LIMIT 500
    ";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$grand_total = array_sum(array_column($rows, 'total_sales'));
$grand_qty = array_sum(array_column($rows, 'total_qty'));

// Store list for filter
$stores_stmt = $db->query("SELECT DISTINCT store_code FROM sale_transactions ORDER BY store_code");
$store_list = $stores_stmt->fetchAll(PDO::FETCH_COLUMN);

// Brand list for sub-filters
$brands_stmt = $db->query("SELECT DISTINCT brand FROM barcode_mapping WHERE brand != '' ORDER BY brand");
$brand_list = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏷️ Brand Report — Cegid Sales</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); min-height: 100vh; }

        .header {
            background: rgba(2, 136, 209, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header-content {
            max-width: 1400px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;
        }
        .header-title {
            color: white; font-size: 28px; font-weight: 800;
            display: flex; align-items: center; gap: 12px;
        }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .filter-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { color: white; font-size: 12px; font-weight: 700; }
        .filter-group input, .filter-group select {
            padding: 10px 14px; border: 2px solid rgba(255,255,255,0.3); border-radius: 10px;
            background: rgba(255,255,255,0.95); font-size: 13px; font-family: 'Sarabun'; font-weight: 600;
        }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: white; }
        .btn-search {
            padding: 10px 24px; background: white; color: #0288d1; border: none; border-radius: 10px;
            cursor: pointer; font-weight: 700; font-size: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        .btn-search:hover { background: #f0f0f0; transform: translateY(-2px); }

        .nav-menu { background: white; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 10px; flex-wrap: wrap; }
        .nav-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #333; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 13px;
            transition: all 0.3s; border: 2px solid transparent;
        }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; transform: translateY(-2px); }

        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white; padding: 25px; border-radius: 16px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.12); position: relative; overflow: hidden;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .stat-label { color: #666; font-size: 13px; margin-bottom: 10px; font-weight: 600; }
        .stat-value {
            font-size: 32px; font-weight: 800;
            background: linear-gradient(135deg, #0288d1, #0097a7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .stat-unit { font-size: 14px; color: #999; font-weight: 500; }

        .view-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .view-tab {
            padding: 10px 20px; background: white; border: 2px solid #e0e0e0; border-radius: 10px;
            text-decoration: none; color: #555; font-weight: 600; font-size: 13px; transition: all 0.2s;
        }
        .view-tab:hover { border-color: #0288d1; color: #0288d1; }
        .view-tab.active { background: #0288d1; color: white; border-color: #0288d1; }

        .table-card {
            background: white; padding: 30px; border-radius: 16px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.12); margin-bottom: 20px;
        }
        .table-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; color: #2c3e50; padding-bottom: 12px; border-bottom: 3px solid #ecf0f1; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 2px solid #f0f0f0; }
        th { background: linear-gradient(135deg, #00588b, #00588b); color: white; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        th:first-child { border-radius: 10px 0 0 0; }
        th:last-child { border-radius: 0 10px 0 0; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr { transition: all 0.2s; }
        tbody tr:hover { background: linear-gradient(135deg, #e1f5fe, #fff); }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); color: black; font-weight: 800; }
        .total-row td { border-bottom: none; }
        .brand-link { color: #0288d1; text-decoration: none; font-weight: 700; }
        .brand-link:hover { text-decoration: underline; }
        .pct-bar { background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px; }
        .pct-fill { height: 100%; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .pct-text { font-size: 11px; color: #888; }
        .unmapped { color: #e67e22; font-style: italic; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; }
            .header-title { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr; }
            th, td { padding: 10px 6px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">🏷️</span>
                Brand Report
            </div>
            <form method="GET" class="filter-form">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view_by) ?>">
                <?php if (!empty($_GET['brand'])): ?>
                <input type="hidden" name="brand" value="<?= htmlspecialchars($_GET['brand']) ?>">
                <?php endif; ?>
                <div class="filter-group">
                    <label>จาก</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>ถึง</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group">
                    <label>สาขา</label>
                    <select name="store">
                        <option value="">— ทุกสาขา —</option>
                        <?php foreach ($store_list as $sc): ?>
                        <option value="<?= $sc ?>" <?= $store_code === $sc ? 'selected' : '' ?>>
                            <?= STORE_NAMES[$sc] ?? $sc ?> (<?= $sc ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-search">🔍 ค้นหา</button>
            </form>
        </div>
    </div>

    <div class="nav-menu">
        <div class="nav-content">
            <a href="index.php" class="nav-btn">📊 Dashboard</a>
            <a href="compare_weeks.php" class="nav-btn">📈 เทียบสัปดาห์</a>
            <a href="compare_period_report.php" class="nav-btn">📈 เทียบช่วงเวลา</a>
            <a href="multi_filter_report.php" class="nav-btn">📈 รายงานเลือกเอง</a>
            <a href="detailed_report.php" class="nav-btn">📋 แยกสาขา</a>
            <a href="brand_report.php" class="nav-btn active">🏷️ Brand Report</a>
        </div>
    </div>

    <div class="container">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ยอดขายรวม</div>
                <div class="stat-value"><?= number_format($grand_total, 0) ?></div>
                <div class="stat-unit">บาท</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">จำนวนชิ้น</div>
                <div class="stat-value"><?= number_format($grand_qty, 0) ?></div>
                <div class="stat-unit">ชิ้น</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= $view_by === 'brand' ? 'จำนวนแบรนด์' : 'จำนวนรายการ' ?></div>
                <div class="stat-value"><?= number_format(count($rows)) ?></div>
                <div class="stat-unit"><?= $view_by === 'brand' ? 'แบรนด์' : 'รายการ' ?></div>
            </div>
            <?php if ($store_code): ?>
            <div class="stat-card">
                <div class="stat-label">สาขา</div>
                <div class="stat-value" style="font-size: 22px;"><?= STORE_NAMES[$store_code] ?? $store_code ?></div>
                <div class="stat-unit">(<?= $store_code ?>)</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- View Tabs -->
        <?php
            $tab_params = http_build_query(array_filter([
                'date_from' => $date_from,
                'date_to' => $date_to,
                'store' => $store_code,
                'brand' => $_GET['brand'] ?? '',
            ]));
        ?>
        <div class="view-tabs">
            <a href="?view=brand&<?= $tab_params ?>" class="view-tab <?= $view_by === 'brand' ? 'active' : '' ?>">🏷️ Brand</a>
            <a href="?view=group&<?= $tab_params ?>" class="view-tab <?= $view_by === 'group' ? 'active' : '' ?>">📁 Group</a>
            <a href="?view=class&<?= $tab_params ?>" class="view-tab <?= $view_by === 'class' ? 'active' : '' ?>">📂 Class</a>
            <a href="?view=detail&<?= $tab_params ?>" class="view-tab <?= $view_by === 'detail' ? 'active' : '' ?>">📋 Detail (Top 500)</a>
        </div>

        <?php if (!empty($_GET['brand'])): ?>
        <div style="margin-bottom: 15px;">
            <a href="?view=brand&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&store=<?= $store_code ?>" 
               style="color: #0288d1; font-weight: 600; text-decoration: none;">
                ← กลับหน้า Brand ทั้งหมด
            </a>
            <span style="font-size: 18px; font-weight: 800; margin-left: 15px; color: #2c3e50;">
                🔎 <?= htmlspecialchars($_GET['brand']) ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="table-card">
            <h2 class="table-title">
                <?php
                $title_map = [
                    'brand' => '🏷️ ยอดขายแยกตามแบรนด์',
                    'group' => '📁 ยอดขายแยกตาม Group',
                    'class' => '📂 ยอดขายแยกตาม Class',
                    'detail' => '📋 รายละเอียดสินค้า',
                ];
                echo ($title_map[$view_by] ?? 'Report');
                echo " — " . date('d/m/Y', strtotime($date_from)) . " ถึง " . date('d/m/Y', strtotime($date_to));
                ?>
            </h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($view_by === 'brand'): ?>
                            <th>แบรนด์</th>
                            <th class="number">บิล</th>
                            <?php elseif ($view_by === 'group'): ?>
                            <th>แบรนด์</th>
                            <th>Group</th>
                            <?php elseif ($view_by === 'class'): ?>
                            <th>แบรนด์</th>
                            <th>Class</th>
                            <?php else: ?>
                            <th>รหัสสินค้า</th>
                            <th>ชื่อสินค้า</th>
                            <th>แบรนด์</th>
                            <th>Group</th>
                            <?php endif; ?>
                            <th class="number">จำนวน</th>
                            <th class="number">ยอดขาย</th>
                            <th style="min-width: 120px;">สัดส่วน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        $max_sales = !empty($rows) ? max(array_column($rows, 'total_sales')) : 1;
                        foreach ($rows as $row):
                            $pct = $grand_total > 0 ? ($row['total_sales'] / $grand_total * 100) : 0;
                            $bar_pct = $max_sales > 0 ? ($row['total_sales'] / $max_sales * 100) : 0;
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <?php if ($view_by === 'brand'): ?>
                            <td>
                                <?php if ($row['brand_name']): ?>
                                <a href="?view=group&brand=<?= urlencode($row['brand_name']) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&store=<?= $store_code ?>" class="brand-link">
                                    <?= htmlspecialchars($row['brand_name']) ?>
                                </a>
                                <?php else: ?>
                                <span class="unmapped">ไม่ระบุ</span>
                                <?php endif; ?>
                            </td>
                            <td class="number"><?= number_format($row['bill_count']) ?></td>
                            <?php elseif ($view_by === 'group'): ?>
                            <td><?= htmlspecialchars($row['brand_name']) ?></td>
                            <td><?= htmlspecialchars($row['group_name']) ?></td>
                            <?php elseif ($view_by === 'class'): ?>
                            <td><?= htmlspecialchars($row['brand_name']) ?></td>
                            <td><?= htmlspecialchars($row['class_name']) ?></td>
                            <?php else: ?>
                            <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($row['article_code']) ?></td>
                            <td style="font-size: 13px;"><?= htmlspecialchars($row['product_title']) ?></td>
                            <td><?= htmlspecialchars($row['brand_name']) ?></td>
                            <td><?= htmlspecialchars($row['group_name']) ?></td>
                            <?php endif; ?>
                            <td class="number"><?= number_format($row['total_qty']) ?></td>
                            <td class="number"><?= number_format($row['total_sales'], 0) ?></td>
                            <td>
                                <div class="pct-bar"><div class="pct-fill" style="width: <?= round($bar_pct) ?>%"></div></div>
                                <div class="pct-text"><?= number_format($pct, 1) ?>%</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <tr class="total-row">
                            <td colspan="<?= $view_by === 'brand' ? 3 : ($view_by === 'detail' ? 4 : 2) ?>">รวมทั้งหมด</td>
                            <?php if ($view_by === 'brand'): ?>
                            <td class="number"><?= number_format(array_sum(array_column($rows, 'bill_count'))) ?></td>
                            <?php endif; ?>
                            <td class="number"><?= number_format($grand_qty) ?></td>
                            <td class="number"><?= number_format($grand_total, 0) ?></td>
                            <td><div class="pct-text">100%</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
