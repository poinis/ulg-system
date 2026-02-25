<?php
/**
 * Test Meta API Connection
 * ใช้ทดสอบว่า Token และ API ทำงานถูกต้องหรือไม่
 */

require_once 'config.php';
require_once 'MetaAPI.php';

$pdo = getDBConnection();
$settings = new APISettingsManager($pdo);
$accessToken = $settings->get('meta', 'access_token', '');

echo "<h1>🔧 Meta API Test</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #1a1a2e; color: #fff; }
    .card { background: rgba(255,255,255,0.1); padding: 20px; margin: 10px 0; border-radius: 10px; }
    .success { color: #00f5a0; }
    .error { color: #ff5252; }
    pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    code { color: #00f5a0; }
</style>";

if (empty($accessToken)) {
    echo "<div class='card error'>❌ ไม่พบ Access Token ในฐานข้อมูล กรุณาตั้งค่าใน api_settings.php ก่อน</div>";
    exit;
}

echo "<div class='card'>";
echo "<h3>📌 Access Token (ตัดย่อ)</h3>";
echo "<code>" . substr($accessToken, 0, 50) . "..." . substr($accessToken, -20) . "</code>";
echo "<br><br>ความยาว Token: " . strlen($accessToken) . " ตัวอักษร";
echo "</div>";

// Test 1: Verify Token
echo "<div class='card'>";
echo "<h3>🔍 Test 1: Verify Token</h3>";
try {
    $api = new MetaAPI($accessToken);
    $tokenInfo = $api->verifyToken();
    
    if ($tokenInfo['valid']) {
        echo "<span class='success'>✅ Token ถูกต้อง!</span><br>";
        echo "<pre>" . json_encode($tokenInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<span class='error'>❌ Token ไม่ถูกต้อง</span><br>";
        echo "<pre>" . json_encode($tokenInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span>";
}
echo "</div>";

// Test 2: Get Pages
echo "<div class='card'>";
echo "<h3>📘 Test 2: Get Facebook Pages</h3>";
try {
    $pages = $api->getPages();
    
    if (!empty($pages)) {
        echo "<span class='success'>✅ พบ " . count($pages) . " Pages</span><br>";
        echo "<pre>" . json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<span class='error'>⚠️ ไม่พบ Pages</span><br>";
        echo "ลองเรียก API โดยตรง...";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span>";
}
echo "</div>";

// Test 3: Direct API Call
echo "<div class='card'>";
echo "<h3>🔗 Test 3: Direct API Call to /me</h3>";
try {
    $url = "https://graph.facebook.com/v18.0/me?access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: <code>$httpCode</code><br>";
    echo "<pre>" . json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span>";
}
echo "</div>";

// Test 4: Direct API Call to /me/accounts
echo "<div class='card'>";
echo "<h3>🔗 Test 4: Direct API Call to /me/accounts</h3>";
try {
    $url = "https://graph.facebook.com/v18.0/me/accounts?fields=id,name,access_token&access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: <code>$httpCode</code><br>";
    $data = json_decode($response, true);
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    // ถ้าได้ pages มา ลองดึง posts
    if (!empty($data['data'])) {
        $pageId = $data['data'][0]['id'];
        $pageToken = $data['data'][0]['access_token'] ?? $accessToken;
        
        echo "<h4>🔗 Test 5: Get Posts from Page $pageId</h4>";
        $postsUrl = "https://graph.facebook.com/v18.0/$pageId/posts?fields=id,message,created_time&limit=3&access_token=" . urlencode($pageToken);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $postsUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $postsResponse = curl_exec($ch);
        curl_close($ch);
        
        echo "<pre>" . json_encode(json_decode($postsResponse, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span>";
}
echo "</div>";

// Show saved settings
echo "<div class='card'>";
echo "<h3>💾 Saved Settings in Database</h3>";
$allSettings = $settings->getAll('meta');
echo "<pre>" . json_encode($allSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
echo "</div>";

echo "<br><a href='api_settings.php' style='color: #667eea;'>← กลับไปหน้า API Settings</a>";
?>
