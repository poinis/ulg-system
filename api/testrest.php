<?php
// --- CONFIGURATION ---
$base_url = 'https://90643827-retail-ondemand.cegid.cloud/Y2';
$folder_id = '90643827_001_PROD';
$username = '90643827_001_PROD\\frt'; // ใส่ \ สองตัว
$password = 'adgjm';

// เงื่อนไขที่เราจะดึง (อ้างอิงจากไฟล์ Excel ล่าสุดของคุณ)
$targetStore = '11010'; // สาขา PRONTO CENTRAL LARDPRAO
$targetDate = '2026-01-31'; // วันที่มีข้อมูลใน Excel

echo "<h2>🚀 Testing API Fetch for Store: $targetStore, Date: $targetDate</h2>";

// --- STEP 1: สร้าง Filter String (หัวใจสำคัญของการแก้ Error 400) ---
// ต้องเว้นวรรคและใช้ Single Quote (') ให้ถูกเป๊ะๆ
$filter = "storeId eq '$targetStore' and date eq datetime'{$targetDate}T00:00:00'";

// Encode Filter ให้เป็น URL ที่ถูกต้อง (เปลี่ยนช่องว่างเป็น %20 ฯลฯ)
$encodedFilter = urlencode($filter);

$apiUrl = "$base_url/$folder_id/api/receipts/v2?\$filter=$encodedFilter";

echo "<b>URL ที่ยิงไป:</b> $apiUrl <br><hr>";

// --- STEP 2: ส่ง Request ---
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- STEP 3: ดูผลลัพธ์ ---
if ($httpCode == 200) {
    $data = json_decode($response, true);
    $items = $data['content'] ?? $data['value'] ?? [];
    $count = count($items);
    
    echo "<h3 style='color:green'>✅ สำเร็จ! (HTTP 200)</h3>";
    echo "<b>เจอข้อมูลจำนวน:</b> $count บิล<br>";
    
    if ($count > 0) {
        echo "<pre style='background:#f0f0f0; padding:10px;'>";
        // แสดงบิลแรกเทียบกับ Excel ของคุณ
        print_r($items[0]);
        echo "</pre>";
    }
} else {
    echo "<h3 style='color:red'>❌ ยังไม่ผ่าน (HTTP $httpCode)</h3>";
    echo "<b>Response:</b> " . htmlspecialchars($response);
}
?>