# Facebook Insights API Integration

ระบบดึงข้อมูลรายงาน Facebook Page Insights ด้วย PHP และแสดงผลด้วย Dashboard แบบ Real-time

## 📁 ไฟล์ที่รวมอยู่ในโปรเจค

1. **facebook_insights.php** - Class หลักสำหรับดึงข้อมูลจาก Facebook API
2. **api.php** - API Endpoint สำหรับเชื่อมต่อ Dashboard
3. **facebook_insights_dashboard.html** - Dashboard แสดงผลข้อมูลพร้อมกราฟ
4. **facebook_access_token_guide.md** - คู่มือการขอ Access Token

## 🚀 การติดตั้ง

### 1. ความต้องการของระบบ

- PHP 7.4 ขึ้นไป
- cURL extension เปิดใช้งาน
- Web Server (Apache/Nginx)
- Facebook Page และ Facebook App

### 2. ขั้นตอนการติดตั้ง

```bash
# 1. Clone หรือ Download ไฟล์ทั้งหมด
# 2. วางไฟล์ในโฟลเดอร์ web root
# 3. แก้ไขค่าการตั้งค่า
```

### 3. การตั้งค่า

แก้ไขไฟล์ `api.php`:

```php
define('FB_ACCESS_TOKEN', 'YOUR_PAGE_ACCESS_TOKEN_HERE');
define('FB_PAGE_ID', 'YOUR_PAGE_ID_HERE');
```

## 📊 ข้อมูลที่สามารถดึงได้

### Metrics ที่รองรับ

| Metric | คำอธิบาย |
|--------|----------|
| **page_impressions** | จำนวนครั้งที่เนื้อหาแสดง |
| **page_impressions_unique** | จำนวนคนที่เห็นเนื้อหา (Reach) |
| **page_fans** | จำนวนผู้ติดตาม |
| **page_fan_adds** | ผู้ติดตามเพิ่มขึ้น |
| **page_fan_removes** | ผู้ติดตามลดลง |
| **page_post_engagements** | การโต้ตอบรวม |
| **page_engaged_users** | จำนวนคนที่มีการโต้ตอบ |
| **page_consumptions** | การคลิกบนเนื้อหา |
| **page_negative_feedback** | Feedback เชิงลบ |

## 💻 การใช้งาน

### 1. ใช้งาน Class โดยตรง

```php
<?php
require_once 'facebook_insights.php';

$fb = new FacebookInsights('YOUR_ACCESS_TOKEN', 'YOUR_PAGE_ID');

// ดึงข้อมูล Reach
$since = strtotime('2025-10-01');
$until = strtotime('2025-10-31');
$data = $fb->getReach($since, $until);

print_r($data);
?>
```

### 2. ใช้งานผ่าน API Endpoint

```javascript
// ดึงข้อมูล Insights
fetch('api.php?action=getInsights&start=2025-10-01&end=2025-10-31')
    .then(response => response.json())
    .then(data => console.log(data));

// ดึงรายการโพสต์
fetch('api.php?action=getPosts&limit=25')
    .then(response => response.json())
    .then(data => console.log(data));

// ดึงข้อมูลสรุป
fetch('api.php?action=getSummary&days=30')
    .then(response => response.json())
    .then(data => console.log(data));
```

### 3. ใช้งาน Dashboard

1. เปิดไฟล์ `facebook_insights_dashboard.html` ในเบราว์เซอร์
2. เลือกช่วงวันที่ที่ต้องการ
3. คลิกปุ่ม "โหลดข้อมูล"
4. ดูผลลัพธ์ในรูปแบบกราฟและตารางสถิติ

## 🔧 ฟีเจอร์ขั้นสูง

### 1. ดึงข้อมูลโพสต์พร้อม Insights

```php
// ดึงโพสต์ล่าสุด
$posts = $fb->getPosts(10);

// วนลูปดูรายละเอียดแต่ละโพสต์
foreach ($posts['data'] as $post) {
    $postId = $post['id'];
    $insights = $fb->getPostInsights($postId);
    
    echo "Post: {$post['message']}\n";
    echo "Reach: {$insights['data'][0]['values'][0]['value']}\n";
}
```

### 2. สร้างรายงานแบบกำหนดเอง

```php
function createCustomReport($fb, $since, $until) {
    $data = [
        'reach' => $fb->getReach($since, $until),
        'followers' => $fb->getFollowers($since, $until),
        'engagement' => $fb->getEngagement($since, $until)
    ];
    
    // ประมวลผลและสร้างรายงาน
    return processReport($data);
}
```

### 3. Export ข้อมูลเป็น CSV

```php
function exportToCSV($data, $filename = 'insights.csv') {
    $fp = fopen($filename, 'w');
    
    // Header
    fputcsv($fp, ['Date', 'Reach', 'Followers', 'Engagement']);
    
    // Data
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
}
```

## 🔒 ความปลอดภัย

### 1. เก็บ Access Token อย่างปลอดภัย

```php
// ไม่ควร hard-code ใน code
// ใช้ environment variables หรือ config file

// วิธีที่แนะนำ:
$accessToken = getenv('FB_ACCESS_TOKEN');
$pageId = getenv('FB_PAGE_ID');
```

### 2. จำกัดการเข้าถึง API

```php
// เพิ่ม authentication
function checkAuth() {
    $apiKey = $_GET['api_key'] ?? '';
    if ($apiKey !== 'YOUR_SECRET_API_KEY') {
        sendError('Unauthorized', 401);
    }
}
```

### 3. ป้องกัน Rate Limiting

```php
// Cache ข้อมูลเพื่อลด API calls
function getCachedData($key, $ttl = 3600) {
    $cacheFile = "cache/{$key}.json";
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    return false;
}

function setCachedData($key, $data) {
    file_put_contents("cache/{$key}.json", json_encode($data));
}
```

## ⚠️ ข้อจำกัดและข้อควรระวัง

1. **Rate Limits**: Facebook จำกัด 200 calls/hour ต่อ User
2. **Token Expiration**: Page Access Token ควรเป็น Long-lived token
3. **Permissions**: ต้องมี `pages_read_engagement` permission
4. **Historical Data**: บางข้อมูลอาจมีเฉพาะย้อนหลัง 2 ปี

## 📈 ตัวอย่างผลลัพธ์

### Response จาก API

```json
{
    "success": true,
    "period": {
        "start": "2025-10-01",
        "end": "2025-10-31"
    },
    "reach": {
        "total": 160000,
        "average": 5161.29,
        "change": 116.9,
        "daily": [50000, 55000, 60000, ...]
    },
    "followers": {
        "total": 86000,
        "average": 85800,
        "change": 66.2,
        "daily": [85000, 85200, 85500, ...]
    }
}
```

## 🐛 การแก้ไขปัญหา

### ปัญหาที่พบบ่อย

**1. Error: Invalid OAuth access token**
- ตรวจสอบ Access Token ว่าหมดอายุหรือไม่
- ใช้ Long-lived token แทน Short-lived token

**2. Error: Unsupported get request**
- ตรวจสอบ Permissions
- ตรวจสอบว่าใช้ Page Access Token ไม่ใช่ User Access Token

**3. Empty data returned**
- เช็คว่าเพจมีข้อมูลในช่วงวันที่ที่เลือก
- ตรวจสอบ metric name ว่าถูกต้อง

### Debug Mode

```php
// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// แสดงข้อมูล request
$fb = new FacebookInsights($token, $pageId);
$data = $fb->getReach($since, $until);
echo "<pre>";
print_r($data);
echo "</pre>";
```

## 📚 เอกสารเพิ่มเติม

- [Facebook Graph API Documentation](https://developers.facebook.com/docs/graph-api)
- [Page Insights Reference](https://developers.facebook.com/docs/graph-api/reference/insights)
- [Marketing API](https://developers.facebook.com/docs/marketing-apis)

## 📝 License

MIT License - ใช้งานได้อย่างอิสระ

## 👨‍💻 ผู้พัฒนา

สร้างโดย Claude AI สำหรับชุมชน PHP Developer

## 🤝 การสนับสนุน

หากพบปัญหาหรือต้องการเพิ่มฟีเจอร์:
1. ตรวจสอบเอกสารก่อน
2. ทดสอบใน Graph API Explorer
3. ตรวจสอบ Facebook API Changelog

---

**หมายเหตุ**: อย่าลืมปกป้อง Access Token และไม่ควร commit ลง Git โดยตรง
