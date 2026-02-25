<?php
/**
 * Pumble Notification Service
 * ส่งการแจ้งเตือนแบบ Private Channel แยกรายบุคคลตาม role
 */

class PumbleNotification {
    private $webhookUrl;
    private $debug = true;
    private $conn;
    
    // เก็บ Webhook URL สำหรับแต่ละ user (สำหรับ private channel)
    private $pumbleUsers = array();
    
    // เก็บ log ว่าส่งไปให้ใครแล้วบ้าง (ป้องกันส่งซ้ำ)
    private $sentWebhooks = array();
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        
        date_default_timezone_set('Asia/Bangkok');
        
        // Webhook URL หลัก (ส่งไป general channel)
        $this->webhookUrl = "https://api.pumble.com/workspaces/68ac2aeee026f6941010344a/incomingWebhooks/postMessage/eKMAEd5TM7yQaw1C86cUXRHh";
        
        $this->loadPumbleUsers();
    }
    
    /**
     * โหลด Webhook URLs สำหรับแต่ละ user
     */
    private function loadPumbleUsers() {
        if (!$this->conn) return;
        
        $sql = "SELECT username, name, pumble_webhook_url FROM users WHERE pumble_webhook_url IS NOT NULL AND pumble_webhook_url != ''";
        $result = mysqli_query($this->conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->pumbleUsers[$row['username']] = array(
                    'webhook' => $row['pumble_webhook_url'],
                    'name' => $row['name']
                );
            }
            mysqli_free_result($result);
        }
        
        $this->logDebug("Loaded " . count($this->pumbleUsers) . " Pumble users");
    }
    
    /**
     * เขียน log
     */
    private function logDebug($message) {
        if ($this->debug) {
            $logFile = 'pumble_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        }
    }
    
    /**
     * ดึงรายชื่อผู้ใช้ตาม role
     */
    private function getUsersByRole($roles) {
        if (!$this->conn) return array();
        
        $users = array();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        
        $sql = "SELECT username, name, role FROM users WHERE role IN ($placeholders)";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if ($stmt) {
            $types = str_repeat('s', count($roles));
            mysqli_stmt_bind_param($stmt, $types, ...$roles);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
            
            mysqli_stmt_close($stmt);
        }
        
        $this->logDebug("Found " . count($users) . " users with roles: " . implode(', ', $roles));
        return $users;
    }
    
    /**
     * ส่งข้อความไปยัง Pumble
     */
    private function sendMessage($message, $webhookUrl = null) {
        $targetWebhook = $webhookUrl ?: $this->webhookUrl;
        
        if (empty($targetWebhook)) {
            $this->logDebug("ERROR: Webhook URL is not configured");
            return false;
        }
        
        // ตรวจสอบว่าเคยส่งไปที่ webhook นี้แล้วหรือไม่ (ป้องกันส่งซ้ำ)
        $webhookHash = md5($targetWebhook . $message);
        if (in_array($webhookHash, $this->sentWebhooks)) {
            $this->logDebug("SKIPPED: Already sent to this webhook (duplicate prevention)");
            return true; // return true เพราะถือว่าส่งแล้ว
        }
        
        if (!function_exists('curl_init')) {
            $this->logDebug("ERROR: cURL is not available");
            return false;
        }
        
        $this->logDebug("Sending message to: " . substr($targetWebhook, -20));
        
        $data = array('text' => $message);
        $payload = json_encode($data);
        
        $ch = curl_init($targetWebhook);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        $this->logDebug("HTTP Code: $httpCode");
        
        if (!empty($error)) {
            $this->logDebug("cURL Error: $error");
        }
        
        curl_close($ch);
        
        $success = ($httpCode == 200 || $httpCode == 204);
        
        if ($success) {
            // บันทึกว่าส่งไปแล้ว (ป้องกันส่งซ้ำ)
            $this->sentWebhooks[] = $webhookHash;
            $this->logDebug("SUCCESS: Message sent");
        } else {
            $this->logDebug("FAILED: Message not sent");
        }
        
        return $success;
    }
    
    /**
     * ส่งข้อความแยกไปยังแต่ละคนตาม role (ป้องกันส่งซ้ำ)
     */
    public function sendToRoles($message, $roles) {
        $users = $this->getUsersByRole($roles);
        
        if (empty($users)) {
            $this->logDebug("No users found for roles: " . implode(', ', $roles));
            return false;
        }
        
        $sentCount = 0;
        $failedUsers = array();
        $skippedUsers = array();
        
        foreach ($users as $user) {
            $username = $user['username'];
            
            if (isset($this->pumbleUsers[$username])) {
                $webhook = $this->pumbleUsers[$username]['webhook'];
                
                // ตรวจสอบว่าเคยส่งไปที่ webhook นี้แล้วหรือไม่
                $webhookHash = md5($webhook . $message);
                if (in_array($webhookHash, $this->sentWebhooks)) {
                    $this->logDebug("SKIPPED: Already sent to {$user['name']} (duplicate)");
                    $skippedUsers[] = $user['name'];
                    continue;
                }
                
                $this->logDebug("Sending to {$user['name']} ({$user['role']})");
                
                if ($this->sendMessage($message, $webhook)) {
                    $sentCount++;
                } else {
                    $failedUsers[] = $user['name'];
                }
            } else {
                $this->logDebug("SKIPPED: No webhook configured for user $username ({$user['name']})");
                $failedUsers[] = $user['name'] . " (no webhook)";
            }
        }
        
        $this->logDebug("Sent to $sentCount out of " . count($users) . " users");
        
        if (!empty($skippedUsers)) {
            $this->logDebug("Skipped (duplicates): " . implode(', ', $skippedUsers));
        }
        
        if (!empty($failedUsers)) {
            $this->logDebug("Failed users: " . implode(', ', $failedUsers));
        }
        
        return $sentCount > 0;
    }
    
    /**
     * แจ้งเตือนเมื่อมีบรีฟใหม่ (ส่งให้ admin)
     */
    public function notifyNewBrief($briefData) {
        $this->logDebug("=== notifyNewBrief called ===");
        
        $message = "🆕 *มีบรีฟใหม่เข้าระบบ*\n\n";
        $message .= "📋 ชื่องาน: " . $briefData['job_title'] . "\n";
        $message .= "🏢 แบรนด์: " . $briefData['brand'] . "\n";
        $message .= "📱 แพลตฟอร์ม: " . $briefData['platform'] . "\n";
        $message .= "📂 หมวดหมู่: " . $briefData['category'] . "\n";
        $message .= "📅 กำหนดส่ง: " . $briefData['due_date'] . "\n";
        $message .= "👤 ผู้สร้าง: " . $briefData['name'] . " (" . $briefData['username'] . ")\n";
        
        if (!empty($briefData['requester_name'])) {
            $message .= "👔 ผู้สั่งงาน: " . $briefData['requester_name'] . "\n";
        }
        
        if (!empty($briefData['remark'])) {
            $message .= "📝 หมายเหตุ: " . $briefData['remark'] . "\n";
        }
        
        if (!empty($briefData['attachment'])) {
            $message .= "📎 มีไฟล์แนบ\n";
        }
        
        if (isset($briefData['url'])) {
            $message .= "\n🔗 ดูรายละเอียด: " . $briefData['url'];
        }
        
        // รีเซ็ต sentWebhooks ก่อนส่งครั้งใหม่
        $this->sentWebhooks = array();
        
        $result = $this->sendToRoles($message, ['admin']);
        
        $this->logDebug("=== notifyNewBrief completed ===");
        
        return $result;
    }
    
    /**
     * แจ้งเตือนเมื่อมีการเปลี่ยนสถานะ
     */
    public function notifyStatusChange($briefId, $jobTitle, $oldStatus, $newStatus, $updatedBy) {
        $this->logDebug("=== notifyStatusChange called: $oldStatus -> $newStatus ===");
        
        $statusEmoji = array(
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅',
            'cancelled' => '❌',
            'need_info' => '❓',
            'need_update' => '🔧',
            'approved' => '🎉'
        );
        
        $statusThai = array(
            'pending' => 'รอดำเนินการ',
            'in_progress' => 'กำลังดำเนินการ',
            'completed' => 'เสร็จสิ้น',
            'cancelled' => 'ยกเลิก',
            'need_info' => 'ต้องการข้อมูลเพิ่มเติม',
            'need_update' => 'ต้องแก้ไขงาน',
            'approved' => 'อนุมัติแล้ว'
        );
        
        $emoji = isset($statusEmoji[$newStatus]) ? $statusEmoji[$newStatus] : '📌';
        $oldStatusText = isset($statusThai[$oldStatus]) ? $statusThai[$oldStatus] : $oldStatus;
        $newStatusText = isset($statusThai[$newStatus]) ? $statusThai[$newStatus] : $newStatus;
        
        $message = "$emoji *เปลี่ยนสถานะบรีฟ*\n\n";
        $message .= "📋 ชื่องาน: $jobTitle\n";
        $message .= "📊 สถานะเดิม: $oldStatusText\n";
        $message .= "📊 สถานะใหม่: $newStatusText\n";
        $message .= "👤 ผู้แก้ไข: $updatedBy\n";
        $message .= "⏰ เวลา: " . date('d/m/Y H:i:s') . "\n";
        $message .= "\n🔗 ดูรายละเอียด: https://www.weedjai.com/content/detail_content.php?id=$briefId";
        
        $targetRoles = array();
        
        switch ($newStatus) {
            case 'completed':
                $targetRoles = ['admin', 'brand', 'marketing', 'approve'];
                break;
                
            case 'approved':
                $targetRoles = ['admin', 'brand', 'marketing'];
                break;
                
            case 'need_update':
                $targetRoles = ['admin'];
                break;
                
            case 'pending':
                $targetRoles = ['admin'];
                break;
                
            default:
                $targetRoles = ['admin'];
                break;
        }
        
        $this->logDebug("Target roles: " . implode(', ', $targetRoles));
        
        // รีเซ็ต sentWebhooks ก่อนส่งครั้งใหม่
        $this->sentWebhooks = array();
        
        $result = $this->sendToRoles($message, $targetRoles);
        
        $this->logDebug("=== notifyStatusChange completed ===");
        
        return $result;
    }
    
    /**
     * แจ้งเตือนเมื่อมีผู้เข้าสู่ระบบ (ส่งให้ admin)
     */
    public function notifyLogin($username, $name, $ipAddress = null) {
        $this->logDebug("=== notifyLogin called ===");
        
        $message = "🔐 *มีผู้เข้าสู่ระบบ*\n\n";
        $message .= "👤 ชื่อ: $name\n";
        $message .= "🆔 Username: $username\n";
        $message .= "📅 เวลา: " . date('d/m/Y H:i:s') . "\n";
        
        if ($ipAddress) {
            $message .= "🌐 IP Address: $ipAddress";
        }
        
        // รีเซ็ต sentWebhooks ก่อนส่งครั้งใหม่
        $this->sentWebhooks = array();
        
        $result = $this->sendToRoles($message, ['admin']);
        
        $this->logDebug("=== notifyLogin completed ===");
        
        return $result;
    }
}
?>