<?php
require_once 'config.php';

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!isAdmin($role)) {
    die('<div style="text-align:center;padding:50px;font-size:20px;color:red;">
        ❌ คุณไม่มีสิทธิ์เข้าถึงหน้านี้
    </div>');
}

// Filter by shop
$filter_shop = $_GET['shop'] ?? '';

// Get all shops
$shops = $pdo->query("SELECT DISTINCT shop_name FROM cny_prizes ORDER BY shop_name")->fetchAll(PDO::FETCH_COLUMN);

// Summary per shop
$summary = [];
foreach ($shops as $shop) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cny_prizes WHERE shop_name = ?");
    $stmt->execute([$shop]);
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cny_prizes WHERE shop_name = ? AND is_claimed = 1");
    $stmt->execute([$shop]);
    $claimed = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cny_spin_log WHERE shop_name = ?");
    $stmt->execute([$shop]);
    $total_spins = $stmt->fetchColumn();
    
    // Count default 15% (spins after prizes ran out)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cny_spin_log WHERE shop_name = ? AND prize_id IS NULL");
    $stmt->execute([$shop]);
    $default_spins = $stmt->fetchColumn();

    $summary[$shop] = [
        'total' => $total,
        'claimed' => $claimed,
        'remaining' => $total - $claimed,
        'total_spins' => $total_spins,
        'default_spins' => $default_spins,
    ];
}

// Prize breakdown per shop
$prize_breakdown = [];
foreach ($shops as $shop) {
    $stmt = $pdo->prepare("
        SELECT prize_name, 
               COUNT(*) as total,
               SUM(is_claimed) as claimed
        FROM cny_prizes 
        WHERE shop_name = ?
        GROUP BY prize_name
        ORDER BY FIELD(prize_name, '50%', '30%', '20%', '15%', 'เสื้อ', 'หมวก')
    ");
    $stmt->execute([$shop]);
    $prize_breakdown[$shop] = $stmt->fetchAll();
}

// Spin log
$log_query = "SELECT l.*, u.name as user_name 
              FROM cny_spin_log l 
              LEFT JOIN users u ON l.user_id = u.id";
$params = [];
if ($filter_shop) {
    $log_query .= " WHERE l.shop_name = ?";
    $params[] = $filter_shop;
}
$log_query .= " ORDER BY l.spun_at DESC LIMIT 200";

$stmt = $pdo->prepare($log_query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 สรุปรางวัลตรุษจีน - Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .nav {
            background: linear-gradient(135deg, #8B0000, #DC143C);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav h1 { color: #FFD700; font-size: 20px; }
        .nav a { color: #FFE4B5; text-decoration: none; font-size: 14px; }
        .nav a:hover { color: #FFF; }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* Summary cards */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin: 20px 0; }
        .card {
            background: #FFF;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #DC143C;
        }
        .card h3 { color: #8B0000; font-size: 14px; margin-bottom: 10px; }
        .card .num { font-size: 28px; font-weight: 900; color: #DC143C; }
        .card .detail { font-size: 12px; color: #888; margin-top: 5px; }
        .card .progress-bar {
            height: 6px;
            background: #eee;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }
        .card .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #DC143C, #FF4500);
            border-radius: 3px;
            transition: width 0.5s;
        }

        /* Prize breakdown */
        .section { background: #FFF; border-radius: 12px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .section h2 { color: #8B0000; margin-bottom: 15px; font-size: 18px; }

        .prize-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .prize-item {
            background: #FFF8F0;
            border: 1px solid #FFE0B2;
            border-radius: 8px;
            padding: 12px;
        }
        .prize-item .name { font-weight: 700; color: #8B0000; }
        .prize-item .count { color: #666; font-size: 13px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th { background: #8B0000; color: #FFD700; padding: 10px 12px; text-align: left; font-size: 13px; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        tr:hover { background: #FFF8F0; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-prize { background: #FFE0B2; color: #E65100; }
        .badge-default { background: #E0E0E0; color: #666; }

        /* Filter */
        .filter { margin: 15px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }

        /* Reset button */
        .reset-section { margin: 20px 0; text-align: center; }
        .reset-btn {
            padding: 12px 30px;
            background: #FF1744;
            color: #FFF;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .reset-btn:hover { background: #D50000; }

        /* Tabs */
        .tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
        .tab {
            padding: 8px 16px;
            background: #eee;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
        }
        .tab.active { background: #8B0000; color: #FFD700; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .export-btn {
            padding: 8px 16px;
            background: #228B22;
            color: #FFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="nav">
        <h1>🔧 สรุปรางวัลตรุษจีน 2569</h1>
        <div>
            <a href="wheel.php">← กลับหน้ากงล้อ</a>
        </div>
    </div>

    <div class="container">
        <!-- Summary Cards -->
        <div class="cards">
            <?php foreach ($summary as $shop => $s): ?>
            <div class="card">
                <h3>🏪 <?= htmlspecialchars($shop) ?></h3>
                <div class="num"><?= $s['total_spins'] ?> <span style="font-size:14px;color:#888">หมุน</span></div>
                <div class="detail">
                    รางวัลทั้งหมด: <?= $s['total'] ?> | 
                    แจกไป: <?= $s['claimed'] ?> | 
                    คงเหลือ: <?= $s['remaining'] ?>
                </div>
                <?php if ($s['default_spins'] > 0): ?>
                <div class="detail" style="color:#FF6B00">
                    ⚠ ส่วนลด 15% (รางวัลหมด): <?= $s['default_spins'] ?> ครั้ง
                </div>
                <?php endif; ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $s['total'] > 0 ? round($s['claimed']/$s['total']*100) : 0 ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Prize Breakdown per Shop -->
        <div class="section">
            <h2>📊 รายละเอียดรางวัลแต่ละร้าน</h2>
            <div class="tabs">
                <?php foreach ($shops as $i => $shop): ?>
                <button class="tab <?= $i === 0 ? 'active' : '' ?>" onclick="showTab(<?= $i ?>)">
                    <?= htmlspecialchars($shop) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($shops as $i => $shop): ?>
            <div class="tab-content <?= $i === 0 ? 'active' : '' ?>" id="tab-<?= $i ?>">
                <div class="prize-grid">
                    <?php foreach ($prize_breakdown[$shop] as $p): ?>
                    <div class="prize-item">
                        <div class="name">
                            <?php
                            $icon = '🎫';
                            if ($p['prize_name'] === 'เสื้อ') $icon = '👕';
                            elseif ($p['prize_name'] === 'หมวก') $icon = '🧢';
                            elseif ($p['prize_name'] === '50%') $icon = '🔥';
                            elseif ($p['prize_name'] === '30%') $icon = '⭐';
                            echo $icon . ' ' . htmlspecialchars($p['prize_name']);
                            if (in_array($p['prize_name'], ['50%','30%','20%','15%'])) echo ' ส่วนลด';
                            ?>
                        </div>
                        <div class="count">
                            ทั้งหมด: <?= $p['total'] ?> | 
                            แจกไป: <?= $p['claimed'] ?> | 
                            เหลือ: <?= $p['total'] - $p['claimed'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Spin Log -->
        <div class="section">
            <h2>📋 ประวัติการหมุน
                <button class="export-btn" onclick="exportCSV()">📥 Export CSV</button>
            </h2>
            <div class="filter">
                <label>กรองร้าน:</label>
                <select onchange="location.href='admin.php?shop='+this.value">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($shops as $shop): ?>
                    <option value="<?= urlencode($shop) ?>" <?= $filter_shop === $shop ? 'selected' : '' ?>>
                        <?= htmlspecialchars($shop) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="overflow-x: auto;">
            <table id="logTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>เวลา</th>
                        <th>ร้าน</th>
                        <th>ผู้หมุน</th>
                        <th>เลขที่บิล</th>
                        <th>รางวัล</th>
                        <th>ประเภท</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= $log['spun_at'] ?></td>
                        <td><?= htmlspecialchars($log['shop_name']) ?></td>
                        <td><?= htmlspecialchars($log['user_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['bill_number']) ?></td>
                        <td>
                            <strong>
                            <?php
                            if (in_array($log['prize_name'], ['50%','30%','20%','15%'])) {
                                echo 'ส่วนลด ' . htmlspecialchars($log['prize_name']);
                            } else {
                                echo htmlspecialchars($log['prize_name']);
                            }
                            ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($log['prize_id']): ?>
                                <span class="badge badge-prize">รางวัลตามลำดับ</span>
                            <?php else: ?>
                                <span class="badge badge-default">ส่วนลด 15% (หมด)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#999;padding:30px">ยังไม่มีการหมุน</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Reset -->
        
    </div>

    <script>
    function showTab(index) {
        document.querySelectorAll('.tab').forEach((t, i) => t.classList.toggle('active', i === index));
        document.querySelectorAll('.tab-content').forEach((t, i) => t.classList.toggle('active', i === index));
    }

    function confirmReset() {
        if (!confirm('⚠️ คุณแน่ใจหรือไม่ที่จะรีเซ็ตรางวัลทั้งหมด? \nการดำเนินการนี้ไม่สามารถยกเลิกได้!')) return;
        if (!confirm('ยืนยันอีกครั้ง: ลบประวัติทั้งหมดและรีเซ็ตรางวัล?')) return;

        fetch('reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_all' })
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            location.reload();
        });
    }

    function confirmResetShop(shop) {
        if (!confirm('รีเซ็ตรางวัลของ ' + shop + '?')) return;

        fetch('reset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_shop', shop: shop })
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            location.reload();
        });
    }

    function exportCSV() {
        const table = document.getElementById('logTable');
        let csv = [];
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('th, td');
            const rowData = [];
            cols.forEach(col => rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"'));
            csv.push(rowData.join(','));
        });
        
        const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'cny_wheel_log_' + new Date().toISOString().slice(0,10) + '.csv';
        link.click();
    }
    </script>
</body>
</html>
