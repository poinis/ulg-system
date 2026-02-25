<?php
// detailed_report.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

// Helper Function
if (!function_exists('formatNumber')) {
    function formatNumber($num, $decimals = 0) {
        return number_format((float)$num, $decimals);
    }
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 1. ดึงรายชื่อสาขา (Active Only)
try {
    $stores_stmt = $db->query("SELECT store_code, store_name FROM stores WHERE is_active = 1 ORDER BY store_code");
    $all_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching stores: " . $e->getMessage());
}

// Get Params
$selected_store = $_GET['store'] ?? ($all_stores[0]['store_code'] ?? '');
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_brands = $_GET['brands'] ?? [];

// หาชื่อสาขาที่เลือก
$current_store_name = $selected_store;
foreach ($all_stores as $s) {
    if ($s['store_code'] == $selected_store) {
        $current_store_name = $s['store_name'];
        break;
    }
}

// 2. ดึง Brands ที่มีขายในสาขานี้ (เพื่อทำตัวกรอง)
$store_brands = [];
if ($selected_store) {
    try {
        $stmt = $db->prepare("SELECT DISTINCT brand FROM daily_sales WHERE store_code = ? AND brand IS NOT NULL AND brand != '' ORDER BY brand");
        $stmt->execute([$selected_store]);
        $store_brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { }
}

// 3. เตรียม Query ข้อมูลรายวัน
$daily_sales = [];
$monthly_total = 0;
$monthly_qty = 0;
$target_val = 0;
$avg_daily = 0;

if ($selected_store) {
    // Conditions
    $conditions = ["store_code = ?", "DATE_FORMAT(sale_date, '%Y-%m') = ?"];
    $params = [$selected_store, $selected_month];

    if (!empty($selected_brands)) {
        $ph = implode(',', array_fill(0, count($selected_brands), '?'));
        $conditions[] = "brand IN ($ph)";
        foreach ($selected_brands as $b) $params[] = $b;
    }

    $where_sql = implode(' AND ', $conditions);

    // Query Daily Sales
    try {
        $sql = "
            SELECT 
                sale_date,
                SUM(tax_incl_total) as daily_sales,
                SUM(qty) as daily_qty,
                COUNT(DISTINCT internal_ref) as bill_count
            FROM daily_sales 
            WHERE $where_sql
            GROUP BY sale_date
            ORDER BY sale_date ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // คำนวณยอดรวมทั้งเดือน
        foreach ($daily_sales as $day) {
            $monthly_total += $day['daily_sales'];
            $monthly_qty += $day['daily_qty'];
        }
        $avg_daily = count($daily_sales) > 0 ? $monthly_total / count($daily_sales) : 0;

    } catch (Exception $e) {
        die("Error fetching daily data: " . $e->getMessage());
    }

    // Query Target (เป้าหมาย)
    try {
        $t_stmt = $db->prepare("SELECT monthly_target FROM sales_targets WHERE store_code = ? AND DATE_FORMAT(target_month, '%Y-%m') = ?");
        $t_stmt->execute([$selected_store, $selected_month]);
        $target_val = $t_stmt->fetchColumn() ?: 0;
    } catch (Exception $e) { }
}

// 4. ข้อมูลกราฟ (Top Brands & Division)
$brand_sales = [];
$division_sales = [];

if ($selected_store) {
    // Top 10 Brands
    try {
        $b_sql = "SELECT brand, SUM(tax_incl_total) as brand_sales FROM daily_sales WHERE $where_sql AND brand IS NOT NULL GROUP BY brand ORDER BY brand_sales DESC LIMIT 10";
        $stmt = $db->prepare($b_sql);
        $stmt->execute($params);
        $brand_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }

    // Division
    try {
        $d_sql = "SELECT sales_division, SUM(tax_incl_total) as division_sales FROM daily_sales WHERE $where_sql AND sales_division IS NOT NULL GROUP BY sales_division ORDER BY division_sales DESC";
        $stmt = $db->prepare($d_sql);
        $stmt->execute($params);
        $division_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานแยกสาขา - <?=$current_store_name?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); min-height: 100vh; }
        .header { background: rgba(2, 136, 209, 0.95); padding: 25px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .header-title { color: white; font-size: 28px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .back-link { background: white; color: #0288d1; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px 40px; }
        
        /* Filters */
        .filters { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 13px; font-weight: 600; color: #666; }
        .filter-group select, .filter-group input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-width: 200px; }
        .btn-search { background: #0288d1; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        
        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .stat-label { color: #666; font-size: 14px; margin-bottom: 5px; }
        .stat-value { font-size: 28px; font-weight: bold; color: #333; }
        
        /* Charts & Tables */
        .grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 20px; color: #333; font-size: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .chart-container { position: relative; height: 300px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .number { text-align: right; font-family: 'Courier New', monospace; }
        .btn-detail { background: #48c78e; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        
        /* Dropdown Checkboxes */
        .dropdown-check-list { display: inline-block; position: relative; }
        .dropdown-check-list .anchor { position: relative; cursor: pointer; display: inline-block; padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 200px; background: white; }
        .dropdown-check-list .anchor:after { position: absolute; content: ""; border-left: 2px solid black; border-top: 2px solid black; padding: 5px; right: 10px; top: 20%; -moz-transform: rotate(-135deg); -ms-transform: rotate(-135deg); -o-transform: rotate(-135deg); -webkit-transform: rotate(-135deg); transform: rotate(-135deg); }
        .dropdown-check-list ul.items { padding: 2px; display: none; margin: 0; border: 1px solid #ccc; border-top: none; position: absolute; background: white; z-index: 100; max-height: 200px; overflow-y: auto; width: 200px; }
        .dropdown-check-list ul.items li { list-style: none; padding: 5px; }
        .dropdown-check-list.visible .items { display: block; }
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

        @media (max-width: 1024px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">📋 รายงานแยกสาขา</div>

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
            <div class="filter-group">
                <label>🏪 สาขา</label>
                <select name="store" onchange="this.form.submit()">
                    <?php foreach ($all_stores as $s): ?>
                        <option value="<?=$s['store_code']?>" <?=$s['store_code']==$selected_store?'selected':''?>>
                            <?=$s['store_code']?> - <?=$s['store_name']?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>📅 เดือน</label>
                <input type="month" name="month" value="<?=$selected_month?>">
            </div>
            
            <div class="filter-group">
                <label>🏷️ แบรนด์</label>
                <div id="list1" class="dropdown-check-list" tabindex="100">
                    <span class="anchor">เลือก Brand (<?=count($selected_brands)?>)</span>
                    <ul class="items">
                        <?php foreach($store_brands as $b): ?>
                        <li><input type="checkbox" name="brands[]" value="<?=$b?>" <?=in_array($b, $selected_brands)?'checked':''?> /> <?=$b?> </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <button type="submit" class="btn-search">🔍 ค้นหา</button>
        </form>
        
        <?php if($selected_store): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ยอดขายรวม</div>
                <div class="stat-value" style="color: #0288d1;"><?=formatNumber($monthly_total)?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">เป้าหมาย</div>
                <div class="stat-value" style="color: #666;"><?=formatNumber($target_val)?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">ยอดเฉลี่ย/วัน</div>
                <div class="stat-value" style="color: #28a745;"><?=formatNumber($avg_daily)?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">จำนวนสินค้า</div>
                <div class="stat-value" style="color: #f39c12;"><?=formatNumber($monthly_qty)?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h2>📈 ยอดขายรายวัน</h2>
                <div class="chart-container"><canvas id="dailyChart"></canvas></div>
            </div>
            <div class="card">
                <h2>🏷️ Top 10 Brands</h2>
                <div class="chart-container"><canvas id="brandChart"></canvas></div>
            </div>
        </div>
        
        <div class="card">
            <h2>📅 รายละเอียดรายวัน</h2>
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th class="number">ยอดขาย</th>
                        <th class="number">จำนวนบิล</th>
                        <th class="number">จำนวนชิ้น</th>
                        <th style="text-align:center">ดูรายการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($daily_sales as $row): ?>
                    <tr>
                        <td><?=date('d/m/Y', strtotime($row['sale_date']))?></td>
                        <td class="number"><?=formatNumber($row['daily_sales'])?></td>
                        <td class="number"><?=number_format($row['bill_count'])?></td>
                        <td class="number"><?=number_format($row['daily_qty'])?></td>
                        <td style="text-align:center">
                            <a href="transaction_detail.php?store=<?=$selected_store?>&date_from=<?=$row['sale_date']?>&date_to=<?=$row['sale_date']?>" class="btn-detail" target="_blank">🔍 ดูบิล</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Dropdown Script
        var checkList = document.getElementById('list1');
        if(checkList){
            checkList.getElementsByClassName('anchor')[0].onclick = function(evt) {
                if (checkList.classList.contains('visible')) checkList.classList.remove('visible');
                else checkList.classList.add('visible');
            }
        }

        <?php if($selected_store && !empty($daily_sales)): ?>
        // Daily Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: <?=json_encode(array_map(function($d){ return date('d/m', strtotime($d['sale_date'])); }, $daily_sales))?>,
                datasets: [{
                    label: 'ยอดขาย',
                    data: <?=json_encode(array_column($daily_sales, 'daily_sales'))?>,
                    backgroundColor: '#0288d1'
                }]
            },
            options: { maintainAspectRatio: false }
        });

        // Brand Chart
        new Chart(document.getElementById('brandChart'), {
            type: 'doughnut',
            data: {
                labels: <?=json_encode(array_column($brand_sales, 'brand'))?>,
                datasets: [{
                    data: <?=json_encode(array_column($brand_sales, 'brand_sales'))?>,
                    backgroundColor: ['#0288d1', '#039be5', '#29b6f6', '#4fc3f7', '#81d4fa', '#b3e5fc', '#e1f5fe', '#cfd8dc', '#b0bec5', '#90a4ae']
                }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });
        <?php endif; ?>
    </script>
</body>
</html>