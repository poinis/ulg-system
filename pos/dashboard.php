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

// Superadmin
$superadmins = ['admin', 'oat', 'it', 'may'];
$is_superadmin = in_array(strtolower($username), $superadmins);

// Report access
$report_roles = ['owner', 'brand', 'area', 'admin'];
$can_view_report = $is_superadmin || in_array(strtolower($user_role), $report_roles);

// Incentive access (admin, owner, shop)
$incentive_roles = ['admin', 'owner', 'shop'];
$can_access_incentive = $is_superadmin || in_array(strtolower($user_role), $incentive_roles);

// Incentive Admin (admin, owner)
$incentive_admin_roles = ['admin', 'owner'];
$is_incentive_admin = $is_superadmin || in_array(strtolower($user_role), $incentive_admin_roles);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ULG Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0d0d1a;
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: #1a1a2e;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #2a2a4a;
        }
        .welcome-text { font-size: 1.5em; color: #ffffff; font-weight: 600; }
        .welcome-text span { color: #a78bfa; }
        .user-role {
            display: inline-block;
            margin-left: 10px;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.7em;
            font-weight: 600;
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            color: white;
        }
        .superadmin-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #000;
        }
        
        .button-group { display: flex; gap: 10px; align-items: center; }
        .logout-btn, .add-user-btn {
            background: #2a2a4a;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #3a3a5a;
        }
        .logout-btn:hover, .add-user-btn:hover {
            background: #3a3a5a;
            transform: translateY(-2px);
        }
        
        .main-content {
            background: #1a1a2e;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            border: 1px solid #2a2a4a;
        }
        .section-title {
            font-size: 1.8em;
            color: #ffffff;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #7c3aed;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .system-card {
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
            padding: 30px;
            border-radius: 15px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
            position: relative;
            overflow: hidden;
        }
        .system-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .system-card:hover::before { transform: translateX(0); }
        .system-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.5);
        }
        
        .system-card.content { background: linear-gradient(135deg, #059669 0%, #34d399 100%); box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3); }
        .system-card.content:hover { box-shadow: 0 15px 35px rgba(5, 150, 105, 0.5); }
        .system-card.issue { background: linear-gradient(135deg, #dc2626 0%, #f87171 100%); box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3); }
        .system-card.issue:hover { box-shadow: 0 15px 35px rgba(220, 38, 38, 0.5); }
        .system-card.order { background: linear-gradient(135deg, #1f2937 0%, #374151 100%); box-shadow: 0 8px 20px rgba(31, 41, 55, 0.3); }
        .system-card.order:hover { box-shadow: 0 15px 35px rgba(31, 41, 55, 0.5); }
        .system-card.promotion { background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%); box-shadow: 0 8px 20px rgba(75, 85, 99, 0.3); }
        .system-card.promotion:hover { box-shadow: 0 15px 35px rgba(75, 85, 99, 0.5); }
        .system-card.report { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3); }
        .system-card.report:hover { box-shadow: 0 15px 35px rgba(37, 99, 235, 0.5); }
        .system-card.incentive { background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%); box-shadow: 0 8px 20px rgba(236, 72, 153, 0.3); }
        .system-card.incentive:hover { box-shadow: 0 15px 35px rgba(236, 72, 153, 0.5); }
        .system-card.admin { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3); }
        .system-card.admin:hover { box-shadow: 0 15px 35px rgba(245, 158, 11, 0.5); }
        .system-card.admin .card-title, .system-card.admin .card-description { color: #1a1a2e; }
        
        .card-icon { font-size: 3em; margin-bottom: 15px; display: block; }
        .card-title { font-size: 1.5em; font-weight: 600; margin-bottom: 10px; }
        .card-description { font-size: 0.95em; opacity: 0.9; line-height: 1.5; }
        .card-badge {
            position: absolute;
            top: 15px; right: 15px;
            background: rgba(0,0,0,0.3);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
        }
        
        .info-section {
            background: #252542;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
            border: 1px solid #3a3a5a;
        }
        .info-title { font-size: 1.2em; color: #ffffff; margin-bottom: 15px; font-weight: 600; }
        .info-text { color: #a0a0b0; line-height: 1.6; }
        
        @media screen and (max-width: 768px) {
            body { padding: 15px; }
            .header { padding: 20px; text-align: center; justify-content: center; }
            .welcome-text { font-size: 1.2em; width: 100%; text-align: center; }
            .button-group { width: 100%; flex-direction: column; }
            .logout-btn, .add-user-btn { width: 100%; text-align: center; }
            .main-content { padding: 25px 20px; }
            .section-title { font-size: 1.4em; }
            .card-grid { grid-template-columns: 1fr; gap: 20px; }
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
                <a href="https://www.weedjai.com/content" class="system-card content">
                    <span class="card-icon">📝</span>
                    <div class="card-title">Content Management</div>
                    <div class="card-description">ระบบจัดการเนื้อหา บทความ และสื่อต่างๆ</div>
                </a>

                <a href="https://www.weedjai.com/issue" class="system-card issue">
                    <span class="card-icon">🎫</span>
                    <div class="card-title">Issue Tracking</div>
                    <div class="card-description">ระบบติดตามปัญหา จัดการงาน และ Bug Report</div>
                </a>

                <?php if ($can_view_report): ?>
                <a href="https://www.weedjai.com/report/dashboard.php" class="system-card report">
                    <span class="card-icon">📊</span>
                    <div class="card-title">Sales Report</div>
                    <div class="card-description">รายงานยอดขาย วิเคราะห์ข้อมูล และสถิติ</div>
                </a>
                <?php endif; ?>

                <a href="https://www.weedjai.com/promotions/promotion_list.php" class="system-card promotion">
                    <span class="card-icon">🎁</span>
                    <div class="card-title">Promotion Management</div>
                    <div class="card-description">ระบบแจ้งจัดโปรโมชั่น</div>
                </a>

                <a href="https://www.weedjai.com/order" class="system-card order">
                    <span class="card-icon">🛒</span>
                    <div class="card-title">Order Management</div>
                    <div class="card-description">ระบบจัดการคำสั่งซื้อจากต่างประเทศ</div>
                </a>
                
                <a href="pos/" class="system-card" style="background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%); box-shadow: 0 8px 20px rgba(233, 69, 96, 0.3);">
                    <span class="card-icon">🛒</span>
                    <div class="card-title">POS System</div>
                    <div class="card-description">ระบบขายหน้าร้าน - ขายสินค้า, ออกบิล</div>
                </a>
                
                <?php if ($can_access_incentive): ?>
                <a href="incentive/" class="system-card incentive">
                    <?php if ($is_incentive_admin): ?>
                    <span class="card-badge">👑 Admin</span>
                    <?php endif; ?>
                    <span class="card-icon">📋</span>
                    <div class="card-title">Incentive Checklist</div>
                    <div class="card-description">
                        <?php if ($is_incentive_admin): ?>
                        ระบบจัดการ Incentive - อนุมัติงาน, คำนวณเงิน
                        <?php else: ?>
                        กรอก Checklist รายวัน - TikTok, Google Maps, Review
                        <?php endif; ?>
                    </div>
                </a>
                <?php endif; ?>
                
                <?php if ($is_superadmin): ?>
                <a href="admin/" class="system-card admin">
                    <span class="card-badge">🔒 Superadmin</span>
                    <span class="card-icon">⚙️</span>
                    <div class="card-title">Admin Tools</div>
                    <div class="card-description">จัดการผู้ใช้, Reset Password</div>
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
