<?php
/**
 * ตรวจสอบประเภทเพจและความพร้อมของ Insights
 */

header('Content-Type: text/html; charset=utf-8');

$accessToken = 'EAATEt12ZCH1YBQMoGgzvWTb4gCuWI5DkeMGwipffwumMw2pZAcwpYSWH1xGpmJ1MzFDZCyOoHwVDl0rgBG7F7INCoDPd88lWLmN47enPEPEdiQR3e7oa1ZCmFHmzU1VvKtNcIcsJ3WUhfyyZCfKk80AdwZB2jxuZCjyypmrajCftG3XQvI5BuBntTNMaCh7eMUZAQwZDZD';
$pageId = '489861450880996';

echo "<h1>🔍 ตรวจสอบเพจและ Insights</h1>";
echo "<hr>";

// ตรวจสอบข้อมูลเพจ
echo "<h2>1. ข้อมูลเพจ</h2>";

$pageUrl = "https://graph.facebook.com/v21.0/{$pageId}?fields=id,name,category,fan_count,followers_count,is_published,verification_status,created_time,link&access_token={$accessToken}";

$pageResponse = @file_get_contents($pageUrl);

if ($pageResponse) {
    $pageData = json_decode($pageResponse, true);
    
    if (isset($pageData['error'])) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
        echo "<strong>❌ Error:</strong> " . $pageData['error']['message'];
        echo "</div>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white;'>";
        
        echo "<tr><td><strong>Page ID:</strong></td><td>{$pageData['id']}</td></tr>";
        echo "<tr><td><strong>ชื่อเพจ:</strong></td><td>{$pageData['name']}</td></tr>";
        echo "<tr><td><strong>Category:</strong></td><td>{$pageData['category']}</td></tr>";
        echo "<tr><td><strong>Fan Count:</strong></td><td>" . number_format($pageData['fan_count']) . "</td></tr>";
        echo "<tr><td><strong>Followers:</strong></td><td>" . number_format($pageData['followers_count']) . "</td></tr>";
        echo "<tr><td><strong>Published:</strong></td><td>" . ($pageData['is_published'] ? '✅ Yes' : '❌ No') . "</td></tr>";
        
        if (isset($pageData['verification_status'])) {
            echo "<tr><td><strong>Verification:</strong></td><td>{$pageData['verification_status']}</td></tr>";
        }
        
        if (isset($pageData['created_time'])) {
            $created = date('d/m/Y', strtotime($pageData['created_time']));
            $daysOld = floor((time() - strtotime($pageData['created_time'])) / 86400);
            echo "<tr><td><strong>สร้างเมื่อ:</strong></td><td>{$created} ({$daysOld} วันที่แล้ว)</td></tr>";
        }
        
        if (isset($pageData['link'])) {
            echo "<tr><td><strong>Link:</strong></td><td><a href='{$pageData['link']}' target='_blank'>{$pageData['link']}</a></td></tr>";
        }
        
        echo "</table>";
        
        // วิเคราะห์
        echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>📊 วิเคราะห์:</h3>";
        
        if ($pageData['fan_count'] < 100) {
            echo "<p>⚠️ <strong>เพจมีแฟนน้อยกว่า 100 คน</strong></p>";
            echo "<p>Facebook อาจยังไม่เปิด Insights ให้กับเพจที่มีแฟนน้อย</p>";
        }
        
        if (isset($daysOld) && $daysOld < 30) {
            echo "<p>⚠️ <strong>เพจอายุน้อยกว่า 30 วัน</strong></p>";
            echo "<p>Facebook อาจยังไม่มีข้อมูล Insights เพียงพอ</p>";
        }
        
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ ไม่สามารถดึงข้อมูลเพจได้</p>";
}

echo "<hr>";

// ตรวจสอบ Permissions
echo "<h2>2. ตรวจสอบ Permissions</h2>";

$permUrl = "https://graph.facebook.com/v21.0/me/permissions?access_token={$accessToken}";
$permResponse = @file_get_contents($permUrl);

if ($permResponse) {
    $permData = json_decode($permResponse, true);
    
    $requiredPerms = [
        'pages_show_list' => 'ดูรายการเพจ',
        'pages_read_engagement' => 'อ่าน Insights',
        'pages_read_user_content' => 'อ่านเนื้อหา'
    ];
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white;'>";
    echo "<tr><th>Permission</th><th>คำอธิบาย</th><th>สถานะ</th></tr>";
    
    $grantedPerms = [];
    if (isset($permData['data'])) {
        foreach ($permData['data'] as $perm) {
            if ($perm['status'] === 'granted') {
                $grantedPerms[] = $perm['permission'];
            }
        }
    }
    
    foreach ($requiredPerms as $perm => $desc) {
        $hasIt = in_array($perm, $grantedPerms);
        echo "<tr>";
        echo "<td><code>{$perm}</code></td>";
        echo "<td>{$desc}</td>";
        echo "<td style='color: " . ($hasIt ? 'green' : 'red') . "; font-weight: bold;'>";
        echo $hasIt ? '✅ มี' : '❌ ไม่มี';
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    $missingPerms = array_diff(array_keys($requiredPerms), $grantedPerms);
    
    if (count($missingPerms) > 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 20px; color: #856404;'>";
        echo "<p><strong>⚠️ คุณขาด Permissions:</strong></p>";
        echo "<ul>";
        foreach ($missingPerms as $perm) {
            echo "<li><code>{$perm}</code> - {$requiredPerms[$perm]}</li>";
        }
        echo "</ul>";
        echo "<p><strong>วิธีแก้:</strong> ไปที่ Graph API Explorer และขอ Permissions ใหม่</p>";
        echo "</div>";
    }
    
} else {
    echo "<p style='color: red;'>❌ ไม่สามารถตรวจสอบ Permissions ได้</p>";
}

echo "<hr>";

// ตรวจสอบว่า Insights พร้อมใช้งานหรือไม่
echo "<h2>3. ตรวจสอบความพร้อมของ Insights</h2>";

// ลองดึงข้อมูลย้อนหลัง 90 วัน
$daysToTest = [7, 30, 90];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white; width: 100%;'>";
echo "<tr><th>ช่วงเวลา</th><th>วันที่</th><th>มีข้อมูลหรือไม่</th><th>จำนวนวันที่มีข้อมูล</th></tr>";

foreach ($daysToTest as $days) {
    $since = strtotime("-{$days} days");
    $until = strtotime('today');
    
    $testUrl = "https://graph.facebook.com/v21.0/{$pageId}/insights?metric=page_impressions_unique&period=day&since={$since}&until={$until}&access_token={$accessToken}";
    
    $testResponse = @file_get_contents($testUrl);
    
    echo "<tr>";
    echo "<td>{$days} วันย้อนหลัง</td>";
    echo "<td style='font-size: 12px;'>" . date('d/m/Y', $since) . " - " . date('d/m/Y', $until) . "</td>";
    
    if ($testResponse) {
        $testData = json_decode($testResponse, true);
        
        if (isset($testData['data']) && count($testData['data']) > 0 && isset($testData['data'][0]['values'])) {
            $dataPoints = count($testData['data'][0]['values']);
            echo "<td style='color: green; font-weight: bold;'>✅ มี</td>";
            echo "<td>{$dataPoints} วัน</td>";
        } else {
            echo "<td style='color: red; font-weight: bold;'>❌ ไม่มี</td>";
            echo "<td>0 วัน</td>";
        }
    } else {
        echo "<td style='color: red;'>❌ Error</td>";
        echo "<td>-</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// ดูโพสต์ล่าสุด
echo "<h2>4. โพสต์ล่าสุด</h2>";

$postsUrl = "https://graph.facebook.com/v21.0/{$pageId}/posts?fields=id,message,created_time&limit=5&access_token={$accessToken}";
$postsResponse = @file_get_contents($postsUrl);

if ($postsResponse) {
    $postsData = json_decode($postsResponse, true);
    
    if (isset($postsData['data']) && count($postsData['data']) > 0) {
        echo "<p>✅ พบ " . count($postsData['data']) . " โพสต์</p>";
        
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: white; width: 100%;'>";
        echo "<tr><th>วันที่</th><th>ข้อความ</th></tr>";
        
        foreach ($postsData['data'] as $post) {
            echo "<tr>";
            echo "<td style='white-space: nowrap;'>" . date('d/m/Y H:i', strtotime($post['created_time'])) . "</td>";
            
            $message = isset($post['message']) ? $post['message'] : '(ไม่มีข้อความ)';
            $message = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
            echo "<td>" . htmlspecialchars($message) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ ไม่มีโพสต์</p>";
        echo "<p>เพจอาจยังไม่มี activity มากพอ</p>";
    }
} else {
    echo "<p style='color: red;'>❌ ไม่สามารถดึงโพสต์ได้</p>";
}

echo "<hr>";

// สรุปและคำแนะนำ
echo "<h2>5. สรุปและคำแนะนำ</h2>";

echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #1877f2;'>";
echo "<h3>💡 สาเหตุที่ไม่มีข้อมูล Insights:</h3>";

echo "<ol>";

echo "<li><strong>เพจยังใหม่เกินไป</strong>";
echo "<p>Facebook จะเริ่มเก็บข้อมูล Insights หลังจากเพจมี:</p>";
echo "<ul>";
echo "<li>แฟนอย่างน้อย 30-100 คน</li>";
echo "<li>มีอายุอย่างน้อย 7-30 วัน</li>";
echo "<li>มี activity สม่ำเสมอ (โพสต์, interaction)</li>";
echo "</ul>";
echo "</li>";

echo "<li><strong>เพจไม่ได้เปิด Insights</strong>";
echo "<p>ตรวจสอบที่:</p>";
echo "<ul>";
echo "<li>เข้า Facebook Page → Settings → Insights</li>";
echo "<li>ตรวจสอบว่าเปิดใช้งาน Insights หรือยัง</li>";
echo "</ul>";
echo "</li>";

echo "<li><strong>Token ไม่มี Permissions</strong>";
echo "<p>ตรวจสอบว่ามี permissions: <code>pages_read_engagement</code></p>";
echo "</li>";

echo "<li><strong>ใช้ Page Access Token ผิดเพจ</strong>";
echo "<p>ตรวจสอบว่า Token นี้เป็นของเพจที่ถูกต้อง</p>";
echo "</li>";

echo "</ol>";

echo "<h3>🔧 วิธีแก้ไข:</h3>";

echo "<ol>";

echo "<li><strong>รอให้เพจโตขึ้น</strong>";
echo "<p>เชิญเพื่อนมากด Like เพื่อให้มีแฟนมากกว่า 100 คน</p>";
echo "</li>";

echo "<li><strong>โพสต์เนื้อหาสม่ำเสมอ</strong>";
echo "<p>Facebook จะเริ่มเก็บข้อมูลเมื่อมี activity</p>";
echo "</li>";

echo "<li><strong>ใช้ข้อมูลจากที่อื่นแทน</strong>";
echo "<p>หากไม่มี Insights ให้ใช้ข้อมูลจาก:</p>";
echo "<ul>";
echo "<li>จำนวนแฟน (fan_count)</li>";
echo "<li>จำนวน followers (followers_count)</li>";
echo "<li>จำนวนโพสต์และ engagement ในแต่ละโพสต์</li>";
echo "</ul>";
echo "</li>";

echo "<li><strong>ทดสอบใน Facebook Business Suite</strong>";
echo "<p>เข้า <a href='https://business.facebook.com/' target='_blank'>business.facebook.com</a> แล้วดูว่ามี Insights หรือไม่</p>";
echo "</li>";

echo "</ol>";

echo "</div>";

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
        border-bottom: 2px solid #1877f2;
        padding-bottom: 10px;
    }
    
    h3 {
        color: #555;
        margin-top: 15px;
    }
    
    table {
        width: 100%;
        margin: 20px 0;
    }
    
    th {
        background: #1877f2;
        color: white;
        padding: 12px;
        text-align: left;
    }
    
    td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
    }
    
    a {
        color: #1877f2;
        text-decoration: none;
    }
    
    a:hover {
        text-decoration: underline;
    }
    
    ul {
        margin: 10px 0;
    }
    
    li {
        margin: 5px 0;
    }
</style>