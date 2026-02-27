<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400;
$default_compare_from = date('Y-m-d', strtotime($date_from . ' -' . ($days_diff + 1) . ' days'));
$default_compare_to = date('Y-m-d', strtotime($date_to . ' -' . ($days_diff + 1) . ' days'));

$compare_from = $_GET['compare_from'] ?? $default_compare_from;
$compare_to = $_GET['compare_to'] ?? $default_compare_to;
$selected_stores = $_GET['stores'] ?? [];

function getPeriodSales($db, $from, $to, $stores = []) {
    $sql = "SELECT store_code, SUM(amount_total) as total_sales, COUNT(*) as tx_count
            FROM sale_payments WHERE date_piece BETWEEN ? AND ? AND ticket_annule = '-'";
    $params = [$from, $to];
    if (!empty($stores)) {
        $ph = implode(',', array_fill(0, count($stores), '?'));
        $sql .= " AND store_code IN ($ph)";
        $params = array_merge($params, $stores);
    }
    $sql .= " GROUP BY store_code";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = [];
    foreach ($stmt->fetchAll() as $r) $data[$r['store_code']] = $r;
    return $data;
}

$current = getPeriodSales($db, $date_from, $date_to, $selected_stores);
$compare = getPeriodSales($db, $compare_from, $compare_to, $selected_stores);

$all_codes = array_unique(array_merge(array_keys($current), array_keys($compare)));
$rows = []; $total_cur = 0; $total_comp = 0;
foreach ($all_codes as $code) {
    $cur = $current[$code]['total_sales'] ?? 0;
    $comp = $compare[$code]['total_sales'] ?? 0;
    if ($cur == 0 && $comp == 0) continue;
    $diff = $cur - $comp;
    $pct = $comp > 0 ? ($diff / $comp * 100) : ($cur > 0 ? 100 : 0);
    $total_cur += $cur; $total_comp += $comp;
    $rows[] = ['code' => $code, 'name' => STORE_NAMES[$code] ?? $code, 'cur' => $cur, 'comp' => $comp, 'diff' => $diff, 'pct' => $pct, 'tx' => $current[$code]['tx_count'] ?? 0];
}
usort($rows, fn($a, $b) => $b['cur'] <=> $a['cur']);
$total_diff = $total_cur - $total_comp;
$total_pct = $total_comp > 0 ? ($total_diff / $total_comp * 100) : 0;

// Available stores
$store_stmt = $db->query("SELECT DISTINCT store_code FROM sale_payments ORDER BY store_code");
$available_stores = $store_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📈 เทียบยอดหลายตัวเลือก</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd, #f5f5f5); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
        .header-title { color: white; font-size: 32px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; }
        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .filter-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .filter-group label { display: block; font-weight: 700; font-size: 13px; color: #555; margin-bottom: 8px; }
        .filter-group input, .filter-group select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 10px; font-family: 'Sarabun'; font-size: 14px; }
        .btn-search { padding: 12px 28px; background: linear-gradient(135deg, #0288d1, #0097a7); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 35px; }
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
        th { background: #00588b; color: white; font-weight: 700; font-size: 14px; }
        th:first-child { border-radius: 10px 0 0 0; } th:last-child { border-radius: 0 10px 0 0; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr:hover { background: #e1f5fe; }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); font-weight: 800; }
        .total-row td { border-bottom: none; }
        .store-info { display: flex; flex-direction: column; gap: 4px; }
        .store-name { font-weight: 700; color: #2c3e50; } .store-code { font-size: 12px; color: #7f8c8d; }
        .up { color: #27ae60; font-weight: 700; } .down { color: #e74c3c; font-weight: 700; }
        @media (max-width: 768px) { .header-content { flex-direction: column; } .stats-grid { grid-template-columns: 1fr; } th, td { padding: 12px 8px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="header"><div class="header-content"><div class="header-title"><span class="header-icon">📈</span> เทียบยอดหลายตัวเลือก</div></div></div>
    <div class="nav-menu"><div class="nav-content">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn active">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
    </div></div>
    <div class="container">
        <form method="GET" class="filter-card">
            <div class="filter-grid">
                <div class="filter-group"><label>ช่วง A: จาก</label><input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"></div>
                <div class="filter-group"><label>ถึง</label><input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"></div>
                <div class="filter-group"><label>ช่วง B (เทียบ): จาก</label><input type="date" name="compare_from" value="<?= htmlspecialchars($compare_from) ?>"></div>
                <div class="filter-group"><label>ถึง</label><input type="date" name="compare_to" value="<?= htmlspecialchars($compare_to) ?>"></div>
                <div class="filter-group"><label>สาขา (เลือกได้หลายรายการ)</label>
                    <select name="stores[]" multiple size="4">
                        <option value="">-- ทุกสาขา --</option>
                        <?php foreach ($available_stores as $s): ?>
                        <option value="<?= $s ?>" <?= in_array($s, $selected_stores) ? 'selected' : '' ?>><?= STORE_NAMES[$s] ?? $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group"><button type="submit" class="btn-search">🔍 ค้นหา</button></div>
            </div>
        </form>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">ช่วง A (<?= date('d/m', strtotime($date_from)) ?> - <?= date('d/m/y', strtotime($date_to)) ?>)</div><div class="stat-value"><?= number_format($total_cur, 0) ?></div><div class="stat-unit">บาท</div></div>
            <div class="stat-card"><div class="stat-label">ช่วง B (<?= date('d/m', strtotime($compare_from)) ?> - <?= date('d/m/y', strtotime($compare_to)) ?>)</div><div class="stat-value"><?= number_format($total_comp, 0) ?></div><div class="stat-unit">บาท</div></div>
            <div class="stat-card"><div class="stat-label">ผลต่าง</div><div class="stat-value"><?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0) ?></div><div class="stat-unit <?= $total_diff >= 0 ? 'up' : 'down' ?>"><?= ($total_pct >= 0 ? '▲' : '▼') . ' ' . number_format(abs($total_pct), 1) ?>%</div></div>
        </div>
        <div class="table-card">
            <h2 class="table-title">เปรียบเทียบยอดขายแยกสาขา</h2>
            <div class="table-wrapper"><table>
                <thead><tr><th>#</th><th>สาขา</th><th class="number">ช่วง A</th><th class="number">บิล</th><th class="number">ช่วง B</th><th class="number">ผลต่าง</th><th class="number">%</th></tr></thead>
                <tbody>
                <?php $i=1; foreach ($rows as $r): ?>
                <tr><td><?= $i++ ?></td><td><div class="store-info"><span class="store-name"><?= htmlspecialchars($r['name']) ?></span><span class="store-code"><?= $r['code'] ?></span></div></td><td class="number"><?= number_format($r['cur'], 0) ?></td><td class="number"><?= number_format($r['tx']) ?></td><td class="number"><?= number_format($r['comp'], 0) ?></td><td class="number <?= $r['diff'] >= 0 ? 'up' : 'down' ?>"><?= ($r['diff'] >= 0 ? '+' : '') . number_format($r['diff'], 0) ?></td><td class="number <?= $r['pct'] >= 0 ? 'up' : 'down' ?>"><?= ($r['pct'] >= 0 ? '▲' : '▼') . number_format(abs($r['pct']), 1) ?>%</td></tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="2">รวมทั้งหมด</td><td class="number"><?= number_format($total_cur, 0) ?></td><td></td><td class="number"><?= number_format($total_comp, 0) ?></td><td class="number"><?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0) ?></td><td class="number"><?= ($total_pct >= 0 ? '▲' : '▼') . number_format(abs($total_pct), 1) ?>%</td></tr>
                </tbody>
            </table></div>
        </div>
    </div>
</body></html>
