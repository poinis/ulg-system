<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$brand = $_GET['brand'] ?? '';

$params = [$dateFrom, $dateTo];
$brandCond = '';
if ($brand) { $brandCond = ' AND l.brand_code = ?'; $params[] = $brand; }

$products = $db->fetchAll("SELECT l.item_code, l.item_reference, MAX(l.item_label) as name, l.brand_code, c.category_name as brand_name, MAX(l.complementary_desc) as color, SUM(l.quantity) as qty, SUM(l.line_total) as sales, AVG(l.net_unit_price) as avg_price
    FROM cegid_sale_lines l INNER JOIN cegid_sale_documents d ON l.doc_key=d.doc_key LEFT JOIN cegid_categories c ON c.category_type_id=1 AND c.category_code=l.brand_code
    WHERE d.doc_type='Receipt' AND d.is_active=1 AND d.doc_date BETWEEN ? AND ? {$brandCond}
    GROUP BY l.item_code, l.item_reference, l.brand_code, c.category_name ORDER BY sales DESC LIMIT 100", $params);

$brands = $db->fetchAll("SELECT DISTINCT l.brand_code, c.category_name FROM cegid_sale_lines l LEFT JOIN cegid_categories c ON c.category_type_id=1 AND c.category_code=l.brand_code INNER JOIN cegid_sale_documents d ON l.doc_key=d.doc_key WHERE d.doc_type='Receipt' ORDER BY c.category_name");
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Top Products</title>
<style>*{box-sizing:border-box}body{font-family:-apple-system,sans-serif;margin:0;padding:20px;background:#f5f5f5}.container{max-width:1400px;margin:0 auto}
.filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px}.filter-bar form{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.filter-bar input,.filter-bar select{padding:8px;border:1px solid #ddd;border-radius:4px}.filter-bar select{min-width:160px}
.filter-bar button{padding:8px 20px;background:#007bff;color:white;border:none;border-radius:4px}
.section{background:white;padding:20px;border-radius:10px;margin-bottom:20px}.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa;position:sticky;top:0}.text-right{text-align:right}
.product-name .name{font-weight:600}.product-name .meta{font-size:11px;color:#999;margin-top:2px}
.badge{display:inline-block;padding:3px 10px;background:#e3f2fd;border-radius:15px;font-size:11px;color:#1976d2;font-weight:600}
.rank{width:34px;height:34px;background:#f5f5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;color:#666}
.rank.top1{background:#ffd700;color:#333}.rank.top2{background:#c0c0c0}.rank.top3{background:#cd7f32;color:white}
.back{margin-bottom:15px}.back a{color:#007bff;text-decoration:none}</style>
</head><body><div class="container">
<div class="back"><a href="../sync_manager.php">← Back</a></div>
<h1>🏆 Top Products</h1>
<div class="filter-bar"><form method="GET"><label>From:</label><input type="date" name="date_from" value="<?= $dateFrom ?>"><label>To:</label><input type="date" name="date_to" value="<?= $dateTo ?>">
<label>Brand:</label><select name="brand"><option value="">All Brands</option><?php foreach ($brands as $b): ?><option value="<?= htmlspecialchars($b['brand_code']) ?>" <?= $brand===$b['brand_code']?'selected':'' ?>><?= htmlspecialchars($b['category_name'] ?: $b['brand_code']) ?></option><?php endforeach; ?></select>
<button type="submit">🔍 Filter</button></form></div>
<div class="section"><h2>Top 100 Products</h2>
<table><thead><tr><th style="width:45px">#</th><th>Product</th><th>Brand</th><th class="text-right">Qty</th><th class="text-right">Sales</th><th class="text-right">Avg Price</th></tr></thead><tbody>
<?php foreach ($products as $i => $row): $rankClass = $i===0?'top1':($i===1?'top2':($i===2?'top3':'')); ?>
<tr><td><div class="rank <?= $rankClass ?>"><?= $i+1 ?></div></td>
<td class="product-name"><div class="name"><?= htmlspecialchars($row['name'] ?: $row['item_code']) ?></div><div class="meta">Code: <?= htmlspecialchars($row['item_code']) ?><?php if($row['color']): ?> | <?= htmlspecialchars($row['color']) ?><?php endif; ?></div></td>
<td><span class="badge"><?= htmlspecialchars($row['brand_code']) ?></span><br><small><?= htmlspecialchars($row['brand_name'] ?: '') ?></small></td>
<td class="text-right"><?= number_format($row['qty']) ?></td><td class="text-right"><strong>฿<?= number_format($row['sales']) ?></strong></td><td class="text-right">฿<?= number_format($row['avg_price']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div></body></html>
