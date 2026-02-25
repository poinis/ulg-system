<?php
// ฟังก์ชันส่งการแจ้งเตือนผ่าน Pumble (อัพเดทเวอร์ชัน)

/**
 * ส่งการแจ้งเตือนไปยังผู้ใช้เฉพาะคน
 * @param mysqli $conn Database connection
 * @param int $user_id ID ของผู้ใช้
 * @param string $message ข้อความที่จะส่ง
 * @return bool สำเร็จหรือไม่
 */
function sendPumbleToUser($conn, $user_id, $message) {
    // ดึง webhook URL ของผู้ใช้
    $sql = "SELECT name, pumble_webhook, role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user || empty($user['pumble_webhook'])) {
        // ไม่มี webhook ส่งไปที่ webhook ทั่วไป
        return sendPumbleNotification($message);
    }
    
    // เพิ่มชื่อผู้ใช้ในข้อความ
    $full_message = "🔔 แจ้งเตือนถึง: {$user['name']}\n" . $message;
    
    return sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user_id, $conn);
}

/**
 * ส่งการแจ้งเตือนไปยังแผนก
 * @param mysqli $conn Database connection
 * @param int $department_id ID ของแผนก
 * @param string $message ข้อความที่จะส่ง
 * @return bool สำเร็จหรือไม่
 */
function sendPumbleToDepartment($conn, $department_id, $message) {
    // ดึง webhook URL ของแผนก
    $sql = "SELECT name, pumble_webhook FROM departments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $department_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dept = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$dept || empty($dept['pumble_webhook'])) {
        // ไม่มี webhook ส่งไปที่ webhook ทั่วไป
        return sendPumbleNotification($message);
    }
    
    // เพิ่มชื่อแผนกในข้อความ
    $full_message = "🔔 แจ้งเตือนถึง: {$dept['name']}\n" . $message;
    
    return sendPumbleNotificationToWebhook($dept['pumble_webhook'], $full_message, null, $conn);
}

/**
 * ส่งการแจ้งเตือนไปยัง Role เฉพาะ (เช่น ทุกคนที่เป็น admin)
 * @param mysqli $conn Database connection
 * @param string $role Role ของผู้ใช้
 * @param string $message ข้อความที่จะส่ง
 * @return bool สำเร็จหรือไม่
 */
function sendPumbleToRole($conn, $role, $message) {
    $success = true;
    
    // ดึงผู้ใช้ทั้งหมดในบทบาทนี้ที่มี webhook
    $sql = "SELECT id, name, pumble_webhook FROM users WHERE role = ? AND pumble_webhook IS NOT NULL AND pumble_webhook != ''";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $count = 0;
    while ($user = mysqli_fetch_assoc($result)) {
        $full_message = "🔔 แจ้งเตือนถึง Role: " . strtoupper($role) . "\n" . $message;
        $result = sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user['id'], $conn);
        if (!$result) {
            $success = false;
        }
        $count++;
    }
    mysqli_stmt_close($stmt);
    
    // ถ้าไม่มีใครมี webhook ส่งไปที่ webhook ทั่วไป
    if ($count == 0) {
        return sendPumbleNotification($message);
    }
    
    return $success;
}

/**
 * ส่งการแจ้งเตือนไปยัง Multiple Users
 * @param mysqli $conn Database connection
 * @param array $user_ids Array ของ user IDs
 * @param string $message ข้อความที่จะส่ง
 * @return bool สำเร็จหรือไม่
 */
function sendPumbleToMultipleUsers($conn, $user_ids, $message) {
    if (empty($user_ids)) {
        return false;
    }
    
    $success = true;
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    
    $sql = "SELECT id, name, pumble_webhook FROM users WHERE id IN ($placeholders) AND pumble_webhook IS NOT NULL AND pumble_webhook != ''";
    $stmt = mysqli_prepare($conn, $sql);
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($user_ids));
    mysqli_stmt_bind_param($stmt, $types, ...$user_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($user = mysqli_fetch_assoc($result)) {
        $full_message = "🔔 แจ้งเตือนถึง: {$user['name']}\n" . $message;
        $result = sendPumbleNotificationToWebhook($user['pumble_webhook'], $full_message, $user['id'], $conn);
        if (!$result) {
            $success = false;
        }
    }
    mysqli_stmt_close($stmt);
    
    return $success;
}

/**
 * ส่งการแจ้งเตือนไป Webhook URL เฉพาะ
 * @param string $webhook_url Webhook URL
 * @param string $message ข้อความที่จะส่ง
 * @param int $user_id ID ของผู้ใช้ (สำหรับเก็บ log)
 * @param mysqli $conn Database connection (สำหรับเก็บ log)
 * @return bool สำเร็จหรือไม่
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
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $message, $webhook_url, $status, $response);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    return $success;
}

/**
 * ตรวจสอบว่าผู้ใช้เปิดการแจ้งเตือน Pumble หรือไม่
 * @param mysqli $conn Database connection
 * @param int $user_id ID ของผู้ใช้
 * @return bool เปิดหรือไม่
 */
function isPumbleEnabledForUser($conn, $user_id) {
    $sql = "SELECT pumble_enabled FROM notification_settings WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // ถ้าไม่มีการตั้งค่า ให้ถือว่าเปิดอยู่
    return $settings ? (bool)$settings['pumble_enabled'] : true;
}

/**
 * ฟังก์ชันสำหรับแจ้งเตือนเมื่อมีการมอบหมายงาน
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
 * ฟังก์ชันสำหรับแจ้งเตือนเมื่อมีการอัพเดทงาน
 */
function notifyIssueUpdated($conn, $issue_id, $user_ids, $issue_title, $update_message) {
    $message = "🔄 งานมีการอัพเดท!\n";
    $message .= "หัวข้อ: {$issue_title}\n";
    $message .= "รายละเอียด: {$update_message}";
    
    // กรองเฉพาะคนที่เปิดการแจ้งเตือน
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
 * ฟังก์ชันสำหรับแจ้งเตือนเมื่องานเสร็จสมบูรณ์
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

// ตัวอย่างการใช้งาน:
/*
// ส่งแจ้งเตือนไปยังผู้ใช้เฉพาะคน
sendPumbleToUser($conn, $user_id, "คุณมีงานใหม่!");

// ส่งแจ้งเตือนไปยังแผนก
sendPumbleToDepartment($conn, $department_id, "แผนกมีงานเร่งด่วน!");

// ส่งแจ้งเตือนไปยังทุกคนใน Role
sendPumbleToRole($conn, 'admin', "มีปัญหาเร่งด่วนต้องการความช่วยเหลือ!");

// ส่งแจ้งเตือนไปยังหลายคน
sendPumbleToMultipleUsers($conn, [1, 2, 3], "การประชุมในอีก 15 นาที");
*/
?>