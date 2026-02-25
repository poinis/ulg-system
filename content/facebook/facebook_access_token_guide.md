# วิธีการขอ Access Token จาก Facebook

## ขั้นตอนที่ 1: สร้าง Facebook App

1. ไปที่ https://developers.facebook.com
2. คลิก "My Apps" > "Create App"
3. เลือกประเภท "Business"
4. กรอกข้อมูล:
   - App Name: ชื่อแอปของคุณ
   - Contact Email: อีเมลติดต่อ
5. คลิก "Create App"

## ขั้นตอนที่ 2: เพิ่ม Facebook Login

1. ในหน้า Dashboard ของ App
2. คลิก "Add Product"
3. เลือก "Facebook Login" > "Set Up"
4. กำหนด Valid OAuth Redirect URIs (ถ้าจำเป็น)

## ขั้นตอนที่ 3: ขอ Access Token

### วิธีที่ 1: ใช้ Graph API Explorer (สำหรับทดสอบ)

1. ไปที่ https://developers.facebook.com/tools/explorer/
2. เลือก App ของคุณจากเมนู dropdown
3. คลิก "Generate Access Token"
4. เลือก Permissions ที่ต้องการ:
   - `pages_show_list` - ดูรายการเพจ
   - `pages_read_engagement` - อ่านข้อมูล Insights
   - `pages_read_user_content` - อ่านเนื้อหา
5. คัดลอก Access Token ที่ได้

### วิธีที่ 2: ใช้ PHP สำหรับ OAuth Flow

```php
<?php
// กำหนดค่า
$app_id = 'YOUR_APP_ID';
$app_secret = 'YOUR_APP_SECRET';
$redirect_uri = 'https://your-domain.com/callback.php';

// ขั้นตอนที่ 1: สร้าง Login URL
$permissions = ['pages_show_list', 'pages_read_engagement'];
$login_url = "https://www.facebook.com/v21.0/dialog/oauth?" . http_build_query([
    'client_id' => $app_id,
    'redirect_uri' => $redirect_uri,
    'scope' => implode(',', $permissions),
    'response_type' => 'code'
]);

echo '<a href="' . $login_url . '">Login with Facebook</a>';
?>
```

**callback.php:**
```php
<?php
$app_id = 'YOUR_APP_ID';
$app_secret = 'YOUR_APP_SECRET';
$redirect_uri = 'https://your-domain.com/callback.php';

// รับ code จาก Facebook
$code = $_GET['code'];

// ขั้นตอนที่ 2: แลก code เป็น Access Token
$token_url = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
    'client_id' => $app_id,
    'client_secret' => $app_secret,
    'redirect_uri' => $redirect_uri,
    'code' => $code
]);

$response = file_get_contents($token_url);
$token_data = json_decode($response, true);

$access_token = $token_data['access_token'];

echo "Access Token: " . $access_token;
?>
```

## ขั้นตอนที่ 4: แปลง Short-lived Token เป็น Long-lived Token

Short-lived token จะหมดอายุใน 1-2 ชั่วโมง  
Long-lived token จะอยู่ได้ 60 วัน

```php
<?php
$app_id = 'YOUR_APP_ID';
$app_secret = 'YOUR_APP_SECRET';
$short_token = 'YOUR_SHORT_LIVED_TOKEN';

$url = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
    'grant_type' => 'fb_exchange_token',
    'client_id' => $app_id,
    'client_secret' => $app_secret,
    'fb_exchange_token' => $short_token
]);

$response = file_get_contents($url);
$data = json_decode($response, true);

$long_lived_token = $data['access_token'];

echo "Long-lived Token: " . $long_lived_token;
?>
```

## ขั้นตอนที่ 5: รับ Page Access Token

Page Access Token จะไม่หมดอายุและใช้เข้าถึงข้อมูลเพจได้

```php
<?php
$user_access_token = 'YOUR_USER_ACCESS_TOKEN';

// ดึงรายการเพจ
$url = "https://graph.facebook.com/v21.0/me/accounts?access_token={$user_access_token}";
$response = file_get_contents($url);
$data = json_decode($response, true);

foreach ($data['data'] as $page) {
    echo "Page Name: {$page['name']}\n";
    echo "Page ID: {$page['id']}\n";
    echo "Page Access Token: {$page['access_token']}\n";
    echo "---\n";
}
?>
```

## Permissions ที่จำเป็น

| Permission | รายละเอียด |
|-----------|-----------|
| `pages_show_list` | ดูรายการเพจที่จัดการ |
| `pages_read_engagement` | อ่านข้อมูล Insights และ Engagement |
| `pages_read_user_content` | อ่านเนื้อหาที่โพสต์บนเพจ |
| `read_insights` | อ่านข้อมูล Analytics (เพิ่มเติม) |

## หมายเหตุสำคัญ

1. **Access Token จะหมดอายุ**: ต้องมีระบบ refresh token อัตโนมัติ
2. **Rate Limits**: Facebook จำกัดจำนวน API calls
3. **Permissions**: ต้องผ่านการ Review จาก Facebook สำหรับ production
4. **Page Access Token**: แนะนำให้ใช้สำหรับ automated systems

## การตรวจสอบ Token

```php
<?php
$access_token = 'YOUR_ACCESS_TOKEN';

$url = "https://graph.facebook.com/v21.0/me?access_token={$access_token}";
$response = file_get_contents($url);
$data = json_decode($response, true);

print_r($data);
?>
```

## การ Debug Token

ใช้ Access Token Debugger:
https://developers.facebook.com/tools/debug/accesstoken/
