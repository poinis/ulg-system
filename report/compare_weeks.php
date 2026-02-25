<?php
// compare_weeks.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('-1 day'));

// Calculate last period
$days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
$last_period_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
$last_period_start = date('Y-m-d', strtotime($last_period_end . ' -' . $days_diff . ' days'));

// --- ✨ STORE MAPPING ---
$stores_stmt = $db->query("SELECT store_code, store_code_new, store_name FROM stores");
$all_stores_db = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
$store_map = []; 
$main_stores_info = [];
foreach ($all_stores_db as $s) {
    $main = $s['store_code'];
    $new = $s['store_code_new'];
    $main_stores_info[$main] = $s['store_name'];
    $store_map[$main] = $main;
    if(!empty($new)) $store_map[$new] = $main;
}

// Helper to get aggregated data
function getAggregatedData($db, $s_date, $e_date, $store_map, $main_stores_info) {
    $stmt = $db->prepare("
        SELECT ds.store_code, SUM(ds.tax_incl_total) as total_sales, COUNT(DISTINCT ds.sale_date) as sales_days
        FROM daily_sales ds WHERE ds.sale_date BETWEEN ? AND ? GROUP BY ds.store_code
    ");
    $stmt->execute([$s_date, $e_date]);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $aggregated = [];
    foreach ($raw as $row) {
        $code = $row['store_code'];
        $main_code = $store_map[$code] ?? $code;
        
        if (!isset($aggregated[$main_code])) {
            $aggregated[$main_code] = [
                'store_code' => $main_code,
                'store_name' => $main_stores_info[$main_code] ?? $main_code,
                'total_sales' => 0,
                'sales_days' => 0
            ];
        }
        $aggregated[$main_code]['total_sales'] += $row['total_sales'];
        $aggregated[$main_code]['sales_days'] = max($aggregated[$main_code]['sales_days'], $row['sales_days']);
    }
    return $aggregated;
}

$this_period_agg = getAggregatedData($db, $start_date, $end_date, $store_map, $main_stores_info);
$last_period_agg = getAggregatedData($db, $last_period_start, $last_period_end, $store_map, $main_stores_info);

// Combine
$comparison = [];
$total_this_period = 0;
$total_last_period = 0;

foreach ($this_period_agg as $code => $data) {
    $this_sales = $data['total_sales'];
    $last_sales = $last_period_agg[$code]['total_sales'] ?? 0;
    
    $total_this_period += $this_sales;
    $total_last_period += $last_sales;
    
    $diff = $this_sales - $last_sales;
    $diff_pct = $last_sales > 0 ? (($diff / $last_sales) * 100) : 0;
    
    $comparison[] = [
        'store_code' => $code,
        'store_name' => $data['store_name'],
        'this_period' => $this_sales,
        'last_period' => $last_sales,
        'diff' => $diff,
        'diff_pct' => $diff_pct
    ];
}

// Sort by sales descending
usort($comparison, function($a, $b) { return $b['this_period'] <=> $a['this_period']; });

$total_diff = $total_this_period - $total_last_period;
$total_diff_pct = $total_last_period > 0 ? (($total_diff / $total_last_period) * 100) : 0;

// Get Daily Breakdown (No change needed here as it's by date, global total)
$stmt = $db->prepare("SELECT sale_date, SUM(tax_incl_total) as daily_total FROM daily_sales WHERE sale_date BETWEEN ? AND ? GROUP BY sale_date ORDER BY sale_date");
$stmt->execute([$start_date, $end_date]);
$daily_this = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute([$last_period_start, $last_period_end]);
$daily_last = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data Prep
$chart_labels = []; $this_data = []; $last_data = [];
foreach($daily_this as $r) { $chart_labels[] = date('d/m', strtotime($r['sale_date'])); $this_data[] = $r['daily_total']; }
foreach($daily_last as $r) { $last_data[] = $r['daily_total']; }
if(count($last_data) < count($this_data)) $last_data = array_pad($last_data, count($this_data), 0);
else $last_data = array_slice($last_data, 0, count($this_data));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปรียบเทียบยอดขาย</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); min-height: 100vh; }
        
        /* Header */
        .header { 
            background: rgba(2, 136, 209, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }
        .header-content { 
            max-width: 1400px; 
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header-title {
            color: white;
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
        }
        .header-icon {
            background: white;
            padding: 10px;
            border-radius: 12px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px 40px; }
        .back-link { 
            background: white;
            color: #0288d1;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-link:hover { 
            background: #f5f5f5;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        .filter-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-filter { padding: 10px 30px; background: #0288d1; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 36px; font-weight: bold; margin-bottom: 8px; }
        .positive { color: #28a745; } .negative { color: #dc3545; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .chart-container { position: relative; height: 400px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .number { text-align: right; font-family: 'Courier New', monospace; }
        .total-row { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📈</span>
                เปรียบเทียบยอดขาย
            </div>
            <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
    <div class="container">
        
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group"><label>🗓️ เริ่มต้น</label><input type="date" name="start_date" value="<?php echo $start_date; ?>" required></div>
                <div class="form-group"><label>🗓️ สิ้นสุด</label><input type="date" name="end_date" value="<?php echo $end_date; ?>" required></div>
                <button type="submit" class="btn-filter">🔍 ดูข้อมูล</button>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ช่วงที่เลือก</div>
                <div class="stat-value" style="color: #667eea;"><?php echo number_format($total_this_period, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">ช่วงก่อนหน้า</div>
                <div class="stat-value" style="color: #999;"><?php echo number_format($total_last_period, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">เปลี่ยนแปลง</div>
                <div class="stat-value <?php echo $total_diff >= 0 ? 'positive' : 'negative'; ?>"><?php echo ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0); ?></div>
                <div style="font-weight: bold;" class="<?php echo $total_diff_pct >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($total_diff_pct, 1); ?>%</div>
            </div>
        </div>
        
        <div class="card">
            <h2>📊 กราฟเปรียบเทียบรายวัน</h2>
            <div class="chart-container"><canvas id="dailyChart"></canvas></div>
        </div>
        
        <div class="card">
            <h2>เปรียบเทียบแยกตามสาขา</h2>
            <table>
                <thead>
                    <tr><th>สาขา</th><th class="number">ช่วงที่เลือก</th><th class="number">ช่วงก่อนหน้า</th><th class="number">ส่วนต่าง</th><th class="number">%</th><th>สถานะ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($comparison as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong><br><small><?php echo $row['store_code']; ?></small></td>
                            <td class="number"><?php echo number_format($row['this_period'], 0); ?></td>
                            <td class="number"><?php echo number_format($row['last_period'], 0); ?></td>
                            <td class="number <?php echo $row['diff'] >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($row['diff'], 0); ?></td>
                            <td class="number <?php echo $row['diff_pct'] >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($row['diff_pct'], 1); ?>%</td>
                            <td style="text-align:center;"><?php echo $row['diff'] >= 0 ? '<span class="positive">▲</span>' : '<span class="negative">▼</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>รวมทั้งหมด</td>
                        <td class="number"><?php echo number_format($total_this_period, 0); ?></td>
                        <td class="number"><?php echo number_format($total_last_period, 0); ?></td>
                        <td class="number <?php echo $total_diff >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($total_diff, 0); ?></td>
                        <td class="number <?php echo $total_diff_pct >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($total_diff_pct, 1); ?>%</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    { label: 'ช่วงที่เลือก', data: <?php echo json_encode($this_data); ?>, backgroundColor: 'rgba(54, 162, 235, 0.7)' },
                    { label: 'ช่วงก่อนหน้า', data: <?php echo json_encode($last_data); ?>, backgroundColor: 'rgba(255, 99, 132, 0.7)' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>
</html>