<?php
/**
 * Birthday Members Report — Topologie Customers
 * Query daily_sales (cmbase) for Topologie brand buyers
 * Enrich with birthday/phone from Cegid CustomerWcfService
 * Filter by birthday month + export XLSX
 */
require_once 'config.php';
require_once 'Database.php';
require_once 'CegidCustomerSOAP.php';

// --- DB connections ---
$ulgcegid = Database::getInstance()->getConnection();

// cmbase connection
try {
    $cmbase = new PDO(
        "mysql:host=localhost;dbname=cmbase;charset=utf8mb4",
        "cmbase",
        "#wmIYH3wazaa",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("cmbase connection failed: " . $e->getMessage());
}

// --- AJAX: Enrich members from SOAP ---
if (isset($_GET['action']) && $_GET['action'] === 'enrich') {
    header('Content-Type: application/json');
    
    $member_ids = json_decode($_POST['member_ids'] ?? '[]', true);
    if (empty($member_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No member IDs']);
        exit;
    }
    
    $soap = new CegidCustomerSOAP();
    $results = [];
    $enriched = 0;
    $errors = 0;
    
    foreach ($member_ids as $mid) {
        $mid = trim($mid);
        if (empty($mid)) continue;
        
        // Check if already in customers table with birthday
        $stmt = $ulgcegid->prepare("SELECT customer_code, first_name, last_name, phone, birthday FROM customers WHERE customer_code = ?");
        $stmt->execute([$mid]);
        $existing = $stmt->fetch();
        
        if ($existing && !empty($existing['birthday'])) {
            $results[] = $existing;
            $enriched++;
            continue;
        }
        
        // Call SOAP API
        $detail = $soap->getCustomerDetail($mid);
        if ($detail) {
            $ins = $ulgcegid->prepare("
                INSERT INTO customers (customer_code, first_name, last_name, phone, email, birthday, usual_store, member_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    first_name = VALUES(first_name), last_name = VALUES(last_name),
                    phone = VALUES(phone), email = VALUES(email),
                    birthday = VALUES(birthday), usual_store = VALUES(usual_store),
                    member_type = VALUES(member_type)
            ");
            $ins->execute([
                $mid,
                $detail['first_name'] ?? '',
                $detail['last_name'] ?? '',
                $detail['phone'] ?? '',
                $detail['email'] ?? '',
                $detail['birthday'] ?? null,
                $detail['usual_store'] ?? '',
                $detail['member_type'] ?? ''
            ]);
            
            $results[] = [
                'customer_code' => $mid,
                'first_name' => $detail['first_name'] ?? '',
                'last_name' => $detail['last_name'] ?? '',
                'phone' => $detail['phone'] ?? '',
                'birthday' => $detail['birthday'] ?? null,
            ];
            $enriched++;
            usleep(50000); // 50ms rate limit
        } else {
            $errors++;
        }
    }
    
    echo json_encode(['status' => 'ok', 'enriched' => $enriched, 'errors' => $errors, 'total' => count($member_ids)]);
    exit;
}

// --- XLSX Export ---
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $date_from = $_GET['date_from'] ?? '2025-01-01';
    $date_to = $_GET['date_to'] ?? '2026-03-15';
    $birth_months = isset($_GET['birth_months']) ? explode(',', $_GET['birth_months']) : [];
    $store_filter = $_GET['store'] ?? '';
    $brand_filter = $_GET['brand'] ?? 'TOPOLOGIE';
    
    $data = getFilteredData($cmbase, $ulgcegid, $date_from, $date_to, $birth_months, $store_filter, $brand_filter);
    
    $filename = "birthday_members_" . ($brand_filter ?: 'all') . '_' . date('Ymd_His');
    
    // Excel-compatible CSV with BOM for Thai support
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    
    // BOM for Excel to recognize UTF-8
    echo "\xEF\xBB\xBF";
    
    $out = fopen('php://output', 'w');
    $monthNames = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
    
    // Header — PHP 8.4 requires explicit escape param
    fputcsv($out, ['Member ID', 'Customer', 'ชื่อ', 'นามสกุล', 'เบอร์โทร', 'วันเกิด', 'เดือนเกิด', 'Member Type', 'สาขาที่ซื้อ', 'จำนวนรายการ', 'ยอดซื้อรวม', 'ซื้อครั้งแรก', 'ซื้อครั้งล่าสุด'], ',', '"', '\\');
    
    foreach ($data as $row) {
        fputcsv($out, [
            $row['member'],
            $row['customer'],
            $row['first_name'],
            $row['last_name'],
            $row['phone'],
            $row['birthday'] ?? '-',
            $row['birth_month'] ? ($monthNames[$row['birth_month']] ?? $row['birth_month']) : '-',
            $row['member_type'],
            $row['stores_bought'],
            $row['purchase_count'],
            round($row['total_spent'], 2),
            $row['first_purchase'],
            $row['last_purchase'],
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// --- Data Query Function ---
function getFilteredData($cmbase, $ulgcegid, $date_from, $date_to, $birth_months = [], $store_filter = '', $brand_filter = 'TOPOLOGIE') {
    // Step 1: Get distinct members from cmbase.daily_sales
    // Exclude Walk-in customers (WI%) — only real members
    // JOIN stores table for store_name mapping
    $where = "WHERE d.sale_date BETWEEN ? AND ? AND d.customer IS NOT NULL AND d.customer != '' AND d.customer NOT LIKE 'WI%'";
    $params = [$date_from, $date_to];
    
    if ($brand_filter) {
        $where .= " AND d.brand = ?";
        $params[] = $brand_filter;
    }
    
    if ($store_filter) {
        $where .= " AND d.store_code = ?";
        $params[] = $store_filter;
    }
    
    $sql = "
        SELECT d.customer as member, d.customer, 
               MAX(d.first_name) as ds_first_name, MAX(d.last_name) as ds_last_name,
               GROUP_CONCAT(DISTINCT COALESCE(s.store_name, s2.store_name, d.store_code) ORDER BY d.store_code SEPARATOR ', ') as stores_bought,
               COUNT(*) as purchase_count,
               SUM(d.tax_incl_total) as total_spent,
               MIN(d.sale_date) as first_purchase,
               MAX(d.sale_date) as last_purchase
        FROM daily_sales d
        LEFT JOIN stores s ON d.store_code = s.store_code
        LEFT JOIN stores s2 ON d.store_code = s2.store_code_new
        $where
        GROUP BY d.customer
        ORDER BY total_spent DESC
    ";
    
    $stmt = $cmbase->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
    
    if (empty($members)) return [];
    
    // Step 2: Get birthday/phone from ulgcegid.customers
    $member_codes = array_column($members, 'member');
    $placeholders = implode(',', array_fill(0, count($member_codes), '?'));
    
    $cust_stmt = $ulgcegid->prepare("
        SELECT customer_code, first_name, last_name, phone, birthday, member_type 
        FROM customers 
        WHERE customer_code IN ($placeholders)
    ");
    $cust_stmt->execute($member_codes);
    $customers = [];
    foreach ($cust_stmt->fetchAll() as $c) {
        $customers[$c['customer_code']] = $c;
    }
    
    // Step 3: Merge + filter by birthday month
    $result = [];
    foreach ($members as $m) {
        $cust = $customers[$m['member']] ?? null;
        $birthday = $cust['birthday'] ?? null;
        $birth_month = $birthday ? (int)date('n', strtotime($birthday)) : null;
        
        // Filter by birth month if specified
        if (!empty($birth_months) && ($birth_month === null || !in_array($birth_month, $birth_months))) {
            continue;
        }
        
        $result[] = [
            'member' => $m['member'],
            'customer' => $m['customer'] ?? '',
            'first_name' => $cust['first_name'] ?? $m['ds_first_name'] ?? '',
            'last_name' => $cust['last_name'] ?? $m['ds_last_name'] ?? '',
            'phone' => $cust['phone'] ?? '',
            'birthday' => $birthday,
            'birth_month' => $birth_month,
            'member_type' => $cust['member_type'] ?? '',
            'stores_bought' => $m['stores_bought'],
            'purchase_count' => $m['purchase_count'],
            'total_spent' => $m['total_spent'],
            'first_purchase' => $m['first_purchase'],
            'last_purchase' => $m['last_purchase'],
            'has_enriched' => $cust !== null,
        ];
    }
    
    return $result;
}

// --- Simple XLSX Generator (no library needed) ---
function generateXLSX($data) {
    // Use XML spreadsheet (Excel 2003 XML) for simplicity — opens in modern Excel
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
    $xml .= '<Styles>
      <Style ss:ID="header"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#4472C4" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF" ss:Bold="1"/></Style>
      <Style ss:ID="date"><NumberFormat ss:Format="yyyy\-mm\-dd"/></Style>
      <Style ss:ID="number"><NumberFormat ss:Format="#,##0"/></Style>
      <Style ss:ID="money"><NumberFormat ss:Format="#,##0.00"/></Style>
    </Styles>' . "\n";
    $xml .= '<Worksheet ss:Name="Birthday Members"><Table>' . "\n";
    
    // Header
    $headers = ['Member ID', 'Customer', 'ชื่อ', 'นามสกุล', 'เบอร์โทร', 'วันเกิด', 'เดือนเกิด', 'Member Type', 'สาขาที่ซื้อ', 'จำนวนรายการ', 'ยอดซื้อรวม', 'ซื้อครั้งแรก', 'ซื้อครั้งล่าสุด'];
    $xml .= '<Row>';
    foreach ($headers as $h) {
        $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
    }
    $xml .= '</Row>' . "\n";
    
    $monthNames = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
    
    foreach ($data as $row) {
        $xml .= '<Row>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['member']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['customer']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['first_name']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['last_name']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['phone']) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="date"><Data ss:Type="String">' . htmlspecialchars($row['birthday'] ?? '-') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . ($row['birth_month'] ? ($monthNames[$row['birth_month']] ?? $row['birth_month']) : '-') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['member_type']) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['stores_bought']) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['purchase_count'] . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="money"><Data ss:Type="Number">' . round($row['total_spent'], 2) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="date"><Data ss:Type="String">' . htmlspecialchars($row['first_purchase']) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="date"><Data ss:Type="String">' . htmlspecialchars($row['last_purchase']) . '</Data></Cell>';
        $xml .= '</Row>' . "\n";
    }
    
    $xml .= '</Table></Worksheet></Workbook>';
    return $xml;
}

// --- Page Load: Get data ---
$date_from = $_GET['date_from'] ?? '2025-01-01';
$date_to = $_GET['date_to'] ?? '2026-03-15';
$birth_months_str = $_GET['birth_months'] ?? '3,4'; // default Mar, Apr
$birth_months = $birth_months_str ? array_map('intval', explode(',', $birth_months_str)) : [];
$store_filter = $_GET['store'] ?? '';
$brand_filter = $_GET['brand'] ?? 'TOPOLOGIE'; // default TOPOLOGIE

$data = getFilteredData($cmbase, $ulgcegid, $date_from, $date_to, $birth_months, $store_filter, $brand_filter);

// Count members needing enrichment
$need_enrich = array_filter($data, fn($d) => !$d['has_enriched']);
$need_enrich_all = []; // For enrich button — get ALL members (not filtered by month)
if (isset($_GET['check_enrich'])) {
    $all_data = getFilteredData($cmbase, $ulgcegid, $date_from, $date_to, [], $store_filter);
    $need_enrich_all = array_filter($all_data, fn($d) => !$d['has_enriched']);
}

// Get store list for filter
$stores_q = "SELECT d.store_code, COALESCE(MAX(s.store_name), MAX(s2.store_name), d.store_code) as store_name FROM daily_sales d LEFT JOIN stores s ON d.store_code = s.store_code LEFT JOIN stores s2 ON d.store_code = s2.store_code_new WHERE d.store_code IS NOT NULL AND d.store_code != ''";
if ($brand_filter) {
    $stores_q .= " AND d.brand = " . $cmbase->quote($brand_filter);
}
$stores_q .= " GROUP BY d.store_code ORDER BY store_name, d.store_code";
$stores_stmt = $cmbase->query($stores_q);
$stores_data = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
$stores = array_column($stores_data, 'store_code');

// Brand list for filter
$brands_stmt = $cmbase->query("SELECT DISTINCT brand FROM daily_sales WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brand_list = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total_members = count($data);
$with_birthday = count(array_filter($data, fn($d) => $d['birthday']));
$with_phone = count(array_filter($data, fn($d) => !empty($d['phone'])));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎂 Birthday Members</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f4f8; color: #1a1a2e; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header { background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); color: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 1.6em; margin-bottom: 4px; }
        .header p { opacity: 0.85; font-size: 0.95em; }
        
        .nav { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .nav-btn { padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.9em; background: white; color: #333; border: 1px solid #ddd; }
        .nav-btn:hover { background: #e3f2fd; }
        .nav-btn.active { background: #1565c0; color: white; border-color: #1565c0; }
        
        .filters { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .filter-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.85em; font-weight: 600; color: #555; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9em; }
        .filter-group select { min-width: 120px; }
        
        .btn { padding: 8px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9em; font-weight: 600; }
        .btn-primary { background: #1565c0; color: white; }
        .btn-primary:hover { background: #0d47a1; }
        .btn-success { background: #2e7d32; color: white; }
        .btn-success:hover { background: #1b5e20; }
        .btn-warning { background: #f57f17; color: white; }
        .btn-warning:hover { background: #e65100; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 16px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-value { font-size: 1.8em; font-weight: 700; color: #1565c0; }
        .stat-label { font-size: 0.85em; color: #666; margin-top: 4px; }
        
        .table-wrap { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; font-size: 0.88em; }
        th { background: #1565c0; color: white; padding: 12px 10px; text-align: left; font-weight: 600; position: sticky; top: 0; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f5f8ff; }
        tr:nth-child(even) { background: #fafbfc; }
        tr:nth-child(even):hover { background: #f5f8ff; }
        
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.8em; font-weight: 600; }
        .badge-mar { background: #e8f5e9; color: #2e7d32; }
        .badge-apr { background: #fff3e0; color: #e65100; }
        .badge-none { background: #f5f5f5; color: #999; }
        .badge-enriched { background: #e3f2fd; color: #1565c0; }
        .badge-pending { background: #fff8e1; color: #f57f17; }
        
        .phone-col { font-family: 'Courier New', monospace; white-space: nowrap; }
        
        .enrich-bar { background: #fff8e1; border: 1px solid #ffe082; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .enrich-bar .info { font-size: 0.9em; color: #795548; }
        .progress-bar { width: 200px; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; display: none; }
        .progress-bar .fill { height: 100%; background: #4caf50; transition: width 0.3s; }
        
        .checkbox-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .checkbox-group label { display: flex; align-items: center; gap: 4px; font-size: 0.9em; cursor: pointer; }
        
        @media (max-width: 768px) {
            .filter-row { flex-direction: column; }
            table { font-size: 0.8em; }
            th, td { padding: 6px 4px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🎂 Birthday Members</h1>
        <p>ลูกค้า Member ที่ซื้อสินค้า — กรองแบรนด์/เดือนเกิดเพื่อส่ง Birthday Discount</p>
    </div>
    
    <div class="nav">
        <a href="index.php" class="nav-btn">📊 Dashboard</a>
        <a href="brand_report.php" class="nav-btn">🏷️ Brand Report</a>
        <a href="birthday_members.php" class="nav-btn active">🎂 Birthday Members</a>
    </div>
    
    <!-- Filters -->
    <form class="filters" method="get">
        <div class="filter-row">
            <div class="filter-group">
                <label>📅 ตั้งแต่วันที่</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label>📅 ถึงวันที่</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="filter-group">
                <label>🏷️ แบรนด์</label>
                <select name="brand">
                    <option value="">ทุกแบรนด์</option>
                    <?php foreach ($brand_list as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>" <?= $brand_filter === $b ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>🏪 สาขา</label>
                <select name="store">
                    <option value="">ทุกสาขา</option>
                    <?php foreach ($stores_data as $sd): ?>
                    <option value="<?= htmlspecialchars($sd['store_code']) ?>" <?= $store_filter === $sd['store_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sd['store_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>🎂 เดือนเกิด</label>
                <div class="checkbox-group">
                    <?php
                    $monthTH = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
                    foreach ($monthTH as $num => $name):
                        $checked = in_array($num, $birth_months) ? 'checked' : '';
                    ?>
                    <label><input type="checkbox" name="bm[]" value="<?= $num ?>" <?= $checked ?>> <?= $name ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="margin-top: 12px; display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" onclick="buildMonths()">🔍 ค้นหา</button>
            <button type="button" class="btn btn-success" onclick="exportCSV()">📥 Export CSV</button>
        </div>
        <input type="hidden" name="birth_months" id="birth_months_input" value="<?= htmlspecialchars($birth_months_str) ?>">
    </form>
    
    <!-- Enrich Bar -->
    <?php
    // Count un-enriched from ALL Topologie members (not just filtered)
    $all_where = "SELECT DISTINCT customer FROM daily_sales WHERE sale_date BETWEEN ? AND ? AND customer IS NOT NULL AND customer != '' AND customer NOT LIKE 'WI%'";
    $all_params = [$date_from, $date_to];
    if ($brand_filter) {
        $all_where .= " AND brand = ?";
        $all_params[] = $brand_filter;
    }
    $all_stmt = $cmbase->prepare($all_where);
    $all_stmt->execute($all_params);
    $all_member_ids = $all_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($all_member_ids)) {
        $ph = implode(',', array_fill(0, count($all_member_ids), '?'));
        $enriched_stmt = $ulgcegid->prepare("SELECT customer_code FROM customers WHERE customer_code IN ($ph) AND birthday IS NOT NULL");
        $enriched_stmt->execute($all_member_ids);
        $enriched_ids = $enriched_stmt->fetchAll(PDO::FETCH_COLUMN);
        $unenriched = array_diff($all_member_ids, $enriched_ids);
        $unenriched_count = count($unenriched);
    } else {
        $unenriched_count = 0;
        $unenriched = [];
    }
    ?>
    <?php if ($unenriched_count > 0): ?>
    <div class="enrich-bar" id="enrichBar">
        <div class="info">
            ⚠️ มี <strong><?= $unenriched_count ?></strong> คนที่ยังไม่มีข้อมูลวันเกิด/เบอร์โทร — 
            กด Enrich เพื่อดึงจาก Cegid API
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="progress-bar" id="enrichProgress"><div class="fill" id="enrichFill"></div></div>
            <span id="enrichStatus" style="font-size: 0.85em; color: #666;"></span>
            <button class="btn btn-warning" id="enrichBtn" onclick="enrichMembers()">🔄 Enrich (<?= $unenriched_count ?>)</button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_members) ?></div>
            <div class="stat-label">👤 ลูกค้าทั้งหมด</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($with_birthday) ?></div>
            <div class="stat-label">🎂 มีวันเกิด</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($with_phone) ?></div>
            <div class="stat-label">📱 มีเบอร์โทร</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($total_members - $with_phone) ?></div>
            <div class="stat-label">❌ ไม่มีเบอร์</div>
        </div>
    </div>
    
    <!-- Table -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member ID</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>📱 เบอร์โทร</th>
                    <th>🎂 วันเกิด</th>
                    <th>เดือน</th>
                    <th>Type</th>
                    <th>สาขาที่ซื้อ</th>
                    <th>จำนวน</th>
                    <th>ยอดซื้อรวม</th>
                    <th>ซื้อล่าสุด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr><td colspan="11" style="text-align: center; padding: 40px; color: #999;">ไม่พบข้อมูล — ลองเปลี่ยน filter หรือกด Enrich ก่อน</td></tr>
                <?php else: ?>
                <?php foreach ($data as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($row['member']) ?></strong></td>
                    <td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?: '<span style="color:#ccc">-</span>' ?></td>
                    <td class="phone-col"><?= htmlspecialchars($row['phone']) ?: '<span style="color:#ccc">-</span>' ?></td>
                    <td><?= $row['birthday'] ? htmlspecialchars($row['birthday']) : '<span class="badge badge-none">ไม่มี</span>' ?></td>
                    <td>
                        <?php if ($row['birth_month'] === 3): ?>
                            <span class="badge badge-mar">มี.ค.</span>
                        <?php elseif ($row['birth_month'] === 4): ?>
                            <span class="badge badge-apr">เม.ย.</span>
                        <?php elseif ($row['birth_month']): ?>
                            <span class="badge badge-enriched"><?= $monthTH[$row['birth_month']] ?? $row['birth_month'] ?></span>
                        <?php else: ?>
                            <span class="badge badge-none">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['member_type']) ?: '-' ?></td>
                    <td style="font-size: 0.85em;"><?= htmlspecialchars($row['stores_bought']) ?></td>
                    <td style="text-align: center;"><?= number_format($row['purchase_count']) ?></td>
                    <td style="text-align: right;"><?= number_format($row['total_spent'], 0) ?></td>
                    <td><?= htmlspecialchars($row['last_purchase']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Build birth_months from checkboxes before form submit
function buildMonths() {
    const checked = [...document.querySelectorAll('input[name="bm[]"]:checked')].map(c => c.value);
    document.getElementById('birth_months_input').value = checked.join(',');
}

// Also intercept form submit
document.querySelector('form').addEventListener('submit', function() {
    buildMonths();
});

// Export CSV
function exportCSV() {
    buildMonths();
    const params = new URLSearchParams(new FormData(document.querySelector('form')));
    params.set('action', 'export');
    params.set('birth_months', document.getElementById('birth_months_input').value);
    window.location.href = 'birthday_members.php?' + params.toString();
}

// Enrich members via SOAP API
const unenrichedIds = <?= json_encode(array_values($unenriched ?? [])) ?>;

async function enrichMembers() {
    if (unenrichedIds.length === 0) return;
    
    const btn = document.getElementById('enrichBtn');
    const progress = document.getElementById('enrichProgress');
    const fill = document.getElementById('enrichFill');
    const status = document.getElementById('enrichStatus');
    
    btn.disabled = true;
    btn.textContent = '⏳ กำลังดึง...';
    progress.style.display = 'block';
    
    const batchSize = 20;
    let done = 0;
    const total = unenrichedIds.length;
    
    for (let i = 0; i < total; i += batchSize) {
        const batch = unenrichedIds.slice(i, i + batchSize);
        
        try {
            const formData = new FormData();
            formData.append('member_ids', JSON.stringify(batch));
            
            const resp = await fetch('birthday_members.php?action=enrich', {
                method: 'POST',
                body: formData
            });
            const result = await resp.json();
            done += batch.length;
            
            const pct = Math.round((done / total) * 100);
            fill.style.width = pct + '%';
            status.textContent = done + '/' + total + ' (' + pct + '%)';
        } catch (e) {
            console.error('Enrich error:', e);
            done += batch.length;
        }
    }
    
    btn.textContent = '✅ เสร็จแล้ว!';
    status.textContent = 'ดึงข้อมูลเสร็จแล้ว — กำลังรีเฟรช...';
    setTimeout(() => location.reload(), 1500);
}
</script>
</body>
</html>
