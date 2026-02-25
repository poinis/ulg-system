<?php
// pos/products.php - Manage Products
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

$userId = $_SESSION['id'];
$username = $_SESSION['username'];

// Check permission
$superadmins = ['admin', 'oat', 'it', 'may'];
$isAdmin = in_array(strtolower($username), $superadmins);

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    
    // Add product
    if (isset($_POST['add_product'])) {
        $sku = trim($_POST['sku']);
        $name = trim($_POST['product_name']);
        $brand = trim($_POST['brand']);
        $category = trim($_POST['category']);
        $size = trim($_POST['size']);
        $price = floatval($_POST['price']);
        
        $check = $conn->prepare("SELECT id FROM pos_products WHERE sku = ?");
        $check->bind_param("s", $sku);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "SKU นี้มีในระบบแล้ว";
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO pos_products (sku, product_name, brand, category, size, price, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssssd", $sku, $name, $brand, $category, $size, $price);
            if ($stmt->execute()) {
                $message = "เพิ่มสินค้าสำเร็จ";
                $messageType = 'success';
            } else {
                $message = "Error: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    // Update product
    if (isset($_POST['update_product'])) {
        $id = (int) $_POST['product_id'];
        $sku = trim($_POST['sku']);
        $name = trim($_POST['product_name']);
        $brand = trim($_POST['brand']);
        $category = trim($_POST['category']);
        $size = trim($_POST['size']);
        $price = floatval($_POST['price']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE pos_products SET sku=?, product_name=?, brand=?, category=?, size=?, price=?, is_active=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sssssidi", $sku, $name, $brand, $category, $size, $price, $isActive, $id);
        if ($stmt->execute()) {
            $message = "อัปเดตสำเร็จ";
            $messageType = 'success';
        }
    }
    
    // Delete product
    if (isset($_POST['delete_product'])) {
        $id = (int) $_POST['product_id'];
        $stmt = $conn->prepare("DELETE FROM pos_products WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "ลบสินค้าสำเร็จ";
            $messageType = 'success';
        }
    }
    
    // Toggle active
    if (isset($_POST['toggle_active'])) {
        $id = (int) $_POST['product_id'];
        $conn->query("UPDATE pos_products SET is_active = NOT is_active, updated_at = NOW() WHERE id = $id");
    }
}

// Filters
$search = $_GET['search'] ?? '';
$brand = $_GET['brand'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];
$types = "";

if ($search) {
    $where[] = "(sku LIKE ? OR product_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}
if ($brand) {
    $where[] = "brand = ?";
    $params[] = $brand;
    $types .= "s";
}
if ($status !== '') {
    $where[] = "is_active = ?";
    $params[] = (int) $status;
    $types .= "i";
}

$whereStr = implode(" AND ", $where);

// Get total
$countSql = "SELECT COUNT(*) as cnt FROM pos_products WHERE $whereStr";
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = ceil($total / $perPage);

// Get products
$sql = "SELECT * FROM pos_products WHERE $whereStr ORDER BY brand, product_name LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get brands for filter
$brands = $conn->query("SELECT DISTINCT brand FROM pos_products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสินค้า | POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;min-height:100vh;color:#fff;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .header{background:#0f3460;padding:20px;border-radius:15px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px}
        .header h1{font-size:1.3em}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600;transition:all .2s}
        .btn-primary{background:#e94560;color:#fff}
        .btn-secondary{background:#252542;color:#fff}
        .btn-success{background:#2ed573;color:#fff}
        .btn-sm{padding:6px 12px;font-size:12px}
        .btn:hover{transform:translateY(-1px);opacity:0.9}
        .alert{padding:12px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .alert.success{background:rgba(46,213,115,0.15);color:#2ed573}
        .alert.error{background:rgba(255,107,107,0.15);color:#ff6b6b}
        .filters{background:#16213e;padding:15px 20px;border-radius:12px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .filters input,.filters select{padding:10px 15px;background:#252542;border:1px solid #3a3a5a;border-radius:8px;color:#fff;font-size:14px}
        .filters input{min-width:200px}
        .filters input:focus,.filters select:focus{outline:none;border-color:#e94560}
        .stats{display:flex;gap:20px;margin-bottom:20px}
        .stat{background:#16213e;padding:15px 25px;border-radius:10px;text-align:center}
        .stat .num{font-size:24px;font-weight:700;color:#2ed573}
        .stat .label{font-size:11px;color:#888}
        .table-wrapper{background:#16213e;border-radius:15px;overflow:hidden}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px 15px;text-align:left;border-bottom:1px solid #252542}
        th{background:#0f3460;font-weight:600;color:#aaa;font-size:12px;white-space:nowrap}
        tr:hover{background:rgba(255,255,255,.02)}
        .sku{font-family:monospace;font-size:11px;color:#888}
        .brand-tag{background:#252542;padding:3px 10px;border-radius:12px;font-size:11px;color:#e94560}
        .price{font-weight:700;color:#2ed573}
        .status{padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .status.active{background:rgba(46,213,115,0.2);color:#2ed573}
        .status.inactive{background:rgba(255,107,107,0.2);color:#ff6b6b}
        .actions{display:flex;gap:5px}
        .pagination{display:flex;justify-content:center;gap:5px;margin-top:20px}
        .pagination a,.pagination span{padding:8px 14px;background:#252542;border-radius:6px;text-decoration:none;color:#fff;font-size:13px}
        .pagination a:hover{background:#e94560}
        .pagination .current{background:#e94560}
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-overlay.show{display:flex}
        .modal{background:#1a1a2e;border-radius:15px;padding:25px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto}
        .modal h3{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;color:#aaa;font-size:13px}
        .form-group input,.form-group select{width:100%;padding:12px;background:#252542;border:1px solid #3a3a5a;border-radius:8px;color:#fff;font-size:14px}
        .form-group input:focus{outline:none;border-color:#e94560}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        .checkbox-group{display:flex;align-items:center;gap:10px}
        .checkbox-group input{width:auto}
        .modal-actions{display:flex;gap:10px;margin-top:20px}
        .modal-actions button{flex:1}
        .nav-links{display:flex;gap:10px}
        @media(max-width:768px){.filters{flex-direction:column}.filters input,.filters select{width:100%}.form-row{grid-template-columns:1fr}table{font-size:12px}th,td{padding:8px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 จัดการสินค้า</h1>
            <div class="nav-links">
                <?php if ($isAdmin): ?>
                <a href="import_products.php" class="btn btn-secondary"><i class="fas fa-upload"></i> Import</a>
                <button class="btn btn-success" onclick="openAddModal()"><i class="fas fa-plus"></i> เพิ่มสินค้า</button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> POS</a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <form class="filters" method="GET">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 ค้นหา SKU หรือชื่อสินค้า...">
            <select name="brand">
                <option value="">ทุกแบรนด์</option>
                <?php foreach ($brands as $b): ?>
                <option value="<?= htmlspecialchars($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            <a href="products.php" class="btn btn-secondary"><i class="fas fa-times"></i></a>
        </form>
        
        <div class="stats">
            <div class="stat"><div class="num"><?= number_format($total) ?></div><div class="label">สินค้าที่พบ</div></div>
        </div>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>ชื่อสินค้า</th>
                        <th>แบรนด์</th>
                        <th>หมวดหมู่</th>
                        <th>ไซส์</th>
                        <th>ราคา</th>
                        <th>สถานะ</th>
                        <?php if ($isAdmin): ?><th>จัดการ</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:#666;">ไม่พบสินค้า</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><span class="sku"><?= htmlspecialchars($p['sku']) ?></span></td>
                        <td><?= htmlspecialchars($p['product_name']) ?></td>
                        <td><span class="brand-tag"><?= htmlspecialchars($p['brand'] ?: '-') ?></span></td>
                        <td><?= htmlspecialchars($p['category'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($p['size'] ?: '-') ?></td>
                        <td><span class="price">฿<?= number_format($p['price'], 0) ?></span></td>
                        <td><span class="status <?= $p['is_active'] ? 'active' : 'inactive' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <div class="actions">
                                <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('ลบสินค้านี้?')">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-sm" style="background:#ff6b6b;color:#fff"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <h3><span id="modalTitle">เพิ่มสินค้า</span><button class="modal-close" onclick="closeModal()">&times;</button></h3>
            <form method="POST" id="productForm">
                <input type="hidden" name="product_id" id="productId">
                <div class="form-group">
                    <label>SKU / Barcode *</label>
                    <input type="text" name="sku" id="formSku" required>
                </div>
                <div class="form-group">
                    <label>ชื่อสินค้า *</label>
                    <input type="text" name="product_name" id="formName" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>แบรนด์</label>
                        <input type="text" name="brand" id="formBrand">
                    </div>
                    <div class="form-group">
                        <label>หมวดหมู่</label>
                        <input type="text" name="category" id="formCategory">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ไซส์</label>
                        <input type="text" name="size" id="formSize">
                    </div>
                    <div class="form-group">
                        <label>ราคา *</label>
                        <input type="number" name="price" id="formPrice" step="0.01" required>
                    </div>
                </div>
                <div class="form-group" id="activeGroup" style="display:none">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="formActive" checked>
                        <label for="formActive">เปิดใช้งาน (Active)</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" name="add_product" id="submitBtn" class="btn btn-success">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่มสินค้า';
            document.getElementById('productId').value = '';
            document.getElementById('formSku').value = '';
            document.getElementById('formName').value = '';
            document.getElementById('formBrand').value = '';
            document.getElementById('formCategory').value = '';
            document.getElementById('formSize').value = '';
            document.getElementById('formPrice').value = '';
            document.getElementById('formActive').checked = true;
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('submitBtn').name = 'add_product';
            document.getElementById('productModal').classList.add('show');
        }
        
        function openEditModal(p) {
            document.getElementById('modalTitle').textContent = 'แก้ไขสินค้า';
            document.getElementById('productId').value = p.id;
            document.getElementById('formSku').value = p.sku;
            document.getElementById('formName').value = p.product_name;
            document.getElementById('formBrand').value = p.brand || '';
            document.getElementById('formCategory').value = p.category || '';
            document.getElementById('formSize').value = p.size || '';
            document.getElementById('formPrice').value = p.price;
            document.getElementById('formActive').checked = p.is_active == 1;
            document.getElementById('activeGroup').style.display = 'block';
            document.getElementById('submitBtn').name = 'update_product';
            document.getElementById('productModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('productModal').classList.remove('show');
        }
        
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
