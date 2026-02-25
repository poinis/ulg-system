<?php
// bill_detail.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

$internal_ref = $_GET['ref'] ?? '';
$store_code = $_GET['store'] ?? '';

if (!$internal_ref) {
    header('Location: transaction_detail.php');
    exit;
}

// Get bill items
$items_stmt = $db->prepare("
    SELECT 
        id,
        sale_date,
        store_code,
        line_barcode,
        item_description,
        brand,
        sales_division,
        group_name,
        class_name,
        size,
        member,
        first_name,
        last_name,
        qty,
        base_price,
        tax_incl_total,
        created_at
    FROM daily_sales
    WHERE internal_ref = ?
    ORDER BY created_at
");
$items_stmt->execute([$internal_ref]);
$items = $items_stmt->fetchAll();

if (empty($items)) {
    echo "ไม่พบข้อมูลบิล";
    exit;
}

// Calculate bill summary
$bill_info = $items[0];
$total_items = count($items);
$total_qty = array_sum(array_column($items, 'qty'));

// Calculate subtotal correctly: (base_price * qty) for each item
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['base_price'] * $item['qty'];
}

$total_amount = array_sum(array_column($items, 'tax_incl_total'));
$total_discount = $subtotal - $total_amount;

// Get store info
$store_stmt = $db->prepare("SELECT * FROM stores WHERE store_code = ?");
$store_stmt->execute([$bill_info['store_code']]);
$store = $store_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดบิล #<?php echo htmlspecialchars($internal_ref); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 28px; margin-bottom: 5px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
        .back-link { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: white; color: #667eea; text-decoration: none; border-radius: 5px; font-weight: 500; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .back-link:hover { background: #667eea; color: white; }
        
        .bill-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        
        .bill-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; }
        .bill-number { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .bill-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
        .meta-item { }
        .meta-label { font-size: 12px; opacity: 0.8; margin-bottom: 5px; }
        .meta-value { font-size: 16px; font-weight: 500; }
        
        .bill-body { padding: 30px; }
        
        .customer-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .customer-info h3 { font-size: 16px; color: #667eea; margin-bottom: 15px; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .info-item { }
        .info-label { font-size: 12px; color: #666; margin-bottom: 3px; }
        .info-value { font-size: 15px; font-weight: 500; color: #333; }
        
        .items-section { margin-bottom: 30px; }
        .items-section h3 { font-size: 18px; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 15px 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .number { text-align: right; font-family: 'Courier New', monospace; }
        tr:hover { background: #f8f9fa; }
        
        .summary-box { background: #f8f9fa; padding: 25px; border-radius: 10px; margin-top: 30px; }
        .summary-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 15px; }
        .summary-row.total { font-size: 20px; font-weight: bold; color: #667eea; border-top: 2px solid #ddd; margin-top: 10px; padding-top: 15px; }
        .summary-label { color: #666; }
        .summary-value { font-family: 'Courier New', monospace; font-weight: 500; }
        
        .stats-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-mini { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-mini-label { font-size: 12px; opacity: 0.9; margin-bottom: 5px; }
        .stat-mini-value { font-size: 24px; font-weight: bold; }
        
        .brand-badge { display: inline-block; background: #667eea; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; margin-right: 5px; }
        .size-badge { display: inline-block; background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        
        @media print {
            .back-link, .no-print { display: none; }
            .bill-card { box-shadow: none; page-break-after: always; }
            body { background: white; }
            .bill-header { background: white !important; color: #333 !important; border-bottom: 2px solid #333; }
            .stat-mini { background: white !important; color: #333 !important; border: 1px solid #ddd; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
        
        @media (max-width: 768px) {
            .bill-meta, .info-grid, .stats-mini { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🧾 รายละเอียดบิลเต็ม</h1>
            <p style="opacity: 0.9;">เลขที่บิล: <?php echo htmlspecialchars($internal_ref); ?></p>
        </div>
    </div>
    
    <div class="container">
        <a href="transaction_detail.php?store=<?php echo $store_code; ?>" class="back-link no-print">← กลับรายการบิล</a>
        
        <div class="bill-card">
            <div class="bill-header">
                <div class="bill-number">📄 BILL #<?php echo htmlspecialchars($internal_ref); ?></div>
                <div class="bill-meta">
                    <div class="meta-item">
                        <div class="meta-label">สาขา</div>
                        <div class="meta-value"><?php echo htmlspecialchars($store['store_name'] ?? $bill_info['store_code']); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">วันที่</div>
                        <div class="meta-value"><?php echo formatDate($bill_info['sale_date']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="bill-body">
                <div class="customer-info">
                    <h3>👤 ข้อมูลสมาชิก</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">รหัสสมาชิก</div>
                            <div class="info-value"><?php echo htmlspecialchars($bill_info['member']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">ชื่อ</div>
                            <div class="info-value"><?php echo htmlspecialchars($bill_info['first_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">นามสกุล</div>
                            <div class="info-value"><?php echo htmlspecialchars($bill_info['last_name']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-mini">
                    <div class="stat-mini">
                        <div class="stat-mini-label">รายการสินค้า</div>
                        <div class="stat-mini-value"><?php echo number_format($total_items); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-label">จำนวนชิ้น</div>
                        <div class="stat-mini-value"><?php echo number_format($total_qty); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-label">ยอดรวม</div>
                        <div class="stat-mini-value"><?php echo formatNumber($total_amount, 0); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-label">เฉลี่ย/ชิ้น</div>
                        <div class="stat-mini-value">
                            <?php echo $total_qty > 0 ? formatNumber($total_amount / $total_qty, 0) : 0; ?>
                        </div>
                    </div>
                </div>
                
                <div class="items-section">
                    <h3>📦 รายการสินค้า</h3>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>บาร์โค้ด</th>
                                <th>รายละเอียดสินค้า</th>
                                <th>แบรนด์</th>
                                <th>Division</th>
                                <th>ไซส์</th>
                                <th class="number">จำนวน</th>
                                <th class="number">ราคา/ชิ้น</th>
                                <th class="number">รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td style="text-align: center; font-weight: 600; color: #667eea;">
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 11px;">
                                        <?php echo htmlspecialchars($item['line_barcode']); ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; margin-bottom: 3px;">
                                            <?php echo htmlspecialchars($item['item_description']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #999;">
                                            <?php echo htmlspecialchars($item['group_name'] . ' / ' . $item['class_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="brand-badge">
                                            <?php echo htmlspecialchars($item['brand']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['sales_division']); ?></td>
                                    <td style="text-align: center;">
                                        <span class="size-badge">
                                            <?php echo htmlspecialchars($item['size']); ?>
                                        </span>
                                    </td>
                                    <td class="number"><?php echo number_format($item['qty']); ?></td>
                                    <td class="number"><?php echo formatNumber($item['base_price'], 2); ?></td>
                                    <td class="number"><strong><?php echo formatNumber($item['tax_incl_total'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="summary-label">รวมราคาสินค้า</span>
                        <span class="summary-value"><?php echo formatNumber($subtotal, 2); ?> บาท</span>
                    </div>
                    <?php if ($total_discount > 0): ?>
                    <div class="summary-row">
                        <span class="summary-label">ส่วนลด</span>
                        <span class="summary-value" style="color: #dc3545;">
                            -<?php echo formatNumber($total_discount, 2); ?> บาท
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span class="summary-label">ยอดรวมสุทธิ</span>
                        <span class="summary-value"><?php echo formatNumber($total_amount, 2); ?> บาท</span>
                    </div>
                </div>
            </div>
        </div>
        
       
    </div>
</body>
</html>