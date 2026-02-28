<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

// Get selected date & range
$selected_date = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('-1 day'));

// 1. Daily sales by store (from sale_payments via SOAP)
$daily_stmt = $db->prepare("
    SELECT store_code, 
           SUM(amount_total) as daily_sales, 
           COUNT(*) as transaction_count
    FROM sale_payments 
    WHERE date_piece = ? AND ticket_annule = '-'
    GROUP BY store_code
");
$daily_stmt->execute([$selected_date]);
$daily_data = [];
foreach ($daily_stmt->fetchAll() as $row) {
    $daily_data[$row['store_code']] = $row;
}

// 2. Period sales by store
$period_stmt = $db->prepare("
    SELECT store_code,
           SUM(amount_total) as period_sales,
           COUNT(*) as period_count
    FROM sale_payments 
    WHERE date_piece BETWEEN ? AND ? AND ticket_annule = '-'
    GROUP BY store_code
");
$period_stmt->execute([$date_from, $date_to]);
$period_data = [];
foreach ($period_stmt->fetchAll() as $row) {
    $period_data[$row['store_code']] = $row;
}

// 3. Daily transaction lines (items sold) — use net_total_ttc if available
$items_stmt = $db->prepare("
    SELECT store_code, SUM(quantity) as total_qty, COUNT(*) as line_count,
           SUM(COALESCE(net_total_ttc, total_ttc)) as items_sales
    FROM sale_transactions
    WHERE date_piece = ? AND ticket_annule = '-'
    GROUP BY store_code
");
$items_stmt->execute([$selected_date]);
$items_data = [];
foreach ($items_stmt->fetchAll() as $row) {
    $items_data[$row['store_code']] = $row;
}

// 4. Build summary — only stores with period sales
$summary = [];
$all_codes = array_unique(array_merge(array_keys($daily_data), array_keys($period_data)));
foreach ($all_codes as $code) {
    $period_sales = $period_data[$code]['period_sales'] ?? 0;
    if ($period_sales <= 0) continue;
    
    $summary[] = [
        'store_code' => $code,
        'store_name' => STORE_NAMES[$code] ?? $code,
        'daily_sales' => $daily_data[$code]['daily_sales'] ?? 0,
        'daily_tx' => $daily_data[$code]['transaction_count'] ?? 0,
        'daily_qty' => $items_data[$code]['total_qty'] ?? 0,
        'daily_lines' => $items_data[$code]['line_count'] ?? 0,
        'period_sales' => $period_sales,
        'period_count' => $period_data[$code]['period_count'] ?? 0,
    ];
}

// Sort by period sales desc
usort($summary, fn($a, $b) => $b['period_sales'] <=> $a['period_sales']);

// Totals
$total_daily = array_sum(array_column($summary, 'daily_sales'));
$total_daily_tx = array_sum(array_column($summary, 'daily_tx'));
$total_daily_qty = array_sum(array_column($summary, 'daily_qty'));
$total_period = array_sum(array_column($summary, 'period_sales'));
$total_period_count = array_sum(array_column($summary, 'period_count'));
$max_period = !empty($summary) ? max(array_column($summary, 'period_sales')) : 1;

// Available dates
$dates_stmt = $db->query("SELECT DISTINCT date_piece FROM sale_payments ORDER BY date_piece DESC LIMIT 30");
$available_dates = $dates_stmt->fetchAll(PDO::FETCH_COLUMN);

// Sync status
$sync_stmt = $db->query("SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 1");
$last_sync = $sync_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Cegid Sales Dashboard</title>
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
            color: white; font-size: 32px; font-weight: 800;
            display: flex; align-items: center; gap: 12px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
        }
        .header-icon {
            background: white; padding: 10px; border-radius: 12px; font-size: 28px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .date-controls { display: flex; gap: 18px; align-items: flex-end; flex-wrap: wrap; }
        .date-input-group { display: flex; flex-direction: column; gap: 8px; }
        .date-input-group label { color: white; font-size: 13px; font-weight: 700; }
        .date-input-group input {
            padding: 12px 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 10px;
            background: rgba(255,255,255,0.95); font-size: 14px; font-family: 'Sarabun', sans-serif; font-weight: 600;
        }
        .date-input-group input:focus { outline: none; border-color: white; box-shadow: 0 0 0 4px rgba(255,255,255,0.3); }
        .btn-search {
            padding: 12px 28px; background: white; color: #0288d1; border: none; border-radius: 10px;
            cursor: pointer; font-weight: 700; font-size: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;
        }
        .btn-search:hover { background: #f0f0f0; transform: translateY(-3px); }

        .nav-menu { background: white; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-content { max-width: 1400px; margin: 0 auto; display: flex; gap: 12px; flex-wrap: wrap; }
        .nav-btn {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 12px 24px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #333; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 14px;
            transition: all 0.3s; border: 2px solid transparent; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .nav-btn:hover { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; transform: translateY(-3px); }
        .nav-btn.active { background: linear-gradient(135deg, #0288d1, #0097a7); color: white; }

        .container { max-width: 1400px; margin: 0 auto; padding: 25px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 35px; }
        .stat-card {
            background: white; padding: 30px; border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15); transition: all 0.3s; position: relative; overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #0288d1, #0097a7); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0,0,0,0.2); }
        .stat-label { color: #666; font-size: 14px; margin-bottom: 12px; font-weight: 600; }
        .stat-value {
            font-size: 36px; font-weight: 800;
            background: linear-gradient(135deg, #0288d1, #0097a7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            margin-bottom: 8px;
        }
        .stat-unit { font-size: 16px; color: #999; font-weight: 500; }

        .table-card {
            background: white; padding: 35px; border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15); margin-bottom: 25px;
            animation: fadeIn 0.6s ease-out;
        }
        .table-title { font-size: 22px; font-weight: 800; margin-bottom: 25px; color: #2c3e50; padding-bottom: 15px; border-bottom: 3px solid #ecf0f1; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; text-align: left; border-bottom: 2px solid #f0f0f0; }
        th { background: linear-gradient(135deg, #00588b, #00588b); color: white; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        th:first-child { border-radius: 10px 0 0 0; }
        th:last-child { border-radius: 0 10px 0 0; }
        td { font-size: 14px; }
        .number { text-align: right; font-family: 'Courier New', monospace; font-weight: 600; }
        tbody tr { transition: all 0.3s ease; }
        tbody tr:hover { background: linear-gradient(135deg, #e1f5fe, #ffffff); transform: scale(1.01); }
        .total-row { background: linear-gradient(135deg, #8fd7fd, #00a8bb); color: black; font-weight: 800; }
        .total-row td { border-bottom: none; }

        .store-info { display: flex; flex-direction: column; gap: 4px; }
        .store-name { font-weight: 700; color: #2c3e50; font-size: 15px; }
        .store-code { font-size: 12px; color: #7f8c8d; font-weight: 600; }

        .progress-bar { background: #e0e0e0; height: 10px; border-radius: 5px; overflow: hidden; margin-top: 5px; position: relative; }
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, #0288d1, #0097a7);
            transition: width 0.6s ease; position: relative; overflow: hidden;
        }
        .progress-fill::after {
            content: ''; position: absolute; top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .progress-text { font-size: 12px; color: #666; margin-top: 4px; font-weight: 600; }

        .sync-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .sync-ok { background: #e8f5e9; color: #2e7d32; }
        .sync-fail { background: #fbe9e7; color: #c62828; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; }
            .header-title { font-size: 24px; }
            .date-controls { width: 100%; }
            .date-input-group { flex: 1; min-width: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-card { padding: 20px; }
            th, td { padding: 12px 8px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📊</span>
                Cegid Sales Dashboard
            </div>

            <form method="GET" class="date-controls">
                <div class="date-input-group">
                    <label>วันที่ (ยอดรายวัน)</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                </div>
                <div class="date-input-group">
                    <label>ช่วงเดือน: จาก</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="date-input-group">
                    <label>ถึง</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <button type="submit" class="btn-search">🔍 ค้นหา</button>
            </form>
        </div>
    </div>

    <div class="nav-menu">
        <div class="nav-content">
            <a href="index.php" class="nav-btn active">📊 Dashboard</a>
            <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
            <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
            <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
            <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
            <a href="sync.php" class="nav-btn">🔄 Sync</a>
            <a href="export.php" class="nav-btn">📥 Export</a>
            <?php if ($last_sync): ?>
            <span class="sync-badge <?= $last_sync['status'] === 'completed' ? 'sync-ok' : 'sync-fail' ?>">
                <?= $last_sync['status'] === 'completed' ? '✅' : '❌' ?>
                Last sync: <?= date('d/m H:i', strtotime($last_sync['started_at'])) ?>
                (<?= $last_sync['records_success'] ?? 0 ?> records)
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ยอดขายวันที่ <?= date('d/m/Y', strtotime($selected_date)) ?></div>
                <div class="stat-value"><?= number_format($total_daily, 0) ?></div>
                <div class="stat-unit">บาท (<?= number_format($total_daily_tx) ?> บิล)</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">สินค้าขายได้วันที่ <?= date('d/m/Y', strtotime($selected_date)) ?></div>
                <div class="stat-value"><?= number_format($total_daily_qty, 0) ?></div>
                <div class="stat-unit">ชิ้น</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">ยอดขายช่วง <?= date('d/m', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?></div>
                <div class="stat-value"><?= number_format($total_period, 0) ?></div>
                <div class="stat-unit">บาท (<?= number_format($total_period_count) ?> บิล)</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">จำนวนสาขาที่มียอดขาย</div>
                <div class="stat-value"><?= count($summary) ?></div>
                <div class="stat-unit">สาขา</div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="table-card">
            <h2 class="table-title">สรุปยอดขายแยกสาขา — <?= date('d/m/Y', strtotime($date_from)) ?> ถึง <?= date('d/m/Y', strtotime($date_to)) ?></h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>สาขา</th>
                            <th class="number">ยอดขาย<br><?= date('d/m/y', strtotime($selected_date)) ?></th>
                            <th class="number">บิล</th>
                            <th class="number">ชิ้น</th>
                            <th class="number">ยอดขายช่วงเดือน</th>
                            <th class="number">บิลรวม</th>
                            <th style="min-width: 150px;">สัดส่วน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($summary as $row):
                            $pct = $max_period > 0 ? ($row['period_sales'] / $max_period * 100) : 0;
                            $share = $total_period > 0 ? ($row['period_sales'] / $total_period * 100) : 0;
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="store-info">
                                    <span class="store-name"><?= htmlspecialchars($row['store_name']) ?></span>
                                    <span class="store-code"><?= htmlspecialchars($row['store_code']) ?></span>
                                </div>
                            </td>
                            <td class="number"><?= number_format($row['daily_sales'], 0) ?></td>
                            <td class="number"><?= number_format($row['daily_tx']) ?></td>
                            <td class="number"><?= number_format($row['daily_qty'], 0) ?></td>
                            <td class="number"><?= number_format($row['period_sales'], 0) ?></td>
                            <td class="number"><?= number_format($row['period_count']) ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= round($pct) ?>%"></div>
                                </div>
                                <div class="progress-text"><?= number_format($share, 1) ?>% ของทั้งหมด</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <tr class="total-row">
                            <td colspan="2">รวมทั้งหมด</td>
                            <td class="number"><?= number_format($total_daily, 0) ?></td>
                            <td class="number"><?= number_format($total_daily_tx) ?></td>
                            <td class="number"><?= number_format($total_daily_qty, 0) ?></td>
                            <td class="number"><?= number_format($total_period, 0) ?></td>
                            <td class="number"><?= number_format($total_period_count) ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 100%"></div>
                                </div>
                                <div class="progress-text">100%</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
