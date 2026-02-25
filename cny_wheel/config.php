<?php
// =============================================
// Database Configuration
// =============================================
$db_host = 'localhost';       // เปลี่ยนเป็น host ของคุณ
$db_name = 'cmbase';          // ชื่อ database
$db_user = 'cmbase';            // username
$db_pass = '#wmIYH3wazaa';                 // password

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['id'];
}

// ดึง role จาก DB ถ้ายังไม่มี
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['role'] = $stmt->fetchColumn() ?: '';
}
// Shop name mapping - map user accounts to shop names in cny_prizes
// ปรับ mapping ตาม user ที่ login ในระบบของคุณ
$shop_mapping = [
    'Central Ladprao'          => ['prontoclp','point'],
    'Mega Bangna'              => ['prontomega', 'oat'],
    'Central Festival Chiangmai' => ['cmf'],
    'Central Rama 9'           => ['Prontorama9'],
    'Siam Paragon'             => ['Paragon' ],
];

/**
 * Get shop name from username
 */
function getShopName($username) {
    global $shop_mapping;
    foreach ($shop_mapping as $shop => $usernames) {
        if (in_array($username, $usernames)) {
            return $shop;
        }
    }
    return null;
}

/**
 * Check if user is admin/owner
 */
function isAdmin($role) {
    return in_array($role, ['marketing', 'admin', 'owner']);
}
