<?php
// เปิดแสดง Error ทั้งหมด (จะได้ไม่เจอหน้าขาว 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================
// 1. ตั้งค่า (เอามาจากหน้าจอดำ Partner Dashboard)
// =========================================================

$apiKey = 'b7cf71a3d859859fdf37eddf3f93f77d'; // Client ID (จากรูปของคุณ)

// ⚠️ ใส่ Client Secret ที่ได้จากหน้าจอดำ (กดรูปตาแล้วก๊อปมา)
$apiSecret = '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'; 

// URL ร้านของคุณ (ไม่ต้องมี https://)
$shop = 'soupth.myshopify.com'; 

// สิทธิ์ที่ต้องการ
$scopes = 'read_products,write_inventory'; 

// Redirect URI (ตรวจจับอัตโนมัติว่าเป็น https://www.weedjai.com/...)
$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]";

// =========================================================

session_start();

// ฟังก์ชันสำหรับเรียก API
function shopify_call($url, $method, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ปิด SSL Verify ชั่วคราวกัน Error
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    return json_decode($response, true);
}

// Step 2: ถ้า Shopify ส่ง Code กลับมา -> เอาไปแลก Token
if (isset($_GET['code'])) {
    
    // ตรวจสอบความปลอดภัย (HMAC) - ข้ามไปก่อนเพื่อความง่ายในการ Test
    
    $url = "https://{$_GET['shop']}/admin/oauth/access_token";
    $payload = [
        'client_id' => $apiKey,
        'client_secret' => $apiSecret,
        'code' => $_GET['code']
    ];

    $result = shopify_call($url, 'POST', $payload);

    if (isset($result['access_token'])) {
        echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'>";
        echo "<h1 style='color:green'>🎉 เย้! ได้ Token แล้ว</h1>";
        echo "<h3>นี่คือ Access Token ของคุณ (shpat_...):</h3>";
        echo "<textarea style='width:100%; max-width:600px; height:100px; font-size:16px; padding:10px;'>" . $result['access_token'] . "</textarea>";
        echo "<p style='color:red; font-weight:bold;'>⚠️ เซฟเก็บไว้เลย! รหัสนี้ใช้ได้ถาวร</p>";
        echo "</div>";
    } else {
        echo "<h1 style='color:red'>❌ แลก Token ไม่สำเร็จ</h1>";
        echo "<pre>"; print_r($result); echo "</pre>";
        echo "<p>เช็ค Client Secret ว่าถูกต้องไหม?</p>";
    }
    exit;
}

// Step 1: สร้างลิงก์ให้กดขออนุญาต
$installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri=" . urlencode($redirectUri);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopify OAuth Helper</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .btn { background: #008060; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 20px; font-weight: bold; }
        .btn:hover { background: #004c3f; }
        .box { background: #f4f6f8; padding: 20px; display: inline-block; border-radius: 8px; border: 1px solid #ccc; text-align: left; }
    </style>
</head>
<body>
    <h1>Shopify Partner OAuth Helper</h1>
    
    <div class="box">
        <p><b>1. ตรวจสอบการตั้งค่าใน Partner Dashboard:</b></p>
        <ul>
            <li>ไปที่หน้า Apps > Inventory Sync > Configuration</li>
            <li>ดูช่อง <b>Allowed redirection URL(s)</b></li>
            <li>ต้องมี URL นี้ใส่ไว้อยู่: <br><code style="background:yellow; color:red;"><?php echo $redirectUri; ?></code></li>
            <li>กด Save</li>
        </ul>
    </div>
    <br><br><br>
    
    <p>เมื่อตั้งค่าข้อ 1 เสร็จแล้ว กดปุ่มนี้:</p>
    <a href="<?php echo $installUrl; ?>" class="btn">👉 คลิกเพื่อขอ Token</a>
</body>
</html>