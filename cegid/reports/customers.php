<?php
/**
 * Customer Report with Loyalty
 */
require_once __DIR__ . '/../config.php';
$db = Database::getInstance();

$search = $_GET['search'] ?? '';
$tier = $_GET['tier'] ?? '';

// Build query
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (c.full_name LIKE ? OR c.email LIKE ? OR c.mobile LIKE ? OR c.customer_id LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($tier) {
    $where .= " AND c.tier = ?";
    $params[] = $tier;
}

// Get customers
$customers = $db->fetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM cegid_sale_documents d WHERE d.customer_id = c.customer_id AND d.is_active = 1) as bill_count,
           (SELECT SUM(tax_included_amount) FROM cegid_sale_documents d WHERE d.customer_id = c.customer_id AND d.is_active = 1) as total_spent,
           (SELECT MAX(doc_date) FROM cegid_sale_documents d WHERE d.customer_id = c.customer_id AND d.is_active = 1) as last_purchase
    FROM cegid_customers c 
    {$where}
    ORDER BY c.total_purchases DESC 
    LIMIT 200
", $params);

// Get tiers for filter
$tiers = $db->fetchAll("SELECT DISTINCT tier FROM cegid_customers WHERE tier IS NOT NULL AND tier != '' ORDER BY tier");

// Stats
$stats = $db->fetchOne("SELECT COUNT(*) as total, SUM(loyalty_points) as total_points, SUM(total_purchases) as total_value FROM cegid_customers");
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Customers</title>
<style>
*{box-sizing:border-box}body{font-family:-apple-system,sans-serif;margin:0;padding:20px;background:#f5f5f5}.container{max-width:1400px;margin:0 auto}
.filter-bar{background:white;padding:15px;border-radius:10px;margin-bottom:20px}.filter-bar form{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;min-width:150px}
.filter-bar button{padding:8px 20px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
.card{background:white;padding:20px;border-radius:10px}.card h3{margin:0 0 10px;color:#666;font-size:13px}.card .value{font-size:28px;font-weight:bold}
.section{background:white;padding:20px;border-radius:10px;margin-bottom:20px}.section h2{margin-top:0;border-bottom:2px solid #eee;padding-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}th{background:#f8f9fa;position:sticky;top:0}
.text-right{text-align:right}.back{margin-bottom:15px}.back a{color:#007bff;text-decoration:none}
.tier-badge{display:inline-block;padding:3px 10px;border-radius:15px;font-size:11px;font-weight:600}
.tier-GOLD{background:#ffd700;color:#333}.tier-SILVER{background:#c0c0c0;color:#333}.tier-BRONZE{background:#cd7f32;color:white}
.tier-VIP{background:#9c27b0;color:white}.tier-NORMAL{background:#e0e0e0;color:#333}
.customer-info .name{font-weight:600}.customer-info .meta{font-size:11px;color:#999;margin-top:2px}
.points{color:#4caf50;font-weight:bold}
</style>
</head><body><div class="container">
<div class="back"><a href="../sync_manager.php">← Back</a></div>
<h1>👥 Customer Report</h1>

<div class="filter-bar"><form method="GET">
    <input type="text" name="search" placeholder="Search name, email, phone..." value="<?= htmlspecialchars($search) ?>">
    <select name="tier"><option value="">All Tiers</option>
        <?php foreach ($tiers as $t): ?><option value="<?= htmlspecialchars($t['tier']) ?>" <?= $tier===$t['tier']?'selected':'' ?>><?= htmlspecialchars($t['tier']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit">🔍 Search</button>
    <a href="?" style="padding:8px 15px;background:#f0f0f0;border-radius:4px;text-decoration:none;color:#333">Clear</a>
</form></div>

<div class="cards">
    <div class="card"><h3>👥 Total Customers</h3><div class="value"><?= number_format($stats['total'] ?? 0) ?></div></div>
    <div class="card"><h3>🎯 Total Points</h3><div class="value"><?= number_format($stats['total_points'] ?? 0) ?></div></div>
    <div class="card"><h3>💰 Total Value</h3><div class="value">฿<?= number_format($stats['total_value'] ?? 0) ?></div></div>
</div>

<div class="section"><h2>Customer List</h2>
<table><thead><tr>
    <th>Customer</th>
    <th>Contact</th>
    <th>Tier</th>
    <th class="text-right">Points</th>
    <th class="text-right">Bills</th>
    <th class="text-right">Total Spent</th>
    <th>Last Purchase</th>
</tr></thead><tbody>
<?php foreach ($customers as $c): ?>
<tr>
    <td class="customer-info">
        <div class="name"><?= htmlspecialchars($c['full_name'] ?: $c['customer_id']) ?></div>
        <div class="meta">ID: <?= htmlspecialchars($c['customer_id']) ?><?php if($c['card_number']): ?> | Card: <?= htmlspecialchars($c['card_number']) ?><?php endif; ?></div>
    </td>
    <td>
        <?php if($c['email']): ?><div style="font-size:12px">📧 <?= htmlspecialchars($c['email']) ?></div><?php endif; ?>
        <?php if($c['mobile']): ?><div style="font-size:12px">📱 <?= htmlspecialchars($c['mobile']) ?></div><?php endif; ?>
    </td>
    <td>
        <?php if($c['tier']): ?>
        <span class="tier-badge tier-<?= strtoupper($c['tier']) ?>"><?= htmlspecialchars($c['tier']) ?></span>
        <?php else: ?>
        <span style="color:#999">-</span>
        <?php endif; ?>
    </td>
    <td class="text-right points"><?= number_format($c['loyalty_points'] ?? 0) ?></td>
    <td class="text-right"><?= number_format($c['bill_count'] ?? 0) ?></td>
    <td class="text-right"><strong>฿<?= number_format($c['total_spent'] ?? $c['total_purchases'] ?? 0) ?></strong></td>
    <td><?= $c['last_purchase'] ? date('d M Y', strtotime($c['last_purchase'])) : '-' ?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($customers)): ?>
<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">No customers found</td></tr>
<?php endif; ?>
</tbody></table>
</div>

<div class="section">
    <h2>⚡ Quick Actions</h2>
    <p>
        <a href="../sync_customers.php" target="_blank" style="padding:10px 20px;background:#17a2b8;color:white;text-decoration:none;border-radius:5px;display:inline-block;margin:5px">📥 Sync from Sales</a>
        <a href="../sync_customers.php?loyalty=1" target="_blank" style="padding:10px 20px;background:#28a745;color:white;text-decoration:none;border-radius:5px;display:inline-block;margin:5px">🎯 Sync Loyalty</a>
        <a href="../sync_customers.php?stats=1" target="_blank" style="padding:10px 20px;background:#ffc107;color:#333;text-decoration:none;border-radius:5px;display:inline-block;margin:5px">📊 Update Stats</a>
    </p>
</div>
</div></body></html>
