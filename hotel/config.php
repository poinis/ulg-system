<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'hotelsystem');
define('DB_PASSWORD', '8UgmwIIvjWcXnKXXOg0G');
define('DB_NAME', 'hotelsystem');

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// สร้างการเชื่อมต่อ
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
mysqli_set_charset($conn, "utf8mb4");

// ตรวจสอบการเชื่อมต่อ
if ($conn === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// ตั้งค่า Pumble Webhook (แทนที่ด้วย webhook URL จริงของคุณ)
define('PUMBLE_WEBHOOK_URL', 'https://api.pumble.com/workspaces/68f56508266a2c592d54f7e9/incomingWebhooks/postMessage/FUjDbAWVY5yRngxb2uRAuCRk');


// ===================================
// PUMBLE NOTIFICATION FUNCTIONS
// ===================================

/**
 * ส่งการแจ้งเตือนผ่าน Pumble แบบทั่วไป
 */
function sendPumbleNotification($message, $webhook_url = null) {
    if ($webhook_url === null) {
        $webhook_url = PUMBLE_WEBHOOK_URL;
    }
    
    $data = json_encode([
        'text' => $message,
        'timestamp' => time()
    ]);
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpcode == 200;
}

/**
 * ส่งการแจ้งเตือนไปยังผู้ใช้เฉพาะคน
 */
function sendPumbleToUser($conn, $user_id, $message) {
    $sql = "SELECT name, pumble_webhook, role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user || empty($user['pumble_webhook'])) {
        return sendPumbleNotification($message);
    }
    
    $full_message = "🔔 แจ้งเตือนถึง: {$user['name']}\n" . $message;
    return sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user_id, $conn);
}

/**
 * ส่งการแจ้งเตือนไปยังแผนก
 */
function sendPumbleToDepartment($conn, $department_id, $message) {
    $sql = "SELECT name, pumble_webhook FROM departments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $department_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dept = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$dept || empty($dept['pumble_webhook'])) {
        return sendPumbleNotification($message);
    }
    
    $full_message = "🔔 แจ้งเตือนถึง: {$dept['name']}\n" . $message;
    return sendPumbleNotificationToWebhook($dept['pumble_webhook'], $full_message, null, $conn);
}

/**
 * ส่งการแจ้งเตือนไปยัง Role เฉพาะ
 */
function sendPumbleToRole($conn, $role, $message) {
    $success = true;
    $sql = "SELECT id, name, pumble_webhook FROM users WHERE role = ? AND pumble_webhook IS NOT NULL AND pumble_webhook != ''";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $count = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        $full_message = "🔔 แจ้งเตือนถึง Role: " . strtoupper($role) . "\n" . $message;
        $result_send = sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user['id'], $conn);
        if (!$result_send) {
            $success = false;
        }
        $count++;
    }
    mysqli_stmt_close($stmt);
    
    if ($count == 0) {
        return sendPumbleNotification($message);
    }
    
    return $success;
}

/**
 * ส่งการแจ้งเตือนไปยัง Multiple Users
 */
function sendPumbleToMultipleUsers($conn, $user_ids, $message) {
    if (empty($user_ids)) {
        return false;
    }
    
    $success = true;
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    
    $sql = "SELECT id, name, pumble_webhook FROM users WHERE id IN ($placeholders) AND pumble_webhook IS NOT NULL AND pumble_webhook != ''";
    $stmt = mysqli_prepare($conn, $sql);
    
    $types = str_repeat('i', count($user_ids));
    mysqli_stmt_bind_param($stmt, $types, ...$user_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($user = mysqli_fetch_assoc($result)) {
        $full_message = "🔔 แจ้งเตือนถึง: {$user['name']}\n" . $message;
        $result_send = sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user['id'], $conn);
        if (!$result_send) {
            $success = false;
        }
    }
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * ส่งการแจ้งเตือนไป Webhook URL เฉพาะ
 */
function sendPumbleNotificationToWebhook($webhook_url, $message, $user_id = null, $conn = null) {
    $data = json_encode([
        'text' => $message,
        'timestamp' => time()
    ]);
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = ($httpcode == 200);
    
    // บันทึก log
    if ($conn) {
        $status = $success ? 'success' : 'failed';
        $sql = "INSERT INTO pumble_logs (user_id, message, webhook_url, status, response) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $message, $webhook_url, $status, $response);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    return $success;
}

/**
 * ตรวจสอบว่าผู้ใช้เปิดการแจ้งเตือน Pumble หรือไม่
 */
function isPumbleEnabledForUser($conn, $user_id) {
    $sql = "SELECT pumble_enabled FROM notification_settings WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return true; // ถ้าไม่มีตาราง settings ให้ถือว่าเปิดอยู่
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $settings ? (bool)$settings['pumble_enabled'] : true;
}

/**
 * แจ้งเตือนเมื่อมีการมอบหมายงาน
 */
function notifyIssueAssigned($conn, $issue_id, $assigned_to, $assignee_name, $issue_title) {
    if (!isPumbleEnabledForUser($conn, $assigned_to)) {
        return false;
    }
    
    $message = "📋 คุณได้รับมอบหมายงานใหม่!\n";
    $message .= "หัวข้อ: {$issue_title}\n";
    $message .= "กรุณาตรวจสอบและดำเนินการ";
    
    return sendPumbleToUser($conn, $assigned_to, $message);
}

/**
 * แจ้งเตือนเมื่อมีการอัพเดทงาน
 */
function notifyIssueUpdated($conn, $issue_id, $user_ids, $issue_title, $update_message) {
    $message = "🔄 งานมีการอัพเดท!\n";
    $message .= "หัวข้อ: {$issue_title}\n";
    $message .= "รายละเอียด: {$update_message}";
    
    $filtered_users = [];
    foreach ($user_ids as $user_id) {
        if (isPumbleEnabledForUser($conn, $user_id)) {
            $filtered_users[] = $user_id;
        }
    }
    
    if (empty($filtered_users)) {
        return false;
    }
    
    return sendPumbleToMultipleUsers($conn, $filtered_users, $message);
}

/**
 * แจ้งเตือนเมื่องานเสร็จสมบูรณ์
 */
function notifyIssueCompleted($conn, $issue_id, $reporter_id, $issue_title) {
    if (!isPumbleEnabledForUser($conn, $reporter_id)) {
        return false;
    }
    
    $message = "✅ งานเสร็จสมบูรณ์!\n";
    $message .= "หัวข้อ: {$issue_title}\n";
    $message .= "งานที่คุณแจ้งได้รับการแก้ไขเรียบร้อยแล้ว";
    
    return sendPumbleToUser($conn, $reporter_id, $message);
}

// ===================================
// OTHER HELPER FUNCTIONS
// ===================================

/**
 * สร้างการแจ้งเตือนในระบบ
 */
function createNotification($conn, $user_id, $issue_id, $title, $message, $type) {
    $sql = "INSERT INTO notifications (user_id, issue_id, title, message, type) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iisss", $user_id, $issue_id, $title, $message, $type);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * อัพโหลดไฟล์
 */
function uploadFile($file, $upload_dir = 'uploads/') {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $target_path;
    }
    return false;
}

/**
 * แปลงวันที่เป็นภาษาไทย
 */
function getThaiDate($date, $format = 'full') {
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $thai_days = [
        'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร',
        'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $thai_months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    $day_name = $thai_days[date('l', $timestamp)];
    
    if ($format == 'full') {
        return "วัน{$day_name}ที่ {$day} {$month} {$year}";
    } else {
        return "{$day} {$month} {$year}";
    }
}

/**
 * คำนวณเวลาที่ผ่านมา
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' วินาทีที่แล้ว';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' นาทีที่แล้ว';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' วันที่แล้ว';
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}

/**
 * ตรวจสอบสิทธิ์การเข้าถึง
 */
function checkPermission($required_roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($required_roles)) {
        return in_array($_SESSION['role'], $required_roles);
    } else {
        return $_SESSION['role'] == $required_roles;
    }
}

/**
 * sanitize input
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>