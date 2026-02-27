<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

// Selected date (default today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get statistics
$stats = [
    'total_sales' => 0,
    'total_receipts' => 0,
    'day_sales' => 0,
    'day_receipts' => 0
];

// Total sales (all time)
$stmt = $db->query("SELECT COALESCE(SUM(amount_total),0) as total, COUNT(*) as count FROM sale_payments WHERE ticket_annule = '-'");
$result = $stmt->fetch();
$stats['total_sales'] = $result['total'];
$stats['total_receipts'] = $result['count'];

// Selected day sales
$stmt = $db->prepare("SELECT COALESCE(SUM(amount_total),0) as total, COUNT(*) as count FROM sale_payments WHERE date_piece = ? AND ticket_annule = '-'");
$stmt->execute([$selectedDate]);
$result = $stmt->fetch();
$stats['day_sales'] = $result['total'];
$stats['day_receipts'] = $result['count'];

// Sales by store for selected date
$stmt = $db->prepare("
    SELECT store_code, COUNT(*) as receipt_count, SUM(amount_total) as total_sales
    FROM sale_payments
    WHERE date_piece = ? AND ticket_annule = '-'
    GROUP BY store_code
    ORDER BY total_sales DESC
");
$stmt->execute([$selectedDate]);
$storesSales = $stmt->fetchAll();

// Daily totals (last 14 days)
$stmt = $db->query("
    SELECT date_piece, COUNT(*) as receipt_count, SUM(amount_total) as total_sales
    FROM sale_payments
    WHERE date_piece >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND ticket_annule = '-'
    GROUP BY date_piece
    ORDER BY date_piece DESC
");
$dailyTotals = $stmt->fetchAll();

// Recent sync logs
$stmt = $db->query("SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 5");
$syncLogs = $stmt->fetchAll();

// Available dates
$stmt = $db->query("SELECT DISTINCT date_piece FROM sale_payments ORDER BY date_piece DESC LIMIT 30");
$availableDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Helper: get store name
function storeName($code) {
    return STORE_NAMES[$code] ?? $code;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cegid Sales Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <nav class="bg-indigo-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-bar text-2xl"></i>
                    <h1 class="text-xl font-bold">Cegid Sales Dashboard</h1>
                </div>
                <div class="flex gap-4 text-sm">
                    <a href="index.php" class="bg-indigo-800 px-3 py-1.5 rounded font-medium">
                        <i class="fas fa-home mr-1"></i> Dashboard
                    </a>
                    <a href="sync.php" class="hover:bg-indigo-600 px-3 py-1.5 rounded">
                        <i class="fas fa-sync mr-1"></i> Sync
                    </a>
                    <a href="export.php" class="hover:bg-indigo-600 px-3 py-1.5 rounded">
                        <i class="fas fa-download mr-1"></i> Export
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">

        <!-- Date Selector -->
        <div class="mb-6 flex items-center gap-4">
            <form method="get" class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">
                    <i class="fas fa-calendar mr-1"></i> วันที่:
                </label>
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>"
                    class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                    onchange="this.form.submit()">
            </form>
            <span class="text-sm text-gray-500">
                <?php
                $dt = new DateTime($selectedDate);
                $thaiDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
                echo 'วัน' . $thaiDays[(int)$dt->format('w')] . ' ' . $dt->format('d/m/Y');
                ?>
            </span>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">ยอดขายวันที่เลือก</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['day_sales'], 2) ?></p>
                <p class="text-xs text-gray-400">THB</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">จำนวนบิล</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['day_receipts']) ?></p>
                <p class="text-xs text-gray-400">รายการ</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">ยอดขายรวมทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['total_sales'], 2) ?></p>
                <p class="text-xs text-gray-400">THB</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">บิลทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['total_receipts']) ?></p>
                <p class="text-xs text-gray-400">รายการ</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Sales by Store -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm">
                <div class="p-5 border-b flex justify-between items-center">
                    <h2 class="font-bold text-gray-900">
                        <i class="fas fa-store text-indigo-600 mr-2"></i>
                        ยอดขายตามสาขา — <?= $dt->format('d/m/Y') ?>
                    </h2>
                    <span class="text-sm text-gray-400"><?= count($storesSales) ?> สาขา</span>
                </div>
                <div class="divide-y">
                    <?php if (empty($storesSales)): ?>
                        <p class="text-gray-400 text-center py-8">ไม่มีข้อมูลวันนี้</p>
                    <?php else: ?>
                        <?php
                        $maxSales = max(array_column($storesSales, 'total_sales'));
                        foreach ($storesSales as $i => $store):
                            $pct = $maxSales > 0 ? ($store['total_sales'] / $maxSales * 100) : 0;
                        ?>
                        <div class="px-5 py-3 hover:bg-gray-50">
                            <div class="flex justify-between items-center mb-1">
                                <div>
                                    <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars(storeName($store['store_code'])) ?></span>
                                    <span class="text-xs text-gray-400 ml-2">(<?= $store['store_code'] ?>)</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-gray-900"><?= number_format($store['total_sales'], 2) ?></span>
                                    <span class="text-xs text-gray-400 ml-1"><?= $store['receipt_count'] ?> บิล</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full" style="width: <?= round($pct) ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">

                <!-- Daily Summary -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-5 border-b">
                        <h2 class="font-bold text-gray-900">
                            <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                            ยอดรายวัน
                        </h2>
                    </div>
                    <div class="divide-y max-h-80 overflow-y-auto">
                        <?php foreach ($dailyTotals as $day): ?>
                        <a href="?date=<?= $day['date_piece'] ?>"
                           class="block px-5 py-3 hover:bg-indigo-50 <?= $day['date_piece'] === $selectedDate ? 'bg-indigo-50 border-l-2 border-indigo-500' : '' ?>">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-700"><?= date('d/m (D)', strtotime($day['date_piece'])) ?></span>
                                <span class="text-sm font-bold text-gray-900"><?= number_format($day['total_sales'], 2) ?></span>
                            </div>
                            <span class="text-xs text-gray-400"><?= $day['receipt_count'] ?> บิล</span>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($dailyTotals)): ?>
                            <p class="text-gray-400 text-center py-6">ยังไม่มีข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sync Status -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-5 border-b">
                        <h2 class="font-bold text-gray-900">
                            <i class="fas fa-sync text-indigo-600 mr-2"></i>
                            Sync ล่าสุด
                        </h2>
                    </div>
                    <div class="divide-y">
                        <?php foreach ($syncLogs as $log): ?>
                        <div class="px-5 py-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700"><?= date('d/m H:i', strtotime($log['started_at'])) ?></span>
                                <?php if ($log['status'] === 'completed'): ?>
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">✓ <?= $log['records_success'] ?></span>
                                <?php elseif ($log['status'] === 'failed'): ?>
                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">✗ failed</span>
                                <?php else: ?>
                                    <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">⏳</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($syncLogs)): ?>
                            <p class="text-gray-400 text-center py-6">ยังไม่มี</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-gray-400 text-xs py-6">
        Cegid Sales Dashboard v2.1 &copy; <?= date('Y') ?>
    </footer>
</body>
</html>
