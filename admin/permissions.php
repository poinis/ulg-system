<?php
// admin/permissions.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

require_once "../config.php";

// Check superadmin
$superadmins = ['admin', 'oat', 'it', 'may', 'sunny'];
$currentUsername = strtolower($_SESSION["username"] ?? '');
if (!in_array($currentUsername, $superadmins)) {
    echo "Access Denied";
    exit;
}

$message = '';
$messageType = '';

// ==========================================
// 🔧 AUTO FIX DATABASE STRUCTURE (ทำงานก่อนส่วนอื่น)
// ==========================================
try {
    // 1. ตรวจสอบว่ามีตารางหรือไม่ ถ้าไม่มีให้สร้างใหม่ (เวอร์ชันสมบูรณ์)
    $conn->query("
        CREATE TABLE IF NOT EXISTS `menu_permissions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `menu_key` varchar(50) NOT NULL,
            `menu_name` varchar(100) NOT NULL,
            `menu_icon` varchar(50) DEFAULT NULL,
            `menu_url` varchar(255) NOT NULL,
            `menu_color` varchar(50) DEFAULT NULL,
            `menu_description` varchar(255) DEFAULT NULL,
            `menu_order` int(11) DEFAULT 0,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `menu_key` (`menu_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS `role_menu_access` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `role` varchar(50) NOT NULL,
            `menu_key` varchar(50) NOT NULL,
            `can_view` tinyint(1) DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `role_menu` (`role`, `menu_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. ตรวจสอบและเพิ่มคอลัมน์ที่ขาดหายไป (สำหรับตารางที่มีอยู่แล้ว)
    $columnsToFix = [
        'menu_description' => "VARCHAR(255) DEFAULT NULL",
        'menu_icon' => "VARCHAR(50) DEFAULT NULL",
        'menu_color' => "VARCHAR(50) DEFAULT NULL"
    ];

    foreach ($columnsToFix as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM menu_permissions LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE menu_permissions ADD COLUMN $col $def");
        }
    }

} catch (Exception $e) {
    die("Database Fix Error: " . $e->getMessage());
}
// ==========================================


// --- AUTO UPDATE: Add Shopify Menu ---
// ตรวจสอบว่ามีเมนู shopify หรือยัง
$checkShopify = $conn->query("SELECT id FROM menu_permissions WHERE menu_key = 'shopify'");
if ($checkShopify->num_rows == 0) {
    // เพิ่มเมนู (ใช้ INSERT IGNORE เพื่อความปลอดภัย)
    $stmt = $conn->prepare("INSERT IGNORE INTO menu_permissions (menu_key, menu_name, menu_icon, menu_url, menu_color, menu_description, menu_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $k='shopify'; $n='Sync Stock'; $i='🔄'; $u='https://www.weedjai.com/shopify'; $c='order'; $d='ระบบซิงค์สต็อกออนไลน์ Shopify'; $o=8;
    $stmt->bind_param("ssssssi", $k, $n, $i, $u, $c, $d, $o);
    $stmt->execute();
    
    // ให้สิทธิ์ Owner
    $conn->query("INSERT IGNORE INTO role_menu_access (role, menu_key, can_view) VALUES ('owner', 'shopify', 1)");
}

// --- Default Menus Check ---
$cntResult = $conn->query("SELECT COUNT(*) as cnt FROM menu_permissions");
$menuCount = ($cntResult) ? $cntResult->fetch_assoc()['cnt'] : 0;

if ($menuCount == 0) {
    $defaultMenus = [
        ['content', 'Content Management', '📝', 'https://www.weedjai.com/content', 'content', 'ระบบจัดการเนื้อหา', 1],
        ['issue', 'Issue Tracking', '🎫', 'https://www.weedjai.com/issue', 'issue', 'ระบบติดตามปัญหา', 2],
        ['report', 'Sales Report', '📊', 'https://www.weedjai.com/report/dashboard.php', 'report', 'รายงานยอดขาย', 3],
        ['promotion', 'Promotion Management', '🎁', 'https://www.weedjai.com/promotions/promotion_list.php', 'promotion', 'ระบบแจ้งจัดโปรโมชั่น', 4],
        ['order', 'Order Management', '📦', 'https://www.weedjai.com/order', 'order', 'ระบบจัดการคำสั่งซื้อ', 5],
        ['incentive', 'Incentive Checklist', '📋', 'incentive/', 'incentive', 'กรอก Checklist รายวัน', 6],
        ['pos', 'POS System', '🛒', 'pos/', 'pos', 'ระบบขายหน้าร้าน', 7],
        ['shopify', 'Sync Stock', '🔄', 'https://www.weedjai.com/shopify', 'order', 'ระบบซิงค์สต็อกออนไลน์', 8],
    ];
    
    $stmt = $conn->prepare("INSERT INTO menu_permissions (menu_key, menu_name, menu_icon, menu_url, menu_color, menu_description, menu_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($defaultMenus as $m) {
        $stmt->bind_param("ssssssi", $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        $stmt->execute();
    }
}

// Available roles
$roles = ['admin', 'owner', 'approve', 'area', 'brand', 'marketing', 'shop'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update permissions
    if (isset($_POST['save_permissions'])) {
        $permissions = $_POST['perm'] ?? [];
        $menusRes = $conn->query("SELECT menu_key FROM menu_permissions WHERE is_active = 1");
        
        if ($menusRes) {
            $menus = $menusRes->fetch_all(MYSQLI_ASSOC);
            $stmt = $conn->prepare("INSERT INTO role_menu_access (role, menu_key, can_view) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_view = ?, updated_at = NOW()");
            
            foreach ($roles as $role) {
                foreach ($menus as $menu) {
                    $menuKey = $menu['menu_key'];
                    $canView = isset($permissions[$role][$menuKey]) ? 1 : 0;
                    $stmt->bind_param("ssii", $role, $menuKey, $canView, $canView);
                    $stmt->execute();
                }
            }
            $message = 'บันทึกสิทธิ์สำเร็จ!';
            $messageType = 'success';
        }
    }
    
    // Add new menu
    if (isset($_POST['add_menu'])) {
        $menuKey = trim($_POST['menu_key']);
        $menuName = trim($_POST['menu_name']);
        $menuIcon = trim($_POST['menu_icon']);
        $menuUrl = trim($_POST['menu_url']);
        $menuColor = trim($_POST['menu_color']);
        $menuDesc = trim($_POST['menu_description']);
        
        if ($menuKey && $menuName && $menuUrl) {
            $stmt = $conn->prepare("INSERT INTO menu_permissions (menu_key, menu_name, menu_icon, menu_url, menu_color, menu_description, menu_order) VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(m.menu_order), 0) + 1 FROM menu_permissions m))");
            $stmt->bind_param("ssssss", $menuKey, $menuName, $menuIcon, $menuUrl, $menuColor, $menuDesc);
            if ($stmt->execute()) {
                $message = 'เพิ่มเมนูสำเร็จ!';
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $stmt->error;
                $messageType = 'error';
            }
        }
    }
    
    // Delete/Toggle
    if (isset($_POST['delete_menu'])) {
        $key = $conn->real_escape_string($_POST['menu_key']);
        $conn->query("DELETE FROM role_menu_access WHERE menu_key = '$key'");
        $conn->query("DELETE FROM menu_permissions WHERE menu_key = '$key'");
        $message = 'ลบเมนูสำเร็จ!';
        $messageType = 'success';
    }
    
    if (isset($_POST['toggle_menu'])) {
        $key = $conn->real_escape_string($_POST['menu_key']);
        $conn->query("UPDATE menu_permissions SET is_active = NOT is_active WHERE menu_key = '$key'");
        $message = 'อัปเดตสถานะสำเร็จ!';
        $messageType = 'success';
    }
}

// Get Data for View
$menus = [];
$menuRes = $conn->query("SELECT * FROM menu_permissions ORDER BY menu_order");
if ($menuRes) $menus = $menuRes->fetch_all(MYSQLI_ASSOC);

$currentPerms = [];
$permRes = $conn->query("SELECT role, menu_key, can_view FROM role_menu_access");
if ($permRes) {
    while ($row = $permRes->fetch_assoc()) {
        $currentPerms[$row['role']][$row['menu_key']] = $row['can_view'];
    }
}

$colorOptions = [
    'content' => '🟢 เขียว (Content)',
    'issue' => '🔴 แดง (Issue)',
    'report' => '🔵 น้ำเงิน (Report)',
    'promotion' => '⚫ เทา (Promotion)',
    'order' => '⚫ เทาเข้ม (Order)',
    'incentive' => '🟣 ชมพู (Incentive)',
    'pos' => '🔵 ฟ้า (POS)',
    'admin' => '🟡 เหลือง (Admin)',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์เมนู | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; min-height: 100vh; color: #fff; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #0f3460; padding: 20px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header h1 { font-size: 1.3em; display: flex; align-items: center; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: #e94560; color: #fff; }
        .btn-secondary { background: #252542; color: #fff; }
        .btn-success { background: #2ed573; color: #fff; }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        .btn-sm { padding: 8px 14px; font-size: 12px; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert.success { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
        .alert.error { background: rgba(255, 107, 107, 0.15); color: #ff6b6b; }
        .card { background: #16213e; border-radius: 15px; padding: 25px; margin-bottom: 25px; }
        .card-title { font-size: 1.1em; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #fbbf24; }
        .permissions-table { width: 100%; border-collapse: collapse; overflow-x: auto; display: block; }
        .permissions-table thead, .permissions-table tbody, .permissions-table tr { display: table; width: 100%; table-layout: fixed; }
        .permissions-table th, .permissions-table td { padding: 12px 10px; text-align: center; border-bottom: 1px solid #252542; }
        .permissions-table th { background: #0f3460; font-weight: 600; color: #aaa; font-size: 12px; position: sticky; top: 0; }
        .permissions-table th:first-child, .permissions-table td:first-child { text-align: left; width: 200px; }
        .permissions-table tr:hover { background: rgba(255,255,255,0.02); }
        .menu-info { display: flex; align-items: center; gap: 10px; }
        .menu-icon { font-size: 20px; }
        .menu-name { font-weight: 600; }
        .menu-url { font-size: 10px; color: #666; word-break: break-all; }
        .checkbox-wrapper { display: flex; justify-content: center; }
        .checkbox-wrapper input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #2ed573; }
        .role-header { font-size: 11px; text-transform: uppercase; }
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .status-active { background: rgba(46, 213, 115, 0.2); color: #2ed573; }
        .status-inactive { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .actions { display: flex; gap: 5px; justify-content: center; }
        .add-menu-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 12px; color: #888; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 15px; background: #252542; border: 1px solid #3a3a5a; border-radius: 8px; color: #fff; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #e94560; }
        .nav-links { display: flex; gap: 10px; }
        .legend { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 1px solid #3a3a5a; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #888; }
        .legend-check { color: #2ed573; }
        .legend-uncheck { color: #666; }
        @media (max-width: 768px) {
            .permissions-table { font-size: 11px; }
            .permissions-table th, .permissions-table td { padding: 8px 5px; }
            .add-menu-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> จัดการสิทธิ์เมนู</h1>
            <div class="nav-links">
                <a href="approve.php" class="btn btn-secondary"><i class="fas fa-user-check"></i> อนุมัติผู้ใช้</a>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-users"></i> จัดการผู้ใช้</a>
                <a href="../dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i></a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3 class="card-title"><i class="fas fa-table"></i> ตารางสิทธิ์การเข้าถึงเมนู</h3>
            
            <form method="POST">
                <div style="overflow-x:auto;">
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>เมนู</th>
                                <?php foreach ($roles as $role): ?>
                                <th><span class="role-header"><?= strtoupper($role) ?></span></th>
                                <?php endforeach; ?>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menus as $menu): ?>
                            <tr>
                                <td>
                                    <div class="menu-info">
                                        <span class="menu-icon"><?= $menu['menu_icon'] ?></span>
                                        <div>
                                            <div class="menu-name"><?= htmlspecialchars($menu['menu_name']) ?></div>
                                            <div class="menu-url"><?= htmlspecialchars($menu['menu_url']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <?php foreach ($roles as $role): ?>
                                <td>
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" 
                                               name="perm[<?= $role ?>][<?= $menu['menu_key'] ?>]" 
                                               value="1"
                                               <?= (!empty($currentPerms[$role][$menu['menu_key']])) ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <?php endforeach; ?>
                                <td>
                                    <span class="status-badge <?= $menu['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $menu['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button type="submit" name="toggle_menu" value="1" formaction="?key=<?= $menu['menu_key'] ?>" class="btn btn-secondary btn-sm" title="เปิด/ปิด" onclick="this.form.menu_key.value='<?= $menu['menu_key'] ?>'">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <button type="submit" name="delete_menu" value="1" class="btn btn-sm" style="background:#ff6b6b;color:#fff" onclick="if(confirm('ลบเมนูนี้?')) { this.form.menu_key.value='<?= $menu['menu_key'] ?>'; return true; } else { return false; }">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <input type="hidden" name="menu_key" value="">

                <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <div class="legend">
                        <div class="legend-item"><i class="fas fa-check-square legend-check"></i> = มองเห็นเมนู</div>
                        <div class="legend-item"><i class="fas fa-square legend-uncheck"></i> = ไม่เห็นเมนู</div>
                    </div>
                    <button type="submit" name="save_permissions" class="btn btn-success">
                        <i class="fas fa-save"></i> บันทึกสิทธิ์ทั้งหมด
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3 class="card-title"><i class="fas fa-plus-circle"></i> เพิ่มเมนูใหม่</h3>
            <form method="POST">
                <div class="add-menu-form">
                    <div class="form-group">
                        <label>Menu Key (ภาษาอังกฤษ) *</label>
                        <input type="text" name="menu_key" placeholder="เช่น inventory" required pattern="[a-z0-9_]+">
                    </div>
                    <div class="form-group">
                        <label>ชื่อเมนู *</label>
                        <input type="text" name="menu_name" placeholder="เช่น Inventory System" required>
                    </div>
                    <div class="form-group">
                        <label>Icon (Emoji)</label>
                        <input type="text" name="menu_icon" placeholder="เช่น 📦">
                    </div>
                    <div class="form-group">
                        <label>URL *</label>
                        <input type="text" name="menu_url" placeholder="เช่น inventory/" required>
                    </div>
                    <div class="form-group">
                        <label>สี</label>
                        <select name="menu_color">
                            <?php foreach ($colorOptions as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>คำอธิบาย</label>
                        <input type="text" name="menu_description" placeholder="คำอธิบายสั้นๆ">
                    </div>
                </div>
                <div style="margin-top:15px;">
                    <button type="submit" name="add_menu" class="btn btn-primary">
                        <i class="fas fa-plus"></i> เพิ่มเมนู
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>