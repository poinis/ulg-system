<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// รับข้อมูล
$input = json_decode(file_get_contents('php://input'), true);
$bill_number = trim($input['bill_number'] ?? '');

if (empty($bill_number)) {
    echo json_encode(['error' => 'กรุณากรอกเลขที่บิล']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$shop_name = getShopName($username);

if (!$shop_name) {
    echo json_encode(['error' => 'ไม่พบร้านค้าสำหรับ user นี้']);
    exit;
}

// ตรวจสอบบิลซ้ำ
$stmt = $pdo->prepare("SELECT id FROM cny_spin_log WHERE bill_number = ? AND shop_name = ?");
$stmt->execute([$bill_number, $shop_name]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'เลขที่บิลนี้ถูกใช้ไปแล้ว']);
    exit;
}

// ใช้ transaction + lock เพื่อป้องกัน race condition
$pdo->beginTransaction();

try {
    // หารางวัลถัดไปที่ยังไม่ถูกรับ (lock row)
    $stmt = $pdo->prepare("
        SELECT id, prize_name, prize_order 
        FROM cny_prizes 
        WHERE shop_name = ? AND is_claimed = 0 
        ORDER BY prize_order ASC 
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$shop_name]);
    $prize = $stmt->fetch();

    if ($prize) {
        // มีรางวัลเหลือ - อัพเดทว่าถูกรับแล้ว
        $stmt = $pdo->prepare("
            UPDATE cny_prizes 
            SET is_claimed = 1, claimed_by = ?, claimed_at = NOW(), bill_number = ? 
            WHERE id = ?
        ");
        $stmt->execute([$user_id, $bill_number, $prize['id']]);

        $prize_name = $prize['prize_name'];
        $prize_id = $prize['id'];
    } else {
        // รางวัลหมด - ให้ส่วนลด 15%
        $prize_name = '15%';
        $prize_id = null;
    }

    // บันทึก log
    $stmt = $pdo->prepare("
        INSERT INTO cny_spin_log (user_id, shop_name, bill_number, prize_name, prize_id, spun_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $shop_name, $bill_number, $prize_name, $prize_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'prize' => $prize_name,
        'bill_number' => $bill_number,
        'is_default' => $prize_id === null,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
