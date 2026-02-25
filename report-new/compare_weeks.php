<?php
// compare_weeks.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('-1 day'));

// Calculate last period
$days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
$last_period_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
$last_period_start = date('Y-m-d', strtotime($last_period_end . ' -' . $days_diff . ' days'));

// ✨ Helper Function: Get Aggregated Data (Clean Version - Direct Store Code)
function getAggregatedData($db, $s_date, $e_date) {
    // ใช้ LEFT JOIN เพื่อเอาชื่อร้านค้ามาด้วยเลย
    $sql = "
        SELECT 
            ds.store_code, 
            s.store_name,
            SUM(ds.tax_incl_total) as total_sales, 
            COUNT(DISTINCT ds.sale_date) as sales_days
        FROM daily_sales ds 
        LEFT JOIN stores s ON ds.store_code = s.store_code
        WHERE ds.sale_date BETWEEN ? AND ? 
        GROUP BY ds.store_code
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$s_date, $e_date]);
    
    // Fetch All โดยใช้ store_code เป็น Key ของ Array
    return $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}

// ดึงข้อมูล 2 ช่วงเวลา
$this_period_agg = getAggregatedData($db, $start_date, $end_date);
$last_period_agg = getAggregatedData($db, $last_period_start, $last_period_end);

// Combine Data
$comparison = [];
$total_this_period = 0;
$total_last_period = 0;

// หา Store Codes ทั้งหมดที่มีรายการขายในทั้ง 2 ช่วง
$all_codes = array_unique(array_merge(array_keys($this_period_agg), array_keys($last_period_agg)));

foreach ($all_codes as $code) {
    // ดึงข้อมูลแต่ละช่วง ถ้าไม่มีให้เป็น 0 หรือ array ว่าง
    $this_data = $this_period_agg[$code] ?? ['total_sales' => 0, 'store_name' => ''];
    $last_data = $last_period_agg[$code] ?? ['total_sales' => 0, 'store_name' => ''];
    
    // ใช้ชื่อร้านจากช่วงไหนก็ได้ที่มีข้อมูล (ถ้าไม่มีเลยใช้ Code แทน)
    $store_name = $this_data['store_name'] ?: ($last_data['store_name'] ?: $code);
    
    $this_sales = $this_data['total_sales'];
    $last_sales = $last_data['total_sales'];
    
    $total_this_period += $this_sales;
    $total_last_period += $last_sales;
    
    $diff = $this_sales - $last_sales;
    $diff_pct = $last_sales > 0 ? (($diff / $last_sales) * 100) : ($this_sales > 0 ? 100 : 0);
    
    $comparison[] = [
        'store_code' => $code,
        'store_name' => $store_name,
        'this_period' => $this_sales,
        'last_period' => $last_sales,
        'diff' => $diff,
        'diff_pct' => $diff_pct
    ];
}

// Sort by sales descending (ยอดขายช่วงปัจจุบันมากสุดขึ้นก่อน)
usort($comparison, function($a, $b) { return $b['this_period'] <=> $a['this_period']; });

$total_diff = $total_this_period - $total_last_period;
$total_diff_pct = $total_last_period > 0 ? (($total_diff / $total_last_period) * 100) : ($total_this_period > 0 ? 100 : 0);

// Get Daily Breakdown for Charts
$stmt = $db->prepare("SELECT sale_date, SUM(tax_incl_total) as daily_total FROM daily_sales WHERE sale_date BETWEEN ? AND ? GROUP BY sale_date ORDER BY sale_date");

// This Period Data
$stmt->execute([$start_date, $end_date]);
$daily_this = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Last Period Data
$stmt->execute([$last_period_start, $last_period_end]);
$daily_last = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare Chart Arrays
$chart_labels = []; 
$this_data = []; 
$last_data = [];

// Map data to preserve order
foreach($daily_this as $r) { 
    $chart_labels[] = date('d/m', strtotime($r['sale_date'])); 
    $this_data[] = $r['daily_total']; 
}

foreach($daily_last as $r) { 
    $last_data[] = $r['daily_total']; 
}

// Adjust array sizes for chart consistency
$max_points = max(count($this_data), count($last_data));
$this_data = array_pad($this_data, $max_points, 0);
$last_data = array_pad($last_data, $max_points, 0);
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
            color: white; /* เปลี่ยนเป็นสีขาวตามธีม */
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
        /* --- เริ่มต้น CSS ส่วนเมนู --- */
.nav-menu {
    background: white;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px; /* เว้นระยะห่างจากเนื้อหาด้านล่าง */
}

.nav-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #333;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.nav-btn:hover {
    background: linear-gradient(135deg, #0288d1 0%, #0097a7 100%);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 136, 209, 0.4);
}
/* --- สิ้นสุด CSS ส่วนเมนู --- */
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📈</span>
                เปรียบเทียบยอดขาย
            </div>
            
        </div>
    </div>
    <div class="container">
    <div class="nav-menu">
    <div class="nav-content">
        <a href="dashboard.php" class="nav-btn">🏠 กลับหน้าหลัก</a>
        <a href="manage_targets.php" class="nav-btn">🎯 จัดการเป้าหมาย</a>
        <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
        <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
        <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
        <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
    </div>
</div>
        
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
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong><br><small style="color:#999;"><?php echo $row['store_code']; ?></small></td>
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