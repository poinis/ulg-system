<?php
/**
 * ระบบดูจำนวนสินค้าในคลังสาขา
 * Features: Filter สาขา, แบรนด์, จำนวน > 0 / < 0, Export Excel, Pagination
 */

// กำหนด Timezone เป็นเวลาไทย
date_default_timezone_set('Asia/Bangkok');

$host = 'localhost';
$user = 'cmbase';
$pass = '#wmIYH3wazaa';
$db = 'cmbase';
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// รับค่า Filter
$warehouse = isset($_GET['warehouse']) ? $_GET['warehouse'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$qty_filter = isset($_GET['qty_filter']) ? $_GET['qty_filter'] : 'all';
$export = isset($_GET['export']) ? $_GET['export'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 100;

// ดึงรายการสาขาสำหรับ Dropdown
$sql_wh = "SELECT DISTINCT `warehouse code`, `warehouse desc` FROM stockc WHERE `warehouse desc` IS NOT NULL AND `warehouse desc` != '' ORDER BY `warehouse desc`";
$result_wh = mysqli_query($conn, $sql_wh);
$warehouses = [];
while ($row = mysqli_fetch_assoc($result_wh)) {
    $warehouses[] = $row;
}

// ดึงรายการแบรนด์สำหรับ Dropdown
$sql_brand = "SELECT DISTINCT brand FROM stockc WHERE brand IS NOT NULL AND brand != '' ORDER BY brand";
$result_brand = mysqli_query($conn, $sql_brand);
$brands = [];
while ($row = mysqli_fetch_assoc($result_brand)) {
    $brands[] = $row;
}

// สร้าง WHERE Condition
$where = "WHERE 1=1";
if (!empty($warehouse)) {
    $warehouse_escaped = mysqli_real_escape_string($conn, $warehouse);
    $where .= " AND `warehouse code` = '$warehouse_escaped'";
}
if (!empty($brand)) {
    $brand_escaped = mysqli_real_escape_string($conn, $brand);
    $where .= " AND brand = '$brand_escaped'";
}
if ($qty_filter === 'positive') {
    $where .= " AND physical > 0";
} elseif ($qty_filter === 'negative') {
    $where .= " AND physical < 0";
} elseif ($qty_filter === 'zero') {
    $where .= " AND physical = 0";
}

// นับจำนวนทั้งหมดสำหรับ Pagination
$sql_count = "SELECT COUNT(*) as total FROM stockc $where";
$result_count = mysqli_query($conn, $sql_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_records = $row_count['total'];
$total_pages = ceil($total_records / $per_page);
$page = max(1, min($page, $total_pages > 0 ? $total_pages : 1));
$offset = ($page - 1) * $per_page;

// ดึงข้อมูลสถิติ
$sql_stats = "SELECT 
    SUM(CASE WHEN physical > 0 THEN 1 ELSE 0 END) as positive_count,
    SUM(CASE WHEN physical < 0 THEN 1 ELSE 0 END) as negative_count,
    SUM(CASE WHEN physical = 0 THEN 1 ELSE 0 END) as zero_count,
    SUM(physical) as total_qty
    FROM stockc $where";
$result_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($result_stats);

// สร้าง Query สำหรับดึงข้อมูล
$sql = "SELECT barcode, `item code`, `item desc`, brand, `warehouse desc`, physical as quantity 
        FROM stockc $where 
        ORDER BY `warehouse desc`, brand, `item code`";

// Export Excel (ดึงทั้งหมด)
if ($export === 'excel') {
    $result_all = mysqli_query($conn, $sql);
    $results_all = [];
    while ($row = mysqli_fetch_assoc($result_all)) {
        $results_all[] = $row;
    }
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr style="background-color:#4CAF50;color:white;font-weight:bold;">';
    echo '<th>ลำดับ</th>';
    echo '<th>Barcode</th>';
    echo '<th>Item Code</th>';
    echo '<th>รายละเอียดสินค้า</th>';
    echo '<th>แบรนด์</th>';
    echo '<th>สาขา</th>';
    echo '<th>จำนวน</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($results_all as $row) {
        $qty_style = $row['quantity'] < 0 ? 'color:red;' : ($row['quantity'] > 0 ? 'color:green;' : '');
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['barcode']) . '</td>';
        echo '<td>' . htmlspecialchars($row['item code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['item desc']) . '</td>';
        echo '<td>' . htmlspecialchars($row['brand']) . '</td>';
        echo '<td>' . htmlspecialchars($row['warehouse desc']) . '</td>';
        echo '<td style="' . $qty_style . 'text-align:right;">' . number_format($row['quantity']) . '</td>';
        echo '</tr>';
    }
    
    $total_qty = array_sum(array_column($results_all, 'quantity'));
    echo '<tr style="background-color:#f0f0f0;font-weight:bold;">';
    echo '<td colspan="6" style="text-align:right;">รวมทั้งหมด:</td>';
    echo '<td style="text-align:right;">' . number_format($total_qty) . '</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    mysqli_close($conn);
    exit;
}

// ดึงข้อมูลตาม Pagination
$sql .= " LIMIT $offset, $per_page";
$result = mysqli_query($conn, $sql);
$results = [];
while ($row = mysqli_fetch_assoc($result)) {
    $results[] = $row;
}

// สร้าง Query String สำหรับ Pagination
function buildQueryString($params, $page) {
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
$query_params = array_filter([
    'warehouse' => $warehouse,
    'brand' => $brand,
    'qty_filter' => $qty_filter
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบดูจำนวนสินค้าในคลังสาขา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            margin: 20px auto;
            padding: 30px;
            max-width: 1400px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .filter-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .filter-card .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-search {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .btn-export {
            background: linear-gradient(135deg, var(--success-color), #047857);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
        }
        
        .btn-reset {
            background: #6b7280;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card.total { border-left: 4px solid var(--primary-color); }
        .stat-card.positive { border-left: 4px solid var(--success-color); }
        .stat-card.negative { border-left: 4px solid var(--danger-color); }
        .stat-card.zero { border-left: 4px solid var(--warning-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .table { margin-bottom: 0; }
        
        .table thead th {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            font-weight: 600;
            padding: 15px;
            border: none;
            position: sticky;
            top: 0;
        }
        
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background-color: #f1f5f9; }
        
        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #e2e8f0;
        }
        
        .qty-positive { color: var(--success-color); font-weight: 700; }
        .qty-negative { color: var(--danger-color); font-weight: 700; }
        .qty-zero { color: var(--warning-color); font-weight: 600; }
        
        .badge-brand {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .badge-warehouse {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .no-data {
            text-align: center;
            padding: 60px;
            color: #6b7280;
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        /* Pagination Styles */
        .pagination-wrapper {
            background: #f8fafc;
            border-radius: 0 0 15px 15px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
            border: none;
            color: var(--primary-color);
            font-weight: 500;
            padding: 10px 15px;
        }
        
        .pagination .page-link:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        
        .pagination .page-item.disabled .page-link {
            color: #9ca3af;
            background: #e5e7eb;
        }
        
        .page-info {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .page-goto {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-goto input {
            width: 70px;
            text-align: center;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 8px;
        }
        
        .page-goto button {
            border-radius: 10px;
            padding: 8px 15px;
        }
        
        @media (max-width: 768px) {
            .filter-buttons {
                flex-direction: column;
                width: 100%;
            }
            .filter-buttons .btn { width: 100%; }
            .stat-card { min-width: 45%; }
            .pagination-wrapper .row > div {
                text-align: center !important;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="bi bi-box-seam me-2"></i>ระบบดูจำนวนสินค้าในคลังสาขา</h1>
            <p>Inventory Management System - ตรวจสอบและติดตามสต็อกสินค้าแต่ละสาขา</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-building me-1"></i>เลือกสาขา</label>
                        <select name="warehouse" class="form-select">
                            <option value="">-- ทุกสาขา --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh['warehouse code']) ?>" 
                                        <?= $warehouse == $wh['warehouse code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['warehouse desc']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-tag me-1"></i>เลือกแบรนด์</label>
                        <select name="brand" class="form-select">
                            <option value="">-- ทุกแบรนด์ --</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= htmlspecialchars($b['brand']) ?>" 
                                        <?= $brand == $b['brand'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['brand']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-filter me-1"></i>กรองจำนวน</label>
                        <select name="qty_filter" class="form-select">
                            <option value="all" <?= $qty_filter == 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                            <option value="positive" <?= $qty_filter == 'positive' ? 'selected' : '' ?>>มากกว่า 0</option>
                            <option value="negative" <?= $qty_filter == 'negative' ? 'selected' : '' ?>>น้อยกว่า 0</option>
                            <option value="zero" <?= $qty_filter == 'zero' ? 'selected' : '' ?>>เท่ากับ 0</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-search text-white">
                                <i class="bi bi-search me-1"></i>ค้นหา
                            </button>
                            <a href="?" class="btn btn-reset text-white">
                                <i class="bi bi-arrow-clockwise me-1"></i>รีเซ็ต
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-number text-primary"><?= number_format($total_records) ?></div>
                <div class="stat-label">รายการทั้งหมด</div>
            </div>
            <div class="stat-card positive">
                <div class="stat-number" style="color: var(--success-color)"><?= number_format($stats['positive_count'] ?? 0) ?></div>
                <div class="stat-label">รายการมีสต็อก (> 0)</div>
            </div>
            <div class="stat-card negative">
                <div class="stat-number" style="color: var(--danger-color)"><?= number_format($stats['negative_count'] ?? 0) ?></div>
                <div class="stat-label">รายการติดลบ (< 0)</div>
            </div>
            <div class="stat-card zero">
                <div class="stat-number" style="color: var(--warning-color)"><?= number_format($stats['zero_count'] ?? 0) ?></div>
                <div class="stat-label">รายการหมด (= 0)</div>
            </div>
        </div>
        
        <!-- Export Button & Info -->
        <?php if ($total_records > 0): ?>
        <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>รายการสินค้า
                <small class="text-muted">(จำนวนรวม: <?= number_format($stats['total_qty'] ?? 0) ?> ชิ้น)</small>
            </h5>
            <a href="?<?= http_build_query(array_merge($query_params, ['export' => 'excel'])) ?>" 
               class="btn btn-export text-white">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel (<?= number_format($total_records) ?> รายการ)
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <div class="table-container">
            <?php if (count($results) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>Barcode</th>
                            <th>Item Code</th>
                            <th>รายละเอียดสินค้า</th>
                            <th>แบรนด์</th>
                            <th>สาขา</th>
                            <th width="120" class="text-end">จำนวน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = $offset + 1; foreach ($results as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><code><?= htmlspecialchars($row['barcode']) ?></code></td>
                            <td><strong><?= htmlspecialchars($row['item code']) ?></strong></td>
                            <td><?= htmlspecialchars($row['item desc']) ?></td>
                            <td>
                                <?php if (!empty($row['brand'])): ?>
                                <span class="badge badge-brand"><?= htmlspecialchars($row['brand']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['warehouse desc'])): ?>
                                <span class="badge badge-warehouse"><?= htmlspecialchars($row['warehouse desc']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end <?= $row['quantity'] > 0 ? 'qty-positive' : ($row['quantity'] < 0 ? 'qty-negative' : 'qty-zero') ?>">
                                <?= number_format($row['quantity']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="row align-items-center">
                    <div class="col-md-4 text-start">
                        <span class="page-info">
                            แสดง <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $per_page, $total_records)) ?> 
                            จาก <?= number_format($total_records) ?> รายการ
                        </span>
                    </div>
                    <div class="col-md-4 text-center">
                        <nav>
                            <ul class="pagination justify-content-center">
                                <!-- First Page -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildQueryString($query_params, 1) ?>" title="หน้าแรก">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Previous Page -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildQueryString($query_params, $page - 1) ?>" title="ก่อนหน้า">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= buildQueryString($query_params, 1) ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= buildQueryString($query_params, $i) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= buildQueryString($query_params, $total_pages) ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Next Page -->
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildQueryString($query_params, $page + 1) ?>" title="ถัดไป">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                
                                <!-- Last Page -->
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildQueryString($query_params, $total_pages) ?>" title="หน้าสุดท้าย">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <div class="col-md-4 text-end">
                        <form class="page-goto d-inline-flex" method="GET">
                            <?php foreach ($query_params as $key => $value): ?>
                                <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>
                            <span>ไปหน้า</span>
                            <input type="number" name="page" min="1" max="<?= $total_pages ?>" value="<?= $page ?>" class="form-control">
                            <span>/ <?= $total_pages ?></span>
                            <button type="submit" class="btn btn-sm btn-primary">ไป</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-data">
                <i class="bi bi-inbox"></i>
                <h4>ไม่พบข้อมูล</h4>
                <p>ลองปรับเงื่อนไขการค้นหาใหม่</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-4 text-muted">
            <small>
                <i class="bi bi-clock me-1"></i>อัพเดทข้อมูลล่าสุด: <?= date('d/m/Y H:i:s') ?>
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php mysqli_close($conn); ?>