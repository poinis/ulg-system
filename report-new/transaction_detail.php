<?php
// transaction_detail.php
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

$db = Database::getInstance()->getConnection();

// 1. ดึงรายชื่อร้านค้า (Active Only) - Clean Logic
try {
    $stores = $db->query("SELECT store_code, store_name FROM stores WHERE is_active = 1 ORDER BY store_code")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching stores: " . $e->getMessage());
}

// Get Params
$selected_store = $_GET['store'] ?? ($stores[0]['store_code'] ?? '');
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search_item = $_GET['search_item'] ?? '';
$search_member = $_GET['search_member'] ?? '';
$selected_brand = $_GET['brand'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 2. สร้างเงื่อนไข SQL (ใช้ store_code ตรงๆ ไม่ต้อง Map)
$conditions = ["store_code = ?"];
$params = [$selected_store];

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

// 3. ดึง Brands เพื่อทำตัวกรอง
$brands = [];
if ($selected_store) {
    try {
        $b_stmt = $db->prepare("SELECT DISTINCT brand FROM daily_sales WHERE store_code = ? AND brand IS NOT NULL AND brand != '' ORDER BY brand");
        $b_stmt->execute([$selected_store]);
        $brands = $b_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { }
}

// 4. Count Bills (Pagination)
try {
    $count_stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as total FROM daily_sales WHERE $where_clause AND internal_ref IS NOT NULL AND internal_ref != ''");
    $count_stmt->execute($params);
    $total_bills = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total_bills / $per_page);
} catch (Exception $e) {
    $total_bills = 0; $total_pages = 0;
}

// 5. Query Bills Header
$bills = [];
try {
    // ใส่ LIMIT ลงใน SQL โดยตรงเพื่อแก้ Error 500
    $bills_sql = "
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
        LIMIT $per_page OFFSET $offset
    ";
    
    $bills_stmt = $db->prepare($bills_sql);
    $bills_stmt->execute($params);
    $bills = $bills_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { die("Error fetching bills: " . $e->getMessage()); }

// 6. ✨ Pre-fetch Items (Server-Side Loading) - ดึงข้อมูลสินค้ามารอเลย
$bill_items_map = [];
if (!empty($bills)) {
    try {
        $refs = array_column($bills, 'internal_ref');
        $in_placeholders = implode(',', array_fill(0, count($refs), '?'));
        
        $items_sql = "
            SELECT *
            FROM daily_sales
            WHERE internal_ref IN ($in_placeholders)
            ORDER BY internal_ref, created_at
        ";
        
        $items_stmt = $db->prepare($items_sql);
        $items_stmt->execute($refs);
        $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_items as $item) {
            $ref = $item['internal_ref'];
            $bill_items_map[$ref][] = $item;
        }
    } catch (Exception $e) { }
}

// 7. Summary Stats (ครบ 6 ช่อง)
$summary = [
    'total_bills' => 0, 'total_sales' => 0, 'total_qty' => 0, 
    'total_items' => 0, 'avg_bill' => 0, 'avg_qty_bill' => 0
];
try {
    // Pos/Neg Logic
    $pos_stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND internal_ref IS NOT NULL GROUP BY internal_ref HAVING SUM(tax_incl_total) > 0) t");
    $pos_stmt->execute($params); $pos = $pos_stmt->fetch()['cnt'] ?? 0;
    
    $neg_stmt = $db->prepare("SELECT COUNT(DISTINCT internal_ref) as cnt FROM (SELECT internal_ref FROM daily_sales WHERE $where_clause AND internal_ref IS NOT NULL GROUP BY internal_ref HAVING SUM(tax_incl_total) < 0) t");
    $neg_stmt->execute($params); $neg = $neg_stmt->fetch()['cnt'] ?? 0;
    
    // Totals
    $sum_stmt = $db->prepare("SELECT COUNT(*) as total_items, SUM(qty) as total_qty, SUM(tax_incl_total) as total_sales FROM daily_sales WHERE $where_clause AND internal_ref IS NOT NULL");
    $sum_stmt->execute($params);
    $d = $sum_stmt->fetch();
    
    $summary['total_bills'] = $pos - $neg;
    $summary['total_sales'] = $d['total_sales']??0;
    $summary['total_qty'] = $d['total_qty']??0;
    $summary['total_items'] = $d['total_items']??0;
    
    if($summary['total_bills'] > 0) {
        $summary['avg_bill'] = $summary['total_sales'] / $summary['total_bills'];
        $summary['avg_qty_bill'] = $summary['total_qty'] / $summary['total_bills'];
    }
} catch(Exception $e) {}

// 8. Top Lists (Item, Member, Size)
$top_items = [];
try {
    $top_sql = "SELECT item_description, line_barcode, brand, size, SUM(qty) as total_qty, SUM(tax_incl_total) as total_sales, COUNT(DISTINCT internal_ref) as bill_count, AVG(tax_incl_total) as avg_price FROM daily_sales WHERE $where_clause AND item_description IS NOT NULL GROUP BY line_barcode, item_description, brand, size ORDER BY total_sales DESC LIMIT 30";
    $top_stmt = $db->prepare($top_sql); $top_stmt->execute($params); $top_items = $top_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

$top_members = [];
try {
    $mem_sql = "SELECT member, first_name, last_name, COUNT(DISTINCT internal_ref) as purchase_count, SUM(qty) as total_qty, SUM(tax_incl_total) as total_spent, MAX(sale_date) as last_purchase FROM daily_sales WHERE $where_clause AND member IS NOT NULL AND member != '' GROUP BY member, first_name, last_name ORDER BY total_spent DESC LIMIT 30";
    $mem_stmt = $db->prepare($mem_sql); $mem_stmt->execute($params); $top_members = $mem_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

$size_analysis = [];
try {
    $sz_sql = "SELECT size, COUNT(*) as item_count, SUM(qty) as total_qty, SUM(tax_incl_total) as total_sales FROM daily_sales WHERE $where_clause AND size IS NOT NULL AND size != '' GROUP BY size ORDER BY total_sales DESC";
    $sz_stmt = $db->prepare($sz_sql); $sz_stmt->execute($params); $size_analysis = $sz_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

// Display Name
$current_store_name = $selected_store;
foreach ($stores as $s) {
    if ($s['store_code'] == $selected_store) { $current_store_name = $s['store_name']; break; }
}

function buildQueryString($exclude = []) {
    $params = $_GET; foreach ($exclude as $key) unset($params[$key]); return http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดบิลและสินค้า - <?=$current_store_name?></title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; margin:0; padding:0; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .container { max-width: 1600px; margin: 20px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: white; color: #667eea; text-decoration: none; border-radius: 5px; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-label { display: flex; flex-direction: column; font-size: 13px; color: #666; font-weight: bold; gap:5px; }
        .filter-label input, .filter-label select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px; }
        .btn-search { padding: 10px 25px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-search:hover { background: #5a67d8; }
        
        /* Quick Filters */
        .quick-filters { display: flex; gap: 10px; align-items: flex-end; }
        .quick-filter-btn { padding: 10px 15px; background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
        .quick-filter-btn:hover { background: #e9ecef; border-color: #adb5bd; }
        
        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; }
        .stat-label { font-size: 13px; color: #666; margin-bottom: 5px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #333; }
        .stat-sub { font-size: 12px; color: #999; }
        
        /* Tabs */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { padding: 12px 20px; background: white; border: none; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: bold; color: #666; transition: 0.2s; }
        .tab.active { background: #667eea; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Tables */
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h3 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .number { text-align: right; font-family: monospace; font-weight: 500; }
        
        /* Bill & Detail Rows */
        .bill-row { background: #fff; cursor: pointer; border-bottom: none; }
        .bill-row:hover { background: #f0f0ff; }
        .bill-detail { display: table-row; background: #f9faff; } /* ✨ สำคัญ: ให้โชว์เลย */
        .detail-content { padding: 10px 20px 20px 20px; border-left: 4px solid #667eea; border-bottom: 1px solid #ddd; }
        
        /* Detail Table */
        .sub-table { width: 100%; background: white; border-radius: 5px; overflow: hidden; margin-top: 5px; border: 1px solid #eee; }
        .sub-table th { background: #667eea; color: white; font-size: 12px; padding: 8px; font-weight: normal; }
        .sub-table td { padding: 6px 8px; font-size: 12px; border-bottom: 1px solid #f0f0f0; color: #444; }
        .sub-table tr:last-child td { border-bottom: none; }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; }
        .pagination .current { background: #667eea; color: white; border-color: #667eea; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container" style="margin:0 auto;">
            <h1 style="margin:0; font-size: 24px;">🧾 รายละเอียดบิลและสินค้า</h1>
            <p style="margin:5px 0 0; opacity: 0.9; font-size: 14px;"><?=$current_store_name?> (<?=$selected_store?>)</p>
        </div>
    </div>
    
    <div class="container">
        <a href="detailed_report.php?store=<?=$selected_store?>" class="back-link">← กลับหน้ารายงาน</a>
        
        <form method="GET" class="filters">
            <div class="filter-label">
                <span>สาขา</span>
                <select name="store" onchange="this.form.submit()">
                    <?php foreach($stores as $s): ?>
                    <option value="<?=$s['store_code']?>" <?=$s['store_code']==$selected_store?'selected':''?>>
                        <?=$s['store_code']?> - <?=$s['store_name']?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-label">
                <span>ตัวกรองวันด่วน</span>
                <div class="quick-filters">
                    <button type="button" class="quick-filter-btn" onclick="setDateFilter('yesterday')">📅 เมื่อวาน</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateFilter('last7days')">📊 7 วันล่าสุด</button>
                    <button type="button" class="quick-filter-btn" onclick="setDateFilter('last30days')">📈 30 วันล่าสุด</button>
                </div>
            </div>

            <div class="filter-label"><span>เริ่ม</span><input type="date" name="date_from" value="<?=$date_from?>"></div>
            <div class="filter-label"><span>ถึง</span><input type="date" name="date_to" value="<?=$date_to?>"></div>
            <div class="filter-label"><span>ค้นหาสินค้า</span><input type="text" name="search_item" value="<?=htmlspecialchars($search_item)?>" placeholder="barcode/ชื่อ"></div>
            <div class="filter-label"><span>ค้นหาสมาชิก</span><input type="text" name="search_member" value="<?=htmlspecialchars($search_member)?>" placeholder="ref/ชื่อ"></div>
            <div class="filter-label"><span>&nbsp;</span><button type="submit" class="btn-search">🔍 ค้นหา</button></div>
        </form>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">จำนวนบิล</div><div class="stat-value" style="color:#667eea"><?=number_format($summary['total_bills'])?></div></div>
            <div class="stat-card"><div class="stat-label">ยอดขายรวม</div><div class="stat-value" style="color:#764ba2"><?=formatNumber($summary['total_sales'])?></div><div class="stat-sub">บาท</div></div>
            <div class="stat-card"><div class="stat-label">เฉลี่ย/บิล</div><div class="stat-value" style="color:#28a745"><?=formatNumber($summary['avg_bill'])?></div><div class="stat-sub">บาท</div></div>
            <div class="stat-card"><div class="stat-label">รายการสินค้า</div><div class="stat-value" style="color:#ff6b6b"><?=number_format($summary['total_items'])?></div></div>
            <div class="stat-card"><div class="stat-label">จำนวนชิ้น</div><div class="stat-value" style="color:#17a2b8"><?=number_format($summary['total_qty'])?></div></div>
            <div class="stat-card"><div class="stat-label">ชิ้น/บิล</div><div class="stat-value" style="color:#6c757d"><?=formatNumber($summary['avg_qty_bill'], 2)?></div></div>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="openTab('bills', this)">📋 รายการบิล</button>
            <button class="tab" onclick="openTab('items', this)">🏆 สินค้าขายดี</button>
            <button class="tab" onclick="openTab('members', this)">👥 สมาชิกประจำ</button>
            <button class="tab" onclick="openTab('sizes', this)">📏 วิเคราะห์ไซส์</button>
        </div>
        
        <div id="bills" class="tab-content active">
            <div class="card">
                <h3>รายการบิล (หน้า <?=$page?>/<?=$total_pages?>) - ทั้งหมด <?=number_format($total_bills)?> บิล</h3>
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>วันที่</th><th>เลขที่บิล</th><th>สมาชิก</th><th>ชื่อลูกค้า</th>
                                <th class="number">รายการ</th><th class="number">ชิ้น</th><th class="number">ยอดรวม</th>
                                <th width="30"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($bills as $i => $row): 
                                $ref = $row['internal_ref'];
                                $items = $bill_items_map[$ref] ?? [];
                                
                                // ✨ คำนวณยอดรวมและส่วนลดใน PHP (ไม่ต้องพึ่ง JS)
                                $subtotal = 0;
                                foreach($items as $item) {
                                    $subtotal += ($item['base_price'] * $item['qty']);
                                }
                                $final_total = $row['bill_total'];
                                $discount = $subtotal - $final_total;
                            ?>
                            <tr class="bill-row" onclick="toggleRow('detail-<?=$i?>')">
                                <td><?=date('d/m/Y', strtotime($row['sale_date']))?></td>
                                <td style="font-family:monospace; font-weight:bold; color:#0288d1;"><?=$ref?></td>
                                <td><?=$row['member']?></td>
                                <td><?=$row['first_name']?> <?=$row['last_name']?></td>
                                <td class="number"><?=number_format($row['item_count'])?></td>
                                <td class="number"><?=number_format($row['total_qty'])?></td>
                                <td class="number" style="font-size:14px; font-weight:bold;"><?=formatNumber($row['bill_total'])?></td>
                                <td style="text-align:center; color:#999;">▼</td>
                            </tr>
                            
                            <tr id="detail-<?=$i?>" class="bill-detail"> <td colspan="8" style="padding:0;">
                                    <div class="detail-content">
                                        <table class="sub-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Barcode</th>
                                                    <th>สินค้า</th>
                                                    <th>Brand</th>
                                                    <th>Size</th>
                                                    <th class="number">ราคา/ชิ้น</th>
                                                    <th class="number">จำนวน</th>
                                                    <th class="number">รวม</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if(empty($items)): ?>
                                                    <tr><td colspan="8" style="text-align:center;">ไม่พบรายการสินค้า</td></tr>
                                                <?php else:
                                                    foreach($items as $k => $item): 
                                                ?>
                                                <tr>
                                                    <td style="color:#fff; text-align:center;"><?=$k+1?></td>
                                                    <td style="font-family:monospace;"><?=$item['line_barcode']?></td>
                                                    <td><?=$item['item_description']?></td>
                                                    <td><?=$item['brand']?></td>
                                                    <td style="text-align:center;"><?=$item['size']?></td>
                                                    <td class="number"><?=formatNumber($item['base_price'])?></td>
                                                    <td class="number"><?=formatNumber($item['qty'])?></td>
                                                    <td class="number" style="font-weight:bold;"><?=formatNumber($item['tax_incl_total'])?></td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                                
                                                <tr style="background:#f9f9f9;">
                                                    <td colspan="6"></td>
                                                    <td style="text-align:right;">รวมเป็นเงิน:</td>
                                                    <td class="number"><?=formatNumber($subtotal, 2)?></td>
                                                </tr>
                                                <?php if($discount > 0.01): ?>
                                                <tr style="background:#fff0f0; color:#d00;">
                                                    <td colspan="6"></td>
                                                    <td style="text-align:right;">ส่วนลด:</td>
                                                    <td class="number">-<?=formatNumber($discount, 2)?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr style="background:#e8f4ff; font-weight:bold; border-top:1px solid #ccc;">
                                                    <td colspan="6"></td>
                                                    <td style="text-align:right;">ยอดสุทธิ:</td>
                                                    <td class="number" style="font-size:14px; color:#0288d1;"><?=formatNumber($final_total, 2)?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?><a href="?<?=buildQueryString(['page'])?>&page=1">« แรก</a><a href="?<?=buildQueryString(['page'])?>&page=<?=$page-1?>">‹ ก่อนหน้า</a><?php endif; ?>
                    <span class="current">หน้า <?=$page?></span>
                    <?php if($page < $total_pages): ?><a href="?<?=buildQueryString(['page'])?>&page=<?=$page+1?>">ถัดไป ›</a><a href="?<?=buildQueryString(['page'])?>&page=<?=$total_pages?>">สุดท้าย »</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="items" class="tab-content">
            <div class="card">
                <h3>Top 30 สินค้าขายดี</h3>
                <table>
                    <thead><tr><th>#</th><th>Barcode</th><th>ชื่อสินค้า</th><th>Brand</th><th>Size</th><th class="number">ชิ้น</th><th class="number">ยอดขาย</th></tr></thead>
                    <tbody>
                        <?php foreach($top_items as $k => $row): ?>
                        <tr>
                            <td><?=$k+1?></td>
                            <td style="font-family:monospace"><?=$row['line_barcode']?></td>
                            <td><?=$row['item_description']?></td>
                            <td><?=$row['brand']?></td>
                            <td><?=$row['size']?></td>
                            <td class="number"><?=number_format($row['total_qty'])?></td>
                            <td class="number"><?=formatNumber($row['total_sales'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="members" class="tab-content">
            <div class="card">
                <h3>Top 30 สมาชิกประจำ</h3>
                <table>
                    <thead><tr><th>#</th><th>รหัส</th><th>ชื่อ-สกุล</th><th class="number">บิล</th><th class="number">ชิ้น</th><th class="number">ยอดซื้อ</th></tr></thead>
                    <tbody>
                        <?php foreach($top_members as $k => $row): ?>
                        <tr>
                            <td><?=$k+1?></td>
                            <td><?=$row['member']?></td>
                            <td><?=$row['first_name']?> <?=$row['last_name']?></td>
                            <td class="number"><?=number_format($row['purchase_count'])?></td>
                            <td class="number"><?=number_format($row['total_qty'])?></td>
                            <td class="number"><?=formatNumber($row['total_spent'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="sizes" class="tab-content">
            <div class="card">
                <h3>วิเคราะห์ไซส์</h3>
                <table>
                    <thead><tr><th>Size</th><th class="number">รายการ</th><th class="number">ชิ้น</th><th class="number">ยอดขาย</th></tr></thead>
                    <tbody>
                        <?php foreach($size_analysis as $row): ?>
                        <tr>
                            <td><b><?=$row['size']?></b></td>
                            <td class="number"><?=number_format($row['item_count'])?></td>
                            <td class="number"><?=number_format($row['total_qty'])?></td>
                            <td class="number"><?=formatNumber($row['total_sales'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Script สำหรับเปิด/ปิดแถวรายละเอียด
        function toggleRow(id) {
            var el = document.getElementById(id);
            if (el.style.display === 'none') {
                el.style.display = 'table-row';
            } else {
                el.style.display = 'none';
            }
        }
        
        // Script สำหรับเปลี่ยน Tab
        function openTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            btn.classList.add('active');
        }

        // Script สำหรับปุ่ม Quick Date Filter
        function setDateFilter(type) {
            const today = new Date();
            let dateFrom, dateTo;
            
            // Helper to format YYYY-MM-DD
            const fmt = d => d.toISOString().split('T')[0];
            
            dateTo = fmt(today);
            
            if(type === 'yesterday') {
                const y = new Date(today); y.setDate(y.getDate() - 1);
                dateFrom = fmt(y); dateTo = fmt(y);
            } else if(type === 'last7days') {
                const d = new Date(today); d.setDate(d.getDate() - 7);
                dateFrom = fmt(d);
            } else if(type === 'last30days') {
                const d = new Date(today); d.setDate(d.getDate() - 30);
                dateFrom = fmt(d);
            }
            
            document.querySelector('input[name="date_from"]').value = dateFrom;
            document.querySelector('input[name="date_to"]').value = dateTo;
            document.querySelector('form.filters').submit();
        }
    </script>
</body>
</html>