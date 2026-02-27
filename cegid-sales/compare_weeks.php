<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Calculate previous period
$days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
$last_period_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
$last_period_start = date('Y-m-d', strtotime($last_period_end . ' -' . $days_diff . ' days'));

function getPeriodData($db, $from, $to) {
    $stmt = $db->prepare("
        SELECT store_code, SUM(amount_total) as total_sales, COUNT(*) as tx_count
        FROM sale_payments WHERE date_piece BETWEEN ? AND ? AND ticket_annule = '-'
        GROUP BY store_code
    ");
    $stmt->execute([$from, $to]);
    $data = [];
    foreach ($stmt->fetchAll() as $r) {
        $data[$r['store_code']] = $r;
    }
    return $data;
}

$this_period = getPeriodData($db, $start_date, $end_date);
$last_period = getPeriodData($db, $last_period_start, $last_period_end);

$all_codes = array_unique(array_merge(array_keys($this_period), array_keys($last_period)));
$comparison = [];
$total_this = 0; $total_last = 0;

foreach ($all_codes as $code) {
    $this_sales = $this_period[$code]['total_sales'] ?? 0;
    $last_sales = $last_period[$code]['total_sales'] ?? 0;
    if ($this_sales == 0 && $last_sales == 0) continue;
    
    $diff = $this_sales - $last_sales;
    $diff_pct = $last_sales > 0 ? ($diff / $last_sales * 100) : ($this_sales > 0 ? 100 : 0);
    
    $total_this += $this_sales;
    $total_last += $last_sales;
    
    $comparison[] = [
        'store_code' => $code,
        'store_name' => STORE_NAMES[$code] ?? $code,
        'this_sales' => $this_sales,
        'last_sales' => $last_sales,
        'diff' => $diff,
        'diff_pct' => $diff_pct,
        'this_tx' => $this_period[$code]['tx_count'] ?? 0,
    ];
}
usort($comparison, fn($a, $b) => $b['this_sales'] <=> $a['this_sales']);
$total_diff = $total_this - $total_last;
$total_diff_pct = $total_last > 0 ? ($total_diff / $total_last * 100) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📈 เทียบยอดสัปดาห์</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd, #f5f5f5); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
        .header-title { color: white; font-size: 32px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; }
        .date-controls { display: flex; gap: 18px; align-items: flex-end; flex-wrap: wrap; }
        .date-input-group { display: flex; flex-direction: column; gap: 8px; }
        .date-input-group label { color: white; font-size: 13px; font-weight: 700; }
        .date-input-group input { padding: 12px 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 10px; background: rgba(255,255,255,0.95); font-size: 14px; font-family: 'Sarabun'; font-weight: 600; }
        .btn-search { padding: 12px 28px; background: white; color: #0288d1; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 15px; }
        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .nav-btn:hover, .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 35px; }
        .stat-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .stat-label { color: #666; font-size: 14px; margin-bottom: 12px; font-weight: 600; }
        .stat-value { font-size: 36px; font-weight: 800; background: linear-gradient(135deg, #0288d1, #0097a7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-unit { font-size: 16px; color: #999; font-weight: 500; }
        .table-card { background: white; padding: 35px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .table-title { font-size: 22px; font-weight: 800; margin-bottom: 25px; color: #2c3e50; padding-bottom: 15px; border-bottom: 3px solid #ecf0f1; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; text-align: left; border-bottom: 2px solid #f0f0f0; }
        th { background: linear-gradient(135deg, #00588b, #00588b); color: white; font-weight: 700; font-size: 14px; }
        th:first-child { border-radius: 10px 0 0 0; } th:last-child { border-radius: 0 10px 0 0; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr:hover { background: linear-gradient(135deg, #e1f5fe, #fff); }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); color: black; font-weight: 800; }
        .total-row td { border-bottom: none; }
        .store-info { display: flex; flex-direction: column; gap: 4px; }
        .store-name { font-weight: 700; color: #2c3e50; font-size: 15px; }
        .store-code { font-size: 12px; color: #7f8c8d; }
        .up { color: #27ae60; font-weight: 700; } .down { color: #e74c3c; font-weight: 700; }
        @media (max-width: 768px) { .header-content { flex-direction: column; } .stats-grid { grid-template-columns: 1fr; } th, td { padding: 12px 8px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title"><span class="header-icon">📈</span> เทียบยอดสัปดาห์</div>
            <form method="GET" class="date-controls">
                <div class="date-input-group"><label>จากวันที่</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></div>
                <div class="date-input-group"><label>ถึงวันที่</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></div>
                <button type="submit" class="btn-search">🔍 ค้นหา</button>
            </form>
        </div>
    </div>
    <div class="nav-menu"><div class="nav-content">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="compare_weeks.php" class="nav-btn active">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
    </div></div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ช่วงนี้ (<?= date('d/m', strtotime($start_date)) ?> - <?= date('d/m/y', strtotime($end_date)) ?>)</div>
                <div class="stat-value"><?= number_format($total_this, 0) ?></div><div class="stat-unit">บาท</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">ช่วงก่อน (<?= date('d/m', strtotime($last_period_start)) ?> - <?= date('d/m/y', strtotime($last_period_end)) ?>)</div>
                <div class="stat-value"><?= number_format($total_last, 0) ?></div><div class="stat-unit">บาท</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">ผลต่าง</div>
                <div class="stat-value"><?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0) ?></div>
                <div class="stat-unit <?= $total_diff >= 0 ? 'up' : 'down' ?>"><?= ($total_diff_pct >= 0 ? '▲' : '▼') . ' ' . number_format(abs($total_diff_pct), 1) ?>%</div>
            </div>
        </div>
        <div class="table-card">
            <h2 class="table-title">เปรียบเทียบยอดขายแยกสาขา</h2>
            <div class="table-wrapper"><table>
                <thead><tr>
                    <th>#</th><th>สาขา</th>
                    <th class="number">ช่วงนี้</th><th class="number">บิล</th>
                    <th class="number">ช่วงก่อน</th><th class="number">ผลต่าง</th><th class="number">%</th>
                </tr></thead>
                <tbody>
                <?php $i = 1; foreach ($comparison as $row): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><div class="store-info"><span class="store-name"><?= htmlspecialchars($row['store_name']) ?></span><span class="store-code"><?= $row['store_code'] ?></span></div></td>
                    <td class="number"><?= number_format($row['this_sales'], 0) ?></td>
                    <td class="number"><?= number_format($row['this_tx']) ?></td>
                    <td class="number"><?= number_format($row['last_sales'], 0) ?></td>
                    <td class="number <?= $row['diff'] >= 0 ? 'up' : 'down' ?>"><?= ($row['diff'] >= 0 ? '+' : '') . number_format($row['diff'], 0) ?></td>
                    <td class="number <?= $row['diff_pct'] >= 0 ? 'up' : 'down' ?>"><?= ($row['diff_pct'] >= 0 ? '▲' : '▼') . number_format(abs($row['diff_pct']), 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="2">รวมทั้งหมด</td>
                    <td class="number"><?= number_format($total_this, 0) ?></td><td></td>
                    <td class="number"><?= number_format($total_last, 0) ?></td>
                    <td class="number"><?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0) ?></td>
                    <td class="number"><?= ($total_diff_pct >= 0 ? '▲' : '▼') . number_format(abs($total_diff_pct), 1) ?>%</td>
                </tr>
                </tbody>
            </table></div>
        </div>
    </div>
</body></html>
