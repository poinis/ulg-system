<?php
/**
 * Manage Posts - ติ๊กยิงแอดและใส่ยอดเงิน
 */

require_once 'MonthlyImporter.php';

$importer = new MonthlyImporter();
$pdo = $importer->getPDO();

$message = '';
$messageType = '';

// จัดการการบันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ads'])) {
        try {
            $updates = [];
            
            foreach ($_POST as $key => $value) {
                if (preg_match('/^is_ad_(\d+)$/', $key, $matches)) {
                    $postId = $matches[1];
                    $updates[] = [
                        'id' => $postId,
                        'is_ad' => 1,
                        'ad_spend' => floatval($_POST["ad_spend_$postId"] ?? 0)
                    ];
                }
            }
            
            // หาโพสต์ที่ไม่ได้ติ๊ก (unchecked)
            if (isset($_POST['all_post_ids'])) {
                $allIds = explode(',', $_POST['all_post_ids']);
                $checkedIds = array_column($updates, 'id');
                
                foreach ($allIds as $id) {
                    if (!in_array($id, $checkedIds)) {
                        $updates[] = [
                            'id' => $id,
                            'is_ad' => 0,
                            'ad_spend' => 0
                        ];
                    }
                }
            }
            
            $importer->bulkUpdateAdStatus($updates);
            $message = "บันทึกข้อมูลโฆษณาสำเร็จ!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// ดึงเดือนที่มีข้อมูล
$availableMonths = $importer->getAvailableMonths();

// ค่าเริ่มต้น
$selectedMonth = $_GET['month'] ?? ($availableMonths[0]['report_month'] ?? date('n'));
$selectedYear = $_GET['year'] ?? ($availableMonths[0]['report_year'] ?? date('Y'));
$selectedPlatform = $_GET['platform'] ?? '';

// ดึงข้อมูลโพสต์
$posts = [];
if (!empty($availableMonths)) {
    $posts = $importer->getPostsByMonth($selectedMonth, $selectedYear, $selectedPlatform ?: null);
}

$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโพสต์ | Monthly Compare</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 20px 0;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.1);
            transition: all 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: rgba(102, 126, 234, 0.3);
        }
        
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .card h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i {
            color: #667eea;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
        }
        
        select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            min-width: 150px;
        }
        
        select option {
            background: #1a1a2e;
            color: #fff;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ed573, #26de81);
            color: #fff;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 213, 115, 0.4);
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: rgba(46, 213, 115, 0.2);
            border: 1px solid rgba(46, 213, 115, 0.5);
            color: #2ed573;
        }
        
        .message.error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.5);
            color: #e74c3c;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        th {
            background: rgba(102, 126, 234, 0.2);
            font-weight: 600;
            white-space: nowrap;
        }
        
        tr:hover {
            background: rgba(255,255,255,0.03);
        }
        
        .platform-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .platform-badge.Facebook { background: rgba(24, 119, 242, 0.2); color: #1877F2; }
        .platform-badge.Instagram { background: rgba(228, 64, 95, 0.2); color: #E4405F; }
        .platform-badge.TikTok { background: rgba(37, 244, 238, 0.2); color: #25F4EE; }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .ad-spend-input {
            width: 120px;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 0.9rem;
            text-align: right;
        }
        
        .ad-spend-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .ad-spend-input:disabled {
            opacity: 0.3;
        }
        
        .is-ad-row {
            background: rgba(102, 126, 234, 0.1) !important;
        }
        
        .title-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .number {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-summary {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.03);
            padding: 10px 20px;
            border-radius: 10px;
        }
        
        .stat-item .value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #667eea;
        }
        
        .stat-item .label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: rgba(255,255,255,0.5);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> จัดการโพสต์ - ระบุโฆษณา</h1>
        </div>
        
        <div class="nav-links">
            <a href="../index.php"><i class="fas fa-home"></i> หน้าหลัก</a>
            <a href="upload.php"><i class="fas fa-upload"></i> อัพโหลด</a>
            <a href="manage.php"><i class="fas fa-edit"></i> จัดการโพสต์</a>
            <a href="index.php"><i class="fas fa-chart-bar"></i> รายงาน</a>
            <a href="ad_analysis.php" class="active"><i class="fas fa-bullhorn"></i> วิเคราะห์โฆษณา</a>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>เดือน</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php foreach ($availableMonths as $m): ?>
                        <option value="<?php echo $m['report_month']; ?>" 
                                data-year="<?php echo $m['report_year']; ?>"
                                <?php echo ($m['report_month'] == $selectedMonth && $m['report_year'] == $selectedYear) ? 'selected' : ''; ?>>
                            <?php echo $thaiMonths[$m['report_month']]; ?> <?php echo $m['report_year'] + 543; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="year" id="yearInput" value="<?php echo $selectedYear; ?>">
                
                <div class="filter-group">
                    <label>Platform</label>
                    <select name="platform" onchange="this.form.submit()">
                        <option value="">ทั้งหมด</option>
                        <option value="Facebook" <?php echo $selectedPlatform === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                        <option value="Instagram" <?php echo $selectedPlatform === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                        <option value="TikTok" <?php echo $selectedPlatform === 'TikTok' ? 'selected' : ''; ?>>TikTok</option>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if (empty($posts)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>ไม่พบข้อมูลในเดือนที่เลือก</p>
                <p><a href="upload.php" style="color: #667eea;">อัพโหลดข้อมูลใหม่</a></p>
            </div>
        </div>
        <?php else: ?>
        
        <form method="POST" id="adForm">
            <input type="hidden" name="all_post_ids" value="<?php echo implode(',', array_column($posts, 'id')); ?>">
            
            <div class="card">
                <div class="action-bar">
                    <div class="stats-summary">
                        <div class="stat-item">
                            <div class="value"><?php echo count($posts); ?></div>
                            <div class="label">โพสต์ทั้งหมด</div>
                        </div>
                        <div class="stat-item">
                            <div class="value" id="adCount"><?php echo count(array_filter($posts, fn($p) => $p['is_ad'])); ?></div>
                            <div class="label">ยิงโฆษณา</div>
                        </div>
                        <div class="stat-item">
                            <div class="value" id="totalSpend">฿<?php echo number_format(array_sum(array_column($posts, 'ad_spend'))); ?></div>
                            <div class="label">ยอดใช้จ่ายรวม</div>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_ads" class="btn btn-success">
                        <i class="fas fa-save"></i> บันทึกข้อมูล
                    </button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">
                                    <input type="checkbox" id="selectAll" title="เลือกทั้งหมด">
                                </th>
                                <th>ยอดเงิน (บาท)</th>
                                <th>Platform</th>
                                <th>ชื่อ/Title</th>
                                <th class="number">Views</th>
                                <th class="number">Likes</th>
                                <th class="number">Comments</th>
                                <th class="number">Shares</th>
                                <th>วันที่โพสต์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                            <tr class="<?php echo $post['is_ad'] ? 'is-ad-row' : ''; ?>" data-id="<?php echo $post['id']; ?>">
                                <td class="checkbox-wrapper">
                                    <input type="checkbox" 
                                           name="is_ad_<?php echo $post['id']; ?>" 
                                           class="ad-checkbox"
                                           data-id="<?php echo $post['id']; ?>"
                                           <?php echo $post['is_ad'] ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="ad_spend_<?php echo $post['id']; ?>" 
                                           class="ad-spend-input"
                                           data-id="<?php echo $post['id']; ?>"
                                           value="<?php echo $post['ad_spend']; ?>"
                                           min="0"
                                           step="0.01"
                                           <?php echo !$post['is_ad'] ? 'disabled' : ''; ?>>
                                </td>
                                <td><span class="platform-badge <?php echo $post['social']; ?>"><?php echo $post['social']; ?></span></td>
                                <td class="title-cell" title="<?php echo htmlspecialchars($post['title'] ?: $post['description']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($post['title'] ?: $post['description'], 0, 50)); ?>
                                    <?php if (strlen($post['title'] ?: $post['description']) > 50): ?>...<?php endif; ?>
                                </td>
                                <td class="number"><?php echo number_format($post['views']); ?></td>
                                <td class="number"><?php echo number_format($post['likes']); ?></td>
                                <td class="number"><?php echo number_format($post['comments']); ?></td>
                                <td class="number"><?php echo number_format($post['shares']); ?></td>
                                <td><?php echo $post['publish_time'] ? date('d/m/Y H:i', strtotime($post['publish_time'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
        
        <?php endif; ?>
    </div>
    
    <script>
        // อัพเดทปีเมื่อเลือกเดือน
        document.querySelector('select[name="month"]')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('yearInput').value = selected.dataset.year;
        });
        
        // Toggle ad spend input
        document.querySelectorAll('.ad-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const id = this.dataset.id;
                const spendInput = document.querySelector(`.ad-spend-input[data-id="${id}"]`);
                const row = this.closest('tr');
                
                if (this.checked) {
                    spendInput.disabled = false;
                    row.classList.add('is-ad-row');
                } else {
                    spendInput.disabled = true;
                    spendInput.value = '0';
                    row.classList.remove('is-ad-row');
                }
                
                updateStats();
            });
        });
        
        // อัพเดท stats realtime
        document.querySelectorAll('.ad-spend-input').forEach(input => {
            input.addEventListener('input', updateStats);
        });
        
        function updateStats() {
            const adCheckboxes = document.querySelectorAll('.ad-checkbox:checked');
            const adCount = adCheckboxes.length;
            
            let totalSpend = 0;
            document.querySelectorAll('.ad-spend-input:not(:disabled)').forEach(input => {
                totalSpend += parseFloat(input.value) || 0;
            });
            
            document.getElementById('adCount').textContent = adCount;
            document.getElementById('totalSpend').textContent = '฿' + totalSpend.toLocaleString();
        }
        
        // Select all
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.ad-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                checkbox.dispatchEvent(new Event('change'));
            });
        });
    </script>
</body>
</html>