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

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d', strtotime('-1 day'));

// Calculate default compare period
$days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
$default_compare_from = date('Y-m-d', strtotime($date_from . ' -' . ($days_diff + 1) . ' days'));
$default_compare_to = date('Y-m-d', strtotime($date_to . ' -' . ($days_diff + 1) . ' days'));

$compare_from = isset($_GET['compare_from']) ? $_GET['compare_from'] : $default_compare_from;
$compare_to = isset($_GET['compare_to']) ? $_GET['compare_to'] : $default_compare_to;

// Filters
$selected_brands = $_GET['brands'] ?? [];
$selected_classes = $_GET['classes'] ?? [];
$selected_stores = $_GET['stores'] ?? [];
$selected_customer_types = $_GET['customer_types'] ?? [];

// --- ✨ STORE MAPPING LOGIC START ---
// ดึงข้อมูลร้านค้าเพื่อทำ Mapping (New Code -> Main Code)
try {
    $stores_stmt = $db->query("SELECT store_code, store_code_new, store_name FROM stores ORDER BY store_code");
    $all_stores_db = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $store_map = []; // Map: Code -> Main Code
    $main_stores_info = []; // Info: Main Code -> Name
    
    foreach ($all_stores_db as $s) {
        $main = $s['store_code'];
        $new = $s['store_code_new'];
        $name = $s['store_name'];
        
        $main_stores_info[$main] = $name;
        $store_map[$main] = $main;
        
        if (!empty($new)) {
            $store_map[$new] = $main;
        }
    }
} catch (Exception $e) {
    die("Error fetching stores: " . $e->getMessage());
}
// --- ✨ STORE MAPPING LOGIC END ---

// Fetch options for filters
try {
    $brands_stmt = $db->query("SELECT DISTINCT brand FROM daily_sales WHERE brand IS NOT NULL AND brand != '' AND (group_name != 'SKATEBOARD' OR group_name IS NULL) AND (class_name != 'GWP' OR class_name IS NULL) ORDER BY brand");
    $all_brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $classes_stmt = $db->query("SELECT DISTINCT class_name FROM daily_sales WHERE class_name IS NOT NULL AND class_name != '' AND (group_name != 'SKATEBOARD' OR group_name IS NULL) AND (class_name != 'GWP' OR class_name IS NULL) ORDER BY class_name");
    $all_classes = $classes_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Use the fetched stores for the filter list (Show only Main Codes or Active ones)
    $all_stores = array_filter($all_stores_db, function($s) {
        return empty($s['store_code_new']); // Show only main stores or stores without replacement in filter list
    });
} catch (Exception $e) {
    die("Error fetching filter options: " . $e->getMessage());
}

// Helper functions
function formatDateShort($date) {
    $dt = new DateTime($date);
    return $dt->format('d/m');
}
function formatDateDisplay($date) {
    $dt = new DateTime($date);
    return $dt->format('d/m/Y');
}

$exclusions = "AND internal_ref IS NOT NULL AND internal_ref != '' AND (group_name != 'SKATEBOARD' OR group_name IS NULL) AND (class_name != 'GWP' OR class_name IS NULL)";

// Build Filter Conditions
function buildFilterConditions($selected_brands, $selected_classes, $selected_stores, $selected_customer_types, $store_map) {
    $conditions = array();
    $params = array();
    
    if (count($selected_brands) > 0) {
        $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
        $conditions[] = "brand IN ($brand_placeholders)";
        foreach ($selected_brands as $brand) $params[] = $brand;
    }
    
    if (count($selected_classes) > 0) {
        $class_placeholders = implode(',', array_fill(0, count($selected_classes), '?'));
        $conditions[] = "class_name IN ($class_placeholders)";
        foreach ($selected_classes as $class) $params[] = $class;
    }
    
    if (count($selected_stores) > 0) {
        // ✨ Expand selected stores to include their new codes if any
        // But since we query raw data and map later, we can just include the selected codes
        // However, if user selects '13010', we should also fetch '14070' if it exists in raw data
        // For simplicity in SQL, let's include all codes that map to the selected main codes
        $expanded_stores = [];
        foreach ($store_map as $code => $main) {
            if (in_array($main, $selected_stores)) {
                $expanded_stores[] = $code;
            }
        }
        $expanded_stores = array_unique($expanded_stores);
        
        if (!empty($expanded_stores)) {
            $store_placeholders = implode(',', array_fill(0, count($expanded_stores), '?'));
            $conditions[] = "store_code IN ($store_placeholders)";
            foreach ($expanded_stores as $s) $params[] = $s;
        }
    }
    
    if (count($selected_customer_types) > 0) {
        $ct_conds = [];
        foreach ($selected_customer_types as $type) {
            if ($type === 'MEMBER') $ct_conds[] = "customer LIKE '99%'";
            elseif ($type === 'WALKIN') $ct_conds[] = "customer LIKE 'WI%TH'";
            elseif ($type === 'FOREIGNER') $ct_conds[] = "(customer LIKE 'WI%' AND customer NOT LIKE 'WI%TH')";
        }
        if (count($ct_conds) > 0) $conditions[] = "(" . implode(' OR ', $ct_conds) . ")";
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

$filter_data = buildFilterConditions($selected_brands, $selected_classes, $selected_stores, $selected_customer_types, $store_map);
$filter_conditions = $filter_data['conditions'];
$filter_params = $filter_data['params'];

$filter_where_ds = '';
if (count($filter_conditions) > 0) {
    $filter_where_arr = [];
    foreach ($filter_conditions as $cond) {
        $cond = str_replace('store_code IN', 'ds.store_code IN', $cond);
        $filter_where_arr[] = $cond;
    }
    $filter_where_ds = ' AND ' . implode(' AND ', $filter_where_arr);
}
$filter_where = count($filter_conditions) > 0 ? ' AND ' . implode(' AND ', $filter_conditions) : '';

// 1. Get Totals (Date-based, store mapping doesn't affect total sum)
$daily_selected = [];
try {
    $params = array_merge([$date_from, $date_to], $filter_params);
    $stmt = $db->prepare("SELECT sale_date, SUM(tax_incl_total) as sales FROM daily_sales WHERE sale_date BETWEEN ? AND ? $filter_where $exclusions GROUP BY sale_date ORDER BY sale_date");
    $stmt->execute($params);
    $daily_selected = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Error selected period: " . $e->getMessage()); }

$daily_compare = [];
try {
    $params = array_merge([$compare_from, $compare_to], $filter_params);
    $stmt = $db->prepare("SELECT sale_date, SUM(tax_incl_total) as sales FROM daily_sales WHERE sale_date BETWEEN ? AND ? $filter_where $exclusions GROUP BY sale_date ORDER BY sale_date");
    $stmt->execute($params);
    $daily_compare = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Error compare period: " . $e->getMessage()); }

// 2. Get Sales by Store (Need Mapping!)
// Function to consolidate store data
function getConsolidatedStoreSales($db, $date_from, $date_to, $filter_where_ds, $filter_params, $exclusions, $store_map, $main_stores_info) {
    $params = array_merge([$date_from, $date_to], $filter_params);
    $sql = "SELECT ds.store_code, SUM(ds.tax_incl_total) as sales 
            FROM daily_sales ds 
            WHERE ds.sale_date BETWEEN ? AND ? $filter_where_ds $exclusions 
            GROUP BY ds.store_code";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $consolidated = [];
    foreach ($raw_data as $row) {
        $code = $row['store_code'];
        $sales = $row['sales'];
        $main_code = $store_map[$code] ?? $code;
        
        if (!isset($consolidated[$main_code])) {
            $consolidated[$main_code] = [
                'store_code' => $main_code,
                'store_name' => $main_stores_info[$main_code] ?? $main_code,
                'sales' => 0
            ];
        }
        $consolidated[$main_code]['sales'] += $sales;
    }
    // Sort descending
    usort($consolidated, function($a, $b) { return $b['sales'] <=> $a['sales']; });
    return $consolidated;
}

$store_selected_consolidated = getConsolidatedStoreSales($db, $date_from, $date_to, $filter_where_ds, $filter_params, $exclusions, $store_map, $main_stores_info);
$store_compare_consolidated = getConsolidatedStoreSales($db, $compare_from, $compare_to, $filter_where_ds, $filter_params, $exclusions, $store_map, $main_stores_info);

// Lookup for compare period
$store_compare_lookup = [];
foreach ($store_compare_consolidated as $row) {
    $store_compare_lookup[$row['store_code']] = $row['sales'];
}

// 3. Get Daily Sales by Store (Charts) - Need Mapping!
function getConsolidatedStoreDaily($db, $date_from, $date_to, $filter_where_ds, $filter_params, $exclusions, $store_map) {
    $params = array_merge([$date_from, $date_to], $filter_params);
    $sql = "SELECT ds.store_code, ds.sale_date, SUM(ds.tax_incl_total) as sales 
            FROM daily_sales ds 
            WHERE ds.sale_date BETWEEN ? AND ? $filter_where_ds $exclusions 
            GROUP BY ds.store_code, ds.sale_date ORDER BY ds.sale_date";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $consolidated = []; // [store_code][date] => sales
    foreach ($raw as $row) {
        $code = $row['store_code'];
        $date = $row['sale_date'];
        $sales = $row['sales'];
        $main_code = $store_map[$code] ?? $code;
        
        if (!isset($consolidated[$main_code])) $consolidated[$main_code] = [];
        if (!isset($consolidated[$main_code][$date])) $consolidated[$main_code][$date] = 0;
        
        $consolidated[$main_code][$date] += $sales;
    }
    return $consolidated;
}

$store_daily_selected = getConsolidatedStoreDaily($db, $date_from, $date_to, $filter_where_ds, $filter_params, $exclusions, $store_map);
$store_daily_compare = getConsolidatedStoreDaily($db, $compare_from, $compare_to, $filter_where_ds, $filter_params, $exclusions, $store_map);

// Prepare Chart Data (Total)
$selected_dates = [];
$c = strtotime($date_from); $e = strtotime($date_to);
while ($c <= $e) { $selected_dates[] = date('Y-m-d', $c); $c = strtotime('+1 day', $c); }

$compare_dates = [];
$c = strtotime($compare_from); $e = strtotime($compare_to);
while ($c <= $e) { $compare_dates[] = date('Y-m-d', $c); $c = strtotime('+1 day', $c); }

$daily_selected_lookup = array_column($daily_selected, 'sales', 'sale_date');
$daily_compare_lookup = array_column($daily_compare, 'sales', 'sale_date');

$chart_labels = []; $chart_selected = []; $chart_compare = [];
$max_days = max(count($selected_dates), count($compare_dates));

for ($i = 0; $i < $max_days; $i++) {
    $chart_labels[] = isset($selected_dates[$i]) ? formatDateShort($selected_dates[$i]) : "";
    $chart_selected[] = isset($selected_dates[$i]) ? round($daily_selected_lookup[$selected_dates[$i]] ?? 0) : 0;
    $chart_compare[] = isset($compare_dates[$i]) ? round($daily_compare_lookup[$compare_dates[$i]] ?? 0) : 0;
}

$total_selected = array_sum($chart_selected);
$total_compare = array_sum($chart_compare);
$total_diff = $total_selected - $total_compare;
$total_pct = $total_compare > 0 ? ($total_diff / $total_compare * 100) : 0;

// Comparison Table Data
$comparison_data = [];
$store_charts = [];

foreach ($store_selected_consolidated as $row) {
    $code = $row['store_code'];
    $sales_sel = $row['sales'];
    $sales_com = $store_compare_lookup[$code] ?? 0;
    $diff = $sales_sel - $sales_com;
    $pct = $sales_com > 0 ? ($diff / $sales_com * 100) : ($sales_sel > 0 ? 100 : 0);
    
    $comparison_data[] = [
        'store_code' => $code,
        'store_name' => $row['store_name'],
        'selected' => $sales_sel,
        'compare' => $sales_com,
        'diff' => $diff,
        'pct_change' => $pct
    ];
    
    // Chart data per store
    $s_chart_sel = []; $s_chart_com = [];
    for ($i = 0; $i < $max_days; $i++) {
        $date_s = $selected_dates[$i] ?? null;
        $date_c = $compare_dates[$i] ?? null;
        $s_chart_sel[] = ($date_s && isset($store_daily_selected[$code][$date_s])) ? round($store_daily_selected[$code][$date_s]) : 0;
        $s_chart_com[] = ($date_c && isset($store_daily_compare[$code][$date_c])) ? round($store_daily_compare[$code][$date_c]) : 0;
    }
    $store_charts[$code] = ['selected' => $s_chart_sel, 'compare' => $s_chart_com];
}
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
        .header { 
            background: rgba(2, 136, 209, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }
        .header-content { 
            max-width: 1600px; 
            margin: 0 auto; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 20px; 
        }
        .header-title { 
            color: #212529; 
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
        }
        .back-link:hover { 
            background: #f5f5f5;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
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
        .card { background: white; padding: 25px; border-radius: 15px; border: 1px solid #dee2e6; margin-bottom: 25px; }
        .chart-container { position: relative; height: 350px; width: 100%; }
        .legend-box { display: flex; justify-content: center; gap: 30px; margin-bottom: 15px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #495057; }
        .legend-color { width: 16px; height: 16px; border-radius: 4px; }
        .legend-color.selected { background: #63b3ed; } .legend-color.compare { background: #f687b3; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e9ecef; font-size: 13px; color: #212529; }
        th { background: #f8f9fa; color: #495057; text-transform: uppercase; font-weight: 600; }
        .number { text-align: right; font-family: 'Monaco', monospace; }
        .status-up { color: #48bb78; } .status-down { color: #fc8181; }
        .change-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .change-badge.up { background: rgba(72, 187, 120, 0.2); color: #48bb78; } .change-badge.down { background: rgba(252, 129, 129, 0.2); color: #fc8181; }
        
        /* Additional text fixes */
        .summary-label { color: #6c757d; font-size: 13px; margin-bottom: 8px; }
        h2, h3 { color: #212529; }
        .filter-label { color: #495057; }
        
        /* Scrollbar styling */
        .checkbox-group::-webkit-scrollbar { width: 8px; }
        .checkbox-group::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .checkbox-group::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }
        .checkbox-group::-webkit-scrollbar-thumb:hover { background: #a0aec0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title"><span class="header-icon">📊</span> เปรียบเทียบยอดขายตามช่วงเวลา</div>
            <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
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
            if(type=='week') { dt = new Date(today); df = new Date(today); df.setDate(today.getDate()-6); cf = new Date(df); cf.setDate(df.getDate()-7); ct = new Date(dt); ct.setDate(dt.getDate()-7); }
            else if(type=='month') { df = new Date(today.getFullYear(), today.getMonth(), 1); dt = new Date(today.getFullYear(), today.getMonth()+1, 0); cf = new Date(today.getFullYear(), today.getMonth()-1, 1); ct = new Date(today.getFullYear(), today.getMonth(), 0); }
            else if(type=='7days') { dt=new Date(today); df=new Date(today); df.setDate(today.getDate()-6); ct=new Date(df); ct.setDate(df.getDate()-1); cf=new Date(ct); cf.setDate(ct.getDate()-6); }
            else if(type=='yoy') { df = new Date(today.getFullYear(), today.getMonth(), 1); dt = new Date(today.getFullYear(), today.getMonth()+1, 0); cf = new Date(today.getFullYear()-1, today.getMonth(), 1); ct = new Date(today.getFullYear()-1, today.getMonth()+1, 0); }
            
            document.querySelector('[name="date_from"]').value = df.toISOString().split('T')[0];
            document.querySelector('[name="date_to"]').value = dt.toISOString().split('T')[0];
            document.querySelector('[name="compare_from"]').value = cf.toISOString().split('T')[0];
            document.querySelector('[name="compare_to"]').value = ct.toISOString().split('T')[0];
        }
    </script>
</body>
</html>