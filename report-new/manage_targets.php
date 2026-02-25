<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['id'])) { header('Location: login.php'); exit; }

$db = Database::getInstance()->getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $store = $_POST['store_code'];
    $month = $_POST['target_month'] . '-01';
    $amount = floatval($_POST['monthly_target']);
    $daily = $amount / date('t', strtotime($month));
    
    $stmt = $db->prepare("INSERT INTO sales_targets (store_code, target_month, monthly_target, daily_target) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE monthly_target=VALUES(monthly_target), daily_target=VALUES(daily_target)");
    if ($stmt->execute([$store, $month, $amount, $daily])) {
        $message = "บันทึกสำเร็จ!";
    } else {
        $message = "เกิดข้อผิดพลาด";
    }
}

// ✨ ใช้ Function กลาง
$stores = getActiveStores($db);

// Get Current Targets
$curr_month = date('Y-m');
$stmt = $db->prepare("SELECT t.*, s.store_name FROM sales_targets t JOIN stores s ON t.store_code = s.store_code WHERE DATE_FORMAT(target_month, '%Y-%m') = ? ORDER BY t.store_code");
$stmt->execute([$curr_month]);
$targets = $stmt->fetchAll();
?>