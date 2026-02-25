<?php
// api/api_init.php

// 1. ตั้งค่าพื้นฐาน (Error Reporting & Headers)
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิด Error หน้าเว็บ (ส่งเป็น JSON แทนถ้ามีปัญหา)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 2. โหลด Config (รองรับทั้งไฟล์อยู่ข้างนอกหรือโฟลเดอร์เดียวกัน)
$config_paths = [

    __DIR__ . '/config.php'     // กรณีอยู่ root เดียวกัน
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Config file not found"]);
    exit;
}

// 3. เชื่อมต่อ Database
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit;
}

// 4. ตรวจสอบ API Key (จาก Database)
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? ''; // รองรับทั้ง GET และ POST

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "API Key is required"]);
    exit;
}

// เช็คในตาราง api_keys (ต้องสร้างตารางนี้ก่อน ตามที่คุยกันรอบที่แล้ว)
$stmt = $db->prepare("SELECT id, user_id FROM api_keys WHERE api_key = ? AND is_active = 1");
$stmt->execute([$api_key]);
$api_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$api_user) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid or Inactive API Key"]);
    exit;
}

// ถ้าผ่านทุกขั้นตอน ตัวแปร $db และ $api_user จะพร้อมใช้งานในไฟล์ถัดไปทันที
?>