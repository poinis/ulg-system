<?php
session_start();
require 'config.php';

// ==================== ตรวจสอบ Password ====================
define('ADMIN_PASSWORD', '996633');

// ถ้ายังไม่ล็อกอิน
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    
    // ตรวจสอบการล็อกอิน
    if (isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $login_error = true;
        }
    }
    
    // แสดงหน้าล็อกอิน
    ?>
    <!doctype html>
    <html lang="th">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: 'Sarabun', Arial, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .login-box {
        background: white;
        padding: 50px 40px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        text-align: center;
        max-width: 400px;
        width: 100%;
        animation: slideDown 0.5s;
    }
    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    .login-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
    h2 {
        color: #333;
        font-size: 28px;
        margin-bottom: 10px;
    }
    p {
        color: #666;
        margin-bottom: 30px;
        font-size: 14px;
    }
    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
    }
    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        font-family: 'Sarabun', Arial, sans-serif;
        transition: all 0.3s;
    }
    .form-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .btn-login {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102,126,234,0.4);
    }
    .error-msg {
        background: #ffebee;
        color: #c62828;
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid #f44336;
        text-align: left;
        animation: shake 0.5s;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    .back-link {
        margin-top: 20px;
        display: inline-block;
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }
    .back-link:hover {
        color: #764ba2;
    }
    </style>
    </head>
    <body>
    
    <div class="login-box">
        <div class="login-icon">🔐</div>
        <h2>ล็อกอินหน้าแอดมิน</h2>
        <p>กรุณาใส่รหัสผ่านเพื่อเข้าถึงระบบจัดการ</p>
        
        <?php if (isset($login_error)): ?>
        <div class="error-msg">
            ❌ <strong>รหัสผ่านไม่ถูกต้อง</strong> กรุณาลองใหม่อีกครั้ง
        </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label>🔑 รหัสผ่าน</label>
                <input type="password" 
                       name="admin_password" 
                       placeholder="ใส่รหัสผ่าน" 
                       required 
                       autofocus>
            </div>
            
            <button type="submit" class="btn-login">
                ✅ เข้าสู่ระบบ
            </button>
        </form>
        
        <a href="search.php" class="back-link">🔙 กลับหน้าค้นหา</a>
    </div>
    
    </body>
    </html>
    <?php
    exit;
}

// ==================== Logout ====================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ==================== อัปเดตข้อมูลแก้ไข ====================
if (isset($_POST['update_field'])) {
    header('Content-Type: application/json');
    
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = $_POST['value'];
    
    // กำหนดฟิลด์ที่อนุญาตให้แก้ไข
    $allowedFields = [
        'return_date', 'new_number', 'return_barcode', 
        'qty_return', 'price_return'
    ];
    
    if (in_array($field, $allowedFields)) {
        // Validate
        if ($field === 'return_barcode' && strlen($value) !== 13) {
            echo json_encode(['success' => false, 'error' => 'บาร์โค้ดต้องมี 13 หลัก']);
            exit;
        }
        
        if ($field === 'qty_return' && $value < 1) {
            echo json_encode(['success' => false, 'error' => 'จำนวนต้องมากกว่า 0']);
            exit;
        }
        
        if ($field === 'price_return' && $value <= 0) {
            echo json_encode(['success' => false, 'error' => 'ราคาต้องมากกว่า 0']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE returns SET `$field` = ? WHERE id = ?");
        $stmt->bind_param('si', $value, $id);
        $result = $stmt->execute();
        
        echo json_encode(['success' => $result, 'value' => $value]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid field']);
    exit;
}

// ==================== ส่วน Export Excel ====================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $sql = "SELECT 
                r.id,
                r.return_date,
                r.store_code,
                st.store_name,
                r.old_number,
                r.old_barcode,
                r.item_description,
                r.sale_date,
                r.brand,
                s.GL_TOTALTTC as old_price,
                r.new_number,
                r.return_barcode,
                r.qty_return,
                r.price_return,
                r.img_old,
                r.img_new,
                r.status,
                r.created_at
            FROM returns r
            LEFT JOIN stores_return st ON r.store_code = st.store_code
            LEFT JOIN sales_return s ON r.old_number = s.GL_NUMERO AND r.old_barcode = s.GL_REFARTBARRE
            WHERE 1=1";
    
    $params = [];
    $types  = '';
    
    if ($from !== '' && $to !== '') {
        $sql .= " AND r.return_date BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types    .= 'ss';
    }
    
    if ($status_filter !== '') {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
        $types    .= 's';
    }
    
    $sql .= " ORDER BY r.return_date DESC, r.old_number ASC, r.id ASC";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="returns_export_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'ID',
        'วันที่เปลี่ยน',
        'รหัสสาขา',
        'ชื่อสาขา',
        'เลขบิลเดิม',
        'บาร์โค้ดเดิม',
        'ชื่อสินค้าเดิม',
        'ราคาเดิม',
        'วันที่ขายเดิม',
        'แบรนด์',
        'เลขบิลใหม่',
        'บาร์โค้ดใหม่',
        'จำนวนเปลี่ยน',
        'ราคาสินค้าใหม่',
        'สถานะ',
        'วันที่บันทึก',
        'รูปบิลเดิม',
        'รูปบิลใหม่'
    ], ',', '"', '\\');
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['return_date'],
            $row['store_code'],
            $row['store_name'] ?? '',
            $row['old_number'] ?? '',
            $row['old_barcode'] ?? '',
            $row['item_description'] ?? '',
            number_format((float)($row['old_price'] ?? 0), 2, '.', ''),
            $row['sale_date'] ?? '',
            $row['brand'] ?? '',
            $row['new_number'] ?? '',
            $row['return_barcode'] ?? '',
            $row['qty_return'] ?? 1,
            number_format((float)($row['price_return'] ?? 0), 2, '.', ''),
            $row['status'] == 'checked' ? 'ตรวจแล้ว' : 'รอตรวจ',
            $row['created_at'] ?? '',
            $row['img_old'] ?? '',
            $row['img_new'] ?? ''
        ], ',', '"', '\\');
    }
    
    fclose($output);
    exit;
}

// ==================== อัปเดตสถานะ ====================
if (isset($_POST['update_status']) && !empty($_POST['checked_ids'])) {
    $ids = array_map('intval', $_POST['checked_ids']);
    $in  = implode(',', $ids);
    $conn->query("UPDATE returns SET status='checked' WHERE id IN ($in)");
    header('Location: admin.php?success=1');
    exit;
}

// ==================== ส่วนแสดงผล HTML ====================
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT 
            r.*,
            st.store_name,
            s.GL_TOTALTTC as old_price,
            s.GL_QTEFACT as old_qty
        FROM returns r
        LEFT JOIN stores_return st ON r.store_code = st.store_code
        LEFT JOIN sales_return s ON r.old_number = s.GL_NUMERO AND r.old_barcode = s.GL_REFARTBARRE
        WHERE 1=1";

$params = [];
$types  = '';

if ($from !== '' && $to !== '') {
    $sql .= " AND r.return_date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types    .= 'ss';
}

if ($status_filter !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
    $types    .= 's';
}

$sql .= " ORDER BY r.status ASC, r.return_date DESC, r.old_number ASC, r.id ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Return Management</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Sarabun', Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 1800px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    padding: 30px;
}
.header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.user-info {
    background: #e3f2fd;
    padding: 8px 15px;
    border-radius: 6px;
    font-size: 13px;
    color: #0d47a1;
}
.btn-logout {
    background: #f44336;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-block;
}
.btn-logout:hover {
    background: #d32f2f;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(244,67,54,0.3);
}
h2 {
    color: #333;
    border-bottom: 3px solid #4CAF50;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 24px;
}
h3 {
    color: #667eea;
    margin-top: 30px;
    margin-bottom: 15px;
    font-size: 18px;
}
.success-msg {
    background: #d4edda;
    color: #155724;
    padding: 12px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}
table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
th, td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
    font-size: 13px;
}
th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}
tr:nth-child(even) {
    background-color: #f9f9f9;
}
tr:hover {
    background-color: #f0f0f0;
}
.btn {
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 6px;
    display: inline-block;
    margin: 2px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.btn-export {
    background: linear-gradient(135deg, #FF9800 0%, #FF5722 100%);
    color: white;
}
.btn-back {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    padding: 10px 20px;
    font-size: 14px;
}
.btn-stores {
    background: linear-gradient(135deg, #9C27B0 0%, #673AB7 100%);
    color: white;
    padding: 10px 20px;
    font-size: 14px;
    margin-left: 10px;
}
.btn-submit {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 12px 30px;
    font-size: 16px;
}
.filter-box {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.filter-box label {
    font-weight: 600;
    margin-right: 8px;
    color: #555;
}
.filter-box input,
.filter-box select {
    padding: 8px 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    margin-right: 10px;
}
.filter-box input:focus,
.filter-box select:focus {
    outline: none;
    border-color: #667eea;
}
img.thumb {
    width: 60px;
    height: auto;
    cursor: pointer;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
img.thumb:hover {
    transform: scale(1.1);
}
.badge {
    background: #ff9800;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.status-pending {
    background: #ffc107;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.status-checked {
    background: #4caf50;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.price-old {
    color: #ff6f00;
    font-weight: 600;
}
.price-new {
    color: #4caf50;
    font-weight: 600;
}
.bill-color-1 { background-color: #e3f2fd; }
.bill-color-2 { background-color: #f3e5f5; }
.bill-color-3 { background-color: #e8f5e9; }
.bill-color-4 { background-color: #fff3e0; }
.bill-color-5 { background-color: #fce4ec; }
.sticky-footer {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 15px;
    text-align: center;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    margin: 20px -30px -30px -30px;
    border-radius: 0 0 12px 12px;
}

/* เซลล์ที่แก้ไขได้ */
.editable {
    cursor: pointer;
    position: relative;
    padding: 8px !important;
    transition: all 0.2s;
    border: 2px solid transparent !important;
}
.editable:hover {
    background-color: #fff9c4 !important;
    border: 2px dashed #fbc02d !important;
}
.editable::after {
    content: "✏️";
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 11px;
}
.editable:hover::after {
    opacity: 0.8;
}
.edit-input {
    width: 100%;
    padding: 6px 8px;
    border: 2px solid #667eea;
    border-radius: 4px;
    font-size: 13px;
    font-family: 'Sarabun', Arial, sans-serif;
    box-shadow: 0 2px 8px rgba(102,126,234,0.3);
}
.edit-input:focus {
    outline: none;
    border-color: #4caf50;
    box-shadow: 0 2px 12px rgba(76,175,80,0.4);
}
.saving {
    background-color: #fff9c4 !important;
    opacity: 0.7;
}
.success-flash {
    animation: flashGreen 0.8s;
}
@keyframes flashGreen {
    0%, 100% { background-color: inherit; }
    50% { background-color: #c8e6c9; }
}

/* Modal Popup */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.7);
    animation: fadeIn 0.3s;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content {
    background-color: #fff;
    margin: 3% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 900px;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0,0,0,0.3);
    animation: slideDown 0.3s;
}
@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 30px;
    position: relative;
}
.modal-header h2 {
    color: white;
    margin: 0;
    border: none;
    padding: 0;
    font-size: 22px;
}
.close {
    position: absolute;
    right: 20px;
    top: 20px;
    color: white;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}
.close:hover {
    transform: rotate(90deg);
}
.modal-body {
    padding: 30px;
    max-height: calc(85vh - 100px);
    overflow-y: auto;
}
.detail-section {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 4px solid #667eea;
}
.detail-section h4 {
    color: #667eea;
    margin-bottom: 10px;
    font-size: 16px;
}
.detail-row {
    display: flex;
    margin-bottom: 8px;
    font-size: 14px;
}
.detail-label {
    font-weight: 600;
    color: #555;
    min-width: 150px;
}
.detail-value {
    color: #333;
}
.images-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}
.image-box {
    text-align: center;
}
.image-box img {
    max-width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.image-box h5 {
    color: #667eea;
    margin-bottom: 10px;
    font-size: 14px;
}
.help-text {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 4px solid #2196f3;
    font-size: 13px;
    color: #0d47a1;
}
</style>
</head>
<body>

<div class="container">
    <div class="header-bar">
        <h2>🛠 ระบบหลังบ้าน - จัดการข้อมูลเปลี่ยนสินค้า</h2>
        <div>
            <span class="user-info">👤 ล็อกอินแล้ว</span>
            <a href="?logout=1" class="btn-logout" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-msg">✅ อัปเดตสถานะเรียบร้อยแล้ว</div>
    <?php endif; ?>

    <div class="help-text">
        💡 <strong>คำแนะนำ:</strong> คลิกที่ช่องที่มีเส้นประสีเหลืองเมื่อ hover เพื่อแก้ไข (วันที่เปลี่ยน, บิลใหม่, บาร์โค้ดใหม่, จำนวน, ราคา) - กด <strong>Enter</strong> บันทึก หรือ <strong>Esc</strong> ยกเลิก
    </div>

    <div class="filter-box">
        <form method="get" action="">
            <label>📅 วันที่เปลี่ยน จาก:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
            
            <label>ถึง:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
            
            <label>สถานะ:</label>
            <select name="status">
                <option value="">ทั้งหมด</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>รอตรวจ</option>
                <option value="checked" <?= $status_filter === 'checked' ? 'selected' : '' ?>>ตรวจแล้ว</option>
            </select>
            
            <button type="submit" class="btn" style="background:#4CAF50;color:white;">🔍 ค้นหา</button>
            
            <a href="admin.php" class="btn" style="background:#9E9E9E;color:white;">🔄 รีเซ็ต</a>
            
            <?php 
            $export_params = http_build_query(array_filter([
                'export' => 'excel',
                'from' => $from,
                'to' => $to,
                'status' => $status_filter
            ]));
            ?>
            <a href="?<?= $export_params ?>" class="btn btn-export">📥 Export Excel</a>
        </form>
    </div>

    <h3>📋 รายการเปลี่ยนสินค้า <span class="badge"><?= $result->num_rows ?> รายการ</span></h3>
    <p style="color:#999; font-size:13px; margin-bottom:10px;">💡 <strong>คลิกที่แถวเพื่อดูรายละเอียดเต็ม</strong> | <strong>คลิกช่องที่แก้ไขได้</strong></p>

    <?php if ($result->num_rows > 0): ?>
    <form method="post" action="" id="checkForm">
    <div style="overflow-x: auto;">
    <table>
    <thead>
    <tr>
        <th style="width:40px;">
            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
        </th>
        <th style="width:40px;">ID</th>
        <th>วันที่เปลี่ยน</th>
        <th>สาขา</th>
        <th>บิลเดิม</th>
        <th>บาร์โค้ดเดิม</th>
        <th>ชื่อสินค้าเดิม</th>
        <th style="text-align:center;">ราคาเดิม</th>
        <th>บิลใหม่</th>
        <th>บาร์โค้ดใหม่</th>
        <th style="text-align:center;">จำนวน</th>
        <th style="text-align:center;">ราคาใหม่</th>
        <th style="text-align:center;">สถานะ</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    $lastBill = '';
    $colorIndex = 0;
    $colors = ['bill-color-1', 'bill-color-2', 'bill-color-3', 'bill-color-4', 'bill-color-5'];
    
    while ($r = $result->fetch_assoc()): 
        if ($r['old_number'] !== $lastBill) {
            $colorIndex = ($colorIndex + 1) % count($colors);
            $lastBill = $r['old_number'];
        }
        $rowColor = $colors[$colorIndex];
        $isChecked = ($r['status'] === 'checked');
        
        $modalData = htmlspecialchars(json_encode([
            'id' => $r['id'],
            'return_date' => $r['return_date'],
            'store_code' => $r['store_code'],
            'store_name' => $r['store_name'] ?? '',
            'old_number' => $r['old_number'] ?? '',
            'old_barcode' => $r['old_barcode'] ?? '',
            'item_description' => $r['item_description'] ?? '',
            'old_price' => $r['old_price'] ?? 0,
            'old_qty' => $r['old_qty'] ?? 0,
            'sale_date' => $r['sale_date'] ?? '',
            'brand' => $r['brand'] ?? '',
            'new_number' => $r['new_number'] ?? '',
            'return_barcode' => $r['return_barcode'] ?? '',
            'qty_return' => $r['qty_return'] ?? 1,
            'price_return' => $r['price_return'] ?? 0,
            'status' => $r['status'],
            'img_old' => $r['img_old'] ?? '',
            'img_new' => $r['img_new'] ?? '',
            'created_at' => $r['created_at'] ?? ''
        ]), ENT_QUOTES);
    ?>
    <tr class="<?= $rowColor ?>" data-detail='<?= $modalData ?>'>
        <td onclick="event.stopPropagation();">
            <?php if (!$isChecked): ?>
            <input type="checkbox" name="checked_ids[]" value="<?= $r['id'] ?>" class="check-item">
            <?php else: ?>
            ✓
            <?php endif; ?>
        </td>
        <td onclick="showDetail(this.parentElement)"><?= $r['id'] ?></td>
        <td class="editable" 
            data-id="<?= $r['id'] ?>" 
            data-field="return_date" 
            data-type="date"
            data-value="<?= $r['return_date'] ?>"
            onclick="editCell(this, event)">
            <?= date('d/m/Y', strtotime($r['return_date'])) ?>
        </td>
        <td onclick="showDetail(this.parentElement)">
            <?= htmlspecialchars($r['store_code']) ?><br>
            <small style="color:#999;"><?= htmlspecialchars($r['store_name'] ?? '') ?></small>
        </td>
        <td onclick="showDetail(this.parentElement)"><strong><?= htmlspecialchars($r['old_number'] ?? '') ?></strong></td>
        <td onclick="showDetail(this.parentElement)"><?= htmlspecialchars($r['old_barcode'] ?? '-') ?></td>
        <td onclick="showDetail(this.parentElement)"><?= htmlspecialchars($r['item_description'] ?? '') ?></td>
        <td style="text-align:center;" class="price-old" onclick="showDetail(this.parentElement)">
            <?= number_format((float)($r['old_price'] ?? 0), 2) ?>
        </td>
        <td class="editable" 
            data-id="<?= $r['id'] ?>" 
            data-field="new_number" 
            data-type="text"
            data-value="<?= htmlspecialchars($r['new_number'] ?? '') ?>"
            onclick="editCell(this, event)">
            <?= htmlspecialchars($r['new_number'] ?? '-') ?>
        </td>
        <td class="editable" 
            data-id="<?= $r['id'] ?>" 
            data-field="return_barcode" 
            data-type="text"
            data-value="<?= htmlspecialchars($r['return_barcode'] ?? '') ?>"
            onclick="editCell(this, event)">
            <?= htmlspecialchars($r['return_barcode'] ?? '-') ?>
        </td>
        <td style="text-align:center;" 
            class="editable" 
            data-id="<?= $r['id'] ?>" 
            data-field="qty_return" 
            data-type="number"
            data-value="<?= $r['qty_return'] ?? 1 ?>"
            onclick="editCell(this, event)">
            <?= $r['qty_return'] ?? 1 ?>
        </td>
        <td style="text-align:center;" 
            class="price-new editable" 
            data-id="<?= $r['id'] ?>" 
            data-field="price_return" 
            data-type="number"
            data-value="<?= $r['price_return'] ?? 0 ?>"
            onclick="editCell(this, event)">
            <?= number_format((float)($r['price_return'] ?? 0), 2) ?>
        </td>
        <td style="text-align:center;" onclick="showDetail(this.parentElement)">
            <?php if ($isChecked): ?>
            <span class="status-checked">✅ ตรวจแล้ว</span>
            <?php else: ?>
            <span class="status-pending">⏳ รอตรวจ</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
    </table>
    </div>

    <div class="sticky-footer">
        <button type="submit" name="update_status" class="btn btn-submit" id="btnSubmit" disabled>
            ✅ ตรวจรับรายการที่เลือก (<span id="selectedCount">0</span> รายการ)
        </button>
    </div>
    </form>
    <?php else: ?>
    <p style="color:#999; text-align:center; padding:30px;">ไม่พบข้อมูลในช่วงเวลาที่ระบุ</p>
    <?php endif; ?>

    <p style="margin-top:30px;">
        <a href="search.php" class="btn btn-back">🔙 กลับหน้าค้นหา</a>
        <a href="stores.php" class="btn btn-stores">🏪 จัดการสาขา</a>
    </p>
</div>

<!-- Modal Popup รายละเอียด -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📋 รายละเอียดการเปลี่ยนสินค้า</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
// ระบบ checkbox
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.check-item');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSubmitButton();
}

document.querySelectorAll('.check-item').forEach(cb => {
    cb.addEventListener('change', updateSubmitButton);
});

function updateSubmitButton() {
    const checked = document.querySelectorAll('.check-item:checked');
    const count = checked.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('btnSubmit').disabled = (count === 0);
}

// ระบบแก้ไข inline
let currentEditCell = null;
let originalValue = '';

function editCell(cell, event) {
    event.stopPropagation();
    
    if (currentEditCell) {
        restoreCell();
    }
    
    const id = cell.dataset.id;
    const field = cell.dataset.field;
    const type = cell.dataset.type;
    const value = cell.dataset.value;
    
    currentEditCell = cell;
    originalValue = cell.innerHTML;
    
    let inputValue = value;
    
    let input;
    if (type === 'date') {
        input = document.createElement('input');
        input.type = 'date';
        input.value = inputValue;
    } else if (type === 'number') {
        input = document.createElement('input');
        input.type = 'number';
        input.step = field === 'price_return' ? '0.01' : '1';
        input.min = field === 'qty_return' ? '1' : '0.01';
        input.value = inputValue;
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.value = inputValue;
        if (field === 'return_barcode') {
            input.maxLength = 13;
            input.pattern = '[0-9]{13}';
        }
    }
    
    input.className = 'edit-input';
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEdit(cell, id, field, input.value, type);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            restoreCell();
        }
    });
    
    input.addEventListener('blur', function() {
        setTimeout(() => {
            if (currentEditCell === cell) {
                restoreCell();
            }
        }, 200);
    });
    
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    input.select();
}

function saveEdit(cell, id, field, value, type) {
    // Validate
    if (field === 'return_barcode' && value.length !== 13) {
        alert('บาร์โค้ดต้องมี 13 หลักเท่านั้น');
        restoreCell();
        return;
    }
    
    if (field === 'qty_return' && value < 1) {
        alert('จำนวนต้องมากกว่า 0');
        restoreCell();
        return;
    }
    
    if (field === 'price_return' && value <= 0) {
        alert('ราคาต้องมากกว่า 0');
        restoreCell();
        return;
    }
    
    cell.classList.add('saving');
    cell.innerHTML = '💾 กำลังบันทึก...';
    
    const formData = new FormData();
    formData.append('update_field', '1');
    formData.append('id', id);
    formData.append('field', field);
    formData.append('value', value);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let displayValue = value;
            if (field === 'return_date') {
                const d = new Date(value + 'T00:00:00');
                displayValue = d.toLocaleDateString('th-TH');
            } else if (field === 'price_return') {
                displayValue = parseFloat(value).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else if (!value) {
                displayValue = '-';
            }
            
            cell.innerHTML = displayValue;
            cell.dataset.value = value;
            cell.classList.remove('saving');
            cell.classList.add('success-flash');
            
            setTimeout(() => {
                cell.classList.remove('success-flash');
            }, 800);
            
            currentEditCell = null;
        } else {
            alert(data.error || 'เกิดข้อผิดพลาดในการบันทึก');
            restoreCell();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        restoreCell();
    });
}

function restoreCell() {
    if (currentEditCell) {
        currentEditCell.innerHTML = originalValue;
        currentEditCell.classList.remove('saving');
        currentEditCell = null;
        originalValue = '';
    }
}

// ระบบแสดง popup รายละเอียด
function showDetail(row) {
    const data = JSON.parse(row.getAttribute('data-detail'));
    
    const statusText = data.status === 'checked' ? '<span class="status-checked">✅ ตรวจแล้ว</span>' : '<span class="status-pending">⏳ รอตรวจ</span>';
    
    let html = `
        <div class="detail-section">
            <h4>🔢 ข้อมูลรายการ</h4>
            <div class="detail-row">
                <div class="detail-label">ID:</div>
                <div class="detail-value">${data.id}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">วันที่เปลี่ยน:</div>
                <div class="detail-value">${new Date(data.return_date).toLocaleDateString('th-TH')}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">สถานะ:</div>
                <div class="detail-value">${statusText}</div>
            </div>
        </div>

        <div class="detail-section">
            <h4>🏪 ข้อมูลสาขา</h4>
            <div class="detail-row">
                <div class="detail-label">รหัสสาขา:</div>
                <div class="detail-value">${data.store_code}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">ชื่อสาขา:</div>
                <div class="detail-value">${data.store_name}</div>
            </div>
        </div>

        <div class="detail-section">
            <h4>📦 สินค้าเดิม (ที่เปลี่ยน)</h4>
            <div class="detail-row">
                <div class="detail-label">เลขที่บิลเดิม:</div>
                <div class="detail-value"><strong>${data.old_number}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">วันที่ขายเดิม:</div>
                <div class="detail-value">${data.sale_date ? new Date(data.sale_date).toLocaleDateString('th-TH') : '-'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">แบรนด์:</div>
                <div class="detail-value">${data.brand}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">บาร์โค้ดเดิม:</div>
                <div class="detail-value">${data.old_barcode}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">ชื่อสินค้าเดิม:</div>
                <div class="detail-value">${data.item_description}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">จำนวนเดิม:</div>
                <div class="detail-value">${data.old_qty}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">ราคาเดิม:</div>
                <div class="detail-value price-old" style="font-size:18px;"><strong>${parseFloat(data.old_price).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</strong></div>
            </div>
        </div>

        <div class="detail-section" style="border-left-color: #4caf50;">
            <h4>🎁 สินค้าใหม่ (ที่เปลี่ยนให้)</h4>
            <div class="detail-row">
                <div class="detail-label">เลขที่บิลใหม่:</div>
                <div class="detail-value"><strong>${data.new_number || '-'}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">บาร์โค้ดใหม่:</div>
                <div class="detail-value">${data.return_barcode || '-'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">จำนวนเปลี่ยน:</div>
                <div class="detail-value">${data.qty_return}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">ราคาสินค้าใหม่:</div>
                <div class="detail-value price-new" style="font-size:18px;"><strong>${parseFloat(data.price_return).toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</strong></div>
            </div>
        </div>
    `;
    
    if (data.img_old || data.img_new) {
        html += '<div class="images-grid">';
        
        if (data.img_old) {
            html += `
                <div class="image-box">
                    <h5>📷 รูปบิลเดิม</h5>
                    <a href="uploads/${data.img_old}" target="_blank">
                        <img src="uploads/${data.img_old}" alt="รูปบิลเดิม">
                    </a>
                </div>
            `;
        }
        
        if (data.img_new) {
            html += `
                <div class="image-box">
                    <h5>📷 รูปบิลใหม่</h5>
                    <a href="uploads/${data.img_new}" target="_blank">
                        <img src="uploads/${data.img_new}" alt="รูปบิลใหม่">
                    </a>
                </div>
            `;
        }
        
        html += '</div>';
    }
    
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('detailModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('detailModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('detailModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

</body>
</html>
