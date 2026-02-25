<?php
require_once __DIR__ . '/../config.php';
$db = Database::getInstance();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$dailyTotals = $db->fetchAll("SELECT doc_date, COUNT(DISTINCT doc_key) as bills, SUM(total_quantity) as qty, SUM(tax_included_amount) as sales
    FROM cegid_sale_documents WHERE doc_type='Receipt' AND is_active=1 AND doc_date BETWEEN ? AND ? GROUP BY doc_date ORDER BY doc_date DESC", [$dateFrom, $dateTo]);

$brandSales = $db->fetchAll("SELECT l.brand_code, c.category_name as brand_name, SUM(l.quantity) as qty, SUM(l.line_total) as sales
    FROM cegid_sale_lines l INNER JOIN cegid_sale_documents d ON l.doc_key=d.doc_key LEFT JOIN cegid_categories c ON c.category_type_id=1 AND c.category_code=l.brand_code
    WHERE d.doc_type='Receipt' AND d.is_active=1 AND d.doc_date BETWEEN ? AND ? GROUP BY l.brand_code, c.category_name ORDER BY sales DESC LIMIT 15", [$dateFrom, $dateTo]);

$grandTotal = $db->fetchOne("SELECT COUNT(DISTINCT doc_key) as bills, SUM(total_quantity) as qty, SUM(tax_included_amount) as sales FROM cegid_sale_documents WHERE doc_type='Receipt' AND is_active=1 AND doc_date BETWEEN ? AND ?", [$dateFrom, $dateTo]);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Daily Sales</title>
<style>*{box-sizing:border-box}body{font-family:-apple-system,sans-serif;margin:0;padding:20px;background:#f5f5f5}.container{max-width:1200px;margin:0 auto}
.filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px}.filter-bar form{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.filter-bar input{padding:8px;border:1px solid #ddd;border-radius:4px}.filter-bar button{padding:8px 20px;background:#007bff;color:white;border:none;border-radius:4px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px}
.card{background:white;padding:18px;border-radius:10px}.card h3{margin:0 0 8px;color:#666;font-size:12px}.card .value{font-size:26px;font-weight:bold}
.section{background:white;padding:20px;border-radius:10px;margin-bottom:20px}.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa}.text-right{text-align:right}
.back{margin-bottom:15px}.back a{color:#007bff;text-decoration:none}</style>
</head><body><div class="container">
<div class="back"><a href="../sync_manager.php">← Back</a></div>
<h1>📅 Daily Sales Report</h1>
<div class="filter-bar"><form method="GET"><label>From:</label><input type="date" name="date_from" value="<?= $dateFrom ?>"><label>To:</label><input type="date" name="date_to" value="<?= $dateTo ?>"><button type="submit">🔍 Filter</button></form></div>
<div class="cards">
    <div class="card"><h3>💰 Total Sales</h3><div class="value">฿<?= number_format($grandTotal['sales'] ?? 0) ?></div></div>
    <div class="card"><h3>🧾 Total Bills</h3><div class="value"><?= number_format($grandTotal['bills'] ?? 0) ?></div></div>
    <div class="card"><h3>📦 Total Items</h3><div class="value"><?= number_format($grandTotal['qty'] ?? 0) ?></div></div>
    <div class="card"><h3>📊 Avg/Bill</h3><div class="value">฿<?= $grandTotal['bills'] ? number_format($grandTotal['sales']/$grandTotal['bills']) : 0 ?></div></div>
</div>
<div class="section"><h2>📈 Daily Summary</h2>
<table><thead><tr><th>Date</th><th class="text-right">Bills</th><th class="text-right">Items</th><th class="text-right">Sales</th></tr></thead><tbody>
<?php foreach ($dailyTotals as $row): ?>
<tr><td><?= date('D, d M Y', strtotime($row['doc_date'])) ?></td><td class="text-right"><?= number_format($row['bills']) ?></td><td class="text-right"><?= number_format($row['qty']) ?></td><td class="text-right">฿<?= number_format($row['sales']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if (!empty($brandSales)): ?>
<div class="section"><h2>🏷️ Top Brands</h2>
<table><thead><tr><th>Brand</th><th class="text-right">Items</th><th class="text-right">Sales</th></tr></thead><tbody>
<?php foreach ($brandSales as $row): ?>
<tr><td><strong><?= htmlspecialchars($row['brand_code']) ?></strong> <?= htmlspecialchars($row['brand_name'] ?: '') ?></td><td class="text-right"><?= number_format($row['qty']) ?></td><td class="text-right">฿<?= number_format($row['sales']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
</div></body></html>
