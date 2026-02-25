<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 ทดสอบระบบ Pumble Notification</h2>";

// ทดสอบ 1: ตรวจสอบไฟล์
echo "<h3>1. ตรวจสอบไฟล์</h3>";
if (file_exists("config.php")) {
    echo "✅ config.php พบแล้ว<br>";
} else {
    echo "❌ ไม่พบ config.php<br>";
}

if (file_exists("pumble_notification.php")) {
    echo "✅ pumble_notification.php พบแล้ว<br>";
} else {
    echo "❌ ไม่พบ pumble_notification.php<br>";
}

if (file_exists("pumble_notification_improved.php")) {
    echo "✅ pumble_notification_improved.php พบแล้ว<br>";
} else {
    echo "❌ ไม่พบ pumble_notification_improved.php<br>";
}

// ทดสอบ 2: ตรวจสอบ Database Connection
echo "<h3>2. ตรวจสอบ Database Connection</h3>";
try {
    require_once "config.php";
    if (isset($conn) && $conn) {
        echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ<br>";
        
        // ตรวจสอบตาราง users
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if (mysqli_num_rows($result) > 0) {
            echo "✅ ตาราง users พบแล้ว<br>";
            
            // ตรวจสอบคอลัมน์ pumble_webhook_url
            $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'pumble_webhook_url'");
            if (mysqli_num_rows($result) > 0) {
                echo "✅ คอลัมน์ pumble_webhook_url พบแล้ว<br>";
            } else {
                echo "❌ ไม่พบคอลัมน์ pumble_webhook_url (ต้องเพิ่มด้วย ALTER TABLE)<br>";
                echo "<code>ALTER TABLE users ADD COLUMN pumble_webhook_url TEXT;</code><br>";
            }
        } else {
            echo "❌ ไม่พบตาราง users<br>";
        }
    } else {
        echo "❌ ไม่สามารถเชื่อมต่อฐานข้อมูล<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// ทดสอบ 3: โหลด PumbleNotification Class
echo "<h3>3. ทดสอบโหลด PumbleNotification Class</h3>";
try {
    // ลองใช้ไฟล์เดิมก่อน
    if (file_exists("pumble_notification.php")) {
        require_once "pumble_notification.php";
        $pumble = new PumbleNotification();
        echo "✅ โหลด PumbleNotification สำเร็จ (ไฟล์เดิม)<br>";
    } elseif (file_exists("pumble_notification_improved.php")) {
        require_once "pumble_notification_improved.php";
        $pumble = new PumbleNotification();
        echo "✅ โหลด PumbleNotification สำเร็จ (ไฟล์ใหม่)<br>";
    } else {
        echo "❌ ไม่พบไฟล์ PumbleNotification<br>";
    }
} catch (Exception $e) {
    echo "❌ Error โหลด class: " . $e->getMessage() . "<br>";
}

// ทดสอบ 4: ตรวจสอบ cURL
echo "<h3>4. ตรวจสอบ cURL</h3>";
if (function_exists('curl_init')) {
    echo "✅ cURL พร้อมใช้งาน<br>";
} else {
    echo "❌ cURL ไม่พร้อมใช้งาน (ต้องติดตั้ง php-curl)<br>";
}

// ทดสอบ 5: ตรวจสอบสิทธิ์ไฟล์
echo "<h3>5. ตรวจสอบสิทธิ์การเขียนไฟล์</h3>";
$logFile = 'pumble_debug.log';
if (is_writable(dirname($logFile))) {
    echo "✅ สามารถเขียนไฟล์ log ได้<br>";
    file_put_contents($logFile, "[TEST] " . date('Y-m-d H:i:s') . " Test write\n", FILE_APPEND);
    echo "✅ เขียนไฟล์ทดสอบสำเร็จ<br>";
} else {
    echo "❌ ไม่สามารถเขียนไฟล์ log ได้<br>";
}

// ทดสอบ 6: ดึงข้อมูล users ที่มี webhook
echo "<h3>6. ตรวจสอบ Users ที่มี Webhook</h3>";
if (isset($conn) && $conn) {
    $sql = "SELECT username, name, pumble_webhook_url FROM users WHERE pumble_webhook_url IS NOT NULL AND pumble_webhook_url != ''";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $count = mysqli_num_rows($result);
        echo "✅ พบผู้ใช้ที่ตั้งค่า webhook: $count คน<br>";
        
        if ($count > 0) {
            echo "<table border='1' style='margin-top:10px; border-collapse: collapse;'>";
            echo "<tr><th>Username</th><th>Name</th><th>Webhook URL</th></tr>";
            while ($row = mysqli_fetch_assoc($result)) {
                $webhook_short = substr($row['pumble_webhook_url'], 0, 50) . "...";
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($webhook_short) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "⚠️ ยังไม่มีผู้ใช้ที่ตั้งค่า webhook<br>";
            echo "ต้อง UPDATE users SET pumble_webhook_url = '...' WHERE username = '...'<br>";
        }
    } else {
        echo "❌ ไม่สามารถดึงข้อมูลผู้ใช้ได้: " . mysqli_error($conn) . "<br>";
    }
}

echo "<hr>";
echo "<h3>📋 สรุป</h3>";
echo "<p>ตรวจสอบข้อความด้านบนแล้วแก้ไขสิ่งที่ขึ้น ❌</p>";
?>