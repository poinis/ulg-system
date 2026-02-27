<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_stores = $_GET['stores'] ?? [];
$selected_items = $_GET['items'] ?? [];
$group_by = $_GET['group_by'] ?? 'store'; // store, item, date

// Build query based on group_by
$where = "WHERE t.date_piece BETWEEN ? AND ? AND t.ticket_annule = '-'";
$params = [$date_from, $date_to];

if (!empty($selected_stores)) {
    $ph = implode(',', array_fill(0, count($selected_stores), '?'));
    $where .= " AND t.store_code IN ($ph)";
    $params = array_merge($params, $selected_stores);
}
if (!empty($selected_items)) {
    $ph = implode(',', array_fill(0, count($selected_items), '?'));
    $where .= " AND t.article_code IN ($ph)";
    $params = array_merge($params, $selected_items);
}

$groupCol = match($group_by) {
    'item' => 't.article_code',
    'date' => 't.date_piece',
    default => 't.store_code',
};

$stmt = $db->prepare("
    SELECT $groupCol as group_key, 
           " . ($group_by === 'item' ? "MAX(t.product_title) as group_label," : "") . "
           SUM(t.quantity) as total_qty, 
           SUM(t.total_ttc) as total_sales,
           COUNT(*) as line_count
    FROM sale_transactions t $where
    GROUP BY group_key
    ORDER BY total_sales DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Add labels
foreach ($rows as &$r) {
    if ($group_by === 'store') $r['group_label'] = STORE_NAMES[$r['group_key']] ?? $r['group_key'];
    elseif ($group_by === 'date') $r['group_label'] = date('d/m/Y (D)', strtotime($r['group_key']));
    elseif (!isset($r['group_label']) || empty($r['group_label'])) $r['group_label'] = $r['group_key'];
}
unset($r);

$total_qty = array_sum(array_column($rows, 'total_qty'));
$total_sales = array_sum(array_column($rows, 'total_sales'));

// Available filters
$store_codes = $db->query("SELECT DISTINCT store_code FROM sale_transactions ORDER BY store_code")->fetchAll(PDO::FETCH_COLUMN);
$item_codes = $db->query("SELECT DISTINCT article_code FROM sale_transactions WHERE article_code != '' ORDER BY article_code LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📈 รายงานแบบเลือกเอง</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd, #f5f5f5); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; }
        .header-content { max-width: 1400px; margin: 0 auto; }
        .header-title { color: white; font-size: 32px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; }
        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .filter-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }
        .filter-group label { display: block; font-weight: 700; font-size: 13px; color: #555; margin-bottom: 8px; }
        .filter-group input, .filter-group select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 10px; font-family: 'Sarabun'; font-size: 14px; }
        .btn-search { padding: 12px 28px; background: linear-gradient(135deg, #0288d1, #0097a7); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .stat-label { color: #666; font-size: 14px; margin-bottom: 12px; font-weight: 600; }
        .stat-value { font-size: 36px; font-weight: 800; background: linear-gradient(135deg, #0288d1, #0097a7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-unit { font-size: 16px; color: #999; }
        .table-card { background: white; padding: 35px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .table-title { font-size: 22px; font-weight: 800; margin-bottom: 25px; color: #2c3e50; padding-bottom: 15px; border-bottom: 3px solid #ecf0f1; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; text-align: left; border-bottom: 2px solid #f0f0f0; }
        th { background: #00588b; color: white; font-weight: 700; }
        th:first-child { border-radius: 10px 0 0 0; } th:last-child { border-radius: 0 10px 0 0; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr:hover { background: #e1f5fe; }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); font-weight: 800; }
        .total-row td { border-bottom: none; }
        .progress-bar { background: #e0e0e0; height: 10px; border-radius: 5px; overflow: hidden; margin-top: 5px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .progress-text { font-size: 12px; color: #666; margin-top: 4px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } th, td { padding: 12px 8px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="header"><div class="header-content"><div class="header-title"><span class="header-icon">📈</span> รายงานแบบเลือกเอง</div></div></div>
    <div class="nav-menu"><div class="nav-content">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn active">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
    </div></div>
    <div class="container">
        <form method="GET" class="filter-card">
            <div class="filter-grid">
                <div class="filter-group"><label>จากวันที่</label><input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"></div>
                <div class="filter-group"><label>ถึงวันที่</label><input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"></div>
                <div class="filter-group"><label>จัดกลุ่มตาม</label>
                    <select name="group_by">
                        <option value="store" <?= $group_by === 'store' ? 'selected' : '' ?>>สาขา</option>
                        <option value="item" <?= $group_by === 'item' ? 'selected' : '' ?>>สินค้า</option>
                        <option value="date" <?= $group_by === 'date' ? 'selected' : '' ?>>วันที่</option>
                    </select>
                </div>
                <div class="filter-group"><label>สาขา</label>
                    <select name="stores[]" multiple size="3">
                        <?php foreach ($store_codes as $s): ?>
                        <option value="<?= $s ?>" <?= in_array($s, $selected_stores) ? 'selected' : '' ?>><?= STORE_NAMES[$s] ?? $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group"><button type="submit" class="btn-search">🔍 ค้นหา</button></div>
            </div>
        </form>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">ยอดขายรวม</div><div class="stat-value"><?= number_format($total_sales, 0) ?></div><div class="stat-unit">บาท</div></div>
            <div class="stat-card"><div class="stat-label">จำนวนชิ้นรวม</div><div class="stat-value"><?= number_format($total_qty, 0) ?></div><div class="stat-unit">ชิ้น</div></div>
            <div class="stat-card"><div class="stat-label">จำนวนกลุ่ม</div><div class="stat-value"><?= count($rows) ?></div><div class="stat-unit"><?= $group_by === 'store' ? 'สาขา' : ($group_by === 'item' ? 'สินค้า' : 'วัน') ?></div></div>
        </div>
        <div class="table-card">
            <h2 class="table-title">รายงาน จัดกลุ่มตาม<?= $group_by === 'store' ? 'สาขา' : ($group_by === 'item' ? 'สินค้า' : 'วันที่') ?></h2>
            <div class="table-wrapper"><table>
                <thead><tr><th>#</th><th><?= $group_by === 'store' ? 'สาขา' : ($group_by === 'item' ? 'สินค้า' : 'วันที่') ?></th><th class="number">จำนวน (ชิ้น)</th><th class="number">ยอดขาย (บาท)</th><th class="number">รายการ</th><th style="min-width:120px">สัดส่วน</th></tr></thead>
                <tbody>
                <?php $i=1; $max_sales = !empty($rows) ? max(array_column($rows, 'total_sales')) : 1; foreach ($rows as $r): $pct = $max_sales > 0 ? ($r['total_sales'] / $max_sales * 100) : 0; $share = $total_sales > 0 ? ($r['total_sales'] / $total_sales * 100) : 0; ?>
                <tr><td><?= $i++ ?></td><td><strong><?= htmlspecialchars($r['group_label']) ?></strong><?php if ($group_by !== 'date'): ?><br><small style="color:#999"><?= $r['group_key'] ?></small><?php endif; ?></td><td class="number"><?= number_format($r['total_qty'], 0) ?></td><td class="number"><?= number_format($r['total_sales'], 0) ?></td><td class="number"><?= number_format($r['line_count']) ?></td><td><div class="progress-bar"><div class="progress-fill" style="width:<?= round($pct) ?>%"></div></div><div class="progress-text"><?= number_format($share, 1) ?>%</div></td></tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="2">รวมทั้งหมด</td><td class="number"><?= number_format($total_qty, 0) ?></td><td class="number"><?= number_format($total_sales, 0) ?></td><td class="number"><?= number_format(array_sum(array_column($rows, 'line_count'))) ?></td><td></td></tr>
                </tbody>
            </table></div>
        </div>
    </div>
</body></html>
