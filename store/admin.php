<?php
session_start();
require_once 'config.php';

// Simple authentication (change these credentials!)
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'admin123';

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Check if logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

$pdo = getConnection();

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (!$is_logged_in) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_stores':
            $stmt = $pdo->query("SELECT * FROM stores_sms ORDER BY sort_order ASC, id ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
            
        case 'get_store':
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM stores_sms WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
            break;
            
        case 'save_store':
            $id = isset($_POST['id']) && $_POST['id'] ? (int)$_POST['id'] : null;
            $name = trim($_POST['name']);
            $phone = trim($_POST['phone'] ?? '');
            $map_link = trim($_POST['map_link'] ?? '');
            $opening_hours = trim($_POST['opening_hours'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Handle image upload
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                if (in_array($ext, $allowed)) {
                    $filename = uniqid() . '_' . time() . '.' . $ext;
                    $filepath = UPLOAD_DIR . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                        $image = $filepath;
                    }
                }
            }
            
            if ($id) {
                // Update existing
                $sql = "UPDATE stores_sms SET name = ?, phone = ?, map_link = ?, opening_hours = ?, sort_order = ?, is_active = ?, updated_at = NOW()";
                $params = [$name, $phone ?: null, $map_link ?: null, $opening_hours ?: null, $sort_order, $is_active];
                
                if ($image) {
                    $sql .= ", image = ?";
                    $params[] = $image;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'message' => 'อัพเดทสาขาเรียบร้อยแล้ว']);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO stores_sms (name, image, phone, map_link, opening_hours, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$name, $image, $phone ?: null, $map_link ?: null, $opening_hours ?: null, $sort_order, $is_active]);
                
                echo json_encode(['success' => true, 'message' => 'เพิ่มสาขาใหม่เรียบร้อยแล้ว', 'id' => $pdo->lastInsertId()]);
            }
            break;
            
        case 'delete_store':
            $id = (int)$_POST['id'];
            
            // Get image to delete
            $stmt = $pdo->prepare("SELECT image FROM stores_sms WHERE id = ?");
            $stmt->execute([$id]);
            $store = $stmt->fetch();
            
            if ($store && $store['image'] && file_exists($store['image'])) {
                unlink($store['image']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM stores_sms WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'ลบสาขาเรียบร้อยแล้ว']);
            break;
            
        case 'toggle_status':
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE stores_sms SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'เปลี่ยนสถานะเรียบร้อยแล้ว']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get stores for initial load
$stores = [];
if ($is_logged_in) {
    $stmt = $pdo->query("SELECT * FROM stores_sms ORDER BY sort_order ASC, id ASC");
    $stores = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | จัดการสาขา</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 8px;
            --radius-lg: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Noto Sans Thai', sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* Login Page */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .login-card {
            background: white;
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
        }

        .login-card h1 {
            text-align: center;
            margin-bottom: 8px;
            color: var(--gray-800);
            font-size: 1.75rem;
        }

        .login-card p {
            text-align: center;
            color: var(--gray-500);
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .error-message {
            background: #fef2f2;
            color: var(--danger);
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 0.875rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: var(--radius);
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.875rem;
        }

        .btn-block {
            width: 100%;
        }

        /* Admin Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--gray-800);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s;
            z-index: 100;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-menu {
            padding: 16px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: var(--radius);
            margin-bottom: 4px;
            transition: all 0.2s;
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .menu-item i {
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .top-bar h1 {
            font-size: 1.5rem;
            color: var(--gray-800);
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .card-header h2 {
            font-size: 1.125rem;
            color: var(--gray-800);
        }

        .card-body {
            padding: 24px;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background: var(--gray-50);
        }

        .store-img-preview {
            width: 60px;
            height: 60px;
            border-radius: var(--radius);
            object-fit: cover;
            background: var(--gray-200);
        }

        .store-img-placeholder {
            width: 60px;
            height: 60px;
            border-radius: var(--radius);
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.25rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-400);
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--gray-600);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Image Upload */
        .image-upload {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--gray-50);
        }

        .image-upload:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .image-upload i {
            font-size: 2rem;
            color: var(--gray-400);
            margin-bottom: 8px;
        }

        .image-upload p {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .image-upload input {
            display: none;
        }

        .image-preview {
            position: relative;
            display: inline-block;
            margin-top: 16px;
        }

        .image-preview img {
            max-width: 200px;
            border-radius: var(--radius);
        }

        .image-preview .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--danger);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--gray-800);
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            bottom: 24px;
            left: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            z-index: 99;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .top-bar h1 {
                font-size: 1.25rem;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            th, td {
                padding: 12px 8px;
                font-size: 0.875rem;
            }

            .store-img-preview, .store-img-placeholder {
                width: 48px;
                height: 48px;
            }

            .actions {
                flex-direction: column;
            }

            .modal {
                max-height: 95vh;
            }

            .modal-body {
                padding: 16px;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: var(--gray-700);
        }
    </style>
</head>
<body>
<?php if (!$is_logged_in): ?>
    <!-- Login Page -->
    <div class="login-container">
        <div class="login-card">
            <h1><i class="fas fa-lock"></i> เข้าสู่ระบบ</h1>
            <p>กรุณาเข้าสู่ระบบเพื่อจัดการสาขา</p>
            
            <?php if (isset($login_error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= $login_error ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
                </div>
                
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                </button>
            </form>
            
            <p style="text-align: center; margin-top: 24px; color: var(--gray-500); font-size: 0.875rem;">
                <a href="index.php" style="color: var(--primary); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                </a>
            </p>
        </div>
    </div>

<?php else: ?>
    <!-- Admin Panel -->
    <div class="admin-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-menu">
                <a href="admin.php" class="menu-item active">
                    <i class="fas fa-store"></i> จัดการสาขา
                </a>
                <a href="index.php" class="menu-item" target="_blank">
                    <i class="fas fa-external-link-alt"></i> ดูหน้าเว็บไซต์
                </a>
                <a href="?logout=1" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1><i class="fas fa-store"></i> จัดการสาขา</h1>
                <button class="btn btn-success" onclick="openModal()">
                    <i class="fas fa-plus"></i> เพิ่มสาขาใหม่
                </button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>รายการสาขาทั้งหมด (<?= count($stores) ?> สาขา)</h2>
                    <div class="search-box" style="position: relative; width: 300px;">
                        <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
                        <input type="text" id="tableSearch" placeholder="ค้นหาสาขา..." style="width: 100%; padding: 10px 14px 10px 40px; border: 2px solid var(--gray-200); border-radius: var(--radius);">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (empty($stores)): ?>
                    <div class="empty-state">
                        <i class="fas fa-store-slash"></i>
                        <h3>ยังไม่มีสาขา</h3>
                        <p>คลิกปุ่ม "เพิ่มสาขาใหม่" เพื่อเริ่มต้นเพิ่มสาขา</p>
                    </div>
                    <?php else: ?>
                    <table id="storesTable">
                        <thead>
                            <tr>
                                <th style="width: 80px;">รูปภาพ</th>
                                <th>ชื่อสาขา</th>
                                <th>เบอร์โทร</th>
                                <th>เวลาเปิด-ปิด</th>
                                <th style="width: 100px;">สถานะ</th>
                                <th style="width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store): ?>
                            <tr data-id="<?= $store['id'] ?>">
                                <td>
                                    <?php if ($store['image']): ?>
                                    <img src="<?= htmlspecialchars($store['image']) ?>" alt="" class="store-img-preview">
                                    <?php else: ?>
                                    <div class="store-img-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($store['name']) ?></strong>
                                    <?php if ($store['map_link']): ?>
                                    <br><a href="<?= htmlspecialchars($store['map_link']) ?>" target="_blank" style="font-size: 0.75rem; color: var(--primary);">
                                        <i class="fas fa-map-marker-alt"></i> ดูแผนที่
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($store['phone'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($store['opening_hours'] ?: '-') ?></td>
                                <td>
                                    <span class="status-badge <?= $store['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                        <?= $store['is_active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-outline btn-icon" onclick="editStore(<?= $store['id'] ?>)" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline btn-icon" onclick="toggleStatus(<?= $store['id'] ?>)" title="เปลี่ยนสถานะ">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon" onclick="deleteStore(<?= $store['id'] ?>, '<?= htmlspecialchars($store['name'], ENT_QUOTES) ?>')" title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Store Modal -->
    <div class="modal-overlay" id="storeModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">เพิ่มสาขาใหม่</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="storeForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="storeId">
                    
                    <div class="form-group">
                        <label for="name">ชื่อสาขา <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="name" name="name" placeholder="เช่น PRONTO - Central Rama 9" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">เบอร์โทรศัพท์</label>
                        <input type="text" id="phone" name="phone" placeholder="เช่น 02-xxx-xxxx หรือ 08x-xxx-xxxx">
                    </div>
                    
                    <div class="form-group">
                        <label for="map_link">ลิงก์ Google Map</label>
                        <input type="url" id="map_link" name="map_link" placeholder="https://maps.app.goo.gl/...">
                    </div>
                    
                    <div class="form-group">
                        <label for="opening_hours">เวลาเปิด-ปิด</label>
                        <input type="text" id="opening_hours" name="opening_hours" placeholder="เช่น 10:00 - 22:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="sort_order">ลำดับการแสดงผล</label>
                        <input type="number" id="sort_order" name="sort_order" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>รูปภาพสาขา</label>
                        <label class="image-upload" id="imageUpload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>คลิกเพื่ออัพโหลดรูปภาพ</p>
                            <p style="font-size: 0.75rem; color: var(--gray-400);">รองรับ: JPG, PNG, WEBP, GIF</p>
                            <input type="file" name="image" id="image" accept="image/*">
                        </label>
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img src="" alt="Preview" id="previewImg">
                            <button type="button" class="remove-btn" onclick="removeImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            <label for="is_active" style="margin-bottom: 0;">เปิดใช้งานสาขานี้</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">บันทึกเรียบร้อยแล้ว</span>
    </div>

    <script>
        const modal = document.getElementById('storeModal');
        const form = document.getElementById('storeForm');
        const sidebar = document.getElementById('sidebar');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
        }
        
        function openModal(editData = null) {
            form.reset();
            document.getElementById('storeId').value = '';
            document.getElementById('modalTitle').textContent = 'เพิ่มสาขาใหม่';
            document.getElementById('imagePreview').style.display = 'none';
            modal.classList.add('active');
        }
        
        function closeModal() {
            modal.classList.remove('active');
        }
        
        function editStore(id) {
            fetch(`admin.php?action=get_store&id=${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const store = data.data;
                    document.getElementById('storeId').value = store.id;
                    document.getElementById('name').value = store.name;
                    document.getElementById('phone').value = store.phone || '';
                    document.getElementById('map_link').value = store.map_link || '';
                    document.getElementById('opening_hours').value = store.opening_hours || '';
                    document.getElementById('sort_order').value = store.sort_order || 0;
                    document.getElementById('is_active').checked = store.is_active == 1;
                    
                    if (store.image) {
                        document.getElementById('previewImg').src = store.image;
                        document.getElementById('imagePreview').style.display = 'inline-block';
                    } else {
                        document.getElementById('imagePreview').style.display = 'none';
                    }
                    
                    document.getElementById('modalTitle').textContent = 'แก้ไขสาขา';
                    modal.classList.add('active');
                }
            });
        }
        
        function deleteStore(id, name) {
            if (confirm(`คุณต้องการลบสาขา "${name}" ใช่หรือไม่?\n\nการกระทำนี้ไม่สามารถยกเลิกได้`)) {
                const formData = new FormData();
                formData.append('action', 'delete_store');
                formData.append('id', id);
                
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            }
        }
        
        function toggleStatus(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', id);
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            });
        }
        
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        function removeImage() {
            document.getElementById('image').value = '';
            document.getElementById('imagePreview').style.display = 'none';
        }
        
        // Form submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'save_store');
            
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> บันทึก';
                }
            })
            .catch(err => {
                showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> บันทึก';
            });
        });
        
        // Table search
        document.getElementById('tableSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#storesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
        
        // Toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = 'toast ' + type;
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Close modal on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !e.target.closest('.mobile-menu-btn')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
