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
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Handle brands filter
$selected_brands = array();
if (isset($_GET['brands']) && is_array($_GET['brands'])) {
    $selected_brands = $_GET['brands'];
}

// Handle stores filter
$selected_stores = array();
if (isset($_GET['stores']) && is_array($_GET['stores'])) {
    $selected_stores = $_GET['stores'];
}

// Get all brands for filter
try {
    $brands_stmt = $db->query("
        SELECT DISTINCT brand_name 
        FROM sales_documents 
        WHERE brand_name IS NOT NULL 
            AND brand_name != ''
        ORDER BY brand_name
    ");
    $all_brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Error fetching brands: " . $e->getMessage());
}

// Get all stores for filter
try {
    $stores_stmt = $db->query("
        SELECT store_code, store_name 
        FROM stores 
        WHERE is_active = 1
        ORDER BY store_code
    ");
    $all_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching stores: " . $e->getMessage());
}

// Build WHERE conditions
$conditions = array();
$params = array();

$conditions[] = "date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

// Add brand filter
if (count($selected_brands) > 0) {
    $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
    $conditions[] = "brand_name IN ($brand_placeholders)";
    foreach ($selected_brands as $brand) {
        $params[] = $brand;
    }
}

// Add store filter
if (count($selected_stores) > 0) {
    $store_placeholders = implode(',', array_fill(0, count($selected_stores), '?'));
    $conditions[] = "store_code IN ($store_placeholders)";
    foreach ($selected_stores as $store) {
        $params[] = $store;
    }
}

$where_clause = implode(' AND ', $conditions);

// Get overall summary
try {
    // Count positive bills (net sales > 0)
    $positive_bills_sql = "
        SELECT COUNT(DISTINCT internal_reference_document) as bill_count
        FROM (
            SELECT internal_reference_document
            FROM sales_documents
            WHERE $where_clause
                AND internal_reference_document IS NOT NULL
                AND internal_reference_document != ''
            GROUP BY internal_reference_document
            HAVING SUM(tax_incl_total) > 0
        ) as positive_bills
    ";
    
    $positive_stmt = $db->prepare($positive_bills_sql);
    $positive_stmt->execute($params);
    $positive_result = $positive_stmt->fetch(PDO::FETCH_ASSOC);
    $positive_bills_count = $positive_result['bill_count'] ?? 0;
    
    // Count negative bills (net sales < 0)
    $negative_bills_sql = "
        SELECT COUNT(DISTINCT internal_reference_document) as bill_count
        FROM (
            SELECT internal_reference_document
            FROM sales_documents
            WHERE $where_clause
                AND internal_reference_document IS NOT NULL
                AND internal_reference_document != ''
            GROUP BY internal_reference_document
            HAVING SUM(tax_incl_total) < 0
        ) as negative_bills
    ";
    
    $negative_stmt = $db->prepare($negative_bills_sql);
    $negative_stmt->execute($params);
    $negative_result = $negative_stmt->fetch(PDO::FETCH_ASSOC);
    $negative_bills_count = $negative_result['bill_count'] ?? 0;
    
    // Calculate total bills: positive - negative
    $total_bills_count = $positive_bills_count - $negative_bills_count;
    
    // Then get totals for all bills (sales and qty)
    $summary_sql = "
        SELECT 
            COALESCE(SUM(tax_incl_total), 0) as total_sales,
            COALESCE(SUM(qty), 0) as total_qty,
            COUNT(DISTINCT store_code) as store_count,
            COUNT(DISTINCT CASE WHEN brand_name IS NOT NULL AND brand_name != '' THEN brand_name END) as brand_count
        FROM sales_documents
        WHERE $where_clause
            AND internal_reference_document IS NOT NULL
            AND internal_reference_document != ''
    ";
    
    $summary_stmt = $db->prepare($summary_sql);
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$summary) {
        $summary = array(
            'total_bills' => 0,
            'total_sales' => 0,
            'total_qty' => 0,
            'store_count' => 0,
            'brand_count' => 0
        );
    }
    
    // Set the calculated total bills (positive - negative)
    $summary['total_bills'] = $total_bills_count;
    
    // Convert null values to 0
    $summary['total_sales'] = $summary['total_sales'] ?? 0;
    $summary['total_qty'] = $summary['total_qty'] ?? 0;
    $summary['store_count'] = $summary['store_count'] ?? 0;
    $summary['brand_count'] = $summary['brand_count'] ?? 0;
} catch (Exception $e) {
    die("Error fetching summary: " . $e->getMessage());
}

// Calculate averages
$avg_per_bill = 0;
$avg_qty_per_bill = 0;
if ($summary['total_bills'] > 0) {
    $avg_per_bill = $summary['total_sales'] / $summary['total_bills'];
    $avg_qty_per_bill = $summary['total_qty'] / $summary['total_bills'];
}

// Get sales by brand
$brand_data = array();
try {
    $brand_sql = "
        SELECT 
            brand_name as brand,
            COALESCE(SUM(tax_incl_total), 0) as sales,
            COALESCE(SUM(qty), 0) as qty,
            COUNT(DISTINCT store_code) as stores
        FROM sales_documents
        WHERE $where_clause
            AND internal_reference_document IS NOT NULL
            AND internal_reference_document != ''
            AND brand_name IS NOT NULL
            AND brand_name != ''
        GROUP BY brand_name
        ORDER BY sales DESC
    ";
    
    $brand_stmt = $db->prepare($brand_sql);
    $brand_stmt->execute($params);
    $brand_data = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count bills (positive - negative) for each brand
    foreach ($brand_data as &$brand_row) {
        $brand_bills_conditions = $conditions;
        $brand_bills_params = $params;
        
        // Add brand condition
        $brand_bills_conditions[] = "brand_name = ?";
        array_push($brand_bills_params, $brand_row['brand']);
        
        $brand_bills_where = implode(' AND ', $brand_bills_conditions);
        
        // Count positive bills
        $brand_positive_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $brand_bills_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) > 0
            ) as positive_bills
        ";
        
        $brand_positive_stmt = $db->prepare($brand_positive_sql);
        $brand_positive_stmt->execute($brand_bills_params);
        $brand_positive_result = $brand_positive_stmt->fetch(PDO::FETCH_ASSOC);
        $brand_positive_count = $brand_positive_result['bill_count'] ?? 0;
        
        // Count negative bills
        $brand_negative_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $brand_bills_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) < 0
            ) as negative_bills
        ";
        
        $brand_negative_stmt = $db->prepare($brand_negative_sql);
        $brand_negative_stmt->execute($brand_bills_params);
        $brand_negative_result = $brand_negative_stmt->fetch(PDO::FETCH_ASSOC);
        $brand_negative_count = $brand_negative_result['bill_count'] ?? 0;
        
        // Calculate total: positive - negative
        $brand_row['bills'] = $brand_positive_count - $brand_negative_count;
    }
    unset($brand_row);
    
    $brand_stmt = $db->prepare($brand_sql);
    $brand_stmt->execute($params);
    $brand_data = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching brand data: " . $e->getMessage());
}

// Get sales by store
$store_data = array();
try {
    // Build WHERE clause with table prefix for store query
    $store_conditions = array();
    $store_params = array();
    
    $store_conditions[] = "sd.date BETWEEN ? AND ?";
    $store_params[] = $date_from;
    $store_params[] = $date_to;
    
    // Add brand filter
    if (count($selected_brands) > 0) {
        $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
        $store_conditions[] = "sd.brand_name IN ($brand_placeholders)";
        foreach ($selected_brands as $brand) {
            $store_params[] = $brand;
        }
    }
    
    // Add store filter with table prefix
    if (count($selected_stores) > 0) {
        $store_placeholders = implode(',', array_fill(0, count($selected_stores), '?'));
        $store_conditions[] = "sd.store_code IN ($store_placeholders)";
        foreach ($selected_stores as $store) {
            $store_params[] = $store;
        }
    }
    
    $store_where_clause = implode(' AND ', $store_conditions);
    
    $store_sql = "
        SELECT 
            sd.store_code,
            COALESCE(s.store_name, sd.store_code) as store_name,
            COALESCE(SUM(sd.tax_incl_total), 0) as sales,
            COALESCE(SUM(sd.qty), 0) as qty,
            COUNT(DISTINCT CASE WHEN sd.brand_name IS NOT NULL AND sd.brand_name != '' THEN sd.brand_name END) as brands
        FROM sales_documents sd
        LEFT JOIN stores s ON sd.store_code COLLATE utf8mb4_unicode_ci = s.store_code COLLATE utf8mb4_unicode_ci
        WHERE $store_where_clause
            AND sd.internal_reference_document IS NOT NULL
            AND sd.internal_reference_document != ''
        GROUP BY sd.store_code, s.store_name
        ORDER BY sales DESC
    ";
    
    $store_stmt = $db->prepare($store_sql);
    $store_stmt->execute($store_params);
    $store_data = $store_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count bills (positive - negative) for each store
    foreach ($store_data as &$store_row) {
        $current_store = $store_row['store_code'];
        
        // Build WHERE for this specific store (without sd. prefix)
        $store_specific_conditions = array();
        $store_specific_params = array();
        
        $store_specific_conditions[] = "store_code = ?";
        $store_specific_params[] = $current_store;
        
        $store_specific_conditions[] = "date BETWEEN ? AND ?";
        $store_specific_params[] = $date_from;
        $store_specific_params[] = $date_to;
        
        // Add brand filter if exists
        if (count($selected_brands) > 0) {
            $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
            $store_specific_conditions[] = "brand_name IN ($brand_placeholders)";
            foreach ($selected_brands as $brand) {
                $store_specific_params[] = $brand;
            }
        }
        
        $store_bill_where = implode(' AND ', $store_specific_conditions);
        
        // Count positive bills
        $store_positive_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $store_bill_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) > 0
            ) as positive_bills
        ";
        
        $store_positive_stmt = $db->prepare($store_positive_sql);
        $store_positive_stmt->execute($store_specific_params);
        $store_positive_result = $store_positive_stmt->fetch(PDO::FETCH_ASSOC);
        $store_positive_count = $store_positive_result['bill_count'] ?? 0;
        
        // Count negative bills
        $store_negative_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $store_bill_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) < 0
            ) as negative_bills
        ";
        
        $store_negative_stmt = $db->prepare($store_negative_sql);
        $store_negative_stmt->execute($store_specific_params);
        $store_negative_result = $store_negative_stmt->fetch(PDO::FETCH_ASSOC);
        $store_negative_count = $store_negative_result['bill_count'] ?? 0;
        
        // Calculate total: positive - negative
        $store_row['bills'] = $store_positive_count - $store_negative_count;
    }
    unset($store_row);
} catch (Exception $e) {
    die("Error fetching store data: " . $e->getMessage());
}

// Get daily trend
$daily_trend = array();
try {
    $daily_sql = "
        SELECT 
            date as sale_date,
            COALESCE(SUM(tax_incl_total), 0) as sales,
            COALESCE(SUM(qty), 0) as qty
        FROM sales_documents
        WHERE $where_clause
            AND internal_reference_document IS NOT NULL
            AND internal_reference_document != ''
        GROUP BY date
        ORDER BY date
    ";
    
    $daily_stmt = $db->prepare($daily_sql);
    $daily_stmt->execute($params);
    $daily_trend = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count bills (positive - negative) for each day
    foreach ($daily_trend as &$daily_row) {
        $current_date = $daily_row['sale_date'];
        
        // Build conditions for this date
        $daily_bill_conditions = array_filter($conditions, function($cond) {
            return strpos($cond, 'BETWEEN') === false;
        });
        $daily_bill_conditions[] = "date = ?";
        $daily_bill_where = implode(' AND ', $daily_bill_conditions);
        
        // Build params (remove date range params, add specific date)
        $daily_bill_params = array_values(array_slice($params, 2)); // Skip first 2 (date range)
        array_unshift($daily_bill_params, $current_date);
        
        // Count positive bills
        $daily_positive_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $daily_bill_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) > 0
            ) as positive_bills
        ";
        
        $daily_positive_stmt = $db->prepare($daily_positive_sql);
        $daily_positive_stmt->execute($daily_bill_params);
        $daily_positive_result = $daily_positive_stmt->fetch(PDO::FETCH_ASSOC);
        $daily_positive_count = $daily_positive_result['bill_count'] ?? 0;
        
        // Count negative bills
        $daily_negative_sql = "
            SELECT COUNT(DISTINCT internal_reference_document) as bill_count
            FROM (
                SELECT internal_reference_document
                FROM sales_documents
                WHERE $daily_bill_where
                    AND internal_reference_document IS NOT NULL
                    AND internal_reference_document != ''
                GROUP BY internal_reference_document
                HAVING SUM(tax_incl_total) < 0
            ) as negative_bills
        ";
        
        $daily_negative_stmt = $db->prepare($daily_negative_sql);
        $daily_negative_stmt->execute($daily_bill_params);
        $daily_negative_result = $daily_negative_stmt->fetch(PDO::FETCH_ASSOC);
        $daily_negative_count = $daily_negative_result['bill_count'] ?? 0;
        
        // Calculate total: positive - negative
        $daily_row['bills'] = $daily_positive_count - $daily_negative_count;
    }
    unset($daily_row);
    
    $daily_stmt = $db->prepare($daily_sql);
    $daily_stmt->execute($params);
    $daily_trend = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching daily trend: " . $e->getMessage());
}

// Functions formatNumber() and formatDate() are already defined in config.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานยอดขายแบบกรองหลายเงื่อนไข - Sales Documents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Prompt', 'Sarabun', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .icon {
            font-size: 28px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .filter-label {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        input[type="date"] {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-preset {
            padding: 8px 16px;
            font-size: 14px;
            background: #17a2b8;
            color: white;
        }
        
        .btn-preset:hover {
            background: #138496;
        }
        
        .btn-clear {
            padding: 8px 16px;
            font-size: 14px;
            background: #dc3545;
            color: white;
        }
        
        .btn-clear:hover {
            background: #c82333;
        }
        
        .preset-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 12px;
            color: white;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-sub {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        th.number {
            text-align: right;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        td.number {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 รายงานยอดขายแบบกรองหลายเงื่อนไข</h1>
            <p>Sales Documents Database</p>
        </div>
        
        <form method="GET" class="card">
            <h2><span class="icon">🔍</span> เงื่อนไขการค้นหา</h2>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">📅 วันที่เริ่มต้น</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">📅 วันที่สิ้นสุด</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">🏷️ เลือก Brand</label>
                    <div class="checkbox-group">
                        <?php foreach ($all_brands as $brand): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" 
                                       name="brands[]" 
                                       value="<?php echo htmlspecialchars($brand); ?>" 
                                       id="brand_<?php echo htmlspecialchars($brand); ?>" 
                                       <?php echo in_array($brand, $selected_brands) ? 'checked' : ''; ?>>
                                <label for="brand_<?php echo htmlspecialchars($brand); ?>">
                                    <?php echo htmlspecialchars($brand); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">🏪 เลือกสาขา</label>
                    <div class="preset-buttons">
                        <button type="button" class="btn btn-preset" onclick="selectStores(['001', '002', '003', '004', '005', '006', '007', '008', '009', '010', '011', '012', '013', '014', '015', '016', '017', '018', '019', '020', '021', '022', '023', '024', '025', '026', '027', '028', '029', '030', '031', '032', '033', '034', '035', '036', '037', '038', '039', '040', '041', '042', '043', '044', '045', '046', '048', '050'])">
                            ทุกสาขา (ยกเว้น Central, OnlineTH)
                        </button>
                        <button type="button" class="btn btn-preset" onclick="selectStores(['001', '002', '003', '004', '006', '007', '008', '009', '010', '012', '013', '014', '015', '016', '017', '018', '019', '020', '021', '022', '023', '024', '026', '027', '028', '029', '030', '031', '032', '033', '034', '036', '037', '038', '039', '040', '041', '042', '044', '045', '046', '048', '050'])">
                            ทุกสาขา (ยกเว้น Central, OnlineTH, 005, 011, 025, 035, 043)
                        </button>
                        <button type="button" class="btn btn-clear" onclick="clearStores()">
                            ล้างทั้งหมด
                        </button>
                    </div>
                    <div class="checkbox-group">
                        <?php foreach ($all_stores as $store): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" 
                                       name="stores[]" 
                                       value="<?php echo htmlspecialchars($store['store_code']); ?>" 
                                       id="store_<?php echo htmlspecialchars($store['store_code']); ?>" 
                                       <?php echo in_array($store['store_code'], $selected_stores) ? 'checked' : ''; ?>>
                                <label for="store_<?php echo htmlspecialchars($store['store_code']); ?>">
                                    <?php echo htmlspecialchars($store['store_code'] . ' - ' . $store['store_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                <a href="<?php echo basename(__FILE__); ?>" class="btn btn-secondary">🔄 รีเซ็ต</a>
            </div>
        </form>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-label">ยอดขายรวม</div>
                <div class="stat-value"><?php echo formatNumber($summary['total_sales'] ?? 0, 2); ?></div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🧾</div>
                <div class="stat-label">จำนวนบิล</div>
                <div class="stat-value"><?php echo formatNumber($summary['total_bills'] ?? 0,0); ?></div>
                <div class="stat-sub">บิล</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">จำนวนชิ้น</div>
                <div class="stat-value"><?php echo formatNumber($summary['total_qty'] ?? 0,0); ?></div>
                <div class="stat-sub">ชิ้น</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💵</div>
                <div class="stat-label">ยอดเฉลี่ย/บิล</div>
                <div class="stat-value"><?php echo formatNumber($avg_per_bill ?? 0, 2); ?></div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-label">ชิ้นเฉลี่ย/บิล</div>
                <div class="stat-value"><?php echo formatNumber($avg_qty_per_bill ?? 0, 2); ?></div>
                <div class="stat-sub">ชิ้น</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏪</div>
                <div class="stat-label">จำนวนสาขา</div>
                <div class="stat-value"><?php echo formatNumber($summary['store_count'] ?? 0, 0); ?></div>
                <div class="stat-sub">สาขา</div>
            </div>
        </div>
 
        <!-- Sales by Store -->
        <?php if (count($store_data) > 0): ?>
        <div class="card">
            <h2><span class="icon">🏪</span> ยอดขายแยกตามสาขา</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสสาขา</th>
                            <th>ชื่อสาขา</th>
                            <th class="number">จำนวนบิล</th>
                            <th class="number">ยอดขาย</th>
                            <th class="number">จำนวนชิ้น</th>
                            <th class="number">เฉลี่ย/บิล</th>
                            <th class="number">ชิ้น/บิล</th>
                            <th class="number">Brands</th>
                            <th>% ของยอดรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($store_data as $row): 
                            $pct = ($summary['total_sales'] ?? 0) > 0 ? (($row['sales'] ?? 0) / $summary['total_sales'] * 100) : 0;
                            $avg_bill = ($row['bills'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['bills'] : 0;
                            $avg_qty = ($row['bills'] ?? 0) > 0 ? ($row['qty'] ?? 0) / $row['bills'] : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['store_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_bill, 2); ?></td>
                                <td class="number"><?php echo formatNumber($avg_qty, 2); ?></td>
                                <td class="number"><?php echo formatNumber($row['brands'] ?? 0); ?></td>
                                <td>
                                    <div><?php echo number_format($pct, 1); ?>%</div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min($pct, 100); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sales by Brand -->
        <?php if (count($brand_data) > 0): ?>
        <div class="card">
            <h2><span class="icon">🏷️</span> ยอดขายแยกตาม Brand</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th class="number">จำนวนบิล</th>
                            <th class="number">ยอดขาย</th>
                            <th class="number">จำนวนชิ้น</th>
                            <th class="number">เฉลี่ย/บิล</th>
                            <th class="number">ชิ้น/บิล</th>
                            <th class="number">สาขา</th>
                            <th>% ของยอดรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brand_data as $row): 
                            $pct = ($summary['total_sales'] ?? 0) > 0 ? (($row['sales'] ?? 0) / $summary['total_sales'] * 100) : 0;
                            $avg_bill = ($row['bills'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['bills'] : 0;
                            $avg_qty = ($row['bills'] ?? 0) > 0 ? ($row['qty'] ?? 0) / $row['bills'] : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['brand']); ?></strong></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_bill, 2); ?></td>
                                <td class="number"><?php echo formatNumber($avg_qty, 2); ?></td>
                                <td class="number"><?php echo formatNumber($row['stores'] ?? 0); ?></td>
                                <td>
                                    <div><?php echo number_format($pct, 1); ?>%</div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min($pct, 100); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Daily Trend -->
        <?php if (count($daily_trend) > 0): ?>
        <div class="card">
            <h2><span class="icon">📈</span> แนวโน้มรายวัน</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th class="number">จำนวนบิล</th>
                            <th class="number">ยอดขาย</th>
                            <th class="number">จำนวนชิ้น</th>
                            <th class="number">เฉลี่ย/บิล</th>
                            <th class="number">ชิ้น/บิล</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_trend as $row): 
                            $avg_bill = ($row['bills'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['bills'] : 0;
                            $avg_qty = ($row['bills'] ?? 0) > 0 ? ($row['qty'] ?? 0) / $row['bills'] : 0;
                        ?>
                            <tr>
                                <td><?php echo formatDate($row['sale_date']); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_bill, 2); ?></td>
                                <td class="number"><?php echo formatNumber($avg_qty, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function selectStores(storeCodes) {
            // Uncheck all store checkboxes first
            document.querySelectorAll('input[name="stores[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Check only the specified stores
            storeCodes.forEach(code => {
                const checkbox = document.getElementById('store_' + code);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
        
        function clearStores() {
            // Uncheck all store checkboxes
            document.querySelectorAll('input[name="stores[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    </script>
</body>
</html>