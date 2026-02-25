<?php
// manage_targets.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $store_code = $_POST['store_code'];
    $target_month = $_POST['target_month'] . '-01';
    $monthly_target = floatval($_POST['monthly_target']);
    
    // Calculate daily target (divide by days in month)
    $days_in_month = date('t', strtotime($target_month));
    $daily_target = $monthly_target / $days_in_month;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO sales_targets (store_code, target_month, monthly_target, daily_target)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                monthly_target = VALUES(monthly_target),
                daily_target = VALUES(daily_target)
        ");
        
        $stmt->execute([$store_code, $target_month, $monthly_target, $daily_target]);
        $message = 'บันทึกเป้าหมายสำเร็จ!';
        
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// Get stores
$stores = $db->query("SELECT * FROM stores WHERE is_active = 1 ORDER BY store_code")->fetchAll();

// Get current targets
$current_month = date('Y-m');
$targets_stmt = $db->prepare("
    SELECT st.*, s.store_name 
    FROM sales_targets st
    JOIN stores s ON st.store_code = s.store_code
    WHERE DATE_FORMAT(st.target_month, '%Y-%m') = ?
    ORDER BY st.store_code
");
$targets_stmt->execute([$current_month]);
$targets = $targets_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเป้าหมายยอดขาย</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-weight: 500; color: #555; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn { padding: 10px 25px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f8f9fa; }
        .number { text-align: right; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .back-link { display: inline-block; margin-top: 20px; color: #2196F3; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🎯 จัดการเป้าหมายยอดขาย</h1>
            <p class="subtitle">กำหนดเป้าหมายยอดขายรายเดือนของแต่ละสาขา</p>
            
            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>สาขา</label>
                        <select name="store_code" required>
                            <option value="">-- เลือกสาขา --</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['store_code']; ?>">
                                    <?php echo $store['store_code'] . ' - ' . $store['store_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>เดือน</label>
                        <input type="month" name="target_month" value="<?php echo $current_month; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>เป้าหมายรายเดือน (บาท)</label>
                        <input type="number" name="monthly_target" step="0.01" placeholder="0.00" required>
                    </div>
                    
                    <button type="submit" class="btn">💾 บันทึก</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>📊 เป้าหมายปัจจุบัน - <?php echo getThaiMonth(date('m')) . ' ' . (date('Y') + 543); ?></h2>
            
            <table>
                <thead>
                    <tr>
                        <th>รหัสสาขา</th>
                        <th>ชื่อสาขา</th>
                        <th class="number">เป้าหมายรายเดือน</th>
                        <th class="number">เป้าหมายรายวัน</th>
                        <th class="number">จำนวนวัน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($targets)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">ยังไม่มีข้อมูลเป้าหมาย</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($targets as $target): ?>
                            <tr>
                                <td><?php echo $target['store_code']; ?></td>
                                <td><?php echo $target['store_name']; ?></td>
                                <td class="number"><?php echo formatNumber($target['monthly_target']); ?></td>
                                <td class="number"><?php echo formatNumber($target['daily_target']); ?></td>
                                <td class="number"><?php echo date('t', strtotime($target['target_month'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <a href="dashboard.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>