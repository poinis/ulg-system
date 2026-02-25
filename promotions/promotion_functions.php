<?php
require_once "../config.php";

// ฟังก์ชันส่ง Pumble notification
function sendPumbleNotification($webhook_url, $message) {
    if (empty($webhook_url)) return false;
    
    $data = json_encode(['text' => $message]);
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data))
    );
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// ฟังก์ชันส่ง Email
function sendEmail($to, $subject, $message) {
    if (empty($to)) return false;
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@ulg.co.th" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// ฟังก์ชันแจ้งเตือนไปยังผู้อนุมัติ
function notifyApprovers($conn, $promotion_id, $promotion_name, $created_by_name) {
    // ดึงรายชื่อผู้อนุมัติ (role = admin หรือ approve)
    $sql = "SELECT id, username, email, pumble_webhook_url 
            FROM users 
            WHERE role IN ('admin', 'approve')";
    
    $result = mysqli_query($conn, $sql);
    
    $message_pumble = "🔔 แจ้งขออนุมัติโปรโมชั่นใหม่\n\n";
    $message_pumble .= "ชื่อโปรโมชั่น: {$promotion_name}\n";
    $message_pumble .= "ผู้สร้าง: {$created_by_name}\n";
    $message_pumble .= "กรุณาเข้าสู่ระบบเพื่อ อนุมัติ: https://www.weedjai.com/promotions/approve_promotion.php?id={$promotion_id}";
    
    $message_email = "<h2>🔔 แจ้งขออนุมัติโปรโมชั่นใหม่</h2>";
    $message_email .= "<p><strong>ชื่อโปรโมชั่น:</strong> {$promotion_name}</p>";
    $message_email .= "<p><strong>ผู้สร้าง:</strong> {$created_by_name}</p>";
    $message_email .= "<p><a href='https://www.weedjai.com/promotions/approve_promotion.php?id={$promotion_id}'>คลิกที่นี่เพื่ออนุมัติ</a></p>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        // ส่ง Pumble
        if (!empty($row['pumble_webhook_url'])) {
            sendPumbleNotification($row['pumble_webhook_url'], $message_pumble);
        }
        
        // ส่ง Email
        if (!empty($row['email'])) {
            sendEmail($row['email'], "แจ้งขออนุมัติโปรโมชั่น: {$promotion_name}", $message_email);
        }
        
        // บันทึกการแจ้งเตือน
        $insert_sql = "INSERT INTO promotion_notifications (promotion_id, user_id, notification_type) 
                       VALUES (?, ?, 'created')";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "ii", $promotion_id, $row['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ฟังก์ชันแจ้งเตือนเมื่ออนุมัติ
function notifyCreatorApproved($conn, $promotion_id, $promotion_name, $creator_id) {
    $sql = "SELECT username, email, pumble_webhook_url FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $creator_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $message_pumble = "✅ โปรโมชั่นได้รับการอนุมัติแล้ว!\n\n";
        $message_pumble .= "ชื่อโปรโมชั่น: {$promotion_name}\n";
        $message_pumble .= "กรุณาเข้าสู่ระบบเพื่อบันทึกเสร็จสิ้น: https://www.weedjai.com/promotion/complete.php?id={$promotion_id}";
        
        $message_email = "<h2>✅ โปรโมชั่นได้รับการอนุมัติแล้ว!</h2>";
        $message_email .= "<p><strong>ชื่อโปรโมชั่น:</strong> {$promotion_name}</p>";
        $message_email .= "<p><a href='https://www.weedjai.com/promotion/complete.php?id={$promotion_id}'>คลิกที่นี่เพื่อบันทึกเสร็จสิ้น</a></p>";
        
        if (!empty($user['pumble_webhook_url'])) {
            sendPumbleNotification($user['pumble_webhook_url'], $message_pumble);
        }
        
        if (!empty($user['email'])) {
            sendEmail($user['email'], "โปรโมชั่นได้รับการอนุมัติ: {$promotion_name}", $message_email);
        }
        
        $insert_sql = "INSERT INTO promotion_notifications (promotion_id, user_id, notification_type) 
                       VALUES (?, ?, 'approved')";
        $stmt2 = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt2, "ii", $promotion_id, $creator_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }
    
    mysqli_stmt_close($stmt);
}

// ฟังก์ชันแจ้งเตือนเมื่อไม่อนุมัติ
function notifyCreatorRejected($conn, $promotion_id, $promotion_name, $creator_id, $reason) {
    $sql = "SELECT username, email, pumble_webhook_url FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $creator_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $message_pumble = "❌ โปรโมชั่นไม่ผ่านการอนุมัติ\n\n";
        $message_pumble .= "ชื่อโปรโมชั่น: {$promotion_name}\n";
        $message_pumble .= "เหตุผล: {$reason}\n";
        $message_pumble .= "กรุณาแก้ไข: https://www.weedjai.com/promotion/edit.php?id={$promotion_id}";
        
        $message_email = "<h2>❌ โปรโมชั่นไม่ผ่านการอนุมัติ</h2>";
        $message_email .= "<p><strong>ชื่อโปรโมชั่น:</strong> {$promotion_name}</p>";
        $message_email .= "<p><strong>เหตุผล:</strong> {$reason}</p>";
        $message_email .= "<p><a href='https://www.weedjai.com/promotion/edit.php?id={$promotion_id}'>คลิกที่นี่เพื่อแก้ไข</a></p>";
        
        if (!empty($user['pumble_webhook_url'])) {
            sendPumbleNotification($user['pumble_webhook_url'], $message_pumble);
        }
        
        if (!empty($user['email'])) {
            sendEmail($user['email'], "โปรโมชั่นไม่ผ่านการอนุมัติ: {$promotion_name}", $message_email);
        }
        
        $insert_sql = "INSERT INTO promotion_notifications (promotion_id, user_id, notification_type) 
                       VALUES (?, ?, 'rejected')";
        $stmt2 = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt2, "ii", $promotion_id, $creator_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }
    
    mysqli_stmt_close($stmt);
}

// ฟังก์ชันแจ้งเตือนทุกคนเมื่อเสร็จสิ้น
function notifyAllCompleted($conn, $promotion_id, $promotion_name, $description, $start_date, $end_date) {
    $sql = "SELECT id, username, email, pumble_webhook_url FROM users WHERE role IN ('admin', 'promotion', 'approve', 'brand', 'marketing')";
    $result = mysqli_query($conn, $sql);
    
    $message_pumble = "🎉 โปรโมชั่นใหม่เริ่มใช้งานแล้ว!\n\n";
    $message_pumble .= "ชื่อโปรโมชั่น: {$promotion_name}\n";
    $message_pumble .= "วันที่: {$start_date} - {$end_date}\n";
    $message_pumble .= "รายละเอียด: {$description}\n";
    $message_pumble .= "ดูรายละเอียด: https://www.weedjai.com/promotion/view.php?id={$promotion_id}";
    
    $message_email = "<h2>🎉 โปรโมชั่นใหม่เริ่มใช้งานแล้ว!</h2>";
    $message_email .= "<p><strong>ชื่อโปรโมชั่น:</strong> {$promotion_name}</p>";
    $message_email .= "<p><strong>วันที่:</strong> {$start_date} - {$end_date}</p>";
    $message_email .= "<p><strong>รายละเอียด:</strong> {$description}</p>";
    $message_email .= "<p><a href='https://www.weedjai.com/promotion/view.php?id={$promotion_id}'>คลิกที่นี่เพื่อดูรายละเอียด</a></p>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['pumble_webhook_url'])) {
            sendPumbleNotification($row['pumble_webhook_url'], $message_pumble);
        }
        
        if (!empty($row['email'])) {
            sendEmail($row['email'], "โปรโมชั่นใหม่: {$promotion_name}", $message_email);
        }
        
        $insert_sql = "INSERT INTO promotion_notifications (promotion_id, user_id, notification_type) 
                       VALUES (?, ?, 'completed')";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "ii", $promotion_id, $row['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>