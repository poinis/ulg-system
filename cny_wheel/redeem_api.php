<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    echo json_encode(['error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$shop_name = getShopName($username);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// === ค้นหาบิล ===
if ($action === 'search') {
    $bill = trim($input['bill_number'] ?? '');
    if (empty($bill)) {
        echo json_encode(['error' => 'กรุณากรอกเลขที่บิล']);
        exit;
    }

    // ค้นหาทุกสาขา
    $sql = "SELECT l.*, u.name as user_name 
            FROM cny_spin_log l 
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.bill_number = ?";
    $params = [$bill];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    if (!$result) {
        echo json_encode(['error' => 'ไม่พบเลขที่บิลนี้']);
        exit;
    }

    // Format prize name
    $prize_display = $result['prize_name'];
    if (in_array($result['prize_name'], ['50%', '30%', '20%', '15%'])) {
        $prize_display = 'ส่วนลด ' . $result['prize_name'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $result['id'],
            'bill_number' => $result['bill_number'],
            'shop_name' => $result['shop_name'],
            'prize_name' => $result['prize_name'],
            'prize_display' => $prize_display,
            'spun_at' => $result['spun_at'],
            'is_redeemed' => (int)$result['is_redeemed'],
            'redeemed_at' => $result['redeemed_at'],
            'redeemed_shop' => $result['redeemed_shop'] ?? null,
            'user_name' => $result['user_name'],
        ]
    ]);
    exit;
}

// === ยืนยันใช้สิทธิ์ ===
if ($action === 'redeem') {
    $spin_id = (int)($input['spin_id'] ?? 0);
    if (!$spin_id) {
        echo json_encode(['error' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    // ตรวจสอบว่ามี record นี้จริง + ยังไม่ใช้สิทธิ์ (ค้นหาทุกสาขา)
    $sql = "SELECT * FROM cny_spin_log WHERE id = ?";
    $params = [$spin_id];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['error' => 'ไม่พบข้อมูล']);
        exit;
    }

    if ($record['is_redeemed']) {
        echo json_encode(['error' => 'สิทธิ์นี้ถูกใช้ไปแล้ว']);
        exit;
    }

    // อัพเดทสถานะ + บันทึกสาขาที่ใช้สิทธิ์
    $redeemed_shop = $shop_name ?: 'unknown';
    $stmt = $pdo->prepare("UPDATE cny_spin_log SET is_redeemed = 1, redeemed_at = NOW(), redeemed_by = ?, redeemed_shop = ? WHERE id = ?");
    $stmt->execute([$user_id, $redeemed_shop, $spin_id]);

    echo json_encode(['success' => true, 'message' => '✅ ใช้สิทธิ์เรียบร้อยแล้ว']);
    exit;
}

echo json_encode(['error' => 'คำสั่งไม่ถูกต้อง']);
