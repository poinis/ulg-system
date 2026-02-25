<?php
// transaction_detail.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// ✨ Get stores with store_code_new
$stores = $db->query("SELECT store_code, store_code_new, store_name FROM stores WHERE is_active = 1 ORDER BY store_code")->fetchAll();

// ✨ สร้าง mapping
$store_map = [];
foreach ($stores as $store) {
    $main_code = $store['store_code'];
    $new_code = $store['store_code_new'];
    
    $store_map[$main_code] = $main_code;
    if (!empty($new_code)) {
        $store_map[$new_code] = $main_code;
    }
}

// Get selected store and date range
$selected_store = $_GET['store'] ?? ($stores[0]['store_code'] ?? '');
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search_item = $_GET['search_item'] ?? '';
$search_member = $_GET['search_member'] ?? '';
$selected_brand = $_GET['brand'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// ✨ หา store codes ที่ต้อง query (รหัสหลัก + รหัสใหม่ถ้ามี)
$target_store_codes = [$selected_store];
foreach ($stores as $store) {
    if ($store['store_code'] == $selected_store && !empty($store['store_code_new'])) {
        $target_store_codes[] = $store['store_code_new'];
        break;
    }
}

// ✨ Build query conditions (ใช้ IN แทน =)
$store_ph = implode(',', array_fill(0, count($target_store_codes), '?'));
$conditions = ["store_code IN ($store_ph)"];
$params = $target_store_codes;

if ($date_from) {
    $conditions[] = "sale_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "sale_date <= ?";
    $params[] = $date_to;
}

if ($search_item) {
    $conditions[] = "(line_barcode LIKE ? OR item_description LIKE ?)";
    $params[] = "%$search_item%";
    $params[] = "%$search_item%";
}

if ($search_member) {
    $conditions[] = "(member LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search_member%";
    $params[] = "%$search_member%";
    $params[] = "%$search_member%";
}

if ($selected_brand) {
    $conditions[] = "brand = ?";
    $params[] = $selected_brand;
}

$where_clause = implode(" AND ", $conditions);

// ✨ Get brands for filter (จากทั้งรหัสหลักและรหัสใหม่)
$brands_stmt = $db->prepare("
    SELECT DISTINCT brand 
    FROM daily_sales 
    WHERE store_code IN ($store_ph) AND brand IS NOT NULL AND brand != ''
    ORDER BY brand
");
$brands_stmt->execute($target_store_codes);
$brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);

// Count total bills for pagination
$count_stmt = $db->prepare("
    SELECT COUNT(DISTINCT internal_ref) as total
    FROM daily_sales
    WHERE $where_clause
        AND internal_ref IS NOT NULL
        AND internal_ref != ''
");
$count_stmt->execute($params);
$total_bills = $count_stmt->fetch()['total'];
$total_pages = ceil($total_bills / $per_page);

// Get transaction details grouped by internal_ref (bills) with pagination
$bills_params = array_merge($params, [$per_page, $offset]);
$bills_stmt = $db->prepare("
    SELECT 
        internal_ref,
        sale_date,
        member,
        first_name,
        last_name,
        COUNT(*) as item_count,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as bill_total,
        MIN(created_at) as transaction_time
    FROM daily_sales
    WHERE $where_clause
        AND internal_ref IS NOT NULL
        AND internal_ref != ''
    GROUP BY internal_ref, sale_date, member, first_name, last_name
    ORDER BY sale_date DESC, transaction_time DESC
    LIMIT ? OFFSET ?
");
$bills_stmt->execute($bills_params);
$bills = $bills_stmt->fetchAll();

// Get summary statistics
// Count positive bills
$summary_pos_stmt = $db->prepare("
    SELECT COUNT(DISTINCT internal_ref) as bill_count
    FROM (
        SELECT internal_ref
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL
            AND internal_ref != ''
        GROUP BY internal_ref
        HAVING SUM(tax_incl_total) > 0
    ) as positive_bills
");
$summary_pos_stmt->execute($params);
$positive_bills = $summary_pos_stmt->fetch()['bill_count'] ?? 0;

// Count negative bills
$summary_neg_stmt = $db->prepare("
    SELECT COUNT(DISTINCT internal_ref) as bill_count
    FROM (
        SELECT internal_ref
        FROM daily_sales
        WHERE $where_clause
            AND internal_ref IS NOT NULL
            AND internal_ref != ''
        GROUP BY internal_ref
        HAVING SUM(tax_incl_total) < 0
    ) as negative_bills
");
$summary_neg_stmt->execute($params);
$negative_bills = $summary_neg_stmt->fetch()['bill_count'] ?? 0;

// Get other summary stats
$summary_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as total_sales,
        COUNT(DISTINCT member) as unique_members,
        COUNT(DISTINCT brand) as unique_brands
    FROM daily_sales
    WHERE $where_clause
        AND internal_ref IS NOT NULL
        AND internal_ref != ''
");
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch();

// Add bill count to summary
$summary['total_bills'] = $positive_bills - $negative_bills;

// Get top items
$top_items_stmt = $db->prepare("
    SELECT 
        item_description,
        line_barcode,
        brand,
        size,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as total_sales,
        COUNT(DISTINCT internal_ref) as bill_count,
        AVG(tax_incl_total) as avg_price
    FROM daily_sales
    WHERE $where_clause
        AND item_description IS NOT NULL
        AND item_description != ''
    GROUP BY line_barcode, item_description, brand, size
    ORDER BY total_sales DESC
    LIMIT 30
");
$top_items_stmt->execute($params);
$top_items = $top_items_stmt->fetchAll();

// Get member summary
$member_stmt = $db->prepare("
    SELECT 
        member,
        first_name,
        last_name,
        COUNT(DISTINCT internal_ref) as purchase_count,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as total_spent,
        MAX(sale_date) as last_purchase
    FROM daily_sales
    WHERE $where_clause
        AND member IS NOT NULL
        AND member != ''
    GROUP BY member, first_name, last_name
    ORDER BY total_spent DESC
    LIMIT 30
");
$member_stmt->execute($params);
$top_members = $member_stmt->fetchAll();

// Get size analysis
$size_stmt = $db->prepare("
    SELECT 
        size,
        COUNT(*) as item_count,
        SUM(qty) as total_qty,
        SUM(tax_incl_total) as total_sales
    FROM daily_sales
    WHERE $where_clause
        AND size IS NOT NULL
        AND size != ''
    GROUP BY size
    ORDER BY total_sales DESC
");
$size_stmt->execute($params);
$size_analysis = $size_stmt->fetchAll();

// Get store name
$store_name = '';
foreach ($stores as $s) {
    if ($s['store_code'] == $selected_store) {
        $store_name = $s['store_name'];
        break;
    }
}

// Build query string for pagination
function buildQueryString($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดบิลและสินค้า</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1600px; margin: 0 auto; }
        h1 { font-size: 28px; margin-bottom: 10px; }
        .container { max-width: 1600px; margin: 20px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: white; color: #667eea; text-decoration: none; border-radius: 5px; font-weight: 500; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .back-link:hover { background: #667eea; color: white; }
        
        .filters { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-row:last-child { margin-bottom: 0; }
        .filters select, .filters input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filters button { padding: 10px 25px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .filters button:hover { background: #5568d3; }
        .filter-label { display: flex; flex-direction: column; gap: 5px; }
        .filter-label span { font-size: 12px; color: #666; font-weight: 500; }
        
        /* Quick date filters */
        .quick-filters { display: flex; gap: 10px; align-items: flex-end; }
        .quick-filter-btn { 
            padding: 10px 20px; 
            background: #f8f9fa; 
            color: #495057; 
            border: 1px solid #dee2e6; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 13px;
            transition: all 0.2s;
        }
        .quick-filter-btn:hover { 
            background: #e9ecef; 
            border-color: #adb5bd;
        }
        .quick-filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-label { color: #666; font-size: 13px; margin-bottom: 5px; }
        .stat-value { font-size: 28px; font-weight: bold; color: #333; }
        .stat-sub { font-size: 12px; color: #999; margin-top: 5px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab { padding: 12px 24px; background: white; border: none; border-radius: 8px 8px 0 0; cursor: pointer; font-size: 14px; font-weight: 500; color: #666; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .tab.active { background: #667eea; color: white; }
        .tab:hover:not(.active) { background: #f0f0f0; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { margin-bottom: 20px; color: #333; font-size: 20px; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; position: sticky; top: 0; z-index: 10; }
        .number { text-align: right; font-family: 'Courier New', monospace; }
        tr:hover { background: #f8f9fa; }
        
        .member-name { font-weight: 500; color: #333; }
        .member-id { font-size: 11px; color: #999; font-family: monospace; }
        
        /* Accordion styles */
        .bill-row { transition: background 0.2s; }
        .bill-row:hover { background: #f0f0ff !important; }
        .bill-row.expanded { background: #e8ecff !important; }
        
        .bill-detail { display: none; }
        .bill-detail.show { display: table-row; }
        .bill-detail td { padding: 0 !important; background: #f8f9ff; }
        
        .detail-content { padding: 20px; animation: slideDown 0.3s ease-out; }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .detail-items-table { width: 100%; font-size: 12px; background: white; border-radius: 5px; overflow: hidden; }
        .detail-items-table th { background: #667eea; color: white; padding: 10px; font-size: 11px; }
        .detail-items-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .detail-items-table tr:last-child td { border-bottom: none; }
        .detail-items-table tr:hover { background: #f0f0f0; }
        
        .loading { text-align: center; padding: 20px; color: #667eea; }
        .error { text-align: center; padding: 20px; color: #dc3545; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { 
            padding: 8px 12px; 
            background: white; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            text-decoration: none; 
            color: #667eea;
            font-size: 14px;
        }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination .current { background: #667eea; color: white; border-color: #667eea; font-weight: 600; }
        .pagination .disabled { color: #ccc; cursor: not-allowed; }
        .pagination .disabled:hover { background: white; color: #ccc; }
        
        @media print {
            .filters, .tabs, .back-link, button, .pagination { display: none; }
            .card { box-shadow: none; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🧾 รายละเอียดบิลและสินค้า</h1>
            <p style="opacity: 0.9;"><?php echo $store_name; ?></p>
        </div>
    </div>
    
    <div class="container">
        <a href="detailed_report.php?store=<?php echo $selected_store; ?>" class="back-link">← กลับรายงานสาขา</a>
        
        <form method="GET" class="filters">
            <div class="filter-row">
                <label class="filter-label">
                    <span>สาขา</span>
                    <select name="store" onchange="this.form.submit()">
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['store_code']; ?>" 
                                <?php echo $store['store_code'] == $selected_store ? 'selected' : ''; ?>>
                                <?php echo $store['store_code'] . ' - ' . $store['store_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                
                <label class="filter-label">
                    <span>วันที่เริ่มต้น</span>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </label>
                
                <label class="filter-label">
                    <span>วันที่สิ้นสุด</span>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </label>
                
                <label class="filter-label">
                    <span>แบรนด์</span>
                    <select name="brand">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand; ?>" <?php echo $brand == $selected_brand ? 'selected' : ''; ?>>
                                <?php echo $brand; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            
            <div class="filter-row">
                <label class="filter-label">
                    <span>ตัวกรองวันด่วน</span>
                    <div class="quick-filters">
                        <button type="button" class="quick-filter-btn" onclick="setDateFilter('yesterday')">📅 เมื่อวาน</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateFilter('last7days')">📊 7 วันล่าสุด</button>
                        <button type="button" class="quick-filter-btn" onclick="setDateFilter('last30days')">📈 30 วันล่าสุด</button>
                    </div>
                </label>
            </div>
            
            <div class="filter-row">
                <label class="filter-label">
                    <span>ค้นหาสินค้า (บาร์โค้ด/ชื่อ)</span>
                    <input type="text" name="search_item" value="<?php echo htmlspecialchars($search_item); ?>" 
                           placeholder="ค้นหาบาร์โค้ดหรือชื่อสินค้า" style="width: 250px;">
                </label>
                
                <label class="filter-label">
                    <span>ค้นหาสมาชิก</span>
                    <input type="text" name="search_member" value="<?php echo htmlspecialchars($search_member); ?>" 
                           placeholder="รหัส/ชื่อ/นามสกุล" style="width: 200px;">
                </label>
                
                <label class="filter-label">
                    <span>&nbsp;</span>
                    <button type="submit">🔍 ค้นหา</button>
                </label>
            </div>
        </form>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">จำนวนบิล</div>
                <div class="stat-value" style="color: #667eea;"><?php echo number_format($summary['total_bills']); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">ยอดขายรวม</div>
                <div class="stat-value" style="color: #764ba2;"><?php echo formatNumber($summary['total_sales'], 0); ?></div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">ค่าเฉลี่ย/บิล</div>
                <div class="stat-value" style="color: #28a745;">
                    <?php echo $summary['total_bills'] > 0 ? formatNumber($summary['total_sales'] / $summary['total_bills'], 0) : 0; ?>
                </div>
                <div class="stat-sub">บาท</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">รายการสินค้า</div>
                <div class="stat-value" style="color: #ff6b6b;"><?php echo number_format($summary['total_items']); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">จำนวนชิ้น</div>
                <div class="stat-value" style="color: #17a2b8;"><?php echo number_format($summary['total_qty']); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">จำนวนชิ้น/บิล</div>
                <div class="stat-value" style="color: #6c757d;">
                    <?php echo $summary['total_bills'] > 0 ? number_format($summary['total_qty'] / $summary['total_bills'], 2) : 0; ?>
                </div>
                <div class="stat-sub">ชิ้น</div>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('bills')">📋 รายการบิล</button>
            <button class="tab" onclick="switchTab('top-items')">🏆 สินค้าขายดี</button>
            <button class="tab" onclick="switchTab('top-members')">👥 สมาชิกประจำ</button>
            <button class="tab" onclick="switchTab('size-analysis')">📏 วิเคราะห์ไซส์</button>
        </div>
        
        <div id="bills" class="tab-content active">
            <div class="card">
                <h2>รายการบิลทั้งหมด (หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?>) - แสดง <?php echo count($bills); ?> บิล</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>เลขที่บิล</th>
                                <th>สมาชิก</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th class="number">รายการ</th>
                                <th class="number">ชิ้น</th>
                                <th class="number">ยอดรวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $index => $row): ?>
                                <tr class="bill-row expanded">
                                    <td><?php echo formatDate($row['sale_date']); ?></td>
                                    <td style="font-family: monospace; font-size: 12px;">
                                        <?php echo htmlspecialchars($row['internal_ref']); ?>
                                    </td>
                                    <td>
                                        <div class="member-id"><?php echo htmlspecialchars($row['member']); ?></div>
                                    </td>
                                    <td>
                                        <div class="member-name">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="number"><?php echo number_format($row['item_count']); ?></td>
                                    <td class="number"><?php echo number_format($row['total_qty']); ?></td>
                                    <td class="number"><strong><?php echo formatNumber($row['bill_total'], 0); ?></strong></td>
                                </tr>
                                <tr class="bill-detail show" id="detail-<?php echo $index; ?>" data-ref="<?php echo htmlspecialchars($row['internal_ref']); ?>" data-store="<?php echo $selected_store; ?>">
                                    <td colspan="7">
                                        <div class="detail-content" id="content-<?php echo $index; ?>">
                                            <div class="loading">กำลังโหลด...</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo buildQueryString(['page']); ?>&page=1">« แรก</a>
                        <a href="?<?php echo buildQueryString(['page']); ?>&page=<?php echo $page - 1; ?>">‹ ก่อนหน้า</a>
                    <?php else: ?>
                        <span class="disabled">« แรก</span>
                        <span class="disabled">‹ ก่อนหน้า</span>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo buildQueryString(['page']); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo buildQueryString(['page']); ?>&page=<?php echo $page + 1; ?>">ถัดไป ›</a>
                        <a href="?<?php echo buildQueryString(['page']); ?>&page=<?php echo $total_pages; ?>">สุดท้าย »</a>
                    <?php else: ?>
                        <span class="disabled">ถัดไป ›</span>
                        <span class="disabled">สุดท้าย »</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="top-items" class="tab-content">
            <div class="card">
                <h2>Top 30 สินค้าขายดี</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>บาร์โค้ด</th>
                                <th>ชื่อสินค้า</th>
                                <th>แบรนด์</th>
                                <th>ไซส์</th>
                                <th class="number">จำนวนขาย</th>
                                <th class="number">ยอดขายรวม</th>
                                <th class="number">จำนวนบิล</th>
                                <th class="number">ราคาเฉลี่ย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_items as $index => $row): ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold; color: #667eea;">
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px;">
                                        <?php echo htmlspecialchars($row['line_barcode']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['item_description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['brand']); ?></td>
                                    <td style="text-align: center; font-weight: 500;">
                                        <?php echo htmlspecialchars($row['size']); ?>
                                    </td>
                                    <td class="number"><?php echo number_format($row['total_qty']); ?></td>
                                    <td class="number"><strong><?php echo formatNumber($row['total_sales'], 0); ?></strong></td>
                                    <td class="number"><?php echo number_format($row['bill_count']); ?></td>
                                    <td class="number"><?php echo formatNumber($row['avg_price'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="top-members" class="tab-content">
            <div class="card">
                <h2>Top 30 สมาชิกประจำ</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>รหัสสมาชิก</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th class="number">จำนวนซื้อ</th>
                                <th class="number">ชิ้นทั้งหมด</th>
                                <th class="number">ยอดรวม</th>
                                <th class="number">เฉลี่ย/ครั้ง</th>
                                <th>ซื้อล่าสุด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_members as $index => $row): ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold; color: #667eea;">
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td>
                                        <div class="member-id"><?php echo htmlspecialchars($row['member']); ?></div>
                                    </td>
                                    <td>
                                        <div class="member-name">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="number"><?php echo number_format($row['purchase_count']); ?> บิล</td>
                                    <td class="number"><?php echo number_format($row['total_qty']); ?></td>
                                    <td class="number"><strong><?php echo formatNumber($row['total_spent'], 0); ?></strong></td>
                                    <td class="number">
                                        <?php echo formatNumber($row['total_spent'] / $row['purchase_count'], 0); ?>
                                    </td>
                                    <td><?php echo formatDate($row['last_purchase']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="size-analysis" class="tab-content">
            <div class="card">
                <h2>📏 วิเคราะห์การขายตามไซส์</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ไซส์</th>
                                <th class="number">จำนวนรายการ</th>
                                <th class="number">จำนวนชิ้น</th>
                                <th class="number">ยอดขาย</th>
                                <th class="number">% ของยอดรวม</th>
                                <th class="number">ราคาเฉลี่ย/ชิ้น</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_sales_all = array_sum(array_column($size_analysis, 'total_sales'));
                            foreach ($size_analysis as $row): 
                                $pct = $total_sales_all > 0 ? ($row['total_sales'] / $total_sales_all * 100) : 0;
                                $avg_price = $row['total_qty'] > 0 ? $row['total_sales'] / $row['total_qty'] : 0;
                            ?>
                                <tr>
                                    <td style="font-weight: 600; font-size: 15px;">
                                        <?php echo htmlspecialchars($row['size']); ?>
                                    </td>
                                    <td class="number"><?php echo number_format($row['item_count']); ?></td>
                                    <td class="number"><?php echo number_format($row['total_qty']); ?></td>
                                    <td class="number"><strong><?php echo formatNumber($row['total_sales'], 0); ?></strong></td>
                                    <td class="number"><?php echo number_format($pct, 1); ?>%</td>
                                    <td class="number"><?php echo formatNumber($avg_price, 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const loadedBills = new Set();
        
        function setDateFilter(type) {
            const today = new Date();
            let dateFrom, dateTo;
            
            // Set dateTo to today
            dateTo = formatDate(today);
            
            switch(type) {
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(today.getDate() - 1);
                    dateFrom = formatDate(yesterday);
                    dateTo = formatDate(yesterday);
                    break;
                    
                case 'last7days':
                    const last7 = new Date(today);
                    last7.setDate(today.getDate() - 7);
                    dateFrom = formatDate(last7);
                    break;
                    
                case 'last30days':
                    const last30 = new Date(today);
                    last30.setDate(today.getDate() - 30);
                    dateFrom = formatDate(last30);
                    break;
            }
            
            // Update form inputs
            document.querySelector('input[name="date_from"]').value = dateFrom;
            document.querySelector('input[name="date_to"]').value = dateTo;
            
            // Submit the form
            document.querySelector('.filters').submit();
        }
        
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        async function loadBillDetail(detailRow) {
            const internalRef = detailRow.getAttribute('data-ref');
            const storeCode = detailRow.getAttribute('data-store');
            
            // Check if already loaded
            if (loadedBills.has(internalRef)) {
                return;
            }
            
            const contentDiv = detailRow.querySelector('.detail-content');
            
            try {
                const response = await fetch(`get_bill_items.php?ref=${encodeURIComponent(internalRef)}&store=${encodeURIComponent(storeCode)}`);
                const data = await response.json();
                
                if (data.success && data.items && data.items.length > 0) {
                    let html = '<table class="detail-items-table">';
                    html += '<thead><tr>';
                    html += '<th style="width: 40px;">#</th>';
                    html += '<th>บาร์โค้ด</th>';
                    html += '<th>ชื่อสินค้า</th>';
                    html += '<th>แบรนด์</th>';
                    html += '<th>แผนก</th>';
                    html += '<th>กลุ่ม</th>';
                    html += '<th>คลาส</th>';
                    html += '<th>ไซส์</th>';
                    html += '<th class="number">จำนวน</th>';
                    html += '<th class="number">ราคา/ชิ้น</th>';
                    html += '<th class="number">รวมเงิน</th>';
                    html += '</tr></thead><tbody>';
                    
                    let subtotal = 0;
                    
                    data.items.forEach((item, index) => {
                        const qty = parseFloat(item.qty || 0);
                        const basePrice = parseFloat(item.base_price || 0);
                        const itemSubtotal = basePrice * qty;
                        subtotal += itemSubtotal;
                        
                        html += '<tr>';
                        html += `<td style="text-align: center; color: #999;">${index + 1}</td>`;
                        html += `<td style="font-family: monospace; font-size: 11px;">${escapeHtml(item.line_barcode || '')}</td>`;
                        html += `<td>${escapeHtml(item.item_description || '')}</td>`;
                        html += `<td>${escapeHtml(item.brand || '')}</td>`;
                        html += `<td>${escapeHtml(item.sales_division || '')}</td>`;
                        html += `<td style="font-size: 11px;">${escapeHtml(item.group_name || '')}</td>`;
                        html += `<td style="font-size: 11px;">${escapeHtml(item.class_name || '')}</td>`;
                        html += `<td style="text-align: center; font-weight: 500;">${escapeHtml(item.size || '')}</td>`;
                        html += `<td class="number">${formatNumber(qty)}</td>`;
                        html += `<td class="number">${formatNumber(basePrice, 2)}</td>`;
                        html += `<td class="number"><strong>${formatNumber(item.tax_incl_total, 2)}</strong></td>`;
                        html += '</tr>';
                    });
                    
                    // Calculate totals
                    const totalQty = data.items.reduce((sum, item) => sum + parseFloat(item.qty || 0), 0);
                    const totalAmount = data.items.reduce((sum, item) => sum + parseFloat(item.tax_incl_total || 0), 0);
                    const totalDiscount = subtotal - totalAmount;
                    
                    // Add summary section
                    html += '<tr style="background: #fff; border-top: 2px solid #ddd;"><td colspan="11" style="padding: 0;"></td></tr>';
                    
                    // Subtotal row
                    html += '<tr style="background: #f8f9fa;">';
                    html += '<td colspan="8" style="text-align: right; padding-right: 20px; color: #666;">รวมราคาสินค้า:</td>';
                    html += `<td class="number" style="font-weight: 600;">${formatNumber(totalQty)}</td>`;
                    html += '<td></td>';
                    html += `<td class="number" style="font-weight: 600;">${formatNumber(subtotal, 2)}</td>`;
                    html += '</tr>';
                    
                    // Discount row (if exists)
                    if (totalDiscount > 0.01) {
                        html += '<tr style="background: #fff8f8;">';
                        html += '<td colspan="10" style="text-align: right; padding-right: 20px; color: #dc3545; font-weight: 500;">ส่วนลด:</td>';
                        html += `<td class="number" style="color: #dc3545; font-weight: 600;">-${formatNumber(totalDiscount, 2)}</td>`;
                        html += '</tr>';
                    }
                    
                    // Total row
                    html += '<tr style="background: #e8ecff; font-weight: bold; border-top: 2px solid #667eea;">';
                    html += '<td colspan="10" style="text-align: right; padding-right: 20px; color: #667eea; font-size: 15px;">ยอดรวมสุทธิ:</td>';
                    html += `<td class="number" style="color: #667eea; font-size: 15px;">${formatNumber(totalAmount, 2)}</td>`;
                    html += '</tr>';
                    
                    html += '</tbody></table>';
                    
                    contentDiv.innerHTML = html;
                    loadedBills.add(internalRef);
                } else {
                    contentDiv.innerHTML = '<div class="error">ไม่พบข้อมูลรายการสินค้า</div>';
                }
            } catch (error) {
                console.error('Error loading bill details:', error);
                contentDiv.innerHTML = '<div class="error">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
            }
        }
        
        // Load all bill details when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const allDetailRows = document.querySelectorAll('.bill-detail');
            allDetailRows.forEach(detailRow => {
                loadBillDetail(detailRow);
            });
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatNumber(num, decimals = 0) {
            const n = parseFloat(num) || 0;
            return n.toLocaleString('th-TH', { 
                minimumFractionDigits: decimals, 
                maximumFractionDigits: decimals 
            });
        }
    </script>
</body>
</html>