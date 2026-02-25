<?php
// detailed_report.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// ✨ 1. Get ALL stores (รวม store_code_new มาด้วย)
$stores_stmt = $db->prepare("SELECT store_code, store_code_new, store_name FROM stores WHERE is_active = 1 ORDER BY store_name, store_code");
$stores_stmt->execute();
$all_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// ✨ 2. สร้างตัวแปร Mapping: จับคู่รหัสใหม่ -> กลับไปหารหัสหลัก
$store_map = [];
$main_stores_list = [];

foreach ($all_stores as $store) {
    $main_code = $store['store_code'];
    $new_code = $store['store_code_new'];
    
    // บันทึกข้อมูลร้านค้าหลัก
    $main_stores_list[$main_code] = $store;
    
    // Map รหัสตัวเอง เข้าหาตัวเอง
    $store_map[$main_code] = $main_code;
    
    // ถ้ามีรหัสใหม่ ให้ Map รหัสใหม่ เข้าหา รหัสหลัก
    if (!empty($new_code)) {
        $store_map[$new_code] = $main_code;
    }
}

// Get selected store and month
$selected_store = $_GET['store'] ?? ($all_stores[0]['store_code'] ?? '');
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_brands = $_GET['brands'] ?? [];

// ✨ 3. หา store codes ที่ต้อง query (รหัสหลัก + รหัสใหม่ถ้ามี)
$target_store_codes = [$selected_store];
if (isset($all_stores)) {
    foreach ($all_stores as $store) {
        if ($store['store_code'] == $selected_store && !empty($store['store_code_new'])) {
            $target_store_codes[] = $store['store_code_new'];
            break;
        }
    }
}

// Get all brands for selected store (ดึงจากทั้งรหัสหลักและรหัสใหม่)
$all_brands = [];
if ($selected_store) {
    $store_ph = implode(',', array_fill(0, count($target_store_codes), '?'));
    $brands_stmt = $db->prepare("
        SELECT DISTINCT brand 
        FROM daily_sales 
        WHERE store_code IN ($store_ph)
            AND brand IS NOT NULL 
            AND brand != ''
            AND (group_name != 'SKATEBOARD' OR group_name IS NULL)
        ORDER BY brand
    ");
    $brands_stmt->execute($target_store_codes);
    $all_brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Initialize variables
$daily_sales = [];
$brand_sales = [];
$division_sales = [];
$monthly_total = 0;
$monthly_qty = 0;
$avg_daily = 0;
$target_val = 0;

if ($selected_store) {
    // ✨ Build WHERE conditions (ใช้ IN แทน = เพื่อรองรับหลาย code)
    $store_ph = implode(',', array_fill(0, count($target_store_codes), '?'));
    $conditions = ["store_code IN ($store_ph)", "DATE_FORMAT(sale_date, '%Y-%m') = ?"];
    $params = array_merge($target_store_codes, [$selected_month]);
    
    // Add brand filter if selected
    if (count($selected_brands) > 0) {
        $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
        $conditions[] = "brand IN ($brand_placeholders)";
        foreach ($selected_brands as $brand) {
            $params[] = $brand;
        }
    }
    
    $where_clause = implode(' AND ', $conditions);
    
    // Get daily sales summary
    $daily_sql = "
        SELECT 
            sale_date,
            SUM(tax_incl_total) as daily_sales,
            SUM(qty) as daily_qty,
            COUNT(DISTINCT id) as transaction_count
        FROM daily_sales
        WHERE $where_clause
        GROUP BY sale_date
        ORDER BY sale_date
    ";
    $daily_stmt = $db->prepare($daily_sql);
    $daily_stmt->execute($params);
    $daily_sales = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate bill counts for each day (Positive bills - Negative bills)
    foreach ($daily_sales as &$day) {
        $day_date = $day['sale_date'];
        
        // ✨ Build day conditions (ใช้ IN แทน =)
        $day_conditions = ["store_code IN ($store_ph)", "sale_date = ?", "internal_ref IS NOT NULL", "internal_ref != ''"];
        $day_params = array_merge($target_store_codes, [$day_date]);
        
        // Add brand filter if selected
        if (count($selected_brands) > 0) {
            $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
            $day_conditions[] = "brand IN ($brand_placeholders)";
            foreach ($selected_brands as $brand) {
                $day_params[] = $brand;
            }
        }
        
        $day_where = implode(' AND ', $day_conditions);
        
        // Count positive bills
        $pos_sql = "
            SELECT COUNT(DISTINCT internal_ref) as cnt
            FROM (
                SELECT internal_ref
                FROM daily_sales
                WHERE $day_where
                GROUP BY internal_ref
                HAVING SUM(tax_incl_total) > 0
            ) as positive_bills
        ";
        $pos_stmt = $db->prepare($pos_sql);
        $pos_stmt->execute($day_params);
        $positive_count = $pos_stmt->fetch()['cnt'] ?? 0;
        
        // Count negative bills (returns/refunds)
        $neg_sql = "
            SELECT COUNT(DISTINCT internal_ref) as cnt
            FROM (
                SELECT internal_ref
                FROM daily_sales
                WHERE $day_where
                GROUP BY internal_ref
                HAVING SUM(tax_incl_total) < 0
            ) as negative_bills
        ";
        $neg_stmt = $db->prepare($neg_sql);
        $neg_stmt->execute($day_params);
        $negative_count = $neg_stmt->fetch()['cnt'] ?? 0;
        
        // Net bill count
        $day['bill_count'] = $positive_count - $negative_count;
    }
    unset($day);
    
    // ✨ Get monthly target (รวม target จากทั้งรหัสหลักและรหัสใหม่)
    $target_stmt = $db->prepare("
        SELECT SUM(monthly_target) as monthly_target
        FROM sales_targets
        WHERE store_code IN ($store_ph)
            AND DATE_FORMAT(target_month, '%Y-%m') = ?
    ");
    $target_stmt->execute(array_merge($target_store_codes, [$selected_month]));
    $target_row = $target_stmt->fetch();
    $target_val = $target_row ? $target_row['monthly_target'] : 0;
    
    // Get top 10 brands
    $brand_conditions = array_merge($conditions, ["brand IS NOT NULL", "brand != ''"]);
    $brand_where = implode(' AND ', $brand_conditions);
    
    $brand_sql = "
        SELECT 
            brand,
            SUM(tax_incl_total) as brand_sales
        FROM daily_sales
        WHERE $brand_where
        GROUP BY brand
        ORDER BY brand_sales DESC
        LIMIT 10
    ";
    $brand_stmt = $db->prepare($brand_sql);
    $brand_stmt->execute($params);
    $brand_sales = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sales by division
    $division_conditions = array_merge($conditions, ["sales_division IS NOT NULL", "sales_division != ''"]);
    $division_where = implode(' AND ', $division_conditions);
    
    $division_sql = "
        SELECT 
            sales_division,
            SUM(tax_incl_total) as division_sales
        FROM daily_sales
        WHERE $division_where
        GROUP BY sales_division
        ORDER BY division_sales DESC
    ";
    $division_stmt = $db->prepare($division_sql);
    $division_stmt->execute($params);
    $division_sales = $division_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $monthly_total = array_sum(array_column($daily_sales, 'daily_sales'));
    $monthly_qty = array_sum(array_column($daily_sales, 'daily_qty'));
    $avg_daily = count($daily_sales) > 0 ? $monthly_total / count($daily_sales) : 0;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานแยกสาขา</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%);
            min-height: 100vh;
        }
        
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
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 20px 40px; 
        }
        
        /* Filters */
        .filters { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 20px; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #555;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .brand-filter-container {
            position: relative;
        }
        
        .brand-toggle-btn {
            padding: 10px 20px;
            background: #0288d1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .brand-toggle-btn:hover {
            background: #0277bd;
        }
        
        .brand-dropdown { 
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            min-width: 200px;
            top: 100%;
            left: 0;
            margin-top: 5px;
        }
        
        .brand-dropdown.show { 
            display: block; 
        }
        
        .brand-dropdown label {
            display: block;
            padding: 5px;
            cursor: pointer;
        }
        
        .brand-dropdown label:hover {
            background: #f5f5f5;
        }
        
        .btn-search {
            padding: 10px 20px;
            background: #0288d1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-search:hover {
            background: #0277bd;
        }
        
        .btn-transaction {
            display: inline-block;
            padding: 10px 20px;
            background: #48c78e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-transaction:hover {
            background: #3ebb81;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 199, 142, 0.3);
        }
        
        /* Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat-value { 
            font-size: 32px; 
            font-weight: bold; 
            color: #0288d1;
        }
        
        /* Grid Layouts */
        .grid-2 { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        
        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        
        .card h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Tables */
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #eee; 
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .number { 
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        /* View Detail Button */
        .view-detail-btn {
            display: inline-block;
            padding: 6px 12px;
            background: #0288d1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .view-detail-btn:hover {
            background: #0277bd;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(2, 136, 209, 0.3);
        }
        
        /* Charts */
        .chart-container { 
            position: relative; 
            height: 300px; 
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📋</span>
                รายงานแยกตามสาขา
            </div>
            <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>🏪 เลือกสาขา</label>
                <select name="store" onchange="this.form.submit()">
                    <?php foreach ($all_stores as $store): ?>
                        <option value="<?php echo htmlspecialchars($store['store_code']); ?>" 
                                <?php echo $store['store_code'] == $selected_store ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($store['store_code'] . ' - ' . $store['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>📅 เลือกเดือน</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <div class="brand-filter-container">
                    <button type="button" class="brand-toggle-btn" onclick="document.getElementById('brandDropdown').classList.toggle('show')">
                        🏷️ เลือก Brand (<?php echo count($selected_brands); ?>)
                    </button>
                    <div id="brandDropdown" class="brand-dropdown">
                        <?php foreach ($all_brands as $brand): ?>
                            <label>
                                <input type="checkbox" name="brands[]" 
                                       value="<?php echo htmlspecialchars($brand); ?>" 
                                       <?php echo in_array($brand, $selected_brands) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($brand); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-search">🔍 ค้นหา</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="transaction_detail.php?store=<?php echo urlencode($selected_store); ?>&date_from=<?php echo $selected_month; ?>-01&date_to=<?php echo date('Y-m-t', strtotime($selected_month . '-01')); ?>" 
                   class="btn-transaction">
                    📋 รายละเอียดบิล
                </a>
            </div>
        </form>
        
        <?php if ($selected_store): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">💰 ยอดขายรวม</div>
                <div class="stat-value"><?php echo number_format($monthly_total, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🎯 เป้าหมาย</div>
                <div class="stat-value"><?php echo number_format($target_val, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📊 ยอดเฉลี่ย/วัน</div>
                <div class="stat-value"><?php echo number_format($avg_daily, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📦 จำนวนสินค้า</div>
                <div class="stat-value"><?php echo number_format($monthly_qty, 0); ?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h2>📈 ยอดขายรายวัน</h2>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <div class="card">
                <h2>🏷️ Top 10 Brands</h2>
                <div class="chart-container">
                    <canvas id="brandChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>📅 รายวันละเอียด</h2>
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th class="number">ยอดขาย (บาท)</th>
                        <th class="number">จำนวนบิล</th>
                        <th class="number">จำนวนชิ้น</th>
                        <th style="text-align: center;">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($daily_sales as $row): ?>
                    <tr>
                        <td><?php echo formatDate($row['sale_date']); ?></td>
                        <td class="number"><?php echo number_format($row['daily_sales'], 0); ?></td>
                        <td class="number"><?php echo number_format($row['bill_count'], 0); ?></td>
                        <td class="number"><?php echo number_format($row['daily_qty'], 0); ?></td>
                        <td style="text-align: center;">
                            <a href="transaction_detail.php?store=<?php echo urlencode($selected_store); ?>&date_from=<?php echo urlencode($row['sale_date']); ?>&date_to=<?php echo urlencode($row['sale_date']); ?>" 
                               class="view-detail-btn" 
                               title="ดูรายละเอียดธุรกรรม">
                                🔍 ดูรายการ
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td>รวม</td>
                        <td class="number"><?php echo number_format($monthly_total, 0); ?></td>
                        <td class="number">-</td>
                        <td class="number"><?php echo number_format($monthly_qty, 0); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <?php if (count($division_sales) > 0): ?>
        <div class="card">
            <h2>🏢 ยอดขายตาม Division</h2>
            <table>
                <thead>
                    <tr>
                        <th>Division</th>
                        <th class="number">ยอดขาย (บาท)</th>
                        <th class="number">% ของยอดรวม</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($division_sales as $row): ?>
                    <?php $percentage = $monthly_total > 0 ? ($row['division_sales'] / $monthly_total * 100) : 0; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['sales_division']); ?></td>
                        <td class="number"><?php echo number_format($row['division_sales'], 0); ?></td>
                        <td class="number"><?php echo number_format($percentage, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        <?php if($selected_store && count($daily_sales) > 0): ?>
        // Daily Sales Chart
        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('formatDate', array_column($daily_sales, 'sale_date'))); ?>,
                datasets: [{
                    label: 'ยอดขาย',
                    data: <?php echo json_encode(array_column($daily_sales, 'daily_sales')); ?>,
                    backgroundColor: '#0288d1',
                    borderColor: '#01579b',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Brand Chart
        <?php if (count($brand_sales) > 0): ?>
        new Chart(document.getElementById('brandChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($brand_sales, 'brand')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($brand_sales, 'brand_sales')); ?>,
                    backgroundColor: [
                        '#0288d1', '#0277bd', '#01579b', '#4fc3f7', '#29b6f6',
                        '#039be5', '#0277bd', '#01579b', '#81d4fa', '#4fc3f7'
                    ]
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
        <?php endif; ?>
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('brandDropdown');
            const button = event.target.closest('.brand-toggle-btn');
            
            if (!button && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>