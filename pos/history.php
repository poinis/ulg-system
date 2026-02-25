<?php
// pos/history.php - Sales History
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}
require_once "../config.php";

$userId = $_SESSION['id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$userBranch = $user['branch_name'] ?: 'HQ';
$userRole = strtolower($user['role']);

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filterDate = $_GET['date'] ?? date('Y-m-d');
    $filterBranch = $_GET['branch'] ?? '';
    
    $where = ["DATE(s.sale_date) = ?"];
    $params = [$filterDate];
    $types = "s";
    
    if (!in_array($userRole, ['admin', 'owner'])) {
        $where[] = "s.branch_name = ?";
        $params[] = $userBranch;
        $types .= "s";
    } elseif ($filterBranch) {
        $where[] = "s.branch_name = ?";
        $params[] = $filterBranch;
        $types .= "s";
    }
    
    $tableExists = @$conn->query("SHOW TABLES LIKE 'pos_sales'")->num_rows > 0;
    if ($tableExists) {
        $sql = "SELECT s.*, u.name as cashier_name FROM pos_sales s LEFT JOIN users u ON s.user_id = u.id WHERE " . implode(" AND ", $where) . " ORDER BY s.created_at DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Set headers for Excel download
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="sales_report_' . $filterDate . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            
            // Excel content
            echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>';
            echo '<body><table border="1">';
            echo '<tr style="background-color:#0f3460;color:#fff;font-weight:bold;">';
            echo '<th>เลขที่</th><th>ร้าน</th><th>สาขา</th><th>พนักงาน</th><th>ยอด</th><th>ส่วนลด</th><th>สุทธิ</th><th>หมายเหตุ</th><th>เวลา</th>';
            echo '</tr>';
            
            foreach ($sales as $s) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($s['sale_code']) . '</td>';
                echo '<td>' . htmlspecialchars($s['store_name'] ?: '-') . '</td>';
                echo '<td>' . htmlspecialchars($s['branch_name']) . '</td>';
                echo '<td>' . htmlspecialchars($s['cashier_name'] ?: '-') . '</td>';
                echo '<td>' . number_format($s['subtotal'] ?: $s['total_amount'], 2) . '</td>';
                echo '<td>' . number_format($s['discount'], 2) . '</td>';
                echo '<td>' . number_format($s['total_amount'], 2) . '</td>';
                echo '<td>' . htmlspecialchars($s['payment_note'] ?: '-') . '</td>';
                echo '<td>' . date('H:i', strtotime($s['created_at'])) . '</td>';
                echo '</tr>';
            }
            
            echo '</table></body></html>';
            exit;
        }
    }
}

$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterBranch = $_GET['branch'] ?? '';

$where = ["DATE(s.sale_date) = ?"];
$params = [$filterDate];
$types = "s";

if (!in_array($userRole, ['admin', 'owner'])) {
    $where[] = "s.branch_name = ?";
    $params[] = $userBranch;
    $types .= "s";
} elseif ($filterBranch) {
    $where[] = "s.branch_name = ?";
    $params[] = $filterBranch;
    $types .= "s";
}

$tableExists = @$conn->query("SHOW TABLES LIKE 'pos_sales'")->num_rows > 0;
$sales = [];
$branches = [];

if ($tableExists) {
    $sql = "SELECT s.*, u.name as cashier_name FROM pos_sales s LEFT JOIN users u ON s.user_id = u.id WHERE " . implode(" AND ", $where) . " ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if (in_array($userRole, ['admin', 'owner'])) {
        $branchResult = @$conn->query("SELECT DISTINCT branch_name FROM pos_sales WHERE branch_name IS NOT NULL AND branch_name != '' ORDER BY branch_name");
        if ($branchResult) while ($row = $branchResult->fetch_assoc()) $branches[] = $row['branch_name'];
    }
}

$totalSales = count($sales);
$totalAmount = array_sum(array_column($sales, 'total_amount'));
$totalDiscount = array_sum(array_column($sales, 'discount'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการขาย | POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;min-height:100vh;color:#fff;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .header{background:#0f3460;padding:20px;border-radius:15px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
        .header h1{font-size:1.3em}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .3s}
        .btn-primary{background:#e94560;color:#fff}
        .btn-primary:hover{background:#d63651}
        .btn-secondary{background:#252542;color:#fff}
        .btn-secondary:hover{background:#3a3a5a}
        .btn-success{background:#2ed573;color:#fff}
        .btn-success:hover{background:#26b862}
        .filters{background:#16213e;padding:15px 20px;border-radius:12px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .filters input,.filters select{padding:10px 15px;background:#252542;border:1px solid #3a3a5a;border-radius:8px;color:#fff;font-size:14px}
        .filters input:focus,.filters select:focus{outline:none;border-color:#e94560}
        .filter-group{display:flex;gap:10px;flex:1;align-items:center}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px}
        .stat-card{background:#16213e;padding:20px;border-radius:12px;text-align:center}
        .stat-card .value{font-size:24px;font-weight:700;color:#2ed573}
        .stat-card .value.discount{color:#ff6b6b}
        .stat-card .label{font-size:11px;color:#888;margin-top:5px}
        .sales-table{background:#16213e;border-radius:15px;overflow:hidden}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 15px;text-align:left;border-bottom:1px solid #252542}
        th{background:#0f3460;font-weight:600;color:#aaa;font-size:12px}
        tr:hover{background:rgba(255,255,255,.02)}
        .sale-code{font-weight:600;color:#e94560}
        .branch-badge{background:#252542;padding:3px 10px;border-radius:12px;font-size:11px}
        .amount{font-weight:700;color:#2ed573}
        .discount{color:#ff6b6b;font-size:12px}
        .note-icon{color:#fbbf24;margin-left:5px}
        .btn-view{padding:6px 12px;background:#3b82f6;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px}
        .empty-state{text-align:center;padding:60px 20px;color:#666}
        .empty-state i{font-size:48px;margin-bottom:15px}
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-overlay.show{display:flex}
        .modal{background:#1a1a2e;border-radius:15px;padding:25px;width:90%;max-width:500px;max-height:80vh;overflow-y:auto}
        .modal h3{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer}
        .detail-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #252542}
        .detail-row.discount{color:#ff6b6b}
        .detail-items{margin-top:15px;background:#252542;border-radius:10px;padding:15px}
        .detail-item{display:flex;justify-content:space-between;padding:8px 0;font-size:14px}
        .detail-total{display:flex;justify-content:space-between;margin-top:15px;padding-top:15px;border-top:2px solid #3a3a5a;font-size:18px;font-weight:700}
        .detail-total .amount{color:#2ed573}
        .detail-note{margin-top:15px;padding:12px;background:#252542;border-radius:8px;font-size:13px;color:#fbbf24}
        .nav-links{display:flex;gap:10px}
        @media(max-width:768px){.header{flex-direction:column;text-align:center}.filters{flex-direction:column}.filters input,.filters select,.filter-group{width:100%}table{font-size:11px}th,td{padding:8px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 ประวัติการขาย</h1>
            <div class="nav-links">
                <a href="products.php" class="btn btn-secondary"><i class="fas fa-box"></i> สินค้า</a>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> POS</a>
            </div>
        </div>
        <form class="filters" method="GET">
            <div class="filter-group">
                <input type="date" name="date" value="<?= $filterDate ?>" required>
                <?php if (!empty($branches)): ?>
                <select name="branch">
                    <option value="">ทุกสาขา</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>" <?= $filterBranch === $b ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> ค้นหา</button>
            </div>
            <?php if (!empty($sales)): ?>
            <button type="submit" name="export" value="excel" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <?php endif; ?>
        </form>
        <div class="stats-row">
            <div class="stat-card"><div class="value"><?= $totalSales ?></div><div class="label">รายการขาย</div></div>
            <div class="stat-card"><div class="value">฿<?= number_format($totalAmount, 0) ?></div><div class="label">ยอดขายสุทธิ</div></div>
            <div class="stat-card"><div class="value discount">฿<?= number_format($totalDiscount, 0) ?></div><div class="label">ส่วนลดรวม</div></div>
            <div class="stat-card"><div class="value">฿<?= $totalSales > 0 ? number_format($totalAmount / $totalSales, 0) : 0 ?></div><div class="label">เฉลี่ย/บิล</div></div>
        </div>
        <div class="sales-table">
            <?php if (empty($sales)): ?>
            <div class="empty-state"><i class="fas fa-receipt"></i><h3>ไม่มีรายการขาย</h3></div>
            <?php else: ?>
            <table>
                <thead><tr><th>เลขที่</th><th>ร้าน/สาขา</th><th>พนักงาน</th><th>ยอด</th><th>ลด</th><th>สุทธิ</th><th>เวลา</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td>
                        <span class="sale-code"><?= htmlspecialchars($s['sale_code']) ?></span>
                        <?php if (!empty($s['payment_note'])): ?><i class="fas fa-sticky-note note-icon" title="<?= htmlspecialchars($s['payment_note']) ?>"></i><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($s['store_name'])): ?><div style="font-size:10px;color:#e94560"><?= htmlspecialchars($s['store_name']) ?></div><?php endif; ?>
                        <span class="branch-badge"><?= htmlspecialchars($s['branch_name']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($s['cashier_name'] ?: '-') ?></td>
                    <td>฿<?= number_format($s['subtotal'] ?: $s['total_amount'], 0) ?></td>
                    <td class="discount"><?= $s['discount'] > 0 ? '-฿'.number_format($s['discount'], 0) : '-' ?></td>
                    <td><span class="amount">฿<?= number_format($s['total_amount'], 0) ?></span></td>
                    <td><?= date('H:i', strtotime($s['created_at'])) ?></td>
                    <td><button class="btn-view" onclick="viewDetail(<?= $s['id'] ?>)"><i class="fas fa-eye"></i></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <h3><span>🧾 รายละเอียด</span><button class="modal-close" onclick="closeModal()">&times;</button></h3>
            <div id="detailContent">Loading...</div>
        </div>
    </div>
    <script>
        async function viewDetail(id){
            document.getElementById('detailModal').classList.add('show');
            document.getElementById('detailContent').innerHTML='<p style="text-align:center">กำลังโหลด...</p>';
            try{
                const r=await fetch('get_sale_detail.php?id='+id);
                const d=await r.json();
                if(d.success){
                    const s=d.sale,items=d.items;
                    let ih='';items.forEach(i=>{ih+=`<div class="detail-item"><span>${i.product_name} x${i.quantity}</span><span>฿${parseFloat(i.subtotal).toLocaleString()}</span></div>`;});
                    let discRow=s.discount&&parseFloat(s.discount)>0?`<div class="detail-row discount"><span>ส่วนลด</span><span>-฿${parseFloat(s.discount).toLocaleString()}</span></div>`:'';
                    let noteHtml=s.payment_note?`<div class="detail-note"><i class="fas fa-sticky-note"></i> ${s.payment_note}</div>`:'';
                    document.getElementById('detailContent').innerHTML=`
                        <div class="detail-row"><span>เลขที่</span><strong style="color:#e94560">${s.sale_code}</strong></div>
                        ${s.store_name?`<div class="detail-row"><span>ร้าน</span><span>${s.store_name}</span></div>`:''}
                        <div class="detail-row"><span>สาขา</span><span>${s.branch_name}</span></div>
                        <div class="detail-row"><span>วันที่</span><span>${s.sale_date} ${s.created_at.split(' ')[1]}</span></div>
                        <div class="detail-items"><strong>รายการสินค้า</strong>${ih}</div>
                        <div class="detail-row"><span>ยอดรวม</span><span>฿${parseFloat(s.subtotal||s.total_amount).toLocaleString()}</span></div>
                        ${discRow}
                        <div class="detail-total"><span>สุทธิ</span><span class="amount">฿${parseFloat(s.total_amount).toLocaleString()}</span></div>
                        ${noteHtml}`;
                }else{document.getElementById('detailContent').innerHTML='<p style="color:#ff6b6b">Error</p>';}
            }catch(e){document.getElementById('detailContent').innerHTML='<p style="color:#ff6b6b">Error</p>';}
        }
        function closeModal(){document.getElementById('detailModal').classList.remove('show');}
        document.getElementById('detailModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
    </script>
</body>
</html>