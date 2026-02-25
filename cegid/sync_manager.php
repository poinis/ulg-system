<?php
/**
 * Cegid Y2 - Sync Manager Dashboard
 */
require_once __DIR__ . '/config.php';
$db = Database::getInstance();

$stats = [];
$stats['documents'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_documents")['cnt'] ?? 0;
$stats['docs_synced'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_documents WHERE lines_synced = 1")['cnt'] ?? 0;
$stats['docs_pending'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_documents WHERE lines_synced = 0")['cnt'] ?? 0;
$stats['lines'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_lines")['cnt'] ?? 0;
$stats['payments'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_sale_payments")['cnt'] ?? 0;
$stats['brands'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_categories WHERE category_type_id = 1")['cnt'] ?? 0;
$stats['categories'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_categories")['cnt'] ?? 0;
$stats['products'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_products")['cnt'] ?? 0;
$stats['customers'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM cegid_customers")['cnt'] ?? 0;

$dateRange = $db->fetchOne("SELECT MIN(doc_date) as min_date, MAX(doc_date) as max_date FROM cegid_sale_documents");
$recentLogs = $db->fetchAll("SELECT * FROM cegid_sync_logs ORDER BY id DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Cegid Sync Manager</title>
<style>
*{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:20px;background:#f5f5f5}
.container{max-width:1200px;margin:0 auto}h1{color:#333}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:15px;margin-bottom:25px}
.stat-card{background:white;padding:18px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.stat-card h3{margin:0 0 8px;color:#666;font-size:12px}.stat-card .value{font-size:26px;font-weight:bold}
.stat-card .sub{font-size:11px;color:#999;margin-top:4px}
.section{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-bottom:20px}
.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
.action-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.action-card{border:1px solid #ddd;border-radius:8px;padding:18px}
.action-card h3{margin-top:0;color:#333}.action-card p{color:#666;font-size:13px;margin-bottom:12px}
.btn{display:inline-block;padding:9px 18px;background:#007bff;color:white;text-decoration:none;border-radius:5px;font-weight:bold;border:none;cursor:pointer;font-size:13px;margin:2px}
.btn:hover{background:#0056b3}.btn-success{background:#28a745}.btn-success:hover{background:#1e7e34}
.btn-warning{background:#ffc107;color:#333}.btn-warning:hover{background:#e0a800}
.btn-info{background:#17a2b8}.btn-info:hover{background:#138496}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa;font-size:13px}
.status-completed{color:#28a745}.status-failed{color:#dc3545}.status-running{color:#ffc107}
.form-group{margin-bottom:10px}.form-group label{display:block;margin-bottom:4px;font-weight:600;font-size:12px}
.form-group input{padding:7px 10px;border:1px solid #ddd;border-radius:4px;width:130px}
</style></head><body>
<div class="container">
<h1>🔄 Cegid Sync Manager</h1>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card"><h3>📄 Documents</h3><div class="value"><?= number_format($stats['documents']) ?></div><div class="sub"><?= number_format($stats['docs_synced']) ?> synced / <?= number_format($stats['docs_pending']) ?> pending</div></div>
    <div class="stat-card"><h3>📦 Sale Lines</h3><div class="value"><?= number_format($stats['lines']) ?></div></div>
    <div class="stat-card"><h3>💳 Payments</h3><div class="value"><?= number_format($stats['payments']) ?></div></div>
    <div class="stat-card"><h3>🏷️ Brands</h3><div class="value"><?= number_format($stats['brands']) ?></div><div class="sub"><?= number_format($stats['categories']) ?> total categories</div></div>
    <div class="stat-card"><h3>🛍️ Products</h3><div class="value"><?= number_format($stats['products']) ?></div></div>
    <div class="stat-card"><h3>👥 Customers</h3><div class="value"><?= number_format($stats['customers']) ?></div></div>
    <div class="stat-card"><h3>📅 Data Range</h3><div class="value" style="font-size:13px"><?= $dateRange['min_date'] ? date('d M Y', strtotime($dateRange['min_date'])) : '-' ?><br>to <?= $dateRange['max_date'] ? date('d M Y', strtotime($dateRange['max_date'])) : '-' ?></div></div>
</div>

<!-- Sync Actions -->
<div class="section">
<h2>⚡ Sync Actions</h2>
<div class="action-grid">
    <div class="action-card">
        <h3>🏷️ Categories</h3><p>Sync Brand, Class, Style from Cegid</p>
        <a href="sync_categories.php" class="btn btn-success" target="_blank">Sync All</a>
    </div>
    <div class="action-card">
        <h3>📄 Sales</h3><p>Sync sale documents with lines</p>
        <form action="sync_sales.php" method="GET" target="_blank">
            <div class="form-group"><label>From:</label><input type="date" name="date_from" value="<?= date('Y-m-01') ?>"></div>
            <div class="form-group"><label>To:</label><input type="date" name="date_to" value="<?= date('Y-m-d') ?>"></div>
            <button type="submit" class="btn">Full Sync</button>
            <button type="submit" name="headers_only" value="1" class="btn btn-warning">Headers Only</button>
        </form>
    </div>
    <div class="action-card">
        <h3>🔄 Pending Lines</h3><p>Sync lines for pending documents</p>
        <a href="sync_pending.php" class="btn" target="_blank">Sync 100</a>
        <a href="sync_pending.php?limit=500" class="btn btn-warning" target="_blank">Sync 500</a>
    </div>
    <div class="action-card">
        <h3>👥 Customers</h3><p>Sync customers & loyalty</p>
        <a href="sync_customers.php" class="btn btn-info" target="_blank">From Sales</a>
        <a href="sync_customers.php?loyalty=1" class="btn btn-success" target="_blank">Loyalty</a>
        <a href="sync_customers.php?stats=1" class="btn btn-warning" target="_blank">Stats</a>
    </div>
    <div class="action-card">
        <h3>🧪 Test API</h3><p>Test API connection</p>
        <a href="test_api.php" class="btn btn-warning" target="_blank">Run Test</a>
    </div>
</div>
</div>

<!-- Logs -->
<div class="section">
<h2>📋 Recent Sync Logs</h2>
<table>
<thead><tr><th>ID</th><th>Type</th><th>Date Range</th><th>Started</th><th>Records</th><th>Status</th><th>Time</th></tr></thead>
<tbody>
<?php foreach ($recentLogs as $log): ?>
<tr>
    <td><?= $log['id'] ?></td>
    <td><?= htmlspecialchars($log['sync_type']) ?></td>
    <td><?= $log['sync_date_from'] ?> ~ <?= $log['sync_date_to'] ?></td>
    <td><?= $log['started_at'] ? date('d/m H:i', strtotime($log['started_at'])) : '-' ?></td>
    <td>Found: <?= number_format($log['records_found']) ?> | Insert: <?= $log['records_inserted'] ?></td>
    <td class="status-<?= $log['status'] ?>"><?= strtoupper($log['status']) ?></td>
    <td><?= $log['execution_time'] ?>s</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Reports -->
<div class="section">
<h2>📊 Reports</h2>
<p>
    <a href="reports/daily_sales.php" class="btn">📅 Daily Sales</a>
    <a href="reports/sales_by_brand.php" class="btn">🏷️ By Brand</a>
    <a href="reports/sales_by_store.php" class="btn">🏪 By Store</a>
    <a href="reports/top_products.php" class="btn">🏆 Top Products</a>
    <a href="reports/customers.php" class="btn btn-info">👥 Customers</a>
</p>
</div>
</div>
</body></html>
