<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$brandSales = $db->fetchAll("SELECT l.brand_code, c.category_name as brand_name, SUM(l.quantity) as qty, SUM(l.line_total) as sales, COUNT(DISTINCT l.doc_key) as bills, COUNT(DISTINCT l.item_code) as products
    FROM cegid_sale_lines l INNER JOIN cegid_sale_documents d ON l.doc_key=d.doc_key LEFT JOIN cegid_categories c ON c.category_type_id=1 AND c.category_code=l.brand_code
    WHERE d.doc_type='Receipt' AND d.is_active=1 AND d.doc_date BETWEEN ? AND ? GROUP BY l.brand_code, c.category_name ORDER BY sales DESC", [$dateFrom, $dateTo]);
$grandTotal = array_sum(array_column($brandSales, 'sales'));
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sales by Brand</title>
<style>*{box-sizing:border-box}body{font-family:-apple-system,sans-serif;margin:0;padding:20px;background:#f5f5f5}.container{max-width:1200px;margin:0 auto}
.filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px}.filter-bar form{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.filter-bar input{padding:8px;border:1px solid #ddd;border-radius:4px}.filter-bar button{padding:8px 20px;background:#007bff;color:white;border:none;border-radius:4px}
.section{background:white;padding:20px;border-radius:10px;margin-bottom:20px}.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa}.text-right{text-align:right}
.badge{display:inline-block;padding:4px 12px;background:#e3f2fd;border-radius:20px;font-weight:600;color:#1976d2}
.bar{background:#e3f2fd;height:18px;border-radius:9px}.bar-fill{background:linear-gradient(90deg,#4caf50,#2e7d32);height:100%;border-radius:9px}
.back{margin-bottom:15px}.back a{color:#007bff;text-decoration:none}</style>
</head><body><div class="container">
<div class="back"><a href="../sync_manager.php">← Back</a></div>
<h1>🏷️ Sales by Brand</h1>
<div class="filter-bar"><form method="GET"><label>From:</label><input type="date" name="date_from" value="<?= $dateFrom ?>"><label>To:</label><input type="date" name="date_to" value="<?= $dateTo ?>"><button type="submit">🔍 Filter</button></form></div>
<div class="section"><h2>Brand Performance</h2>
<table><thead><tr><th>#</th><th>Brand</th><th class="text-right">Products</th><th class="text-right">Items</th><th class="text-right">Bills</th><th class="text-right">Sales</th><th style="width:160px">Share</th></tr></thead><tbody>
<?php foreach ($brandSales as $i => $row): $pct = $grandTotal > 0 ? ($row['sales'] / $grandTotal * 100) : 0; ?>
<tr><td><?= $i+1 ?></td><td><span class="badge"><?= htmlspecialchars($row['brand_code']) ?></span> <?= htmlspecialchars($row['brand_name'] ?: '') ?></td>
<td class="text-right"><?= number_format($row['products']) ?></td><td class="text-right"><?= number_format($row['qty']) ?></td><td class="text-right"><?= number_format($row['bills']) ?></td>
<td class="text-right"><strong>฿<?= number_format($row['sales']) ?></strong></td><td><div class="bar"><div class="bar-fill" style="width:<?= max($pct,3) ?>%"></div></div><small><?= number_format($pct,1) ?>%</small></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr style="background:#f8f9fa;font-weight:bold"><td colspan="3">Total</td><td class="text-right"><?= number_format(array_sum(array_column($brandSales,'qty'))) ?></td><td></td><td class="text-right">฿<?= number_format($grandTotal) ?></td><td>100%</td></tr></tfoot>
</table></div>
</div></body></html>
