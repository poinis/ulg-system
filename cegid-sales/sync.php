<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'CegidAPI.php';
require_once 'SalesSync.php';

$message = '';
$messageType = '';
$syncResult = null;

// Handle sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'test_connection') {
        // Test API connection
        try {
            $sync = new SalesSync();
            $result = $sync->testConnection();
            
            if ($result['success']) {
                $message = '✅ เชื่อมต่อ Cegid API สำเร็จ!';
                $messageType = 'success';
            } else {
                $message = '❌ ไม่สามารถเชื่อมต่อ API: ' . $result['message'];
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
        
    } elseif ($action === 'sync_data') {
        // Sync sales data
        $syncDate = $_POST['sync_date'] ?? date('Y-m-d');
        $storeCode = $_POST['store_code'] ?? null;
        
        try {
            $sync = new SalesSync();
            $syncResult = $sync->syncDate($syncDate, $storeCode);
            
            if ($syncResult['success']) {
                $totalSuccess = $syncResult['payments']['success'] + $syncResult['transactions']['success'];
                $totalFailed = $syncResult['payments']['failed'] + $syncResult['transactions']['failed'];
                
                $message = "✅ ดึงข้อมูลสำเร็จ! Payment: {$syncResult['payments']['success']} รายการ, Transaction: {$syncResult['transactions']['success']} รายการ";
                
                if ($totalFailed > 0) {
                    $message .= " (ล้มเหลว {$totalFailed} รายการ)";
                }
                
                $messageType = 'success';
            } else {
                $message = '❌ การดึงข้อมูลล้มเหลว';
                if (!empty($syncResult['errors'])) {
                    $message .= ': ' . implode(', ', $syncResult['errors']);
                }
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get available stores
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT DISTINCT store_code, store_name FROM stores ORDER BY store_code");
$stores = $stmt->fetchAll();

// Get recent sync logs
$stmt = $db->query("SELECT * FROM sync_logs WHERE sync_type = 'api_sync' ORDER BY started_at DESC LIMIT 10");
$sync_logs = $stmt->fetchAll();

// Get today's stats
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT ref_interne) as receipts,
        SUM(amount_total) as total_sales
    FROM sale_payments 
    WHERE date_piece = ? AND (ticket_annule IS NULL OR ticket_annule = '-')
");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Data - Cegid Sales System</title>
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
                    <a href="index.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-home mr-1"></i> Dashboard
                    </a>
                    <a href="sync.php" class="text-indigo-600 font-medium">
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
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-sync mr-2 text-indigo-600"></i>
                ดึงข้อมูลจาก Cegid API
            </h2>
            <p class="text-gray-600 mt-1">ดึงยอดขายรายวันจาก Cegid REST API มาเก็บใน Database</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
            <div class="flex items-center">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600' ?> mr-3"></i>
                <p class="<?= $messageType === 'success' ? 'text-green-800' : 'text-red-800' ?>"><?= htmlspecialchars($message) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sync Result Details -->
        <?php if ($syncResult && $syncResult['success']): ?>
        <div class="mb-6 bg-white rounded-lg shadow p-6">
            <h3 class="font-bold text-gray-900 mb-4">
                <i class="fas fa-chart-bar mr-2 text-green-600"></i>
                รายละเอียดการดึงข้อมูล
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Payment Records</p>
                    <p class="text-2xl font-bold text-blue-600">
                        <?= $syncResult['payments']['success'] ?> / <?= $syncResult['payments']['total'] ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">สำเร็จ / ทั้งหมด</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Transaction Records</p>
                    <p class="text-2xl font-bold text-purple-600">
                        <?= $syncResult['transactions']['success'] ?> / <?= $syncResult['transactions']['total'] ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">สำเร็จ / ทั้งหมด</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <!-- Today's Stats -->
            <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-calendar-day mr-2"></i>
                    ข้อมูลวันนี้
                </h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm opacity-90">ยอดขาย</p>
                        <p class="text-2xl font-bold">
                            <?= number_format($todayStats['total_sales'] ?? 0, 2) ?> ฿
                        </p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">จำนวนบิล</p>
                        <p class="text-xl font-bold">
                            <?= number_format($todayStats['receipts'] ?? 0) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Connection Test -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-plug mr-2 text-gray-600"></i>
                    ทดสอบการเชื่อมต่อ
                </h3>
                <p class="text-sm text-gray-600 mb-4">ตรวจสอบการเชื่อมต่อกับ Cegid API</p>
                <form method="POST">
                    <input type="hidden" name="action" value="test_connection">
                    <button type="submit" class="w-full px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                        <i class="fas fa-check-circle mr-2"></i>
                        ทดสอบเชื่อมต่อ
                    </button>
                </form>
            </div>

            <!-- Quick Info -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                    ข้อมูล API
                </h3>
                <div class="text-sm text-gray-600 space-y-2">
                    <p><strong>Server:</strong> Cegid Y2 Cloud</p>
                    <p><strong>Method:</strong> REST API</p>
                    <p><strong>Auth:</strong> HTTP Basic</p>
                    <p><strong>Format:</strong> JSON</p>
                </div>
            </div>
        </div>

        <!-- Sync Form -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-cloud-download-alt mr-2 text-indigo-600"></i>
                ดึงข้อมูลยอดขาย
            </h3>
            
            <form method="POST" id="syncForm">
                <input type="hidden" name="action" value="sync_data">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Sync Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            วันที่ต้องการดึงข้อมูล <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="sync_date" value="<?= date('Y-m-d') ?>" required
                               max="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">เลือกวันที่ต้องการดึงยอดขาย</p>
                    </div>

                    <!-- Store Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            กรองตามสาขา (Optional)
                        </label>
                        <select name="store_code"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- ทุกสาขา --</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?= htmlspecialchars($store['store_code']) ?>">
                                    <?= htmlspecialchars($store['store_name'] ?: $store['store_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">เว้นว่างเพื่อดึงทุกสาขา</p>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="font-medium text-blue-900 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>ข้อมูลที่จะดึง
                    </h4>
                    <ul class="text-sm text-blue-800 space-y-1 ml-5">
                        <li>• <strong>Sale Payments</strong> - ข้อมูลการชำระเงิน (Receipt, Amount, Payment Method)</li>
                        <li>• <strong>Sale Transactions</strong> - รายละเอียดสินค้าแต่ละรายการ (Product, Price, Quantity)</li>
                        <li>• ข้อมูลจะถูกเก็บในฐานข้อมูลและสามารถ Export เป็น CSV ได้</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <a href="index.php" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>ยกเลิก
                    </a>
                    <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-sync mr-2"></i>เริ่มดึงข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Sync History -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-history mr-2 text-gray-600"></i>
                ประวัติการ Sync ล่าสุด
            </h3>
            
            <?php if (empty($sync_logs)): ?>
                <p class="text-gray-500 text-center py-8">ยังไม่มีประวัติการ Sync</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ข้อมูล</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ผลลัพธ์</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sync_logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('d/m/Y H:i', strtotime($log['started_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($log['file_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($log['status'] === 'completed'): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i> สำเร็จ
                                        </span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i> ล้มเหลว
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-spinner fa-spin mr-1"></i> กำลังประมวลผล
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($log['records_success']) ?> / <?= number_format($log['records_processed']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Confirm before sync
        document.getElementById('syncForm').addEventListener('submit', function(e) {
            const date = this.querySelector('[name="sync_date"]').value;
            const store = this.querySelector('[name="store_code"]').value;
            
            let msg = `ยืนยันดึงข้อมูลวันที่ ${date}`;
            if (store) {
                msg += ` สาขา ${store}`;
            }
            msg += '?';
            
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    </script>

</body>
</html>
