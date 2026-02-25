<?php
// pos/import_products.php - Import Products from daily_sales
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

// Check if admin
$superadmins = ['admin', 'oat', 'it', 'may'];
$currentUsername = $_SESSION["username"] ?? '';
if (!in_array(strtolower($currentUsername), $superadmins)) {
    echo "<script>alert('เฉพาะ Admin เท่านั้น'); window.location='index.php';</script>";
    exit;
}

$message = '';
$stats = null;

// Create table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `pos_products` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sku` varchar(100) NOT NULL,
        `product_name` varchar(255) NOT NULL,
        `brand` varchar(100) DEFAULT NULL,
        `category` varchar(100) DEFAULT NULL,
        `size` varchar(100) DEFAULT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sku` (`sku`),
        KEY `brand` (`brand`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Alter column if table exists with old size
@$conn->query("ALTER TABLE pos_products MODIFY COLUMN sku varchar(100) NOT NULL");
@$conn->query("ALTER TABLE pos_products MODIFY COLUMN size varchar(100) DEFAULT NULL");

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $mode = $_POST['mode'] ?? 'update'; // update or replace
    
    $conn->begin_transaction();
    
    try {
        // ถ้าเลือก replace ให้ลบทั้งหมดก่อน
        if ($mode === 'replace') {
            $conn->query("TRUNCATE TABLE pos_products");
        }
        
        // Query distinct products จาก daily_sales
        // ใช้ราคาล่าสุด (MAX id = record ล่าสุด)
        $sql = "
            SELECT 
                d.line_barcode as sku,
                d.item_description as product_name,
                d.brand,
                d.class_name as category,
                d.size,
                d.base_price as price
            FROM daily_sales d
            INNER JOIN (
                SELECT line_barcode, MAX(id) as max_id
                FROM daily_sales
                WHERE line_barcode IS NOT NULL AND line_barcode != ''
                GROUP BY line_barcode
            ) latest ON d.id = latest.max_id
            WHERE d.line_barcode IS NOT NULL AND d.line_barcode != ''
        ";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query error: " . $conn->error);
        }
        
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        
        // Prepare statements
        $checkStmt = $conn->prepare("SELECT id FROM pos_products WHERE sku = ?");
        $insertStmt = $conn->prepare("INSERT INTO pos_products (sku, product_name, brand, category, size, price, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $updateStmt = $conn->prepare("UPDATE pos_products SET product_name=?, brand=?, category=?, size=?, price=?, updated_at=NOW() WHERE sku=?");
        
        while ($row = $result->fetch_assoc()) {
            $sku = trim($row['sku']);
            if (empty($sku)) continue;
            
            $productName = trim($row['product_name']);
            $brand = trim($row['brand'] ?? '');
            $category = trim($row['category'] ?? '');
            $size = trim($row['size'] ?? '');
            $price = floatval($row['price']);
            
            // ถ้าไม่มีชื่อสินค้า ให้ใช้ SKU
            if (empty($productName)) {
                $productName = $sku;
            }
            
            // Check if exists
            $checkStmt->bind_param("s", $sku);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            
            if ($exists) {
                if ($mode === 'update') {
                    $updateStmt->bind_param("ssssds", $productName, $brand, $category, $size, $price, $sku);
                    $updateStmt->execute();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $insertStmt->bind_param("sssssd", $sku, $productName, $brand, $category, $size, $price);
                $insertStmt->execute();
                $inserted++;
            }
        }
        
        $conn->commit();
        
        $stats = [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $inserted + $updated
        ];
        
        $message = "Import สำเร็จ!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $stats = null;
    }
}

// Get current stats
$totalProducts = 0;
$totalBrands = 0;
$checkTable = $conn->query("SHOW TABLES LIKE 'pos_products'");
if ($checkTable->num_rows > 0) {
    $totalProducts = $conn->query("SELECT COUNT(*) as cnt FROM pos_products")->fetch_assoc()['cnt'];
    $totalBrands = $conn->query("SELECT COUNT(DISTINCT brand) as cnt FROM pos_products WHERE brand IS NOT NULL AND brand != ''")->fetch_assoc()['cnt'];
}

// Get daily_sales stats
$dailySalesCount = 0;
$dailySalesProducts = 0;
$checkDaily = $conn->query("SHOW TABLES LIKE 'daily_sales'");
if ($checkDaily->num_rows > 0) {
    $dailySalesCount = $conn->query("SELECT COUNT(*) as cnt FROM daily_sales")->fetch_assoc()['cnt'];
    $dailySalesProducts = $conn->query("SELECT COUNT(DISTINCT line_barcode) as cnt FROM daily_sales WHERE line_barcode IS NOT NULL AND line_barcode != ''")->fetch_assoc()['cnt'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products | POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;min-height:100vh;color:#fff;padding:20px}
        .container{max-width:800px;margin:0 auto}
        .header{background:#0f3460;padding:20px;border-radius:15px;display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .header h1{font-size:1.3em}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600}
        .btn-primary{background:#e94560;color:#fff}
        .btn-secondary{background:#252542;color:#fff}
        .btn-success{background:#2ed573;color:#fff}
        .btn:hover{opacity:0.9}
        .card{background:#16213e;border-radius:15px;padding:25px;margin-bottom:20px}
        .card h2{font-size:1.1em;margin-bottom:15px;display:flex;align-items:center;gap:10px;color:#fbbf24}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px}
        .stat-box{background:#252542;padding:20px;border-radius:10px;text-align:center}
        .stat-box .value{font-size:28px;font-weight:700;color:#2ed573}
        .stat-box .label{font-size:12px;color:#888;margin-top:5px}
        .stat-box.warning .value{color:#fbbf24}
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .alert.success{background:rgba(46,213,115,0.15);color:#2ed573;border:1px solid rgba(46,213,115,0.3)}
        .alert.error{background:rgba(255,107,107,0.15);color:#ff6b6b;border:1px solid rgba(255,107,107,0.3)}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:8px;color:#aaa}
        .radio-group{display:flex;gap:20px}
        .radio-group label{display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 20px;background:#252542;border-radius:8px;border:2px solid transparent}
        .radio-group label:hover{border-color:#3a3a5a}
        .radio-group input:checked + label,.radio-group label:has(input:checked){border-color:#e94560;background:#2a2a4a}
        .radio-group input{display:none}
        .result-box{background:#252542;padding:20px;border-radius:10px;margin-top:20px}
        .result-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #3a3a5a}
        .result-row:last-child{border-bottom:none}
        .result-row .num{font-weight:700;color:#2ed573}
        .nav-links{display:flex;gap:10px}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Import สินค้า</h1>
            <div class="nav-links">
                <a href="products.php" class="btn btn-secondary"><i class="fas fa-box"></i> จัดการสินค้า</a>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> POS</a>
            </div>
        </div>
        
        <?php if ($message && $stats): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?= $message ?>
        </div>
        <div class="card">
            <h2><i class="fas fa-chart-bar"></i> ผลการ Import</h2>
            <div class="result-box">
                <div class="result-row"><span>เพิ่มใหม่</span><span class="num"><?= number_format($stats['inserted']) ?> รายการ</span></div>
                <div class="result-row"><span>อัปเดต</span><span class="num"><?= number_format($stats['updated']) ?> รายการ</span></div>
                <div class="result-row"><span>ข้าม</span><span class="num"><?= number_format($stats['skipped']) ?> รายการ</span></div>
                <div class="result-row" style="border-top:2px solid #3a3a5a;padding-top:12px;margin-top:8px">
                    <span><strong>รวมทั้งหมด</strong></span>
                    <span class="num" style="font-size:18px"><?= number_format($stats['total']) ?> รายการ</span>
                </div>
            </div>
        </div>
        <?php elseif ($message): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-database"></i> สถานะปัจจุบัน</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="value"><?= number_format($totalProducts) ?></div>
                    <div class="label">สินค้าใน POS</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?= number_format($totalBrands) ?></div>
                    <div class="label">แบรนด์</div>
                </div>
                <div class="stat-box warning">
                    <div class="value"><?= number_format($dailySalesProducts) ?></div>
                    <div class="label">สินค้าใน daily_sales</div>
                </div>
                <div class="stat-box warning">
                    <div class="value"><?= number_format($dailySalesCount) ?></div>
                    <div class="label">แถวใน daily_sales</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-upload"></i> Import จาก daily_sales</h2>
            <p style="color:#888;margin-bottom:20px;">นำเข้าสินค้าจากตาราง daily_sales โดยจะ dedupe และใช้ราคาล่าสุด</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>โหมดการ Import:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="mode" value="update" checked>
                            <i class="fas fa-sync"></i> อัปเดต (เพิ่มใหม่ + อัปเดตที่มีอยู่)
                        </label>
                        <label>
                            <input type="radio" name="mode" value="replace">
                            <i class="fas fa-trash-alt"></i> แทนที่ (ลบทั้งหมดแล้ว import ใหม่)
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="import" class="btn btn-success" onclick="return confirm('ยืนยันการ Import สินค้า?')">
                    <i class="fas fa-download"></i> เริ่ม Import
                </button>
            </form>
        </div>
    </div>
</body>
</html>