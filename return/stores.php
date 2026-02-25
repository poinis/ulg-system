<?php
require 'config.php';

// สลับสถานะสาขา
if (isset($_GET['toggle_store'])) {
    $sc = intval($_GET['toggle_store']);
    $conn->query("UPDATE stores_return 
                  SET is_active = IF(is_active=1, 0, 1) 
                  WHERE store_code = $sc");
    header('Location: stores.php?success=1');
    exit;
}

// Query สาขา
$stores = $conn->query("SELECT * FROM stores_return ORDER BY store_code ASC");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการสาขา</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Sarabun', Arial, sans-serif;
    background: linear-gradient(135deg, #9C27B0 0%, #673AB7 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 1000px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    padding: 30px;
}
h2 {
    color: #333;
    border-bottom: 3px solid #9C27B0;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 24px;
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
    padding: 12px;
    text-align: left;
    font-size: 14px;
}
th {
    background: linear-gradient(135deg, #9C27B0 0%, #673AB7 100%);
    color: white;
    font-weight: 600;
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
.btn-toggle {
    background: #2196F3;
    color: white;
}
.btn-inactive {
    background: #f44336;
}
.btn-back {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    padding: 10px 20px;
    font-size: 14px;
}
</style>
</head>
<body>

<div class="container">
    <h2>🏪 จัดการสาขา</h2>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-msg">✅ อัปเดตสถานะสาขาเรียบร้อยแล้ว</div>
    <?php endif; ?>

    <table>
    <thead>
    <tr>
        <th>รหัสสาขา</th>
        <th>ชื่อสาขา</th>
        <th style="text-align:center; width:150px;">สถานะ</th>
        <th style="text-align:center; width:150px;">ดำเนินการ</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($s = $stores->fetch_assoc()): ?>
    <tr>
        <td><strong><?= $s['store_code'] ?></strong></td>
        <td><?= htmlspecialchars($s['store_name']) ?></td>
        <td style="text-align:center;">
            <span style="color:<?= ($s['is_active'] == 1) ? 'green' : 'red' ?>; font-weight:600; font-size:16px;">
                <?= ($s['is_active'] == 1) ? '✅ Active' : '❌ Inactive' ?>
            </span>
        </td>
        <td style="text-align:center;">
            <a href="?toggle_store=<?= $s['store_code'] ?>" 
               class="btn btn-toggle <?= ($s['is_active'] == 0) ? 'btn-inactive' : '' ?>"
               onclick="return confirm('ต้องการเปลี่ยนสถานะสาขา <?= htmlspecialchars($s['store_name']) ?> ?')">
                🔄 สลับสถานะ
            </a>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
    </table>

    <p style="margin-top:30px;">
        <a href="admin.php" class="btn btn-back">🔙 กลับหน้าแอดมิน</a>
    </p>
</div>

</body>
</html>
