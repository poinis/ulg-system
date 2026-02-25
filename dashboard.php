<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "config.php";
$username = $_SESSION["username"];
$user_role = '';

$sql = "SELECT role, name FROM users WHERE username = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_role, $user_name);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// Superadmin (ตามที่กำหนดในไฟล์เดิม)
$superadmins = [ 'oat', 'sunny'];
$is_superadmin = in_array(strtolower($username), $superadmins);

// --- เงื่อนไขสำหรับเมนู Sync Stock (ecom, owner, superadmin) ---
$can_view_sync = $is_superadmin || 
                 (strtolower($user_role) === 'owner') || 
                 (strtolower($username) === 'ecom');

// Get menu permissions from database
$userMenus = [];
$menuTableExists = @$conn->query("SHOW TABLES LIKE 'menu_permissions'")->num_rows > 0;

if ($menuTableExists) {
    // Get all active menus
    $menuQuery = $conn->query("SELECT * FROM menu_permissions WHERE is_active = 1 ORDER BY menu_order");
    
    if ($menuQuery) {
        while ($menu = $menuQuery->fetch_assoc()) {
            $canView = false;
            
            if ($is_superadmin) {
                $canView = true;
            } else {
                $permCheck = $conn->prepare("SELECT can_view FROM role_menu_access WHERE role = ? AND menu_key = ?");
                $permCheck->bind_param("ss", $user_role, $menu['menu_key']);
                $permCheck->execute();
                $permResult = $permCheck->get_result();
                
                if ($permResult->num_rows > 0) {
                    $perm = $permResult->fetch_assoc();
                    $canView = $perm['can_view'] == 1;
                }
            }
            
            if ($canView) {
                $userMenus[] = $menu;
            }
        }
    }
} else {
    // Fallback menus
    $defaultMenus = [
        ['menu_key' => 'content', 'menu_name' => 'Content Management', 'menu_icon' => '📝', 'menu_url' => 'https://www.weedjai.com/content', 'menu_color' => 'content', 'menu_description' => 'ระบบจัดการเนื้อหา บทความ และสื่อต่างๆ'],
        ['menu_key' => 'issue', 'menu_name' => 'Issue Tracking', 'menu_icon' => '🎫', 'menu_url' => 'https://www.weedjai.com/issue', 'menu_color' => 'issue', 'menu_description' => 'ระบบติดตามปัญหา จัดการงาน และ Bug Report'],
        ['menu_key' => 'report', 'menu_name' => 'Sales Report', 'menu_icon' => '📊', 'menu_url' => 'https://www.weedjai.com/report/dashboard.php', 'menu_color' => 'report', 'menu_description' => 'รายงานยอดขาย วิเคราะห์ข้อมูล และสถิติ'],
        ['menu_key' => 'promotion', 'menu_name' => 'Promotion Management', 'menu_icon' => '🎁', 'menu_url' => 'https://www.weedjai.com/promotions/promotion_list.php', 'menu_color' => 'promotion', 'menu_description' => 'ระบบแจ้งจัดโปรโมชั่น'],
        ['menu_key' => 'order', 'menu_name' => 'Order Management', 'menu_icon' => '📦', 'menu_url' => 'https://www.weedjai.com/order', 'menu_color' => 'order', 'menu_description' => 'ระบบจัดการคำสั่งซื้อจากต่างประเทศ'],
        ['menu_key' => 'incentive', 'menu_name' => 'Incentive Checklist', 'menu_icon' => '📋', 'menu_url' => 'incentive/', 'menu_color' => 'incentive', 'menu_description' => 'กรอก Checklist รายวัน'],
        ['menu_key' => 'pos', 'menu_name' => 'POS System', 'menu_icon' => '🛒', 'menu_url' => 'pos/', 'menu_color' => 'pos', 'menu_description' => 'ระบบขายหน้าร้าน พิมพ์ใบเสร็จ 80mm'],
    ];
    $userMenus = $defaultMenus;
}

// Incentive Admin check
$incentive_admin_roles = ['admin', 'owner'];
$is_incentive_admin = $is_superadmin || in_array(strtolower($user_role), $incentive_admin_roles);

// Pending users check
$pendingCount = 0;
if ($is_superadmin) {
    $pendingResult = @$conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'pending'");
    if ($pendingResult) {
        $pendingCount = $pendingResult->fetch_assoc()['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ULG Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0d0d1a; min-height: 100vh; padding: 20px; color: #ffffff; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #1a1a2e; padding: 20px 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; border: 1px solid #2a2a4a; }
        .welcome-text { font-size: 1.5em; color: #ffffff; font-weight: 600; }
        .welcome-text span { color: #a78bfa; }
        .user-role { display: inline-block; margin-left: 10px; padding: 4px 12px; border-radius: 15px; font-size: 0.7em; font-weight: 600; background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); color: white; }
        .superadmin-badge { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #000; }
        .button-group { display: flex; gap: 10px; align-items: center; }
        .logout-btn, .add-user-btn { background: #2a2a4a; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; border: 1px solid #3a3a5a; }
        .logout-btn:hover, .add-user-btn:hover { background: #3a3a5a; transform: translateY(-2px); }
        .main-content { background: #1a1a2e; padding: 35px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); margin-bottom: 30px; border: 1px solid #2a2a4a; }
        .section-title { font-size: 1.8em; color: #ffffff; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #7c3aed; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-top: 30px; }
        .system-card { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); padding: 30px; border-radius: 15px; text-decoration: none; color: white; transition: all 0.3s ease; box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3); position: relative; overflow: hidden; }
        .system-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(124, 58, 237, 0.5); }
        .system-card.content { background: linear-gradient(135deg, #059669 0%, #34d399 100%); }
        .system-card.issue { background: linear-gradient(135deg, #dc2626 0%, #f87171 100%); }
        .system-card.order { background: linear-gradient(135deg, #1f2937 0%, #374151 100%); }
        .system-card.promotion { background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%); }
        .system-card.report { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); }
        .system-card.incentive { background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%); }
        .system-card.admin { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .system-card.admin .card-title, .system-card.admin .card-description { color: #1a1a2e; }
        .card-icon { font-size: 3em; margin-bottom: 15px; display: block; }
        .card-title { font-size: 1.5em; font-weight: 600; margin-bottom: 10px; }
        .card-description { font-size: 0.95em; opacity: 0.9; line-height: 1.5; }
        .card-badge { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 600; }
        .card-badge.pending { background: #e94560; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .info-section { background: #252542; padding: 25px; border-radius: 12px; margin-top: 30px; border: 1px solid #3a3a5a; }
        .info-title { font-size: 1.2em; color: #ffffff; margin-bottom: 15px; font-weight: 600; }
        .info-text { color: #a0a0b0; line-height: 1.6; }
        @media screen and (max-width: 768px) {
            .header { padding: 20px; text-align: center; justify-content: center; }
            .welcome-text { font-size: 1.2em; width: 100%; }
            .button-group { width: 100%; flex-direction: column; }
            .card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="welcome-text">
                ยินดีต้อนรับ, <span><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role <?php echo $is_superadmin ? 'superadmin-badge' : ''; ?>">
                    <?php echo $is_superadmin ? '⭐ SUPERADMIN' : strtoupper(htmlspecialchars($user_role)); ?>
                </span> 👋
            </div>
            <div class="button-group">
                <a href="profile.php" class="add-user-btn">👤 เปลี่ยนรหัสผ่าน</a>
                <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
            </div>
        </div>

        <div class="main-content">
            <h2 class="section-title">🚀 ระบบช่วยงาน ULG Portal</h2>

            <div class="card-grid">
                <?php foreach ($userMenus as $menu): ?>
                <a href="<?= htmlspecialchars($menu['menu_url']) ?>" class="system-card <?= htmlspecialchars($menu['menu_color'] ?? '') ?>">
                    <?php if ($menu['menu_key'] === 'incentive' && $is_incentive_admin): ?>
                    <span class="card-badge">👑 Admin</span>
                    <?php endif; ?>
                    <span class="card-icon"><?= $menu['menu_icon'] ?></span>
                    <div class="card-title"><?= htmlspecialchars($menu['menu_name']) ?></div>
                    <div class="card-description"><?= htmlspecialchars($menu['menu_description'] ?? '') ?></div>
                </a>
                <?php endforeach; ?>

                <?php if ($can_view_sync): ?>
                    <a href="api/document.php" class="system-card order">
                        <span class="card-icon">📄</span>
                        <div class="card-title">API Documentation</div>
                        <div class="card-description">คู่มือและการใช้งาน API สำหรับระบบต่างๆ</div>
                    </a>
                <?php endif; ?>
                
                <?php if ($is_superadmin): ?>
                <a href="admin/" class="system-card admin">
                    <?php if ($pendingCount > 0): ?>
                    <span class="card-badge pending">🔔 <?= $pendingCount ?> รออนุมัติ</span>
                    <?php else: ?>
                    <span class="card-badge">🔒 Superadmin</span>
                    <?php endif; ?>
                    <span class="card-icon">⚙️</span>
                    <div class="card-title">Admin Tools</div>
                    <div class="card-description">จัดการผู้ใช้, สิทธิ์เมนู, อนุมัติสาขา</div>
                </a>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <div class="info-title">📌 ข้อมูลเพิ่มเติม</div>
                <div class="info-text">เลือกระบบที่คุณต้องการใช้งานจากด้านบน หากพบปัญหาสามารถติดต่อทีมสนับสนุนได้ตลอดเวลา</div>
            </div>
        </div>
    </div>
</body>
</html>