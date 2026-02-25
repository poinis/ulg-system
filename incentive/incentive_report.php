<?php
// incentive/incentive_report.php - Monthly Report
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$username = $_SESSION['username'];
$userId = $_SESSION['id'];

// Check admin access: admin, owner, brand, marketing
$superadmins = ['admin', 'oat', 'it', 'may'];
$is_superadmin = in_array(strtolower($username), $superadmins);

$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRole = strtolower($stmt->get_result()->fetch_assoc()['role']);

$admin_roles = ['admin', 'owner', 'brand', 'marketing'];
$is_admin = $is_superadmin || in_array($userRole, $admin_roles);

if (!$is_admin) {
    header("location: index.php");
    exit;
}

// Filter
$filterMonth = $_GET['month'] ?? date('Y-m');

// Get all SHOP users with their stats
$sql = "
    SELECT 
        u.id,
        u.username,
        u.name,
        u.branch_name,
        u.department,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as tiktok_clips,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.content_category = 'product_review' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as clips_product_review,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.content_category = 'creative' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as clips_creative,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.content_category = 'store_vibe' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as clips_store_vibe,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.content_category = 'team_allstar' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as clips_team_allstar,
        COALESCE(SUM(CASE WHEN l.task_key = 'tiktok_clip' AND l.content_category = 'mix_match' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as clips_mix_match,
        COALESCE(SUM(CASE WHEN l.task_key = 'google_maps_update' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as google_maps,
        COALESCE(SUM(CASE WHEN l.task_key = 'google_review' AND l.status = 'approved' THEN l.quantity ELSE 0 END), 0) as google_reviews,
        COALESCE(SUM(CASE WHEN l.status = 'approved' THEN l.points_earned ELSE 0 END), 0) as total_points
    FROM users u
    LEFT JOIN incentive_daily_logs l ON u.id = l.user_id AND DATE_FORMAT(l.log_date, '%Y-%m') = ?
    WHERE u.role = 'shop'
    AND u.department = 'Prontodenim'
    GROUP BY u.id
    ORDER BY total_points DESC, u.name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $filterMonth);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalCash = 0;
$totalCredit = 0;

foreach ($users as &$user) {
    $user['base_earned'] = $user['tiktok_clips'] >= 20;
    $user['points_earned'] = $user['total_points'] >= 100;
    $user['growth_earned'] = false; // TODO: implement growth check
    
    $user['cash'] = $user['base_earned'] ? 2000 : 0;
    $user['credit'] = ($user['points_earned'] ? 500 : 0) + ($user['growth_earned'] ? 500 : 0);
    $user['total_reward'] = $user['cash'] + $user['credit'];
    
    $totalCash += $user['cash'];
    $totalCredit += $user['credit'];
}
unset($user);
// Get months for selector
$months = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m', strtotime("-$i months"));
    $months[] = $m;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report | Incentive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; min-height: 100vh; color: #fff; padding: 15px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header { background: #0f3460; padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 1.3em; }
        
        .nav-links { display: flex; gap: 8px; }
        .nav-links a { background: #252542; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; }
        .nav-links a:hover { background: #3a3a5a; }
        
        .filters { background: #16213e; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        .filters select { padding: 10px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #e94560; color: #fff; }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .summary-card { background: #16213e; padding: 20px; border-radius: 12px; text-align: center; }
        .summary-card .value { font-size: 28px; font-weight: 700; }
        .summary-card .value.cash { color: #2ed573; }
        .summary-card .value.credit { color: #fbbf24; }
        .summary-card .value.pink { color: #ec4899; }
        .summary-card .label { font-size: 12px; color: #888; margin-top: 5px; }
        
        .table-container { background: #16213e; border-radius: 15px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th, td { padding: 12px 10px; text-align: center; border-bottom: 1px solid #252542; font-size: 12px; }
        th { background: #0f3460; font-size: 11px; color: #aaa; white-space: nowrap; }
        th:first-child, td:first-child { text-align: left; padding-left: 15px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .user-info .name { font-weight: 600; font-size: 13px; }
        .user-info .branch { font-size: 10px; color: #888; }
        .user-info .store { font-size: 9px; color: #666; }
        
        .cell-done { color: #2ed573; font-weight: 600; }
        .cell-partial { color: #fbbf24; }
        .cell-zero { color: #666; }
        
        .reward-cell { font-weight: 700; }
        .reward-cell.cash { color: #2ed573; }
        .reward-cell.credit { color: #fbbf24; }
        .reward-cell.total { color: #ec4899; font-size: 14px; }
        
        .check { color: #2ed573; }
        .cross { color: #666; }
        
        .category-header { font-size: 10px; }
        
        .info-box { margin-top: 20px; padding: 20px; background: #252542; border-radius: 12px; }
        .info-box h4 { font-size: 14px; margin-bottom: 10px; color: #fbbf24; }
        .info-box ul { font-size: 12px; color: #aaa; line-height: 2; padding-left: 20px; }
        
        @media (max-width: 768px) {
            .filters { flex-direction: column; }
            .summary-cards { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> รายงาน Incentive (พนักงานสาขา)</h1>
            <div class="nav-links">
                <a href="incentive_approve.php"><i class="fas fa-check"></i> อนุมัติ</a>
                <a href="index.php"><i class="fas fa-clipboard-list"></i> Checklist</a>
                <a href="../dashboard.php"><i class="fas fa-home"></i></a>
            </div>
        </div>
        
        <form class="filters" method="GET">
            <label style="font-size:13px">เดือน:</label>
            <select name="month">
                <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
        
        <div class="summary-cards">
            <div class="summary-card">
                <div class="value"><?= count($users) ?></div>
                <div class="label">พนักงานทั้งหมด</div>
            </div>
            <div class="summary-card">
                <div class="value"><?= count(array_filter($users, fn($u) => $u['base_earned'])) ?></div>
                <div class="label">ได้ Base ฿2,000</div>
            </div>
            <div class="summary-card">
                <div class="value"><?= count(array_filter($users, fn($u) => $u['points_earned'])) ?></div>
                <div class="label">ได้ Points ฿500</div>
            </div>
            <div class="summary-card">
                <div class="value cash">฿<?= number_format($totalCash) ?></div>
                <div class="label">รวมเงินสด</div>
            </div>
            <div class="summary-card">
                <div class="value credit">฿<?= number_format($totalCredit) ?></div>
                <div class="label">รวมเครดิต</div>
            </div>
            <div class="summary-card">
                <div class="value pink">฿<?= number_format($totalCash + $totalCredit) ?></div>
                <div class="label">รวมทั้งหมด</div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">พนักงาน</th>
                        <th colspan="6" style="background:#ec4899;color:#fff">TikTok (เป้า 20 คลิป = 60 แต้ม)</th>
                        <th rowspan="2">Maps</th>
                        <th rowspan="2">Review</th>
                        <th rowspan="2">รวม<br>แต้ม</th>
                        <th rowspan="2">Base<br>฿2,000</th>
                        <th rowspan="2">Points<br>฿500</th>
                        <th rowspan="2">Growth<br>฿500</th>
                        <th rowspan="2">รวม</th>
                    </tr>
                    <tr>
                        <th class="category-header">รวม</th>
                        <th class="category-header">รีวิว<br>(8)</th>
                        <th class="category-header">สร้างสรรค์<br>(4)</th>
                        <th class="category-header">ร้าน<br>(2)</th>
                        <th class="category-header">ทีม<br>(2)</th>
                        <th class="category-header">Mix<br>(4)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="14" style="text-align:center;padding:40px;color:#666">ไม่มีข้อมูลพนักงาน</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="name"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></div>
                                <div class="branch"><?= htmlspecialchars($user['branch_name']) ?></div>
                                <?php if ($user['department']): ?>
                                <div class="store"><?= htmlspecialchars($user['department']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="<?= $user['tiktok_clips'] >= 20 ? 'cell-done' : ($user['tiktok_clips'] > 0 ? 'cell-partial' : 'cell-zero') ?>">
                            <?= $user['tiktok_clips'] ?>
                        </td>
                        <td class="<?= $user['clips_product_review'] >= 8 ? 'cell-done' : 'cell-zero' ?>"><?= $user['clips_product_review'] ?></td>
                        <td class="<?= $user['clips_creative'] >= 4 ? 'cell-done' : 'cell-zero' ?>"><?= $user['clips_creative'] ?></td>
                        <td class="<?= $user['clips_store_vibe'] >= 2 ? 'cell-done' : 'cell-zero' ?>"><?= $user['clips_store_vibe'] ?></td>
                        <td class="<?= $user['clips_team_allstar'] >= 2 ? 'cell-done' : 'cell-zero' ?>"><?= $user['clips_team_allstar'] ?></td>
                        <td class="<?= $user['clips_mix_match'] >= 4 ? 'cell-done' : 'cell-zero' ?>"><?= $user['clips_mix_match'] ?></td>
                        <td class="<?= $user['google_maps'] > 0 ? 'cell-partial' : 'cell-zero' ?>"><?= $user['google_maps'] ?></td>
                        <td class="<?= $user['google_reviews'] > 0 ? 'cell-partial' : 'cell-zero' ?>"><?= $user['google_reviews'] ?></td>
                        <td class="<?= $user['total_points'] >= 100 ? 'cell-done' : ($user['total_points'] > 0 ? 'cell-partial' : 'cell-zero') ?>">
                            <strong><?= $user['total_points'] ?></strong>
                        </td>
                        <td><?= $user['base_earned'] ? '<span class="check">✓</span>' : '<span class="cross">-</span>' ?></td>
                        <td><?= $user['points_earned'] ? '<span class="check">✓</span>' : '<span class="cross">-</span>' ?></td>
                        <td><?= $user['growth_earned'] ? '<span class="check">✓</span>' : '<span class="cross">-</span>' ?></td>
                        <td class="reward-cell total">
                            <?php if ($user['total_reward'] > 0): ?>
                            ฿<?= number_format($user['total_reward']) ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($users)): ?>
                <tfoot>
                    <tr style="background:#252542;font-weight:700">
                        <td>รวมทั้งหมด</td>
                        <td><?= array_sum(array_column($users, 'tiktok_clips')) ?></td>
                        <td colspan="5"></td>
                        <td><?= array_sum(array_column($users, 'google_maps')) ?></td>
                        <td><?= array_sum(array_column($users, 'google_reviews')) ?></td>
                        <td><?= array_sum(array_column($users, 'total_points')) ?></td>
                        <td class="reward-cell cash">฿<?= number_format($totalCash) ?></td>
                        <td class="reward-cell credit" colspan="2">฿<?= number_format($totalCredit) ?></td>
                        <td class="reward-cell total">฿<?= number_format($totalCash + $totalCredit) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="info-box">
            <h4>📌 กติกา Incentive 2026</h4>
            <ul>
                <li><strong>Base Incentive ฿2,000 (เงินสด)</strong>: ลง TikTok ครบ 20 คลิป ตามหมวดหมู่ที่กำหนด</li>
                <li><strong>Points Bonus ฿500 (เครดิต)</strong>: สะสมครบ 100 แต้ม (TikTok = 3 แต้ม, Maps/Review = 1 แต้ม)</li>
                <li><strong>Growth Bonus ฿500 (เครดิต)</strong>: Engagement ได้ตามเป้า (Growth จากเดือนก่อน)</li>
                <li><strong>รวมสูงสุด</strong>: ฿3,000/คน (เงินสด ฿2,000 + เครดิตสินค้า ฿1,000)</li>
            </ul>
        </div>
    </div>
</body>
</html>