<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'cmbase');
define('DB_PASS', '#wmIYH3wazaa');
define('DB_NAME', 'cmbase');

// Notification Settings
define('ADMIN_EMAIL', 'admin@example.com');
define('PUMBLE_WEBHOOK_URL', 'your_pumble_webhook_url');

// Timezone
date_default_timezone_set('Asia/Bangkok');

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Helper Functions
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, '.', ',');
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function getThaiMonth($month) {
    $months = [
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
        '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
        '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
        '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    ];
    return $months[$month] ?? '';
}

// ✨ ฟังก์ชันใหม่: ดึงเฉพาะร้านค้าที่ Active (ใช้สำหรับ Dropdown และ Loop ทั่วไป)
function getActiveStores($db) {
    $stmt = $db->query("SELECT store_code, store_name FROM stores WHERE is_active = 1 ORDER BY store_code ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>