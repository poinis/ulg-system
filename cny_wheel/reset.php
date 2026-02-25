<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ login + admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!isAdmin($role)) {
    echo json_encode(['error' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'reset_all') {
        // รีเซ็ตทั้งหมด
        $pdo->exec("UPDATE cny_prizes SET is_claimed = 0, claimed_by = NULL, claimed_at = NULL, bill_number = NULL");
        $pdo->exec("DELETE FROM cny_spin_log");
        echo json_encode(['success' => true, 'message' => '✅ รีเซ็ตรางวัลทั้งหมดเรียบร้อยแล้ว']);
    
    } elseif ($action === 'reset_shop') {
        $shop = $input['shop'] ?? '';
        if (empty($shop)) {
            echo json_encode(['error' => 'กรุณาระบุร้าน']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE cny_prizes SET is_claimed = 0, claimed_by = NULL, claimed_at = NULL, bill_number = NULL WHERE shop_name = ?");
        $stmt->execute([$shop]);
        
        $stmt = $pdo->prepare("DELETE FROM cny_spin_log WHERE shop_name = ?");
        $stmt->execute([$shop]);
        
        echo json_encode(['success' => true, 'message' => '✅ รีเซ็ตรางวัลของ ' . $shop . ' เรียบร้อยแล้ว']);
    
    } else {
        echo json_encode(['error' => 'คำสั่งไม่ถูกต้อง']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
