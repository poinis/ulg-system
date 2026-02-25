<?php
/**
 * Database Configuration
 * แก้ไขค่าตามการตั้งค่าของคุณ
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'cmbase');
define('DB_USER', 'cmbase');
define('DB_PASS', '#wmIYH3wazaa');
define('DB_CHARSET', 'utf8mb4');

// Upload settings
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('UPLOAD_DIR', __DIR__ . '/uploads/');

/**
 * สร้างการเชื่อมต่อ PDO
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
?>
