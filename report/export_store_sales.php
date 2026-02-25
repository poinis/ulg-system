<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    die('Unauthorized access');
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

// Handle class_name filter
$selected_classes = array();
if (isset($_GET['classes']) && is_array($_GET['classes'])) {
    $selected_classes = $_GET['classes'];
}

// Handle stores filter
$selected_stores = array();
if (isset($_GET['stores']) && is_array($_GET['stores'])) {
    $selected_stores = $_GET['stores'];
}

// Handle customer type filter
$selected_customer_types = array();
if (isset($_GET['customer_types']) && is_array($_GET['customer_types'])) {
    $selected_customer_types = $_GET['customer_types'];
}

// Get sales by store
$store_data = array();
try {
    // Build WHERE clause with table prefix for store query
    $store_conditions = array();
    $store_params = array();
    
    $store_conditions[] = "ds.sale_date BETWEEN ? AND ?";
    $store_params[] = $date_from;
    $store_params[] = $date_to;
    
    // Exclude SKATEBOARD group and GWP
    $store_conditions[] = "(ds.group_name != 'SKATEBOARD' OR ds.group_name IS NULL)";
    $store_conditions[] = "(ds.class_name != 'GWP' OR ds.class_name IS NULL)";
    
    // Add brand filter
    if (count($selected_brands) > 0) {
        $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
        $store_conditions[] = "ds.brand IN ($brand_placeholders)";
        foreach ($selected_brands as $brand) {
            $store_params[] = $brand;
        }
    }
    
    // Add store filter with table prefix
    if (count($selected_stores) > 0) {
        $store_placeholders = implode(',', array_fill(0, count($selected_stores), '?'));
        $store_conditions[] = "ds.store_code IN ($store_placeholders)";
        foreach ($selected_stores as $store) {
            $store_params[] = $store;
        }
    }
    
    // Add class filter
    if (count($selected_classes) > 0) {
        $class_placeholders = implode(',', array_fill(0, count($selected_classes), '?'));
        $store_conditions[] = "ds.class_name IN ($class_placeholders)";
        foreach ($selected_classes as $class) {
            $store_params[] = $class;
        }
    }
    
    // Add customer type filter
    if (count($selected_customer_types) > 0) {
        $customer_type_conditions = array();
        foreach ($selected_customer_types as $type) {
            if ($type === 'MEMBER') {
                $customer_type_conditions[] = "ds.customer LIKE '99%'";
            } elseif ($type === 'WALKIN') {
                $customer_type_conditions[] = "ds.customer LIKE 'WI%TH'";
            } elseif ($type === 'FOREIGNER') {
                $customer_type_conditions[] = "(ds.customer LIKE 'WI%' AND ds.customer NOT LIKE 'WI%TH')";
            }
        }
        if (count($customer_type_conditions) > 0) {
            $store_conditions[] = "(" . implode(' OR ', $customer_type_conditions) . ")";
        }
    }
    
    $store_where_clause = implode(' AND ', $store_conditions);
    
    $store_sql = "
        SELECT 
            ds.store_code,
            s.store_name,
            SUM(ds.tax_incl_total) as sales
        FROM daily_sales ds
        LEFT JOIN stores s ON ds.store_code = s.store_code
        WHERE $store_where_clause
            AND ds.internal_ref IS NOT NULL
            AND ds.internal_ref != ''
        GROUP BY ds.store_code, s.store_name
        ORDER BY sales DESC
    ";
    
    $store_stmt = $db->prepare($store_sql);
    $store_stmt->execute($store_params);
    $store_data = $store_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count bills (positive - negative) for each store
    foreach ($store_data as &$store_row) {
        $current_store = $store_row['store_code'];
        
        // Build WHERE for this specific store
        $store_bill_conditions = array();
        $store_bill_params = array();
        
        $store_bill_conditions[] = "store_code = ?";
        $store_bill_params[] = $current_store;
        
        $store_bill_conditions[] = "sale_date BETWEEN ? AND ?";
        $store_bill_params[] = $date_from;
        $store_bill_params[] = $date_to;
        
        // Exclude SKATEBOARD and GWP
        $store_bill_conditions[] = "(group_name != 'SKATEBOARD' OR group_name IS NULL) AND (class_name != 'GWP' OR class_name IS NULL)";
        
        // Add brand filter if exists
        if (count($selected_brands) > 0) {
            $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
            $store_bill_conditions[] = "brand IN ($brand_placeholders)";
            foreach ($selected_brands as $brand) {
                $store_bill_params[] = $brand;
            }
        }
        
        // Add class filter if exists
        if (count($selected_classes) > 0) {
            $class_placeholders = implode(',', array_fill(0, count($selected_classes), '?'));
            $store_bill_conditions[] = "class_name IN ($class_placeholders)";
            foreach ($selected_classes as $class) {
                $store_bill_params[] = $class;
            }
        }
        
        // Add customer type filter
        if (count($selected_customer_types) > 0) {
            $customer_type_conditions = array();
            foreach ($selected_customer_types as $type) {
                if ($type === 'MEMBER') {
                    $customer_type_conditions[] = "customer LIKE '99%'";
                } elseif ($type === 'WALKIN') {
                    $customer_type_conditions[] = "customer LIKE 'WI%TH'";
                } elseif ($type === 'FOREIGNER') {
                    $customer_type_conditions[] = "(customer LIKE 'WI%' AND customer NOT LIKE 'WI%TH')";
                }
            }
            if (count($customer_type_conditions) > 0) {
                $store_bill_conditions[] = "(" . implode(' OR ', $customer_type_conditions) . ")";
            }
        }
        
        $store_bill_where = implode(' AND ', $store_bill_conditions);
        
        // Count positive bills
        $store_positive_sql = "
            SELECT COUNT(DISTINCT internal_ref) as bill_count
            FROM (
                SELECT internal_ref
                FROM daily_sales
                WHERE $store_bill_where
                    AND internal_ref IS NOT NULL
                    AND internal_ref != ''
                GROUP BY internal_ref
                HAVING SUM(tax_incl_total) > 0
            ) as positive_bills
        ";
        
        $store_positive_stmt = $db->prepare($store_positive_sql);
        $store_positive_stmt->execute($store_bill_params);
        $store_positive_result = $store_positive_stmt->fetch(PDO::FETCH_ASSOC);
        $store_positive_count = $store_positive_result['bill_count'] ?? 0;
        
        // Count negative bills
        $store_negative_sql = "
            SELECT COUNT(DISTINCT internal_ref) as bill_count
            FROM (
                SELECT internal_ref
                FROM daily_sales
                WHERE $store_bill_where
                    AND internal_ref IS NOT NULL
                    AND internal_ref != ''
                GROUP BY internal_ref
                HAVING SUM(tax_incl_total) < 0
            ) as negative_bills
        ";
        
        $store_negative_stmt = $db->prepare($store_negative_sql);
        $store_negative_stmt->execute($store_bill_params);
        $store_negative_result = $store_negative_stmt->fetch(PDO::FETCH_ASSOC);
        $store_negative_count = $store_negative_result['bill_count'] ?? 0;
        
        // Calculate total: positive - negative
        $store_row['bills'] = $store_positive_count - $store_negative_count;
        
        // Get qty (include both positive and negative for net calculation)
        $store_qty_sql = "
            SELECT SUM(qty) as total_qty
            FROM daily_sales
            WHERE $store_bill_where
        ";
        
        $store_qty_stmt = $db->prepare($store_qty_sql);
        $store_qty_stmt->execute($store_bill_params);
        $store_qty_result = $store_qty_stmt->fetch(PDO::FETCH_ASSOC);
        $store_row['qty'] = $store_qty_result['total_qty'] ?? 0;
    }
    unset($store_row);
    
} catch (Exception $e) {
    die("Error fetching store data: " . $e->getMessage());
}

// Calculate Run Rate parameters
try {
    $date_from_obj = new DateTime($date_from);
    $date_to_obj = new DateTime($date_to);
    $days_mtd = $date_to_obj->diff($date_from_obj)->days + 1;
    $days_in_month = $date_from_obj->format('t');
} catch (Exception $e) {
    $days_mtd = 1;
    $days_in_month = 30;
}

// Calculate totals
$total_qty = 0;
$total_sales = 0;
$total_bills = 0;

foreach ($store_data as $row) {
    $total_qty += $row['qty'] ?? 0;
    $total_sales += $row['sales'] ?? 0;
    $total_bills += $row['bills'] ?? 0;
}

$total_upt = $total_bills > 0 ? $total_qty / $total_bills : 0;
$total_atv = $total_bills > 0 ? $total_sales / $total_bills : 0;
$total_auv = $total_qty > 0 ? $total_sales / $total_qty : 0;
$total_run_rate = $days_mtd > 0 ? ($total_sales / $days_mtd) * $days_in_month : 0;

// Format date range for filename
$date_from_formatted = date('d-M-Y', strtotime($date_from));
$date_to_formatted = date('d-M-Y', strtotime($date_to));
$filename = 'store_sales_' . $date_from_formatted . '_to_' . $date_to_formatted . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Output UTF-8 BOM for proper Thai character encoding
echo "\xEF\xBB\xBF";

// Start HTML table for Excel
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Store Sales</x:Name>
                    <x:WorksheetOptions>
                        <x:Print>
                            <x:ValidPrinterInfo/>
                        </x:Print>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <style>
        table { border-collapse: collapse; }
        th { 
            background-color: #000000; 
            color: #FFFFFF; 
            font-weight: bold; 
            text-align: center;
            border: 1px solid #000000;
            padding: 5px;
        }
        td { 
            border: 1px solid #CCCCCC;
            padding: 5px;
        }
        .title { 
            font-size: 14pt; 
            font-weight: bold; 
            text-align: center;
        }
        .number { 
            text-align: right; 
            mso-number-format: "\#\,\#\#0";
        }
        .decimal2 { 
            text-align: right; 
            mso-number-format: "0\.00";
        }
        .total { 
            background-color: #000000; 
            color: #FFFFFF; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table>
        <!-- Title Row -->
        <tr>
            <td colspan="8" class="title"><?php echo date('d-M-Y', strtotime($date_from)) . ' - ' . date('d-M-Y', strtotime($date_to)); ?></td>
        </tr>
        
        <!-- Header Row -->
        <tr>
            <th>Store</th>
            <th>Qty.</th>
            <th>THB</th>
            <th>Bill</th>
            <th>UPT</th>
            <th>ATV</th>
            <th>AUV</th>
            <th>RunRate</th>
        </tr>
        
        <!-- Data Rows -->
        <?php foreach ($store_data as $data): 
            $upt = ($data['bills'] ?? 0) > 0 ? ($data['qty'] ?? 0) / $data['bills'] : 0;
            $atv = ($data['bills'] ?? 0) > 0 ? ($data['sales'] ?? 0) / $data['bills'] : 0;
            $auv = ($data['qty'] ?? 0) > 0 ? ($data['sales'] ?? 0) / $data['qty'] : 0;
            $run_rate = $days_mtd > 0 ? (($data['sales'] ?? 0) / $days_mtd) * $days_in_month : 0;
        ?>
        <tr>
            <td><?php echo htmlspecialchars($data['store_name']); ?></td>
            <td class="number"><?php echo number_format($data['qty'] ?? 0, 0); ?></td>
            <td class="number"><?php echo number_format($data['sales'] ?? 0, 0); ?></td>
            <td class="number"><?php echo number_format($data['bills'] ?? 0, 0); ?></td>
            <td class="decimal2"><?php echo number_format($upt, 2); ?></td>
            <td class="number"><?php echo number_format($atv, 0); ?></td>
            <td class="number"><?php echo number_format($auv, 0); ?></td>
            <td class="number"><?php echo number_format($run_rate, 0); ?></td>
        </tr>
        <?php endforeach; ?>
        
        <!-- Total Row -->
        <tr class="total">
            <td>Total:</td>
            <td class="number"><?php echo number_format($total_qty, 0); ?></td>
            <td class="number"><?php echo number_format($total_sales, 0); ?></td>
            <td class="number"><?php echo number_format($total_bills, 0); ?></td>
            <td class="decimal2"><?php echo number_format($total_upt, 2); ?></td>
            <td class="number"><?php echo number_format($total_atv, 0); ?></td>
            <td class="number"><?php echo number_format($total_auv, 0); ?></td>
            <td class="number"><?php echo number_format($total_run_rate, 0); ?></td>
        </tr>
    </table>
</body>
</html>