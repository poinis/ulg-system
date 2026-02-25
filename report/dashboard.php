<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Get selected date (default yesterday / day-1)
$selected_date = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));

// Get date range for monthly calculation
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('-1 day'));

// Get month for target lookup
$selected_month = date('Y-m', strtotime($selected_date));

// 1. Get ALL stores (รวม store_code_new มาด้วย)
$stores_stmt = $db->prepare("SELECT store_code, store_code_new, store_name FROM stores ORDER BY store_name, store_code");
$stores_stmt->execute();
$all_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// ✨ สร้างตัวแปร Mapping: จับคู่รหัสใหม่ -> กลับไปหารหัสหลัก (Main Store Code)
// เพื่อให้ถ้ายอดขายเข้ามาเป็น Code ใหม่ ระบบจะรู้ว่าเป็นของร้านเดิม
$store_map = [];
$main_stores_list = []; // เก็บรายชื่อร้านค้าโดยใช้ store_code หลักเป็น Key

foreach ($all_stores as $store) {
    $main_code = $store['store_code'];
    $new_code = $store['store_code_new'];
    
    // บันทึกข้อมูลร้านค้าหลัก
    $main_stores_list[$main_code] = $store;
    
    // Map รหัสตัวเอง เข้าหาตัวเอง
    $store_map[$main_code] = $main_code;
    
    // ถ้ามีรหัสใหม่ (และไม่ใช่ Null/Empty) ให้ Map รหัสใหม่ เข้าหา รหัสหลัก
    if (!empty($new_code)) {
        $store_map[$new_code] = $main_code;
    }
}

// 2. Get daily sales summary
// ดึงยอดขายตามปกติ (ใน DB อาจจะมีทั้ง Code เก่า และ Code ใหม่ผสมกัน)
$daily_stmt = $db->prepare("
    SELECT 
        ds.store_code,
        SUM(ds.tax_incl_total) as daily_sales,
        COUNT(DISTINCT ds.id) as transaction_count,
        SUM(ds.qty) as total_qty
    FROM daily_sales ds
    WHERE ds.sale_date = ?
    GROUP BY ds.store_code
");
$daily_stmt->execute([$selected_date]);

// รวมยอดขายรายวัน เข้าสู่ร้านหลัก (Consolidate Daily Sales)
$daily_sales_data = [];
foreach ($daily_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $sale_code = $row['store_code'];
    
    // เช็คว่า code ที่ขายได้ map ไปร้านไหน (ถ้าไม่เจอใน map ให้ใช้ code เดิมไปเลย กัน error)
    $target_store = $store_map[$sale_code] ?? $sale_code;
    
    if (!isset($daily_sales_data[$target_store])) {
        $daily_sales_data[$target_store] = [
            'daily_sales' => 0,
            'transaction_count' => 0,
            'total_qty' => 0
        ];
    }
    
    // บวกยอดเพิ่มเข้าไป (กรณีช่วงเปลี่ยนถ่าย อาจมียอดทั้ง Code เก่าและใหม่ในวันเดียว)
    $daily_sales_data[$target_store]['daily_sales'] += $row['daily_sales'];
    $daily_sales_data[$target_store]['transaction_count'] += $row['transaction_count'];
    $daily_sales_data[$target_store]['total_qty'] += $row['total_qty'];
}

// 3. Get monthly sales summary
$monthly_stmt = $db->prepare("
    SELECT 
        ds.store_code,
        SUM(ds.tax_incl_total) as monthly_sales
    FROM daily_sales ds
    WHERE ds.sale_date BETWEEN ? AND ?
    GROUP BY ds.store_code
");
$monthly_stmt->execute([$date_from, $date_to]);

// รวมยอดขายรายเดือน เข้าสู่ร้านหลัก (Consolidate Monthly Sales)
$monthly_sales_data = [];
foreach ($monthly_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $sale_code = $row['store_code'];
    $target_store = $store_map[$sale_code] ?? $sale_code;
    
    if (!isset($monthly_sales_data[$target_store])) {
        $monthly_sales_data[$target_store] = ['monthly_sales' => 0];
    }
    $monthly_sales_data[$target_store]['monthly_sales'] += $row['monthly_sales'];
}

// 4. Get targets
// ดึง Target (Target มักจะผูกกับ Code ใด Code หนึ่ง แต่เราจะรวมด้วยกันพลาด)
$targets_stmt = $db->prepare("
    SELECT store_code, monthly_target, daily_target
    FROM sales_targets
    WHERE DATE_FORMAT(target_month, '%Y-%m') = ?
");
$targets_stmt->execute([$selected_month]);

$targets = [];
foreach ($targets_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $target_code = $row['store_code'];
    $mapped_store = $store_map[$target_code] ?? $target_code;
    
    if (!isset($targets[$mapped_store])) {
        $targets[$mapped_store] = ['monthly_target' => 0, 'daily_target' => 0];
    }
    $targets[$mapped_store]['monthly_target'] += $row['monthly_target'];
    $targets[$mapped_store]['daily_target'] += $row['daily_target'];
}

// 5. Combine all data
$summary = [];
foreach ($all_stores as $store) {
    $store_code = $store['store_code']; // This is the MAIN code
    
    $daily_data = $daily_sales_data[$store_code] ?? null;
    $monthly_data = $monthly_sales_data[$store_code] ?? null;
    $target = $targets[$store_code] ?? null;
    
    $daily_sale = $daily_data ? $daily_data['daily_sales'] : 0;
    $monthly_sale = $monthly_data ? $monthly_data['monthly_sales'] : 0;
    $monthly_target = $target ? $target['monthly_target'] : 0;
    $daily_target = $target ? $target['daily_target'] : 0;
    
    // ✨ เงื่อนไข: ซ่อนสาขาที่มียอดขายช่วงเดือนเป็น 0
    if ($monthly_sale <= 0) {
        continue; 
    }
    
    $summary[] = [
        'store_code' => $store_code,
        'store_name' => $store['store_name'],
        'daily_sales' => $daily_sale,
        'daily_target' => $daily_target,
        'daily_achievement' => $daily_target > 0 ? ($daily_sale / $daily_target * 100) : 0,
        'monthly_sales' => $monthly_sale,
        'monthly_target' => $monthly_target,
        'monthly_achievement' => $monthly_target > 0 ? ($monthly_sale / $monthly_target * 100) : 0,
        'transaction_count' => $daily_data ? $daily_data['transaction_count'] : 0,
    ];
}

// Calculate totals
$total_daily = array_sum(array_column($summary, 'daily_sales'));
$total_daily_target = array_sum(array_column($summary, 'daily_target'));
$total_monthly = array_sum(array_column($summary, 'monthly_sales'));
$total_monthly_target = array_sum(array_column($summary, 'monthly_target'));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Sales Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
        
        /* ========================================
           Enhanced Header
           ======================================== */
        .header {
            background: rgba(2, 136, 209, 0.95);
            backdrop-filter: blur(15px);
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* ========================================
           Date Controls
           ======================================== */
        .date-controls {
            display: flex;
            gap: 18px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .date-input-group label {
            color: white;
            font-size: 13px;
            font-weight: 700;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        .date-input-group input {
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            font-family: 'Sarabun', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .date-input-group input:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.3);
        }
        
        .btn-search {
            padding: 12px 28px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-search:hover {
            background: #f0f0f0;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* ========================================
           Navigation Menu
           ======================================== */
        .nav-menu {
            background: white;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
        
        /* ========================================
           Container
           ======================================== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
        }
        
        /* ========================================
           Stats Cards
           ======================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #0288d1 0%, #0097a7 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #0288d1 0%, #0097a7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .stat-unit {
            font-size: 16px;
            color: #999;
            font-weight: 500;
        }
        
        /* ========================================
           Table Card
           ======================================== */
        .table-card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 25px;
        }
        
        .table-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 25px;
            color: #2c3e50;
            padding-bottom: 15px;
            border-bottom: 3px solid #ecf0f1;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid #f0f0f0;
        }
        
        th {
            background: linear-gradient(135deg, #00588bff 0%, #00588bff 100%);
            color: white;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        td {
            font-size: 14px;
        }
        
        .number {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, #e1f5fe 0%, #ffffff 100%);
            transform: scale(1.01);
        }
        
        .total-row {
            background: linear-gradient(135deg, #8fd7fdff 0%, #00a8bbff 100%);
            color: black;
            font-weight: 800;
        }
        
        .total-row td {
            border-bottom: none;
        }
        
        .store-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .store-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .store-code {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
        }
        
        /* ========================================
           Progress Bar
           ======================================== */
        .progress-bar {
            background: #e0e0e0;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 5px;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0288d1, #0097a7);
            transition: width 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            font-weight: 600;
        }
        
        /* Achievement Colors */
        .achievement-high { color: #27ae60; font-weight: 700; }
        .achievement-medium { color: #f39c12; font-weight: 700; }
        .achievement-low { color: #e74c3c; font-weight: 700; }
        
        /* ========================================
           Responsive Design
           ======================================== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-title {
                font-size: 24px;
            }
            
            .date-controls {
                width: 100%;
            }
            
            .date-input-group {
                flex: 1;
                min-width: 120px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-card {
                padding: 20px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 12px;
            }
        }
        
        /* ========================================
           Animation
           ======================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card,
        .table-card {
            animation: fadeIn 0.6s ease-out;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📊</span>
                Sales Dashboard
            </div>
            
            <form method="GET" class="date-controls">
                <div class="date-input-group">
                    <label>วันนี้ (สำหรับยอดรายวัน)</label>
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
                <button type="submit" class="btn-search">
                    🔍 ค้นหา
                </button>
            </form>
        </div>
    </div>
    
    <div class="nav-menu">
        <div class="nav-content">
            <a href="../dashboard.php" class="nav-btn">🏠 กลับหน้าหลัก</a>
            <a href="manage_targets.php" class="nav-btn">🎯 จัดการเป้าหมาย</a>
            <a href="compare_weeks.php" class="nav-btn">📈 เทียบยอดสัปดาห์</a>
            <a href="compare_period_report.php" class="nav-btn">📈 เทียบยอดหลายตัวเลือก</a>
            <a href="multi_filter_report.php" class="nav-btn">📈 รายงานแบบเลือกเอง</a>
            <a href="detailed_report.php" class="nav-btn">📋 รายงานแยกสาขา</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">ยอดขายวันที่ <?= date('d/m/Y', strtotime($selected_date)) ?></div>
                <div class="stat-value"><?= number_format($total_daily, 0) ?></div>
                <div class="stat-unit">บาท</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $total_daily_target > 0 ? min(100, ($total_daily / $total_daily_target) * 100) : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">เป้าหมายวันที่ <?= date('d/m/Y', strtotime($selected_date)) ?></div>
                <div class="stat-value"><?= number_format($total_daily_target, 0) ?></div>
                <div class="stat-unit">บาท 
                    <?php 
                    $daily_pct = $total_daily_target > 0 ? ($total_daily / $total_daily_target * 100) : 0;
                    $class = $daily_pct >= 100 ? 'achievement-high' : ($daily_pct >= 80 ? 'achievement-medium' : 'achievement-low');
                    ?>
                    <span class="<?= $class ?>">(<?= number_format($daily_pct, 1) ?>%)</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">ยอดขายช่วง <?= date('d/m', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?></div>
                <div class="stat-value"><?= number_format($total_monthly, 0) ?></div>
                <div class="stat-unit">บาท</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $total_monthly_target > 0 ? min(100, ($total_monthly / $total_monthly_target) * 100) : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">เป้าหมายเดือน <?= date('m/Y', strtotime($selected_date)) ?></div>
                <div class="stat-value"><?= number_format($total_monthly_target, 0) ?></div>
                <div class="stat-unit">บาท
                    <?php 
                    $monthly_pct = $total_monthly_target > 0 ? ($total_monthly / $total_monthly_target * 100) : 0;
                    $class = $monthly_pct >= 100 ? 'achievement-high' : ($monthly_pct >= 80 ? 'achievement-medium' : 'achievement-low');
                    ?>
                    <span class="<?= $class ?>">(<?= number_format($monthly_pct, 1) ?>%)</span>
                </div>
            </div>
        </div>
        
        <div class="table-card">
            <h2 class="table-title">สรุปยอดขายแยกสาขา - <?= date('d/m/Y', strtotime($date_from)) ?> ถึง <?= date('d/m/Y', strtotime($date_to)) ?></h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>สาขา</th>
                            <th class="number">ยอดขาย<br><?= date('d/m/y', strtotime($selected_date)) ?></th>
                            <th class="number">เป้าวัน</th>
                            <th class="number">% บรรลุ</th>
                            <th class="number">ยอดขายช่วงเดือน</th>
                            <th class="number">เป้าเดือน</th>
                            <th class="number">% บรรลุ</th>
                            <th style="min-width: 150px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($summary as $row):
                            $daily_class = $row['daily_achievement'] >= 100 ? 'achievement-high' : 
                                          ($row['daily_achievement'] >= 80 ? 'achievement-medium' : 'achievement-low');
                            $monthly_class = $row['monthly_achievement'] >= 100 ? 'achievement-high' : 
                                            ($row['monthly_achievement'] >= 80 ? 'achievement-medium' : 'achievement-low');
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
                            <td class="number"><?= number_format($row['daily_target'], 0) ?></td>
                            <td class="number <?= $daily_class ?>"><?= number_format($row['daily_achievement'], 1) ?>%</td>
                            <td class="number"><?= number_format($row['monthly_sales'], 0) ?></td>
                            <td class="number"><?= number_format($row['monthly_target'], 0) ?></td>
                            <td class="number <?= $monthly_class ?>"><?= number_format($row['monthly_achievement'], 1) ?>%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min(100, $row['monthly_achievement']) ?>%"></div>
                                </div>
                                <div class="progress-text"><?= number_format($row['monthly_achievement'], 1) ?>%</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr class="total-row">
                            <td colspan="2">รวมทั้งหมด</td>
                            <td class="number"><?= number_format($total_daily, 0) ?></td>
                            <td class="number"><?= number_format($total_daily_target, 0) ?></td>
                            <td class="number">
                                <?php 
                                $total_daily_pct = $total_daily_target > 0 ? ($total_daily / $total_daily_target * 100) : 0;
                                echo number_format($total_daily_pct, 1);
                                ?>%
                            </td>
                            <td class="number"><?= number_format($total_monthly, 0) ?></td>
                            <td class="number"><?= number_format($total_monthly_target, 0) ?></td>
                            <td class="number">
                                <?php 
                                $total_monthly_pct = $total_monthly_target > 0 ? ($total_monthly / $total_monthly_target * 100) : 0;
                                echo number_format($total_monthly_pct, 1);
                                ?>%
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min(100, $total_monthly_pct) ?>%"></div>
                                </div>
                                <div class="progress-text"><?= number_format($total_monthly_pct, 1) ?>%</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>