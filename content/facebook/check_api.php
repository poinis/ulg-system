<?php
/**
 * ไฟล์ตรวจสอบ API - ช่วยแก้ปัญหา JSON
 */

echo "<h1>🔍 ตรวจสอบ API Response</h1>";
echo "<hr>";

// ข้อมูล
$accessToken = 'EAAMQjpNPx9kBQOXvfAyNW3HM6bdflMEg6R6fPcFT21uBLybdNT7O71oOJe3qqfay8DB3gu1qOb2KhSXvsnul4RQavkIEA4LSFyiNelFCjs6k07Rk0Hhj6zk4qYFLwNifYfVO5y8ldFFLBZAZCmSzDPRgkZCUkkUDU5zuIml5ZAqtPPiCmIc6Uf8yZAaLPnlyrlc5P';
$pageId = '489861450880996';

echo "<h2>1. ทดสอบ URL ที่เรียกจาก HTML</h2>";

// สร้าง URL เหมือนที่ HTML จะเรียก
$testUrls = [
    'testConnection' => "api_improved.php?action=testConnection",
    'getMonthlyComparison' => "api_improved.php?action=getMonthlyComparison&months=2025-09,2025-10,2025-11"
];

foreach ($testUrls as $name => $url) {
    echo "<h3>Testing: {$name}</h3>";
    echo "<p><strong>URL:</strong> <code>{$url}</code></p>";
    
    // ตรวจสอบว่าไฟล์มีอยู่ไหม
    $filePath = './api_improved.php';
    if (file_exists($filePath)) {
        echo "<p style='color: green;'>✅ ไฟล์ api_improved.php มีอยู่</p>";
    } else {
        echo "<p style='color: red;'>❌ ไม่พบไฟล์ api_improved.php</p>";
        echo "<p>กรุณาตรวจสอบว่า upload ไฟล์แล้ว และอยู่ในโฟลเดอร์เดียวกัน</p>";
        continue;
    }
    
    // ลองเรียก API
    $fullUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $url;
    echo "<p><strong>Full URL:</strong> <code>{$fullUrl}</code></p>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($fullUrl, false, $context);
    
    if ($response === false) {
        echo "<p style='color: red;'>❌ ไม่สามารถเรียก API ได้</p>";
        echo "<p>ตรวจสอบ:</p>";
        echo "<ul>";
        echo "<li>ไฟล์อยู่ในโฟลเดอร์เดียวกันหรือไม่?</li>";
        echo "<li>Web server รัน PHP ได้หรือไม่?</li>";
        echo "<li>มี PHP errors หรือไม่?</li>";
        echo "</ul>";
    } else {
        // ตรวจสอบว่าเป็น JSON หรือไม่
        $decoded = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<p style='color: green;'>✅ Response เป็น JSON ถูกต้อง</p>";
            echo "<details>";
            echo "<summary>ดู Response (คลิก)</summary>";
            echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 300px; overflow: auto;'>";
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
            echo "</details>";
        } else {
            echo "<p style='color: red;'>❌ Response ไม่ใช่ JSON</p>";
            echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
            echo "<details>";
            echo "<summary>ดู Response ที่ได้ (คลิก)</summary>";
            echo "<pre style='background: #fff3cd; padding: 15px; border-radius: 5px; max-height: 300px; overflow: auto;'>";
            echo htmlspecialchars($response);
            echo "</pre>";
            echo "</details>";
            
            // วิเคราะห์ปัญหา
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
            echo "<p><strong>🔍 วิเคราะห์ปัญหา:</strong></p>";
            
            if (strpos($response, '<?php') !== false || strpos($response, '<?') !== false) {
                echo "<p>❌ PHP code ไม่ถูก execute - Web server ไม่รัน PHP</p>";
                echo "<p><strong>วิธีแก้:</strong></p>";
                echo "<ul>";
                echo "<li>ตรวจสอบว่า Web server (Apache/Nginx) ติดตั้ง PHP แล้ว</li>";
                echo "<li>ตรวจสอบนามสกุลไฟล์เป็น .php</li>";
                echo "<li>ลองเปิดไฟล์ php ไฟล์อื่นดู (เช่น test_connection.php)</li>";
                echo "</ul>";
            } else if (strpos($response, '<html') !== false || strpos($response, '<!DOCTYPE') !== false) {
                echo "<p>❌ ได้ HTML กลับมาแทน JSON</p>";
                echo "<p><strong>สาเหตุที่เป็นไปได้:</strong></p>";
                echo "<ul>";
                echo "<li>มี PHP error ทำให้แสดง error page</li>";
                echo "<li>มี output ก่อน json_encode (echo, print, หรือ whitespace)</li>";
                echo "<li>ถูก redirect ไปหน้าอื่น</li>";
                echo "</ul>";
            } else if (strpos($response, 'error') !== false || strpos($response, 'Error') !== false) {
                echo "<p>⚠️ มี Error message</p>";
            }
            
            echo "</div>";
        }
    }
    
    echo "<hr>";
}

echo "<h2>2. ตรวจสอบ PHP Configuration</h2>";

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

// PHP Version
echo "<tr>";
echo "<td>PHP Version</td>";
echo "<td>" . phpversion() . "</td>";
$phpOk = version_compare(phpversion(), '7.4', '>=');
echo "<td style='color: " . ($phpOk ? 'green' : 'red') . "'>" . ($phpOk ? '✅ OK' : '❌ ต้อง >= 7.4') . "</td>";
echo "</tr>";

// allow_url_fopen
echo "<tr>";
echo "<td>allow_url_fopen</td>";
$allowUrlFopen = ini_get('allow_url_fopen');
echo "<td>" . ($allowUrlFopen ? 'On' : 'Off') . "</td>";
echo "<td style='color: " . ($allowUrlFopen ? 'green' : 'red') . "'>" . ($allowUrlFopen ? '✅ OK' : '❌ ต้องเปิด') . "</td>";
echo "</tr>";

// cURL
echo "<tr>";
echo "<td>cURL Extension</td>";
$hasCurl = function_exists('curl_version');
echo "<td>" . ($hasCurl ? 'Installed' : 'Not Installed') . "</td>";
echo "<td style='color: " . ($hasCurl ? 'green' : 'orange') . "'>" . ($hasCurl ? '✅ OK' : '⚠️ แนะนำให้มี') . "</td>";
echo "</tr>";

// JSON Extension
echo "<tr>";
echo "<td>JSON Extension</td>";
$hasJson = function_exists('json_encode');
echo "<td>" . ($hasJson ? 'Installed' : 'Not Installed') . "</td>";
echo "<td style='color: " . ($hasJson ? 'green' : 'red') . "'>" . ($hasJson ? '✅ OK' : '❌ จำเป็น') . "</td>";
echo "</tr>";

// Error Display
echo "<tr>";
echo "<td>display_errors</td>";
$displayErrors = ini_get('display_errors');
echo "<td>" . ($displayErrors ? 'On' : 'Off') . "</td>";
echo "<td style='color: orange'>⚠️ ควรปิดใน production</td>";
echo "</tr>";

echo "</table>";

echo "<h2>3. ทดสอบ API โดยตรง</h2>";
echo "<p>ลองเรียก API โดยตรงจาก PHP:</p>";

// นำเข้า Class
if (file_exists('./facebook_insights.php')) {
    require_once './facebook_insights.php';
    
    try {
        $fb = new FacebookInsights($accessToken, $pageId);
        
        // ทดสอบดึงข้อมูลเพจ
        $pageUrl = "https://graph.facebook.com/v21.0/{$pageId}?fields=name,fan_count&access_token={$accessToken}";
        $pageResponse = @file_get_contents($pageUrl);
        
        if ($pageResponse) {
            $pageData = json_decode($pageResponse, true);
            
            if (isset($pageData['error'])) {
                echo "<p style='color: red;'>❌ Facebook API Error: " . $pageData['error']['message'] . "</p>";
            } else {
                echo "<p style='color: green;'>✅ สามารถเชื่อมต่อ Facebook API ได้</p>";
                echo "<p>เพจ: <strong>" . $pageData['name'] . "</strong></p>";
                echo "<p>Fans: <strong>" . number_format($pageData['fan_count']) . "</strong></p>";
            }
        } else {
            echo "<p style='color: red;'>❌ ไม่สามารถเชื่อมต่อ Facebook API</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ ไม่พบไฟล์ facebook_insights.php</p>";
}

echo "<hr>";

echo "<h2>4. วิธีแก้ไขปัญหา</h2>";

echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 5px;'>";
echo "<h3>📋 Checklist:</h3>";
echo "<ol>";
echo "<li><strong>ตรวจสอบโครงสร้างไฟล์:</strong>";
echo "<pre>
/public_html/
├── facebook_insights.php
├── api_improved.php
├── monthly_report.html
└── test_connection.php (ไฟล์นี้)
</pre>";
echo "</li>";

echo "<li><strong>ตรวจสอบไฟล์ api_improved.php:</strong>";
echo "<ul>";
echo "<li>ไม่มี whitespace หรือ BOM ก่อน &lt;?php</li>";
echo "<li>ไม่มี echo หรือ print ก่อน json output</li>";
echo "<li>มี header('Content-Type: application/json') ที่บรรทัดแรกๆ</li>";
echo "</ul>";
echo "</li>";

echo "<li><strong>แก้ไข monthly_report.html:</strong>";
echo "<pre>
// ถ้าไฟล์อยู่โฟลเดอร์เดียวกัน
const API_URL = './api_improved.php';

// หรือใช้ full path
const API_URL = 'http://your-domain.com/api_improved.php';
</pre>";
echo "</li>";

echo "<li><strong>ทดสอบเรียก API ด้วย Browser:</strong>";
echo "<br>ลองเปิด URL นี้ในเบราว์เซอร์:";
$testUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api_improved.php?action=testConnection";
echo "<br><a href='{$testUrl}' target='_blank'>{$testUrl}</a>";
echo "<br>ควรเห็น JSON ไม่ใช่ HTML";
echo "</li>";

echo "</ol>";
echo "</div>";

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
        margin-top: 20px;
    }
    
    pre {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        border-left: 4px solid #1877f2;
    }
    
    code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
    
    table {
        width: 100%;
        margin: 20px 0;
        background: white;
    }
    
    th {
        background: #1877f2;
        color: white;
        padding: 10px;
        text-align: left;
    }
    
    td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }
    
    details {
        margin: 10px 0;
    }
    
    summary {
        cursor: pointer;
        padding: 10px;
        background: #e9ecef;
        border-radius: 5px;
        font-weight: 600;
    }
    
    summary:hover {
        background: #dee2e6;
    }
</style>