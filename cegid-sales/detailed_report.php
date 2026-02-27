<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

// Available stores
$store_codes = $db->query("SELECT DISTINCT store_code FROM sale_payments ORDER BY store_code")->fetchAll(PDO::FETCH_COLUMN);

$selected_store = $_GET['store'] ?? ($store_codes[0] ?? '');
$selected_month = $_GET['month'] ?? date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Daily sales for selected store
$daily_stmt = $db->prepare("
    SELECT date_piece, SUM(amount_total) as total_sales, COUNT(*) as tx_count
    FROM sale_payments
    WHERE store_code = ? AND date_piece BETWEEN ? AND ? AND ticket_annule = '-'
    GROUP BY date_piece ORDER BY date_piece
");
$daily_stmt->execute([$selected_store, $month_start, $month_end]);
$daily_sales = $daily_stmt->fetchAll();

// Top items for selected store this month
$items_stmt = $db->prepare("
    SELECT article_code, product_title, SUM(quantity) as total_qty, SUM(total_ttc) as total_sales, COUNT(*) as line_count
    FROM sale_transactions
    WHERE store_code = ? AND date_piece BETWEEN ? AND ? AND ticket_annule = '-' AND article_code != ''
    GROUP BY article_code, product_title
    ORDER BY total_sales DESC
    LIMIT 30
");
$items_stmt->execute([$selected_store, $month_start, $month_end]);
$top_items = $items_stmt->fetchAll();

// Monthly totals
$monthly_total = array_sum(array_column($daily_sales, 'total_sales'));
$monthly_tx = array_sum(array_column($daily_sales, 'tx_count'));
$days_with_sales = count($daily_sales);
$avg_daily = $days_with_sales > 0 ? $monthly_total / $days_with_sales : 0;
$max_daily = !empty($daily_sales) ? max(array_column($daily_sales, 'total_sales')) : 1;

$thaiDays = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 รายงานแยกสาขา — <?= STORE_NAMES[$selected_store] ?? $selected_store ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd, #f5f5f5); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
        .header-title { color: white; font-size: 28px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; }
        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .filter-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px; }
        .filter-grid { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group label { display: block; font-weight: 700; font-size: 13px; color: #555; margin-bottom: 8px; }
        .filter-group select, .filter-group input { padding: 10px; border: 2px solid #ddd; border-radius: 10px; font-family: 'Sarabun'; font-size: 14px; min-width: 200px; }
        .btn-search { padding: 12px 28px; background: linear-gradient(135deg, #0288d1, #0097a7); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .stat-label { color: #666; font-size: 14px; margin-bottom: 12px; font-weight: 600; }
        .stat-value { font-size: 36px; font-weight: 800; background: linear-gradient(135deg, #0288d1, #0097a7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-unit { font-size: 16px; color: #999; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .table-card { background: white; padding: 35px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px; }
        .table-title { font-size: 20px; font-weight: 800; margin-bottom: 25px; color: #2c3e50; padding-bottom: 15px; border-bottom: 3px solid #ecf0f1; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 2px solid #f0f0f0; }
        th { background: #00588b; color: white; font-weight: 700; font-size: 13px; }
        th:first-child { border-radius: 10px 0 0 0; } th:last-child { border-radius: 0 10px 0 0; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr:hover { background: #e1f5fe; }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); font-weight: 800; }
        .total-row td { border-bottom: none; }
        .progress-bar { background: #e0e0e0; height: 10px; border-radius: 5px; overflow: hidden; margin-top: 5px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .weekend { background: #fff8e1; }
        @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: 1fr; } th, td { padding: 10px 6px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="header"><div class="header-content">
        <div class="header-title"><span class="header-icon">📋</span> รายงานแยกสาขา — <?= htmlspecialchars(STORE_NAMES[$selected_store] ?? $selected_store) ?></div>
    </div></div>
    <div class="nav-menu"><div class="nav-content">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn active">📋 รายงานแยกสาขา</a>
    </div></div>
    <div class="container">
        <form method="GET" class="filter-card">
            <div class="filter-grid">
                <div class="filter-group"><label>สาขา</label>
                    <select name="store" onchange="this.form.submit()">
                        <?php foreach ($store_codes as $s): ?>
                        <option value="<?= $s ?>" <?= $s === $selected_store ? 'selected' : '' ?>><?= STORE_NAMES[$s] ?? $s ?> (<?= $s ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group"><label>เดือน</label><input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()"></div>
            </div>
        </form>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">ยอดขายเดือน <?= date('m/Y', strtotime($month_start)) ?></div><div class="stat-value"><?= number_format($monthly_total, 0) ?></div><div class="stat-unit">บาท (<?= $monthly_tx ?> บิล)</div></div>
            <div class="stat-card"><div class="stat-label">เฉลี่ย/วัน</div><div class="stat-value"><?= number_format($avg_daily, 0) ?></div><div class="stat-unit">บาท (<?= $days_with_sales ?> วันที่มียอด)</div></div>
        </div>

        <div class="two-col">
            <div class="table-card">
                <h2 class="table-title">📅 ยอดขายรายวัน</h2>
                <div class="table-wrapper"><table>
                    <thead><tr><th>วันที่</th><th>วัน</th><th class="number">ยอดขาย</th><th class="number">บิล</th><th style="min-width:100px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($daily_sales as $d):
                        $dow = (int)date('w', strtotime($d['date_piece']));
                        $isWeekend = ($dow === 0 || $dow === 6);
                        $pct = $max_daily > 0 ? ($d['total_sales'] / $max_daily * 100) : 0;
                    ?>
                    <tr class="<?= $isWeekend ? 'weekend' : '' ?>">
                        <td><?= date('d/m', strtotime($d['date_piece'])) ?></td>
                        <td><?= $thaiDays[$dow] ?></td>
                        <td class="number"><?= number_format($d['total_sales'], 0) ?></td>
                        <td class="number"><?= $d['tx_count'] ?></td>
                        <td><div class="progress-bar"><div class="progress-fill" style="width:<?= round($pct) ?>%"></div></div></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="2">รวม</td><td class="number"><?= number_format($monthly_total, 0) ?></td><td class="number"><?= $monthly_tx ?></td><td></td></tr>
                    </tbody>
                </table></div>
            </div>

            <div class="table-card">
                <h2 class="table-title">🏆 สินค้าขายดี Top 30</h2>
                <div class="table-wrapper"><table>
                    <thead><tr><th>#</th><th>สินค้า</th><th class="number">จำนวน</th><th class="number">ยอดขาย</th></tr></thead>
                    <tbody>
                    <?php $i=1; foreach ($top_items as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($item['product_title']) ?></strong><br><small style="color:#999"><?= $item['article_code'] ?></small></td>
                        <td class="number"><?= number_format($item['total_qty'], 0) ?></td>
                        <td class="number"><?= number_format($item['total_sales'], 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_items)): ?><tr><td colspan="4" style="text-align:center;color:#999;padding:30px">ไม่มีข้อมูลรายการสินค้า</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</body></html>
