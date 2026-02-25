<?php
/**
 * TikTok Auto Engage Fetcher
 * 
 * ไฟล์นี้ใช้สำหรับดึงข้อมูล Engage จาก TikTok API อัตโนมัติ
 * ควรรันผ่าน Cron Job ทุกวันเวลา 09:00 น.
 * 
 * ตัวอย่าง Cron Job:
 * 0 9 * * * /usr/bin/php /path/to/tiktok_auto_engage.php
 */

require_once "config.php";
require_once "pumble_notification.php";

// กำหนด API Credentials
define('TIKTOK_CLIENT_KEY', 'YOUR_TIKTOK_CLIENT_KEY');
define('TIKTOK_CLIENT_SECRET', 'YOUR_TIKTOK_CLIENT_SECRET');
define('TIKTOK_ACCESS_TOKEN', 'YOUR_TIKTOK_ACCESS_TOKEN');

// ฟังก์ชันบันทึก Log
function writeLog($message) {
    $log_file = __DIR__ . '/logs/tiktok_engage_' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// ฟังก์ชันดึงข้อมูลจาก TikTok API
function getTikTokVideoStats($video_id) {
    $url = "https://open.tiktokapis.com/v2/video/query/";
    
    $headers = array(
        'Authorization: Bearer ' . TIKTOK_ACCESS_TOKEN,
        'Content-Type: application/json'
    );
    
    $data = json_encode(array(
        'filters' => array(
            'video_ids' => array($video_id)
        ),
        'fields' => array(
            'id',
            'create_time',
            'view_count',
            'like_count',
            'comment_count',
            'share_count',
            'save_count'
        )
    ));
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        writeLog("TikTok API Error: HTTP $http_code");
        return null;
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['data']['videos'][0])) {
        return $result['data']['videos'][0];
    }
    
    return null;
}

// ฟังก์ชันดึงงานที่ต้องทำ Engage วันนี้
function getPendingEngageEvents($conn) {
    $today = date('Y-m-d');
    
    $sql = "SELECT e.*, t.tiktok_video_id 
            FROM calendar_events e
            LEFT JOIN tiktok_posts t ON e.id = t.event_id
            WHERE e.engage_date = ? 
            AND e.engage_status = 'pending'
            AND t.tiktok_video_id IS NOT NULL
            AND FIND_IN_SET('TT', e.platform) > 0";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $events = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $events;
}

// ฟังก์ชันบันทึก Engage
function saveEngage($conn, $event_id, $stats) {
    // ตรวจสอบว่ามี Engage อยู่แล้วหรือไม่
    $sql_check = "SELECT id FROM calendar_engage WHERE event_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $event_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    $exists = mysqli_stmt_num_rows($stmt_check) > 0;
    mysqli_stmt_close($stmt_check);
    
    $reach = intval($stats['view_count'] ?? 0);
    $impressions = $reach; // TikTok ไม่มี impressions แยก ใช้ view_count แทน
    $likes = intval($stats['like_count'] ?? 0);
    $comments = intval($stats['comment_count'] ?? 0);
    $shares = intval($stats['share_count'] ?? 0);
    $saves = intval($stats['save_count'] ?? 0);
    $note = "ดึงข้อมูลอัตโนมัติจาก TikTok API";
    
    if ($exists) {
        // อัปเดต
        $sql = "UPDATE calendar_engage 
                SET reach = ?, impressions = ?, likes = ?, comments = ?, shares = ?, saves = ?,
                    note = ?, updated_by = 'TikTok Auto', updated_at = NOW()
                WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiissi", 
            $reach, $impressions, $likes, $comments, $shares, $saves, $note, $event_id);
    } else {
        // สร้างใหม่
        $engage_date = date('Y-m-d');
        $sql = "INSERT INTO calendar_engage 
                (event_id, engage_date, reach, impressions, likes, comments, shares, saves, note, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'TikTok Auto')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isiiiiss", 
            $event_id, $engage_date, $reach, $impressions, $likes, $comments, $shares, $saves, $note);
    }
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // อัปเดตสถานะ
        $sql_update = "UPDATE calendar_events SET engage_status = 'completed' WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "i", $event_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
    }
    
    return $success;
}

// ============================
// เริ่มต้นการทำงาน
// ============================

writeLog("=== Starting TikTok Auto Engage Fetcher ===");

try {
    // ดึงรายการงานที่ต้องทำ Engage วันนี้
    $events = getPendingEngageEvents($conn);
    
    writeLog("Found " . count($events) . " events to process");
    
    $success_count = 0;
    $fail_count = 0;
    $pumble_messages = array();
    
    foreach ($events as $event) {
        $event_id = $event['id'];
        $video_id = $event['tiktok_video_id'];
        $job_title = $event['job_title'];
        
        writeLog("Processing Event ID: $event_id | TikTok Video ID: $video_id");
        
        // ดึงข้อมูลจาก TikTok
        $stats = getTikTokVideoStats($video_id);
        
        if ($stats) {
            // บันทึก Engage
            if (saveEngage($conn, $event_id, $stats)) {
                $success_count++;
                writeLog("✓ Success: Event ID $event_id");
                
                // เตรียมข้อความสำหรับ Pumble
                $pumble_messages[] = array(
                    'title' => $job_title,
                    'stats' => $stats
                );
            } else {
                $fail_count++;
                writeLog("✗ Failed to save: Event ID $event_id");
            }
        } else {
            $fail_count++;
            writeLog("✗ Failed to fetch TikTok data: Event ID $event_id");
        }
        
        // หน่วงเวลา 1 วินาทีเพื่อไม่ให้ hit API มากเกินไป
        sleep(1);
    }
    
    // ส่งการแจ้งเตือนไปยัง Pumble
    if (!empty($pumble_messages)) {
        try {
            $pumble = new PumbleNotification();
            
            $message = "📊 *TikTok Auto Engage Report - " . date('d/m/Y') . "*\n\n";
            $message .= "✅ สำเร็จ: $success_count งาน\n";
            $message .= "❌ ล้มเหลว: $fail_count งาน\n\n";
            
            foreach ($pumble_messages as $item) {
                $message .= "━━━━━━━━━━━━━━━━\n";
                $message .= "📋 " . $item['title'] . "\n";
                $message .= "👁️ Views: " . number_format($item['stats']['view_count']) . "\n";
                $message .= "❤️ Likes: " . number_format($item['stats']['like_count']) . "\n";
                $message .= "💬 Comments: " . number_format($item['stats']['comment_count']) . "\n";
                $message .= "🔗 Shares: " . number_format($item['stats']['share_count']) . "\n";
                $message .= "💾 Saves: " . number_format($item['stats']['save_count']) . "\n\n";
            }
            
            $pumble->sendToRoles($message, ['admin', 'marketing']);
            writeLog("Pumble notification sent successfully");
            
        } catch (Exception $e) {
            writeLog("Pumble notification error: " . $e->getMessage());
        }
    }
    
    writeLog("=== Completed: Success=$success_count, Failed=$fail_count ===");
    
} catch (Exception $e) {
    writeLog("CRITICAL ERROR: " . $e->getMessage());
}

mysqli_close($conn);
writeLog("=== End of process ===\n");

?>