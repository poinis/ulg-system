<?php
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
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function formatDateShort($date) {
    return (new DateTime($date))->format('d/m');
}

// 1. Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('-1 day'));

// Calculate default compare period
$days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
$default_compare_from = date('Y-m-d', strtotime($date_from . ' -' . ($days_diff + 1) . ' days'));
$default_compare_to = date('Y-m-d', strtotime($date_to . ' -' . ($days_diff + 1) . ' days'));

$compare_from = $_GET['compare_from'] ?? $default_compare_from;
$compare_to = $_GET['compare_to'] ?? $default_compare_to;

$selected_brands = $_GET['brands'] ?? [];
$selected_stores = $_GET['stores'] ?? [];

// 2. ✨ Fetch Active Stores (Clean List)
try {
    $stmt = $db->query("SELECT store_code, store_name FROM stores WHERE is_active = 1 ORDER BY store_code");
    $all_stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Create map for easy name lookup
    $store_names = array_column($all_stores, 'store_name', 'store_code');
} catch (Exception $e) { $all_stores = []; $store_names = []; }

// Fetch Filter Options (Brands)
try {
    $stmt = $db->query("SELECT DISTINCT brand FROM daily_sales WHERE brand IS NOT NULL AND brand != '' AND (group_name != 'SKATEBOARD' OR group_name IS NULL) AND (class_name != 'GWP' OR class_name IS NULL) ORDER BY brand");
    $all_brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $all_brands = []; }

// 3. ✨ Build Conditions (Clean Logic - No Mapping Expansion)
$base_conds = ["internal_ref IS NOT NULL", "internal_ref != ''", "(group_name != 'SKATEBOARD' OR group_name IS NULL)", "(class_name != 'GWP' OR class_name IS NULL)"];
$base_params = [];

if (!empty($selected_brands)) {
    $ph = implode(',', array_fill(0, count($selected_brands), '?'));
    $base_conds[] = "brand IN ($ph)";
    foreach ($selected_brands as $b) $base_params[] = $b;
}

if (!empty($selected_stores)) {
    $ph = implode(',', array_fill(0, count($selected_stores), '?'));
    $base_conds[] = "store_code IN ($ph)";
    foreach ($selected_stores as $s) $base_params[] = $s;
}

$where_sql = implode(' AND ', $base_conds);

// 4. Data Retrieval Functions
function getPeriodData($db, $start, $end, $where, $params) {
    // Total Sales & Daily Breakdown
    $sql = "SELECT sale_date, SUM(tax_incl_total) as sales 
            FROM daily_sales 
            WHERE sale_date BETWEEN ? AND ? AND $where 
            GROUP BY sale_date ORDER BY sale_date";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$start, $end], $params));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStoreData($db, $start, $end, $where, $params) {
    // Sales by Store (Grouped by Store Code directly)
    // Use 'ds' prefix alias handling if needed, but here simple table is fine
    $sql = "SELECT store_code, SUM(tax_incl_total) as sales 
            FROM daily_sales 
            WHERE sale_date BETWEEN ? AND ? AND $where 
            GROUP BY store_code";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$start, $end], $params));
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns [store_code => sales]
}

function getStoreDailyData($db, $start, $end, $where, $params) {
    // For individual store charts
    $sql = "SELECT store_code, sale_date, SUM(tax_incl_total) as sales 
            FROM daily_sales 
            WHERE sale_date BETWEEN ? AND ? AND $where 
            GROUP BY store_code, sale_date";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$start, $end], $params));
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach($raw as $r) {
        $data[$r['store_code']][$r['sale_date']] = $r['sales'];
    }
    return $data;
}

// Execute Queries
$daily_selected_raw = getPeriodData($db, $date_from, $date_to, $where_sql, $base_params);
$daily_compare_raw = getPeriodData($db, $compare_from, $compare_to, $where_sql, $base_params);

$store_selected_raw = getStoreData($db, $date_from, $date_to, $where_sql, $base_params);
$store_compare_raw = getStoreData($db, $compare_from, $compare_to, $where_sql, $base_params);

$store_daily_selected = getStoreDailyData($db, $date_from, $date_to, $where_sql, $base_params);
$store_daily_compare = getStoreDailyData($db, $compare_from, $compare_to, $where_sql, $base_params);

// 5. Process Data for Charts & Display
// Date Mapping (Align dates by index 0, 1, 2...)
$days_count = max(
    (strtotime($date_to) - strtotime($date_from)) / 86400 + 1,
    (strtotime($compare_to) - strtotime($compare_from)) / 86400 + 1
);

$chart_labels = [];
$chart_selected = [];
$chart_compare = [];

$map_sel = array_column($daily_selected_raw, 'sales', 'sale_date');
$map_com = array_column($daily_compare_raw, 'sales', 'sale_date');

for ($i = 0; $i < $days_count; $i++) {
    $d_sel = date('Y-m-d', strtotime("$date_from +$i days"));
    $d_com = date('Y-m-d', strtotime("$compare_from +$i days"));
    
    $chart_labels[] = formatDateShort($d_sel);
    $chart_selected[] = $map_sel[$d_sel] ?? 0;
    $chart_compare[] = $map_com[$d_com] ?? 0;
}

$total_selected = array_sum($chart_selected);
$total_compare = array_sum($chart_compare);
$total_diff = $total_selected - $total_compare;
$total_pct = $total_compare > 0 ? ($total_diff / $total_compare * 100) : ($total_selected > 0 ? 100 : 0);

// 6. Process Comparison Table
$comparison_data = [];
$all_codes = array_unique(array_merge(array_keys($store_selected_raw), array_keys($store_compare_raw)));

// Store Charts Data
$store_charts = [];

foreach ($all_codes as $code) {
    $sel = $store_selected_raw[$code] ?? 0;
    $com = $store_compare_raw[$code] ?? 0;
    $diff = $sel - $com;
    $pct = $com > 0 ? ($diff / $com * 100) : ($sel > 0 ? 100 : 0);
    
    $comparison_data[] = [
        'store_code' => $code,
        'store_name' => $store_names[$code] ?? $code,
        'selected' => $sel,
        'compare' => $com,
        'diff' => $diff,
        'pct_change' => $pct
    ];
    
    // Build mini-chart data for this store
    $s_data_sel = [];
    $s_data_com = [];
    for ($i = 0; $i < $days_count; $i++) {
        $d_sel = date('Y-m-d', strtotime("$date_from +$i days"));
        $d_com = date('Y-m-d', strtotime("$compare_from +$i days"));
        $s_data_sel[] = $store_daily_selected[$code][$d_sel] ?? 0;
        $s_data_com[] = $store_daily_compare[$code][$d_com] ?? 0;
    }
    $store_charts[$code] = ['selected' => $s_data_sel, 'compare' => $s_data_com];
}

// Sort by Selected Period Sales Desc
usort($comparison_data, function($a, $b) { return $b['selected'] <=> $a['selected']; });

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปรียบเทียบยอดขายตามช่วงเวลา</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); min-height: 100vh; }
        
        /* Header */
        .header { background: rgba(2, 136, 209, 0.95); backdrop-filter: blur(15px); padding: 25px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; }
        .header-content { max-width: 1600px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .header-title { color: #212529; font-size: 32px; font-weight: 800; display: flex; align-items: center; gap: 12px; text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2); }
        .header-icon { background: white; padding: 10px; border-radius: 12px; font-size: 28px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
        .back-link { background: white; color: #0288d1; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 16px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); transition: all 0.3s ease; }
        .back-link:hover { background: #f5f5f5; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 0 20px 40px; }
        .filters { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .date-period-box { background: #f8f9fa; padding: 20px; border-radius: 12px; border: 1px solid #dee2e6; }
        .period-title { font-size: 14px; color: #0288d1; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .period-title.compare { color: #f14668; }
        .date-inputs { display: flex; gap: 10px; align-items: center; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .filter-label { font-size: 12px; color: #6c757d; font-weight: 600; }
        .filter-group input[type="date"] { padding: 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: 14px; background: white; color: #495057; }
        .checkbox-group { max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 8px; padding: 5px; background: white; }
        .checkbox-item { display: flex; align-items: center; padding: 8px 12px; margin-bottom: 2px; border-radius: 4px; }
        .checkbox-item:nth-child(odd) { background: #f8f9fa; }
        .checkbox-item:nth-child(even) { background: #e9ecef; }
        .checkbox-item:hover { background: #d1ecf1; }
        .checkbox-item input { margin-right: 10px; width: 16px; height: 16px; cursor: pointer; }
        .checkbox-item label { cursor: pointer; font-size: 13px; color: #212529; flex: 1; font-weight: 500; }
        .quick-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #dee2e6; }
        .quick-filter-btn { padding: 8px 16px; background: rgba(102, 126, 234, 0.1); color: #495057; border: 1px solid #ced4da; border-radius: 6px; cursor: pointer; font-size: 12px; transition: all 0.2s; }
        .quick-filter-btn:hover { background: #667eea; color: white; }
        .filter-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 12px 30px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: white; color: #495057; border: 1px solid #ced4da; }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .summary-card { background: white; padding: 25px; border-radius: 15px; border: 1px solid #dee2e6; }
        .summary-card.positive { border-left: 4px solid #48bb78; } .summary-card.negative { border-left: 4px solid #fc8181; } .summary-card.neutral { border-left: 4px solid #667eea; }
        .summary-value { font-size: 28px; font-weight: 800; color: #212529; }
        .summary-value.positive { color: #48bb78; } .summary-value.negative { color: #fc8181; }
        .summary-sub { font-size: 13px; font-weight: 600; margin-top: 5px; }
        
        .card { background: white; padding: 25px; border-radius: 15px; border: 1px solid #dee2e6; margin-bottom: 25px; }
        .chart-container { position: relative; height: 350px; width: 100%; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e9ecef; font-size: 13px; color: #212529; }
        th { background: #f8f9fa; color: #495057; text-transform: uppercase; font-weight: 600; }
        .number { text-align: right; font-family: 'Monaco', monospace; }
        .status-up { color: #48bb78; } .status-down { color: #fc8181; }
        .change-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .change-badge.up { background: rgba(72, 187, 120, 0.2); color: #48bb78; } .change-badge.down { background: rgba(252, 129, 129, 0.2); color: #fc8181; }
        .summary-label { color: #6c757d; font-size: 13px; margin-bottom: 8px; }
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
            <div class="header-title"><span class="header-icon">📊</span> เปรียบเทียบยอดขายตามช่วงเวลา</div>
       
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
        <form method="GET" class="filters">
            <div class="quick-filters">
                <button type="button" class="quick-filter-btn" onclick="selectStores(['06010','08010','09030','09080','09100','09110','09130','09160','10010','13010'])">🏪 Soup-Hooga-SW19 (9 สาขา)</button>
                <button type="button" class="quick-filter-btn" onclick="selectStoresAndBrands(['03020','06030','06050','06060','08010'], ['TOPOLOGIE'])">🏪 Topologie (5 สาขา + Brand)</button>
                <button type="button" class="quick-filter-btn" onclick="selectStores(['02010','02020','02030','02080','02090','07020','07030','09140','03010','03030','03060'])">🏪 Pronto-Freitag (11 สาขา)</button>
                <button type="button" class="quick-filter-btn" onclick="clearFilters()">🔄 ล้างการเลือกทั้งหมด</button>
            </div>
            
            <div class="filter-row">
                <div class="date-period-box">
                    <div class="period-title"><span>📅</span> ช่วงที่เลือก (Selected Period)</div>
                    <div class="date-inputs">
                        <div class="filter-group"><label class="filter-label">เริ่มต้น</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
                        <div class="filter-group"><label class="filter-label">สิ้นสุด</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
                    </div>
                </div>
                <div class="date-period-box">
                    <div class="period-title compare"><span>📅</span> ช่วงก่อนหน้า (Compare Period)</div>
                    <div class="date-inputs">
                        <div class="filter-group"><label class="filter-label">เริ่มต้น</label><input type="date" name="compare_from" value="<?php echo $compare_from; ?>"></div>
                        <div class="filter-group"><label class="filter-label">สิ้นสุด</label><input type="date" name="compare_to" value="<?php echo $compare_to; ?>"></div>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
                <button type="button" class="quick-filter-btn" onclick="setPreset('week')">สัปดาห์นี้ vs ที่แล้ว</button>
                <button type="button" class="quick-filter-btn" onclick="setPreset('month')">เดือนนี้ vs ที่แล้ว</button>
                <button type="button" class="quick-filter-btn" onclick="setPreset('7days')">7 วันล่าสุด</button>
                <button type="button" class="quick-filter-btn" onclick="setPreset('yoy')">YoY เดือนนี้</button>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">🏷️ เลือก Brand</label>
                    <div class="checkbox-group">
                        <?php foreach ($all_brands as $brand): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="brands[]" id="brand_<?php echo htmlspecialchars($brand); ?>" value="<?php echo htmlspecialchars($brand); ?>" <?php echo in_array($brand, $selected_brands) ? 'checked' : ''; ?>>
                            <label for="brand_<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">🏪 เลือกสาขา</label>
                    <div class="checkbox-group">
                        <?php foreach ($all_stores as $store): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="stores[]" id="store_<?php echo $store['store_code']; ?>" value="<?php echo $store['store_code']; ?>" <?php echo in_array($store['store_code'], $selected_stores) ? 'checked' : ''; ?>>
                            <label for="store_<?php echo $store['store_code']; ?>"><?php echo $store['store_code'] . ' - ' . htmlspecialchars($store['store_name']); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 เปรียบเทียบ</button>
                <a href="compare_period_report.php" class="btn btn-secondary">🔄 รีเซ็ต</a>
            </div>
        </form>
        
        <div class="summary-cards">
            <div class="summary-card neutral">
                <div class="summary-label">ช่วงที่เลือก</div>
                <div class="summary-value"><?php echo number_format($total_selected, 0); ?></div>
            </div>
            <div class="summary-card neutral">
                <div class="summary-label">ช่วงก่อนหน้า</div>
                <div class="summary-value"><?php echo number_format($total_compare, 0); ?></div>
            </div>
            <div class="summary-card <?php echo $total_diff >= 0 ? 'positive' : 'negative'; ?>">
                <div class="summary-label">ส่วนต่าง</div>
                <div class="summary-value <?php echo $total_diff >= 0 ? 'positive' : 'negative'; ?>"><?php echo ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0); ?></div>
                <div class="summary-sub"><?php echo ($total_diff >= 0 ? '▲' : '▼') . ' ' . number_format(abs($total_pct), 1); ?>%</div>
            </div>
        </div>
        
        <div class="card">
            <h2>📈 กราฟเปรียบเทียบรายวัน (รวมทุกสาขา)</h2>
            <div class="chart-container"><canvas id="totalChart"></canvas></div>
        </div>
        
        <?php if (count($comparison_data) > 0): ?>
        <div class="card">
            <h2>📊 เปรียบเทียบแยกตามสาขา</h2>
            <?php foreach ($comparison_data as $row): 
                $store_code = $row['store_code'];
                $chart_data = $store_charts[$store_code] ?? null;
                if (!$chart_data) continue;
            ?>
            <div style="margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid #dee2e6;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="color: #212529; font-size: 18px; margin: 0;"><?php echo htmlspecialchars($row['store_name']); ?></h3>
                        <span style="color: #6c757d; font-size: 12px;">รหัส: <?php echo $store_code; ?></span>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 22px; font-weight: 700; color: #212529;"><?php echo number_format($row['selected'], 0); ?></div>
                        <div class="<?php echo $row['pct_change'] >= 0 ? 'status-up' : 'status-down'; ?>" style="font-size: 14px;">
                            <?php echo ($row['pct_change'] >= 0 ? '▲' : '▼') . ' ' . number_format(abs($row['pct_change']), 1); ?>%
                        </div>
                    </div>
                </div>
                <div style="height: 250px; margin-bottom: 20px;"><canvas id="chart_<?php echo $store_code; ?>"></canvas></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>สาขา</th><th class="number">ช่วงที่เลือก</th><th class="number">ช่วงก่อนหน้า</th><th class="number">ส่วนต่าง</th><th class="number">% เปลี่ยนแปลง</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                                <td class="number"><?php echo number_format($row['selected'], 0); ?></td>
                                <td class="number"><?php echo number_format($row['compare'], 0); ?></td>
                                <td class="number <?php echo $row['diff'] >= 0 ? 'status-up' : 'status-down'; ?>"><?php echo ($row['diff'] >= 0 ? '+' : '') . number_format($row['diff'], 0); ?></td>
                                <td class="number"><span class="change-badge <?php echo $row['pct_change'] >= 0 ? 'up' : 'down'; ?>"><?php echo ($row['pct_change'] >= 0 ? '+' : '') . number_format($row['pct_change'], 1); ?>%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        
        new Chart(document.getElementById('totalChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'ช่วงที่เลือก', data: <?php echo json_encode($chart_selected); ?>, backgroundColor: 'rgba(99, 179, 237, 0.8)' },
                    { label: 'ช่วงก่อนหน้า', data: <?php echo json_encode($chart_compare); ?>, backgroundColor: 'rgba(246, 135, 179, 0.8)' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { ticks: { color: '#a0aec0' } }, y: { ticks: { color: '#a0aec0' } } } }
        });
        
        <?php foreach ($comparison_data as $row): 
            $store_code = $row['store_code'];
            $chart_data = $store_charts[$store_code] ?? null;
            if (!$chart_data) continue;
        ?>
        new Chart(document.getElementById('chart_<?php echo $store_code; ?>').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'ช่วงที่เลือก', data: <?php echo json_encode($chart_data['selected']); ?>, backgroundColor: 'rgba(99, 179, 237, 0.8)' },
                    { label: 'ช่วงก่อนหน้า', data: <?php echo json_encode($chart_data['compare']); ?>, backgroundColor: 'rgba(246, 135, 179, 0.8)' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#a0aec0' } }, y: { ticks: { color: '#a0aec0' } } } }
        });
        <?php endforeach; ?>
        
        function selectStores(codes) { document.querySelectorAll('input[name="stores[]"]').forEach(c => c.checked = false); codes.forEach(c => { let el = document.getElementById('store_'+c); if(el) el.checked = true; }); }
        function selectStoresAndBrands(codes, brands) { selectStores(codes); document.querySelectorAll('input[name="brands[]"]').forEach(c => c.checked = false); brands.forEach(b => { let el = document.getElementById('brand_'+b); if(el) el.checked = true; }); }
        function clearFilters() { document.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false); }
        function setPreset(type) {
            const today = new Date(); let df, dt, cf, ct;
            // Helper to format YYYY-MM-DD
            const fmt = d => d.toISOString().split('T')[0];
            
            if(type=='week') { dt = new Date(today); df = new Date(today); df.setDate(today.getDate()-6); cf = new Date(df); cf.setDate(df.getDate()-7); ct = new Date(dt); ct.setDate(dt.getDate()-7); }
            else if(type=='month') { df = new Date(today.getFullYear(), today.getMonth(), 1); dt = new Date(today.getFullYear(), today.getMonth()+1, 0); cf = new Date(today.getFullYear(), today.getMonth()-1, 1); ct = new Date(today.getFullYear(), today.getMonth(), 0); }
            else if(type=='7days') { dt=new Date(today); df=new Date(today); df.setDate(today.getDate()-6); ct=new Date(df); ct.setDate(df.getDate()-1); cf=new Date(ct); cf.setDate(ct.getDate()-6); }
            else if(type=='yoy') { df = new Date(today.getFullYear(), today.getMonth(), 1); dt = new Date(today.getFullYear(), today.getMonth()+1, 0); cf = new Date(today.getFullYear()-1, today.getMonth(), 1); ct = new Date(today.getFullYear()-1, today.getMonth()+1, 0); }
            
            document.querySelector('[name="date_from"]').value = fmt(df);
            document.querySelector('[name="date_to"]').value = fmt(dt);
            document.querySelector('[name="compare_from"]').value = fmt(cf);
            document.querySelector('[name="compare_to"]').value = fmt(ct);
        }
    </script>
</body>
</html>