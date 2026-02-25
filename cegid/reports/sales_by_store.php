<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$storeSales = $db->fetchAll("SELECT d.store_id, s.store_name, COUNT(DISTINCT d.doc_key) as bills, SUM(d.total_quantity) as qty, SUM(d.tax_included_amount) as sales, AVG(d.tax_included_amount) as avg_bill
    FROM cegid_sale_documents d LEFT JOIN cegid_stores s ON d.store_id=s.store_id
    WHERE d.doc_type='Receipt' AND d.is_active=1 AND d.doc_date BETWEEN ? AND ? GROUP BY d.store_id, s.store_name ORDER BY sales DESC", [$dateFrom, $dateTo]);
$grandTotal = array_sum(array_column($storeSales, 'sales'));
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sales by Store</title>
<style>*{box-sizing:border-box}body{font-family:-apple-system,sans-serif;margin:0;padding:20px;background:#f5f5f5}.container{max-width:1200px;margin:0 auto}
.filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px}.filter-bar form{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.filter-bar input{padding:8px;border:1px solid #ddd;border-radius:4px}.filter-bar button{padding:8px 20px;background:#007bff;color:white;border:none;border-radius:4px}
.store-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;margin-bottom:20px}
.store-card{background:white;padding:18px;border-radius:10px;border-left:4px solid #2196f3}
.store-card h3{margin:0 0 5px}.store-card .id{color:#999;font-size:11px;margin-bottom:12px}
.store-card .stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}.store-card .stat-label{font-size:11px;color:#666}.store-card .stat-value{font-size:18px;font-weight:bold}
.section{background:white;padding:20px;border-radius:10px;margin-bottom:20px}.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa}.text-right{text-align:right}
.back{margin-bottom:15px}.back a{color:#007bff;text-decoration:none}</style>
</head><body><div class="container">
<div class="back"><a href="../sync_manager.php">← Back</a></div>
<h1>🏪 Sales by Store</h1>
<div class="filter-bar"><form method="GET"><label>From:</label><input type="date" name="date_from" value="<?= $dateFrom ?>"><label>To:</label><input type="date" name="date_to" value="<?= $dateTo ?>"><button type="submit">🔍 Filter</button></form></div>
<div class="store-grid">
<?php foreach ($storeSales as $row): ?>
<div class="store-card"><h3><?= htmlspecialchars($row['store_name'] ?: 'Store '.$row['store_id']) ?></h3><div class="id">ID: <?= htmlspecialchars($row['store_id']) ?></div>
<div class="stats"><div><div class="stat-label">Sales</div><div class="stat-value">฿<?= number_format($row['sales']) ?></div></div>
<div><div class="stat-label">Bills</div><div class="stat-value"><?= number_format($row['bills']) ?></div></div>
<div><div class="stat-label">Items</div><div class="stat-value"><?= number_format($row['qty']) ?></div></div>
<div><div class="stat-label">Avg/Bill</div><div class="stat-value">฿<?= number_format($row['avg_bill']) ?></div></div></div></div>
<?php endforeach; ?>
</div>
<div class="section"><h2>📊 Summary</h2>
<table><thead><tr><th>#</th><th>Store</th><th class="text-right">Bills</th><th class="text-right">Items</th><th class="text-right">Sales</th><th class="text-right">Share</th></tr></thead><tbody>
<?php foreach ($storeSales as $i => $row): $pct = $grandTotal > 0 ? ($row['sales'] / $grandTotal * 100) : 0; ?>
<tr><td><?= $i+1 ?></td><td><strong><?= htmlspecialchars($row['store_name'] ?: $row['store_id']) ?></strong><br><small style="color:#999"><?= $row['store_id'] ?></small></td>
<td class="text-right"><?= number_format($row['bills']) ?></td><td class="text-right"><?= number_format($row['qty']) ?></td>
<td class="text-right"><strong>฿<?= number_format($row['sales']) ?></strong></td><td class="text-right"><?= number_format($pct,1) ?>%</td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr style="background:#f8f9fa;font-weight:bold"><td colspan="2">Total</td><td class="text-right"><?= number_format(array_sum(array_column($storeSales,'bills'))) ?></td><td class="text-right"><?= number_format(array_sum(array_column($storeSales,'qty'))) ?></td><td class="text-right">฿<?= number_format($grandTotal) ?></td><td class="text-right">100%</td></tr></tfoot>
</table></div>
</div></body></html>
