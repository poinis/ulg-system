<?php
/**
 * ไฟล์ทดสอบ Facebook API - พร้อมใช้งาน
 * ใส่ข้อมูลครบแล้ว เพียงแค่ upload และเรียกใช้
 */

// ข้อมูลการเชื่อมต่อ
$accessToken = 'EAATEt12ZCH1YBQMoGgzvWTb4gCuWI5DkeMGwipffwumMw2pZAcwpYSWH1xGpmJ1MzFDZCyOoHwVDl0rgBG7F7INCoDPd88lWLmN47enPEPEdiQR3e7oa1ZCmFHmzU1VvKtNcIcsJ3WUhfyyZCfKk80AdwZB2jxuZCjyypmrajCftG3XQvI5BuBntTNMaCh7eMUZAQwZDZD';
$pageId = '489861450880996';
$appId = '862629473011673';
$appSecret = 'ffdd223393467333a53f384aeae0609f';

echo "<h1>ทดสอบการเชื่อมต่อ Facebook API</h1>";
echo "<hr>";

// ทดสอบที่ 1: ตรวจสอบ Access Token
echo "<h2>1. ตรวจสอบ Access Token</h2>";
$url = "https://graph.facebook.com/v21.0/me?access_token={$accessToken}";
$response = @file_get_contents($url);

if ($response === false) {
    echo "<p style='color: red;'>❌ ไม่สามารถเชื่อมต่อ Facebook API ได้</p>";
} else {
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        echo "<p style='color: red;'>❌ Access Token ไม่ถูกต้อง: " . $data['error']['message'] . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Access Token ใช้งานได้</p>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
}

echo "<hr>";

// ทดสอบที่ 2: ดึงข้อมูลเพจ
echo "<h2>2. ข้อมูลเพจ</h2>";
$url = "https://graph.facebook.com/v21.0/{$pageId}?fields=name,fan_count,followers_count,category&access_token={$accessToken}";
$response = @file_get_contents($url);

if ($response) {
    $pageData = json_decode($response, true);
    
    if (isset($pageData['error'])) {
        echo "<p style='color: red;'>❌ ไม่สามารถดึงข้อมูลเพจได้: " . $pageData['error']['message'] . "</p>";
    } else {
        echo "<p style='color: green;'>✅ ดึงข้อมูลเพจสำเร็จ</p>";
        echo "<ul>";
        echo "<li><strong>ชื่อเพจ:</strong> " . $pageData['name'] . "</li>";
        echo "<li><strong>Page ID:</strong> " . $pageData['id'] . "</li>";
        echo "<li><strong>ประเภท:</strong> " . (isset($pageData['category']) ? $pageData['category'] : 'N/A') . "</li>";
        echo "<li><strong>จำนวน Fans:</strong> " . number_format($pageData['fan_count']) . "</li>";
        echo "<li><strong>จำนวน Followers:</strong> " . number_format($pageData['followers_count']) . "</li>";
        echo "</ul>";
    }
}

echo "<hr>";

// ทดสอบที่ 3: ดึงข้อมูล Insights (7 วันย้อนหลัง)
echo "<h2>3. ข้อมูล Insights (7 วันย้อนหลัง)</h2>";

$until = strtotime('today');
$since = strtotime('-7 days');

$url = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_impressions_unique,page_fans&period=day&since={$since}&until={$until}&access_token={$accessToken}";
$response = @file_get_contents($url);

if ($response) {
    $insightsData = json_decode($response, true);
    
    if (isset($insightsData['error'])) {
        echo "<p style='color: red;'>❌ ไม่สามารถดึงข้อมูล Insights ได้: " . $insightsData['error']['message'] . "</p>";
        echo "<p>ข้อความแนะนำ:</p>";
        echo "<ul>";
        echo "<li>ตรวจสอบว่ามี Permission: pages_read_engagement</li>";
        echo "<li>ตรวจสอบว่าใช้ Page Access Token (ไม่ใช่ User Token)</li>";
        echo "<li>ตรวจสอบว่าเพจมีข้อมูลในช่วงเวลานี้</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ ดึงข้อมูล Insights สำเร็จ</p>";
        
        if (isset($insightsData['data']) && count($insightsData['data']) > 0) {
            echo "<h3>📊 ข้อมูลที่ได้:</h3>";
            
            foreach ($insightsData['data'] as $metric) {
                echo "<h4>" . $metric['title'] . " (" . $metric['name'] . ")</h4>";
                
                if (isset($metric['values']) && count($metric['values']) > 0) {
                    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                    echo "<tr><th>วันที่</th><th>ค่า</th></tr>";
                    
                    foreach ($metric['values'] as $value) {
                        $date = isset($value['end_time']) ? date('Y-m-d', strtotime($value['end_time'])) : 'N/A';
                        echo "<tr>";
                        echo "<td>" . $date . "</td>";
                        echo "<td style='text-align: right;'>" . number_format($value['value']) . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</table><br>";
                }
            }
        } else {
            echo "<p style='color: orange;'>⚠️ ไม่มีข้อมูลในช่วงนี้</p>";
        }
    }
}

echo "<hr>";

// ทดสอบที่ 4: ดึงโพสต์ล่าสุด
echo "<h2>4. โพสต์ล่าสุด (5 โพสต์)</h2>";

$url = "https://graph.facebook.com/v21.0/{$pageId}/posts?fields=id,message,created_time,likes.summary(true),comments.summary(true)&limit=5&access_token={$accessToken}";
$response = @file_get_contents($url);

if ($response) {
    $postsData = json_decode($response, true);
    
    if (isset($postsData['error'])) {
        echo "<p style='color: red;'>❌ ไม่สามารถดึงโพสต์ได้: " . $postsData['error']['message'] . "</p>";
    } else {
        echo "<p style='color: green;'>✅ ดึงโพสต์สำเร็จ</p>";
        
        if (isset($postsData['data']) && count($postsData['data']) > 0) {
            echo "<div style='margin-top: 20px;'>";
            
            foreach ($postsData['data'] as $post) {
                echo "<div style='border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;'>";
                echo "<p><strong>Post ID:</strong> " . $post['id'] . "</p>";
                echo "<p><strong>วันที่:</strong> " . date('Y-m-d H:i:s', strtotime($post['created_time'])) . "</p>";
                
                if (isset($post['message'])) {
                    $message = strlen($post['message']) > 150 ? substr($post['message'], 0, 150) . '...' : $post['message'];
                    echo "<p><strong>ข้อความ:</strong> " . htmlspecialchars($message) . "</p>";
                }
                
                echo "<p>";
                echo "<strong>Likes:</strong> " . (isset($post['likes']['summary']['total_count']) ? $post['likes']['summary']['total_count'] : 0) . " | ";
                echo "<strong>Comments:</strong> " . (isset($post['comments']['summary']['total_count']) ? $post['comments']['summary']['total_count'] : 0);
                echo "</p>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            echo "<p style='color: orange;'>⚠️ ไม่พบโพสต์</p>";
        }
    }
}

echo "<hr>";

// สรุป
echo "<h2>✅ สรุปการทดสอบ</h2>";
echo "<p>หากทุกอย่างแสดงสีเขียว แสดงว่าระบบพร้อมใช้งาน!</p>";
echo "<p><strong>ขั้นตอนต่อไป:</strong></p>";
echo "<ol>";
echo "<li>เปิดไฟล์ <code>monthly_report.html</code> ในเบราว์เซอร์</li>";
echo "<li>คลิกปุ่ม 'ทดสอบการเชื่อมต่อ' เพื่อตรวจสอบ</li>";
echo "<li>เลือกเดือนที่ต้องการและคลิก 'โหลดรายงาน'</li>";
echo "<li>ดูรายงานและ Export เป็น Excel ได้</li>";
echo "</ol>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>Generated at: " . date('Y-m-d H:i:s') . "</p>";
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    
    h1 {
        color: #1877f2;
    }
    
    h2 {
        color: #333;
        margin-top: 30px;
    }
    
    pre {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
    
    table {
        width: 100%;
        margin-top: 10px;
    }
    
    th {
        background: #1877f2;
        color: white;
        padding: 10px;
    }
    
    td {
        background: white;
        padding: 8px;
    }
    
    code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
</style>