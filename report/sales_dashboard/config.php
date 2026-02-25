<?php
/**
 * Sales Dashboard Configuration
 */

// Debug Mode - เปิดให้เห็น error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'excel');
define('DB_USER', 'excel');          // เปลี่ยนเป็น username ของคุณ
define('DB_PASS', 'oatoatoat1');              // เปลี่ยนเป็น password ของคุณ

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('EMAIL_FROM', 'your_email@gmail.com');
define('EMAIL_FROM_NAME', 'Sales Dashboard');
define('EMAIL_RECIPIENTS', [
    'online@prontodenim.com',
]);

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("❌ Database connection failed: " . $e->getMessage() . "<br>กรุณาตรวจสอบ config.php และ database.sql");
        }
    }
    return $pdo;
}

// Helper Functions
function formatNumber($num) {
    return number_format($num, 2);
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check uploads directory
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
