<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'CSVExporter.php';

$message = '';
$messageType = '';

// Handle export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    $exportType = $_POST['export_type'] ?? '';
    $dateFrom = $_POST['date_from'] ?? null;
    $dateTo = $_POST['date_to'] ?? null;
    $storeCode = $_POST['store_code'] ?? null;
    
    if (empty($exportType)) {
        $message = 'กรุณาเลือกประเภทการส่งออก';
        $messageType = 'error';
    } else {
        try {
            $exporter = new CSVExporter();
            
            if ($exportType === 'payment') {
                $result = $exporter->exportPayments($dateFrom, $dateTo, $storeCode);
                $message = "ส่งออกข้อมูล Payment สำเร็จ! ({$result['records']} รายการ)";
            } elseif ($exportType === 'transaction') {
                $result = $exporter->exportTransactions($dateFrom, $dateTo, $storeCode);
                $message = "ส่งออกข้อมูล Transaction สำเร็จ! ({$result['records']} รายการ)";
            } elseif ($exportType === 'both') {
                $result = $exporter->exportBoth($dateFrom, $dateTo, $storeCode);
                $message = "ส่งออกข้อมูลทั้ง 2 ไฟล์สำเร็จ!";
            }
            
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get available stores
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT DISTINCT store_code, store_name FROM stores ORDER BY store_code");
$stores = $stmt->fetchAll();

// Get export files
$exporter = new CSVExporter();
$exportFiles = $exporter->getExportFiles();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export CSV - Cegid Sales Sync</title>
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
                    <a href="import.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-upload mr-1"></i> Import
                    </a>
                    <a href="export.php" class="text-indigo-600 font-medium">
                        <i class="fas fa-download mr-1"></i> Export
                    </a>
                    <a href="reports.php" class="text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-chart-bar mr-1"></i> Reports
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-download mr-2 text-indigo-600"></i>
                ส่งออกข้อมูล CSV
            </h2>
            <p class="text-gray-600 mt-1">ส่งออกข้อมูลจากระบบเป็นไฟล์ CSV</p>
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

        <!-- Export Form -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <form method="POST">
                <input type="hidden" name="action" value="export">
                
                <!-- Export Type -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        ประเภทการส่งออก <span class="text-red-500">*</span>
                    </label>
                    <select name="export_type" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- เลือกประเภท --</option>
                        <option value="payment">Sale Payment (ข้อมูลการชำระเงิน)</option>
                        <option value="transaction">Sale Transaction (รายละเอียดสินค้า)</option>
                        <option value="both">ทั้ง 2 ไฟล์</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ตั้งแต่วันที่
                        </label>
                        <input type="date" name="date_from"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ถึงวันที่
                        </label>
                        <input type="date" name="date_to"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Store Filter -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        กรองตามสาขา (ถ้าไม่เลือก = ทุกสาขา)
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
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-download mr-2"></i>ส่งออกข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Export Files List -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-file-csv mr-2 text-gray-600"></i>
                ไฟล์ที่ส่งออกแล้ว
            </h3>
            
            <?php if (empty($exportFiles)): ?>
                <p class="text-gray-500 text-center py-8">ยังไม่มีไฟล์ที่ส่งออก</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อไฟล์</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ขนาด</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่สร้าง</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">ดาวน์โหลด</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($exportFiles as $file): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-csv text-green-600 mr-2"></i>
                                        <span class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($file['name']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($file['size'] / 1024, 2) ?> KB
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $file['date'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="<?= $file['download_url'] ?>" 
                                       class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition">
                                        <i class="fas fa-download mr-1"></i> ดาวน์โหลด
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
