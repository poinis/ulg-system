<?php
require_once 'config.php';
require_once 'Database.php';

$db = Database::getInstance()->getConnection();

// Get statistics
$stats = [
    'total_sales' => 0,
    'total_receipts' => 0,
    'today_sales' => 0,
    'today_receipts' => 0
];

// Total sales
$stmt = $db->query("SELECT SUM(amount_total) as total, COUNT(*) as count FROM sale_payments WHERE ticket_annule IS NULL OR ticket_annule = '-'");
$result = $stmt->fetch();
$stats['total_sales'] = $result['total'] ?? 0;
$stats['total_receipts'] = $result['count'] ?? 0;

// Today sales
$stmt = $db->prepare("SELECT SUM(amount_total) as total, COUNT(*) as count FROM sale_payments WHERE date_piece = CURDATE() AND (ticket_annule IS NULL OR ticket_annule = '-')");
$stmt->execute();
$result = $stmt->fetch();
$stats['today_sales'] = $result['total'] ?? 0;
$stats['today_receipts'] = $result['count'] ?? 0;

// Recent sync logs
$stmt = $db->query("SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 10");
$sync_logs = $stmt->fetchAll();

// Sales by store (last 7 days)
$stmt = $db->query("
    SELECT 
        sp.store_code,
        s.store_name,
        COUNT(*) as receipt_count,
        SUM(sp.amount_total) as total_sales
    FROM sale_payments sp
    LEFT JOIN stores s ON sp.store_code = s.store_code
    WHERE sp.date_piece >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND (sp.ticket_annule IS NULL OR sp.ticket_annule = '-')
    GROUP BY sp.store_code, s.store_name
    ORDER BY total_sales DESC
    LIMIT 5
");
$stores_sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cegid Sales Sync System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <i class="fas fa-sync-alt text-indigo-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Cegid Sales Sync System</h1>
                </div>
                <div class="flex space-x-4">
                    <a href="index.php" class="text-indigo-600 font-medium">
                        <i class="fas fa-home mr-1"></i> Dashboard
                    </a>
                    <a href="sync.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-sync mr-1"></i> Sync Data
                    </a>
                    <a href="export.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-download mr-1"></i> Export
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">ยอดขายวันนี้</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= number_format($stats['today_sales'], 2) ?> ฿
                        </p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">บิลวันนี้</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= number_format($stats['today_receipts']) ?>
                        </p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-receipt text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">ยอดขายรวม</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= number_format($stats['total_sales'], 2) ?> ฿
                        </p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">บิลทั้งหมด</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= number_format($stats['total_receipts']) ?>
                        </p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i class="fas fa-file-invoice text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales by Store -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-store mr-2 text-indigo-600"></i>
                        ยอดขายตามสาขา (7 วันล่าสุด)
                    </h2>
                </div>
                <div class="p-6">
                    <?php if (empty($stores_sales)): ?>
                        <p class="text-gray-500 text-center py-4">ยังไม่มีข้อมูลยอดขาย</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($stores_sales as $store): ?>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?= htmlspecialchars($store['store_name'] ?: $store['store_code']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= number_format($store['receipt_count']) ?> บิล
                                    </p>
                                </div>
                                <p class="text-lg font-bold text-indigo-600">
                                    <?= number_format($store['total_sales'], 2) ?> ฿
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Sync Logs -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-history mr-2 text-indigo-600"></i>
                        ประวัติการซิงค์ล่าสุด
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php if (empty($sync_logs)): ?>
                            <p class="text-gray-500 text-center py-4">ยังไม่มีข้อมูลการซิงค์</p>
                        <?php else: ?>
                            <?php foreach (array_slice($sync_logs, 0, 5) as $log): ?>
                            <div class="flex items-center justify-between border-b pb-3">
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?= htmlspecialchars($log['file_name']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($log['started_at'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <?php if ($log['status'] === 'completed'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i> สำเร็จ
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= number_format($log['records_success']) ?> รายการ
                                        </p>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i> ล้มเหลว
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-spinner fa-spin mr-1"></i> กำลังประมวลผล
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-bolt mr-2 text-indigo-600"></i>
                Quick Actions
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="sync.php" class="flex items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition">
                    <i class="fas fa-sync text-2xl text-indigo-600 mr-4"></i>
                    <div>
                        <p class="font-medium text-gray-900">Sync Data</p>
                        <p class="text-sm text-gray-500">ดึงข้อมูลจาก Cegid API</p>
                    </div>
                </a>

                <a href="export.php" class="flex items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                    <i class="fas fa-download text-2xl text-green-600 mr-4"></i>
                    <div>
                        <p class="font-medium text-gray-900">Export CSV</p>
                        <p class="text-sm text-gray-500">ส่งออกข้อมูลเป็น CSV</p>
                    </div>
                </a>

                <a href="api_endpoint_tester.php" class="flex items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition" target="_blank">
                    <i class="fas fa-vial text-2xl text-purple-600 mr-4"></i>
                    <div>
                        <p class="font-medium text-gray-900">API Tester</p>
                        <p class="text-sm text-gray-500">ทดสอบ API Endpoints</p>
                    </div>
                </a>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-center text-gray-500 text-sm">
                Cegid Sales Sync System v2.0 &copy; <?= date('Y') ?> | Powered by REST API
            </p>
        </div>
    </footer>

</body>
</html>
