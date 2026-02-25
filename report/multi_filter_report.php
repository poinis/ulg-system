<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

// Helper Function: Format Number (ป้องกัน Error ใน HTML)
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

// ==========================================
// 1. ✨ สร้าง STORE MAPPING (จับคู่รหัสเก่า-ใหม่)
// ==========================================
$store_map = [];      // Map: รหัสใดๆ -> รหัสหลัก
$main_stores_info = []; // Map: รหัสหลัก -> ชื่อร้าน
$child_to_parent = []; // Map: รหัสใหม่ -> รหัสเดิม (ใช้สำหรับ expand filter)

try {
    $map_stmt = $db->query("SELECT store_code, store_code_new, store_name FROM stores");
    while ($row = $map_stmt->fetch(PDO::FETCH_ASSOC)) {
        $main_code = $row['store_code']; // รหัสเดิม (Main)
        $new_code = $row['store_code_new']; // รหัสใหม่ (ถ้ามี)
        
        // บันทึกข้อมูลร้านหลัก
        $main_stores_info[$main_code] = $row['store_name'];
        
        // Map ตัวเอง
        $store_map[$main_code] = $main_code;
        
        // ถ้ามีรหัสใหม่ ให้ Map กลับมารหัสเดิม
        if (!empty($new_code)) {
            $store_map[$new_code] = $main_code;
            $child_to_parent[$main_code][] = $new_code; // บันทึกว่าแม่คนนี้ มีลูกชื่ออะไรบ้าง
        }
    }
} catch (Exception $e) { }

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d', strtotime('yesterday'));

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

// Get all brands for filter (include all groups)
try {
    $brands_stmt = $db->query("
        SELECT DISTINCT brand 
        FROM daily_sales 
        WHERE brand IS NOT NULL 
            AND brand != ''
            AND (class_name != 'GWP' OR class_name IS NULL)
        ORDER BY brand
    ");
    $all_brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Error fetching brands: " . $e->getMessage());
}

// Get all classes for filter (include all groups)
try {
    $classes_stmt = $db->query("
        SELECT DISTINCT class_name 
        FROM daily_sales 
        WHERE class_name IS NOT NULL 
            AND class_name != ''
            AND (class_name != 'GWP' OR class_name IS NULL)
        ORDER BY class_name
    ");
    $all_classes = $classes_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Error fetching classes: " . $e->getMessage());
}

// Get all stores for filter (แสดงเฉพาะร้าน Active หรือร้านหลัก)
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

$conditions[] = "sale_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

// Add brand filter
if (count($selected_brands) > 0) {
    $brand_placeholders = implode(',', array_fill(0, count($selected_brands), '?'));
    $conditions[] = "brand IN ($brand_placeholders)";
    foreach ($selected_brands as $brand) {
        $params[] = $brand;
    }
}

// Add class_name filter
if (count($selected_classes) > 0) {
    $class_placeholders = implode(',', array_fill(0, count($selected_classes), '?'));
    $conditions[] = "class_name IN ($class_placeholders)";
    foreach ($selected_classes as $class_name) {
        $params[] = $class_name;
    }
}

// Add store filter
// ✨ แก้ไข Logic: ขยายการค้นหาให้รวมรหัสใหม่ด้วย
if (count($selected_stores) > 0) {
    $expanded_stores = [];
    foreach ($selected_stores as $s) {
        $expanded_stores[] = $s; // รหัสที่เลือก
        // ถ้ารหัสที่เลือกมีลูก (รหัสใหม่) ให้รวมด้วย
        if (isset($child_to_parent[$s])) {
            foreach ($child_to_parent[$s] as $child) {
                $expanded_stores[] = $child;
            }
        }
    }
    // หา mapping เผื่อกรณีเลือกรหัสใหม่ตรงๆ
    $final_search_stores = [];
    foreach ($expanded_stores as $es) {
        $final_search_stores[] = $es;
    }
    $final_search_stores = array_unique($final_search_stores);
    
    if (!empty($final_search_stores)) {
        $store_placeholders = implode(',', array_fill(0, count($final_search_stores), '?'));
        $conditions[] = "store_code IN ($store_placeholders)";
        foreach ($final_search_stores as $store) {
            $params[] = $store;
        }
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
        $conditions[] = "(" . implode(' OR ', $customer_type_conditions) . ")";
    }
}

$where_clause = implode(' AND ', $conditions);

// Get overall summary
try {
    // Count positive bills (net sales > 0)
    $positive_bills_sql = "
        SELECT COUNT(DISTINCT internal_ref) as bill_count
        FROM (
            SELECT internal_ref
            FROM daily_sales
            WHERE $where_clause
                AND internal_ref IS NOT NULL
                AND internal_ref != ''
                AND (class_name != 'GWP' OR class_name IS NULL)
            GROUP BY internal_ref
            HAVING SUM(tax_incl_total) > 0
        ) as positive_bills
    ";
    
    $positive_stmt = $db->prepare($positive_bills_sql);
    $positive_stmt->execute($params);
    $positive_result = $positive_stmt->fetch(PDO::FETCH_ASSOC);
    $positive_bills_count = $positive_result['bill_count'] ?? 0;
    
    // Count negative bills (net sales < 0)
    $negative_bills_sql = "
        SELECT COUNT(DISTINCT internal_ref) as bill_count
        FROM (
            SELECT internal_ref
            FROM daily_sales
            WHERE $where_clause
                AND internal_ref IS NOT NULL
                AND internal_ref != ''
                AND (class_name != 'GWP' OR class_name IS NULL)
            GROUP BY internal_ref
            HAVING SUM(tax_incl_total) < 0
        ) as negative_bills
    ";
    
    $negative_stmt = $db->prepare($negative_bills_sql);
    $negative_stmt->execute($params);
    $negative_result = $negative_stmt->fetch(PDO::FETCH_ASSOC);
    $negative_bills_count = $negative_result['bill_count'] ?? 0;
    
    // Calculate total bills: positive - negative
    $total_bills_count = $positive_bills_count - $negative_bills_count;
    
    // Get totals for sales and qty
    $summary_sql = "
        SELECT 
            SUM(tax_incl_total) as total_sales,
            SUM(CASE WHEN tax_incl_total != 0 THEN qty ELSE 0 END) as total_qty,
            COUNT(DISTINCT store_code) as store_count,
            COUNT(DISTINCT brand) as brand_count
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL
            AND internal_ref != ''
            AND (class_name != 'GWP' OR class_name IS NULL)
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
    
    // Set calculated total bills
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
$auv = 0;
if ($summary['total_bills'] > 0) {
    $avg_per_bill = $summary['total_sales'] / $summary['total_bills'];
    $avg_qty_per_bill = $summary['total_qty'] / $summary['total_bills'];
}
if ($summary['total_qty'] > 0) {
    $auv = $summary['total_sales'] / $summary['total_qty'];
}

// Calculate Run Rate
$run_rate = 0;
try {
    $date_from_obj = new DateTime($date_from);
    $date_to_obj = new DateTime($date_to);
    $days_mtd = $date_to_obj->diff($date_from_obj)->days + 1;
    $days_in_month = $date_from_obj->format('t'); // total days in month
    
    if ($days_mtd > 0) {
        $run_rate = ($summary['total_sales'] / $days_mtd) * $days_in_month;
    }
} catch (Exception $e) {
    $run_rate = 0;
}

// Count customer types
$member_count = 0;
$walkin_count = 0;
$foreigner_count = 0;

try {
    $exclusions = "AND internal_ref IS NOT NULL AND internal_ref != '' AND (class_name != 'GWP' OR class_name IS NULL)";

    // MEMBER
    $mem_pos_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE '99%' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t";
    $stmt = $db->prepare($mem_pos_sql); $stmt->execute($params); $mem_pos = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $mem_neg_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE '99%' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t";
    $stmt = $db->prepare($mem_neg_sql); $stmt->execute($params); $mem_neg = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $member_count = $mem_pos - $mem_neg;

    // WALKIN
    $wi_pos_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE 'WI%TH' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t";
    $stmt = $db->prepare($wi_pos_sql); $stmt->execute($params); $wi_pos = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $wi_neg_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE 'WI%TH' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t";
    $stmt = $db->prepare($wi_neg_sql); $stmt->execute($params); $wi_neg = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $walkin_count = $wi_pos - $wi_neg;

    // FOREIGNER
    $for_pos_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE 'WI%' AND customer NOT LIKE 'WI%TH' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t";
    $stmt = $db->prepare($for_pos_sql); $stmt->execute($params); $for_pos = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $for_neg_sql = "SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND customer LIKE 'WI%' AND customer NOT LIKE 'WI%TH' $exclusions GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t";
    $stmt = $db->prepare($for_neg_sql); $stmt->execute($params); $for_neg = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    $foreigner_count = $for_pos - $for_neg;

} catch (Exception $e) { }

// Get sales by brand (Top 20)
$brand_data = array();
try {
    $brand_sql = "
        SELECT 
            brand,
            SUM(tax_incl_total) as sales,
            SUM(CASE WHEN tax_incl_total != 0 THEN qty ELSE 0 END) as qty,
            COUNT(DISTINCT store_code) as stores
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL
            AND internal_ref != ''
            AND brand IS NOT NULL
            AND brand != ''
            AND (class_name != 'GWP' OR class_name IS NULL)
        GROUP BY brand
        ORDER BY sales DESC
        LIMIT 20
    ";
    $brand_stmt = $db->prepare($brand_sql);
    $brand_stmt->execute($params);
    $brand_data = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($brand_data as &$brand_row) {
        $current_brand = $brand_row['brand'];
        $brand_bill_conditions = ["sale_date BETWEEN ? AND ?", "brand = ?"];
        $brand_bill_params = [$date_from, $date_to, $current_brand];
        $brand_bill_conditions[] = "(class_name != 'GWP' OR class_name IS NULL)";
        
        if (count($selected_classes) > 0) {
            $class_placeholders = implode(',', array_fill(0, count($selected_classes), '?'));
            $brand_bill_conditions[] = "class_name IN ($class_placeholders)";
            foreach ($selected_classes as $class) $brand_bill_params[] = $class;
        }
        
        // Use Expanded stores here as well
        if (!empty($final_search_stores)) {
            $store_placeholders = implode(',', array_fill(0, count($final_search_stores), '?'));
            $brand_bill_conditions[] = "store_code IN ($store_placeholders)";
            foreach ($final_search_stores as $store) $brand_bill_params[] = $store;
        }
        
        $brand_bill_where = implode(' AND ', $brand_bill_conditions);
        
        $brand_pos_sql = "SELECT COUNT(DISTINCT internal_ref) as bill_count FROM (SELECT internal_ref FROM daily_sales WHERE $brand_bill_where AND internal_ref IS NOT NULL AND internal_ref != '' GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t";
        $brand_pos_stmt = $db->prepare($brand_pos_sql); $brand_pos_stmt->execute($brand_bill_params);
        $brand_positive = $brand_pos_stmt->fetch(PDO::FETCH_ASSOC)['bill_count'] ?? 0;
        
        $brand_neg_sql = "SELECT COUNT(DISTINCT internal_ref) as bill_count FROM (SELECT internal_ref FROM daily_sales WHERE $brand_bill_where AND internal_ref IS NOT NULL AND internal_ref != '' GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t";
        $brand_neg_stmt = $db->prepare($brand_neg_sql); $brand_neg_stmt->execute($brand_bill_params);
        $brand_negative = $brand_neg_stmt->fetch(PDO::FETCH_ASSOC)['bill_count'] ?? 0;
        
        $brand_row['bills'] = $brand_positive - $brand_negative;
    }
    unset($brand_row);
} catch (Exception $e) { die("Error fetching brand data: " . $e->getMessage()); }

// Get Top 20 products
$top_products = array();
try {
    $product_sql = "
        SELECT 
            `Item_description` as item_name, brand,
            SUM(tax_incl_total) as sales,
            SUM(CASE WHEN tax_incl_total != 0 THEN qty ELSE 0 END) as qty,
            COUNT(DISTINCT internal_ref) as bills
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL AND internal_ref != '' AND `Item_description` IS NOT NULL AND (class_name != 'GWP' OR class_name IS NULL)
        GROUP BY `Item_description`, brand
        ORDER BY sales DESC LIMIT 20
    ";
    $product_stmt = $db->prepare($product_sql);
    $product_stmt->execute($params);
    $top_products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

// ==========================================
// 4. ✨ SALES BY STORE (Aggregated Old+New)
// ==========================================
$store_data = array();
try {
    // ใช้ params เดียวกันกับ main query (ซึ่งผ่านการ expand store มาแล้ว)
    // แต่เราต้องใช้ prefix 'ds.' เพื่อป้องกัน field ambiguous ถ้ามีการ JOIN
    // แต่ในโค้ดเดิมใช้ LEFT JOIN stores s ON ds.store_code = s.store_code
    // ดังนั้นต้องแก้ชื่อ field ใน WHERE clause ให้มี prefix ds.
    
    $store_conds = [];
    $store_params = [];
    
    // Date
    $store_conds[] = "ds.sale_date BETWEEN ? AND ?";
    $store_params[] = $date_from;
    $store_params[] = $date_to;
    
    // Brands
    if (count($selected_brands) > 0) {
        $ph = implode(',', array_fill(0, count($selected_brands), '?'));
        $store_conds[] = "ds.brand IN ($ph)";
        foreach ($selected_brands as $b) $store_params[] = $b;
    }
    
    // Classes
    if (count($selected_classes) > 0) {
        $ph = implode(',', array_fill(0, count($selected_classes), '?'));
        $store_conds[] = "ds.class_name IN ($ph)";
        foreach ($selected_classes as $c) $store_params[] = $c;
    }
    
    // Stores (Expanded)
    if (!empty($final_search_stores)) {
        $ph = implode(',', array_fill(0, count($final_search_stores), '?'));
        $store_conds[] = "ds.store_code IN ($ph)";
        foreach ($final_search_stores as $s) $store_params[] = $s;
    }
    
    // Customer Types
    if (count($selected_customer_types) > 0) {
        $ct_conds = [];
        foreach ($selected_customer_types as $type) {
            if ($type === 'MEMBER') $ct_conds[] = "ds.customer LIKE '99%'";
            elseif ($type === 'WALKIN') $ct_conds[] = "ds.customer LIKE 'WI%TH'";
            elseif ($type === 'FOREIGNER') $ct_conds[] = "(ds.customer LIKE 'WI%' AND ds.customer NOT LIKE 'WI%TH')";
        }
        if (count($ct_conds) > 0) $store_conds[] = "(" . implode(' OR ', $ct_conds) . ")";
    }
    
    // Exclusion
    $store_conds[] = "(ds.class_name != 'GWP' OR ds.class_name IS NULL)";
    $store_where_clause = implode(' AND ', $store_conds);
    
    $store_sql = "
        SELECT 
            ds.store_code,
            s.store_name,
            SUM(ds.tax_incl_total) as sales,
            SUM(CASE WHEN ds.tax_incl_total != 0 THEN ds.qty ELSE 0 END) as qty
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
    $raw_store_data = $store_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✨ Aggregation Logic: รวมรหัสใหม่เข้าหารหัสเดิม
    foreach ($raw_store_data as $row) {
        $code = $row['store_code'];
        $main_code = $store_map[$code] ?? $code; // แปลงเป็นรหัสหลัก
        
        if (!isset($store_data[$main_code])) {
            $store_data[$main_code] = [
                'store_code' => $main_code,
                'store_name' => $main_stores_info[$main_code] ?? $row['store_name'],
                'sales' => 0,
                'qty' => 0,
                'sub_codes' => [] // เก็บรายการรหัสย่อยไว้สำหรับ query บิล
            ];
        }
        $store_data[$main_code]['sales'] += $row['sales'];
        $store_data[$main_code]['qty'] += $row['qty'];
        $store_data[$main_code]['sub_codes'][] = $code;
    }
    
    // ✨ Calculate Bills per Aggregated Store
    foreach ($store_data as &$store_row) {
        $sub_codes = array_unique($store_row['sub_codes']);
        
        // สร้าง conditions สำหรับนับบิลเฉพาะกลุ่มร้านนี้
        $bill_conds = $conditions; // copy global conditions (without table prefix)
        // ต้องเอา store_code IN (...) ออกจาก global conditions ก่อน (ถ้ามี)
        // วิธีง่ายสุด: สร้างเงื่อนไขใหม่หมดโดยใช้ params เดิม แต่เปลี่ยน store filter
        
        // เพื่อความชัวร์ สร้างใหม่เฉพาะส่วน store
        $local_conds = [];
        $local_params = [];
        
        $local_conds[] = "sale_date BETWEEN ? AND ?";
        $local_params[] = $date_from;
        $local_params[] = $date_to;
        
        // Brand
        if (count($selected_brands) > 0) {
            $ph = implode(',', array_fill(0, count($selected_brands), '?'));
            $local_conds[] = "brand IN ($ph)";
            foreach ($selected_brands as $b) $local_params[] = $b;
        }
        // Class
        if (count($selected_classes) > 0) {
            $ph = implode(',', array_fill(0, count($selected_classes), '?'));
            $local_conds[] = "class_name IN ($ph)";
            foreach ($selected_classes as $c) $local_params[] = $c;
        }
        // Customer
        if (count($selected_customer_types) > 0) {
            $ct = [];
            foreach ($selected_customer_types as $type) {
                if ($type === 'MEMBER') $ct[] = "customer LIKE '99%'";
                elseif ($type === 'WALKIN') $ct[] = "customer LIKE 'WI%TH'";
                elseif ($type === 'FOREIGNER') $ct[] = "(customer LIKE 'WI%' AND customer NOT LIKE 'WI%TH')";
            }
            if (count($ct) > 0) $local_conds[] = "(" . implode(' OR ', $ct) . ")";
        }
        
        // ✨ ใส่เงื่อนไข Store Code ที่เป็น Sub Codes ทั้งหมด
        $ph = implode(',', array_fill(0, count($sub_codes), '?'));
        $local_conds[] = "store_code IN ($ph)";
        foreach ($sub_codes as $sc) $local_params[] = $sc;
        
        // Exclusion
        $local_conds[] = "(class_name != 'GWP' OR class_name IS NULL)";
        
        $local_where = implode(' AND ', $local_conds);
        
        // Pos Bills
        $stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $local_where GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t");
        $stmt->execute($local_params); $pos = $stmt->fetch()['cnt'] ?? 0;
        
        // Neg Bills
        $stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $local_where GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t");
        $stmt->execute($local_params); $neg = $stmt->fetch()['cnt'] ?? 0;
        
        $store_row['bills'] = $pos - $neg;
        
        // GT2
        $stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $local_where GROUP BY internal_ref HAVING SUM(qty) > 2) t");
        $stmt->execute($local_params); $store_row['bills_qty_gt2'] = $stmt->fetch()['cnt'] ?? 0;
    }
    unset($store_row);
    
    // Sort
    usort($store_data, function($a, $b) { return $b['sales'] <=> $a['sales']; });

} catch (Exception $e) { die("Error fetching store data: " . $e->getMessage()); }

// Get daily trend
$daily_trend = array();
try {
    $daily_sql = "
        SELECT 
            sale_date,
            COUNT(DISTINCT internal_ref) as bills,
            SUM(tax_incl_total) as sales,
            SUM(CASE WHEN tax_incl_total != 0 THEN qty ELSE 0 END) as qty
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL
            AND internal_ref != ''
            AND (class_name != 'GWP' OR class_name IS NULL)
        GROUP BY sale_date
        ORDER BY sale_date
    ";
    
    $daily_stmt = $db->prepare($daily_sql);
    $daily_stmt->execute($params);
    $daily_trend = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching daily trend: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการขายแบบหลายเงื่อนไข</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, sans-serif; 
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
        
        .container { max-width: 1600px; margin: 0 auto; padding: 0 20px 40px; }
        
        /* Filter Section */
        .filters { 
            background: white; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin-bottom: 25px;
        }
        .filter-row { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .filter-row:last-child { margin-bottom: 0; }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-label { 
            font-size: 13px; 
            color: #666; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input[type="date"] {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .filter-group input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            background: #f8f9fa;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px;
            margin-bottom: 6px;
            background: white;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .checkbox-item:hover {
            background: #f0f0ff;
        }
        .checkbox-item input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-item label {
            cursor: pointer;
            font-size: 14px;
            flex: 1;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
        }
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        /* Quick filter buttons */
        .quick-filter-btn {
            padding: 10px 20px;
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .quick-filter-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .quick-filter-btn:active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* Stats Cards */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 25px;
        }
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .stat-label { 
            color: #666; 
            font-size: 13px; 
            margin-bottom: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value { 
            font-size: 30px; 
            font-weight: bold; 
            color: #333;
            line-height: 1.2;
        }
        .stat-sub { 
            font-size: 12px; 
            color: #999; 
            margin-top: 5px;
        }
        
        /* Data Cards */
        .card { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            margin-bottom: 25px;
        }
        .card h2 { 
            margin-bottom: 20px; 
            color: #333; 
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 .icon {
            font-size: 28px;
        }
        
        /* Tables */
        .table-container { overflow-x: auto; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 14px;
        }
        th, td { 
            padding: 14px 12px; 
            text-align: left; 
            border-bottom: 1px solid #f0f0f0;
        }
        th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            position: sticky; 
            top: 0; 
            z-index: 10;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        tbody tr {
            transition: all 0.2s;
        }
        tbody tr:hover { 
            background: #f8f9ff;
            transform: scale(1.01);
        }
        .number { 
            text-align: right; 
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        /* Progress bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        
        /* Print styles */
        @media print {
            body { background: white; }
            .filters, .back-link, .btn { display: none; }
            .card, .stat-card { box-shadow: none; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <span class="header-icon">📊</span>
                รายงานการขายแบบหลายเงื่อนไข
            </div>
            <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
    
    <div class="container">
        <form method="GET" class="filters">
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
                <div class="filter-group" style="grid-column: 1 / -1;">
                    <label class="filter-label">🏅 ตัวกรองสาขาด่วน</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                        <button type="button" class="quick-filter-btn" onclick="selectStores(['06010','08010','09030','09080','09100','09110','09130','09160','10010','13010'])">
                            🏪 Soup-Hooga-SW19 (9 สาขา)
                        </button>
                        <button type="button" class="quick-filter-btn" onclick="selectStoresAndBrands(['03020','06030','06050','06060','08010','06070'], ['TOPOLOGIE'])">
                            🏪 Topologie (6 สาขา + Brand)
                        </button>
                        <button type="button" class="quick-filter-btn" onclick="selectStores(['02010','02020','02030','02080','02090','07020','07030','09140','03010','03030','03060'])">
                            🏪 Pronto-Freitag (11 สาขา)
                        </button>
                        <button type="button" class="quick-filter-btn" onclick="clearStores()">
                            🔄 ล้างการเลือกสาขา
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">🏷️ เลือก Brand (เลือกได้หลายรายการ)</label>
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
                    <label class="filter-label">📂 เลือก Class (เลือกได้หลายรายการ)</label>
                    <div class="checkbox-group">
                        <?php foreach ($all_classes as $class_name): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" 
                                       name="classes[]" 
                                       value="<?php echo htmlspecialchars($class_name); ?>"
                                       id="class_<?php echo htmlspecialchars($class_name); ?>"
                                       <?php echo in_array($class_name, $selected_classes) ? 'checked' : ''; ?>>
                                <label for="class_<?php echo htmlspecialchars($class_name); ?>">
                                    <?php echo htmlspecialchars($class_name); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">🏪 เลือกสาขา (เลือกได้หลายรายการ)</label>
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
                
                <div class="filter-group">
                    <label class="filter-label">👥 ประเภทลูกค้า (เลือกได้หลายรายการ)</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="customer_types[]" 
                                   value="MEMBER"
                                   id="customer_type_member"
                                   <?php echo in_array('MEMBER', $selected_customer_types) ? 'checked' : ''; ?>>
                            <label for="customer_type_member">👤 MEMBER </label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="customer_types[]" 
                                   value="WALKIN"
                                   id="customer_type_walkin"
                                   <?php echo in_array('WALKIN', $selected_customer_types) ? 'checked' : ''; ?>>
                            <label for="customer_type_walkin">🚶 Walk-in TH </label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="customer_types[]" 
                                   value="FOREIGNER"
                                   id="customer_type_foreigner"
                                   <?php echo in_array('FOREIGNER', $selected_customer_types) ? 'checked' : ''; ?>>
                            <label for="customer_type_foreigner">🌍 ต่างชาติ </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                <a href="multi_filter_report.php" class="btn btn-secondary">🔄 ล้างตัวกรอง</a>
            </div>
        </form>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-label">ยอดขายรวม</div>
                <div class="stat-value"><?php echo formatNumber($summary['total_sales'] ?? 0, 0); ?></div>
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
                <div class="stat-label">ยอดเฉลี่ย/บิล (ATV)</div>
                <div class="stat-value"><?php echo formatNumber($avg_per_bill ?? 0, 2); ?></div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💎</div>
                <div class="stat-label">ราคาเฉลี่ย/ชิ้น (AUV)</div>
                <div class="stat-value"><?php echo formatNumber($auv ?? 0, 2); ?></div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-label">ชิ้นเฉลี่ย/บิล (UPT)</div>
                <div class="stat-value"><?php echo formatNumber($avg_qty_per_bill ?? 0, 2); ?></div>
                <div class="stat-sub">ชิ้น</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏪</div>
                <div class="stat-label">จำนวนสาขา</div>
                <div class="stat-value"><?php echo formatNumber($summary['store_count'] ?? 0, 0); ?></div>
                <div class="stat-sub">สาขา</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-label">Run Rate</div>
                <div class="stat-value"><?php echo formatNumber($run_rate ?? 0, 0); ?></div>
                <div class="stat-sub">บาท/เดือน</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">จำนวนลูกค้า </div>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <span>Member</span>
                        <strong><?php echo formatNumber($member_count ?? 0, 0); ?> คน</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <span>Walk-in TH</span>
                        <strong><?php echo formatNumber($walkin_count ?? 0, 0); ?> คน</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <span>ต่างชาติ</span>
                        <strong><?php echo formatNumber($foreigner_count ?? 0, 0); ?> คน</strong>
                    </div>
                    <div style="border-top: 1px solid #e0e0e0; padding-top: 8px; display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; color: #0288d1;">
                        <span>รวม</span>
                        <span><?php echo formatNumber(($member_count ?? 0) + ($walkin_count ?? 0) + ($foreigner_count ?? 0), 0); ?> คน</span>
                    </div>
                </div>
            </div>
        </div>
 
        <?php if (count($store_data) > 0): ?>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;"><span class="icon">🏪</span> ยอดขายแยกตามสาขา</h2>
                <form method="GET" action="export_store_sales.php" style="margin: 0;">
                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    <?php foreach ($selected_brands as $brand): ?>
                        <input type="hidden" name="brands[]" value="<?php echo htmlspecialchars($brand); ?>">
                    <?php endforeach; ?>
                    <?php foreach ($selected_classes as $class): ?>
                        <input type="hidden" name="classes[]" value="<?php echo htmlspecialchars($class); ?>">
                    <?php endforeach; ?>
                    <?php foreach ($selected_stores as $store): ?>
                        <input type="hidden" name="stores[]" value="<?php echo htmlspecialchars($store); ?>">
                    <?php endforeach; ?>
                    <?php foreach ($selected_customer_types as $type): ?>
                        <input type="hidden" name="customer_types[]" value="<?php echo htmlspecialchars($type); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary" style="background: #28a745; padding: 8px 20px;">
                        📊 Export to Excel
                    </button>
                </form>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>รหัสสาขา</th>
                            <th>ชื่อสาขา</th>
                            <th class="number">จำนวนบิล</th>
                            <th class="number">บิล > 2 ชิ้น</th>
                            <th class="number">ยอดขาย</th>
                            <th class="number">จำนวนชิ้น</th>
                            <th class="number">เฉลี่ย/บิล (ATV)</th>
                            <th class="number">ชิ้น/บิล (UPT)</th>
                            <th class="number">ราคา/ชิ้น (AUV)</th>
                            <th class="number">Run Rate</th>
                            <th>% ของยอดรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Calculate days in month and MTD days for Run Rate
                        $date_from_obj = new DateTime($date_from);
                        $date_to_obj = new DateTime($date_to);
                        $days_mtd = $date_to_obj->diff($date_from_obj)->days + 1;
                        $days_in_month = $date_from_obj->format('t'); // total days in month
                        
                        foreach ($store_data as $row): 
                            $pct = ($summary['total_sales'] ?? 0) > 0 ? (($row['sales'] ?? 0) / $summary['total_sales'] * 100) : 0;
                            $avg_bill = ($row['bills'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['bills'] : 0;
                            $avg_qty = ($row['bills'] ?? 0) > 0 ? ($row['qty'] ?? 0) / $row['bills'] : 0;
                            $auv = ($row['qty'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['qty'] : 0;
                            
                            // Run Rate calculation per store
                            $run_rate = $days_mtd > 0 ? (($row['sales'] ?? 0) / $days_mtd) * $days_in_month : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['store_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills_qty_gt2'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_bill, 2); ?></td>
                                <td class="number"><?php echo formatNumber($avg_qty, 2); ?></td>
                                <td class="number"><?php echo formatNumber($auv, 2); ?></td>
                                <td class="number"><?php echo formatNumber($run_rate, 0); ?></td>
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

        <?php if (count($brand_data) > 0): ?>
        <div class="card">
            <h2><span class="icon">🏷️</span> ยอดขายแยกตาม Brand (Top 20)</h2>
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
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_bill, 2); ?></td>
                                <td class="number"><?php echo formatNumber($avg_qty, 2); ?></td>
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
        
        <?php if (count($top_products) > 0): ?>
        <div class="card">
            <h2><span class="icon">⭐</span> Top 20 สินค้าขายดี</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อสินค้า</th>
                            <th>Brand</th>
                            <th class="number">ยอดขาย</th>
                            <th class="number">จำนวนชิ้น</th>
                            <th class="number">จำนวนบิล</th>
                            <th class="number">ราคาเฉลี่ย/ชิ้น</th>
                            <th>% ของยอดรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($top_products as $row): 
                            $pct = ($summary['total_sales'] ?? 0) > 0 ? (($row['sales'] ?? 0) / $summary['total_sales'] * 100) : 0;
                            $avg_price = ($row['qty'] ?? 0) > 0 ? ($row['sales'] ?? 0) / $row['qty'] : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['brand']); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($avg_price, 2); ?></td>
                                <td>
                                    <div><?php echo number_format($pct, 2); ?>%</div>
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
                                <td><?php echo date('d/m/Y', strtotime($row['sale_date'])); ?></td>
                                <td class="number"><?php echo formatNumber($row['bills'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['sales'] ?? 0, 0); ?></td>
                                <td class="number"><?php echo formatNumber($row['qty'] ?? 0, 0); ?></td>
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
        
        function selectStoresAndBrands(storeCodes, brandNames) {
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
            
            // Uncheck all brand checkboxes first
            document.querySelectorAll('input[name="brands[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Check only the specified brands
            brandNames.forEach(brand => {
                const checkbox = document.getElementById('brand_' + brand);
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