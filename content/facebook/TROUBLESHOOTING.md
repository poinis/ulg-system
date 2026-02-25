# คู่มือแก้ไขปัญหา - Facebook Insights API

## 🔧 ปัญหาที่พบบ่อยและวิธีแก้ไข

### 1. ไม่สามารถโหลดข้อมูลเมื่อเปลี่ยนเดือน

#### สาเหตุ:
- Facebook API มีข้อจำกัดในการดึงข้อมูลย้อนหลัง
- Access Token หมดอายุ
- Permissions ไม่ครบถ้วน
- ไม่มีข้อมูลในช่วงเดือนนั้น

#### วิธีแก้ไข:

**ขั้นตอนที่ 1: ตรวจสอบ Access Token**

```php
<?php
// ทดสอบว่า Token ยังใช้งานได้อยู่หรือไม่
$accessToken = 'YOUR_ACCESS_TOKEN';
$url = "https://graph.facebook.com/v21.0/me?access_token={$accessToken}";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (isset($data['error'])) {
    echo "Token หมดอายุหรือไม่ถูกต้อง: " . $data['error']['message'];
} else {
    echo "Token ยังใช้งานได้";
}
?>
```

**ขั้นตอนที่ 2: ตรวจสอบ Permissions**

```php
<?php
$accessToken = 'YOUR_ACCESS_TOKEN';
$url = "https://graph.facebook.com/v21.0/me/permissions?access_token={$accessToken}";

$response = file_get_contents($url);
$data = json_decode($response, true);

echo "Permissions ที่มี:\n";
foreach ($data['data'] as $permission) {
    if ($permission['status'] === 'granted') {
        echo "✓ " . $permission['permission'] . "\n";
    }
}
?>
```

**ขั้นตอนที่ 3: ใช้ Long-lived Token แทน Short-lived**

Short-lived token จะหมดอายุใน 1-2 ชั่วโมง ให้แปลงเป็น Long-lived (60 วัน):

```php
<?php
function getLongLivedToken($appId, $appSecret, $shortToken) {
    $url = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
        'grant_type' => 'fb_exchange_token',
        'client_id' => $appId,
        'client_secret' => $appSecret,
        'fb_exchange_token' => $shortToken
    ]);
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    return $data['access_token'];
}

$longLivedToken = getLongLivedToken('YOUR_APP_ID', 'YOUR_APP_SECRET', 'SHORT_TOKEN');
echo "Long-lived Token: " . $longLivedToken;
?>
```

**ขั้นตอนที่ 4: ใช้ Page Access Token (Never Expire)**

Page Access Token จะไม่หมดอายุและเหมาะสำหรับ automated system:

```php
<?php
function getPageAccessToken($userToken) {
    $url = "https://graph.facebook.com/v21.0/me/accounts?access_token={$userToken}";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    $pages = [];
    foreach ($data['data'] as $page) {
        $pages[] = [
            'name' => $page['name'],
            'id' => $page['id'],
            'access_token' => $page['access_token']
        ];
    }
    
    return $pages;
}

$pages = getPageAccessToken('YOUR_USER_TOKEN');
print_r($pages);
?>
```

---

### 2. Error: "Unsupported get request"

#### สาเหตุ:
- ใช้ User Access Token แทน Page Access Token
- Metric ที่ขอไม่มีอยู่
- Permission ไม่ครบ

#### วิธีแก้ไข:

```php
<?php
// ตรวจสอบว่าใช้ Page Token หรือ User Token
$token = 'YOUR_TOKEN';
$url = "https://graph.facebook.com/v21.0/me?access_token={$token}";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (isset($data['category'])) {
    echo "นี่คือ Page Token ✓";
} else if (isset($data['name'])) {
    echo "นี่คือ User Token ✗ กรุณาใช้ Page Token";
}
?>
```

---

### 3. Error: "Please reduce the amount of data you're asking for"

#### สาเหตุ:
- ขอข้อมูลมากเกินไปในครั้งเดียว
- ช่วงวันที่ยาวเกินไป

#### วิธีแก้ไข:

แบ่งการดึงข้อมูลเป็นช่วงๆ:

```php
<?php
function getInsightsByChunks($fb, $startDate, $endDate, $chunkDays = 30) {
    $results = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    
    while ($current <= $end) {
        $chunkEnd = min($current + ($chunkDays * 86400), $end);
        
        try {
            $data = $fb->getReach($current, $chunkEnd);
            $results[] = $data;
        } catch (Exception $e) {
            echo "Error getting data for " . date('Y-m-d', $current) . ": " . $e->getMessage() . "\n";
        }
        
        $current = $chunkEnd + 86400; // เพิ่ม 1 วัน
    }
    
    return $results;
}

// ใช้งาน
$data = getInsightsByChunks($fb, '2025-01-01', '2025-12-31', 30);
?>
```

---

### 4. ข้อมูลไม่ตรงกับที่เห็นใน Facebook

#### สาเหตุ:
- Facebook ใช้เวลาในการประมวลผลข้อมูล
- Time zone ไม่ตรงกัน
- Metric ที่เลือกไม่ถูกต้อง

#### วิธีแก้ไข:

**ตั้งค่า Timezone:**

```php
<?php
// ตั้งค่า timezone เป็นไทย
date_default_timezone_set('Asia/Bangkok');

// หรือใช้ UTC และแปลงเอง
$timestamp = strtotime('2025-10-01 00:00:00 UTC');
?>
```

**รอให้ Facebook ประมวลผลข้อมูล:**

```php
<?php
// ดึงข้อมูลล่าช้า 1-2 วัน
$until = strtotime('-2 days');
$since = strtotime('-32 days');

$data = $fb->getReach($since, $until);
?>
```

---

### 5. Rate Limiting - "User request limit reached"

#### สาเหตุ:
- เรียก API บ่อยเกินไป (มากกว่า 200 calls/hour)

#### วิธีแก้ไข:

**ใช้ Cache:**

```php
<?php
class CachedFacebookInsights extends FacebookInsights {
    private $cacheDir = './cache/';
    private $cacheTTL = 3600; // 1 ชั่วโมง
    
    public function __construct($accessToken, $pageId) {
        parent::__construct($accessToken, $pageId);
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function getReach($since, $until) {
        $cacheKey = "reach_{$since}_{$until}";
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        
        // ตรวจสอบ cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTTL)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        // ดึงข้อมูลจาก API
        $data = parent::getReach($since, $until);
        
        // บันทึก cache
        file_put_contents($cacheFile, json_encode($data));
        
        return $data;
    }
}

// ใช้งาน
$fb = new CachedFacebookInsights(FB_ACCESS_TOKEN, FB_PAGE_ID);
?>
```

**ใช้ Batch Requests:**

```php
<?php
function batchRequest($accessToken, $requests) {
    $batchData = [];
    
    foreach ($requests as $i => $request) {
        $batchData[] = [
            'method' => 'GET',
            'relative_url' => $request
        ];
    }
    
    $url = "https://graph.facebook.com/v21.0/?access_token={$accessToken}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'batch' => json_encode($batchData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// ตัวอย่างการใช้งาน
$requests = [
    "me?fields=name,fan_count",
    "{$pageId}/insights?metric=page_impressions&since={$since}&until={$until}",
    "{$pageId}/posts?limit=10"
];

$results = batchRequest($accessToken, $requests);
?>
```

---

### 6. ไม่มีข้อมูลบางเดือน (Empty Data)

#### สาเหตุ:
- เพจยังไม่มีข้อมูลในช่วงนั้น
- Facebook ไม่เก็บข้อมูลบางประเภท
- เพจถูกสร้างหลังจากช่วงเวลานั้น

#### วิธีแก้ไข:

```php
<?php
function getAvailableDataRange($fb, $pageId) {
    // ดึงข้อมูลการสร้างเพจ
    $url = "https://graph.facebook.com/v21.0/{$pageId}?fields=created_time&access_token=" . FB_ACCESS_TOKEN;
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    $createdTime = strtotime($data['created_time']);
    $now = time();
    
    echo "เพจถูกสร้างเมื่อ: " . date('Y-m-d', $createdTime) . "\n";
    echo "สามารถดึงข้อมูลได้ตั้งแต่: " . date('Y-m-d', $createdTime) . "\n";
    echo "จนถึงวันนี้: " . date('Y-m-d', $now) . "\n";
    
    return [
        'earliest' => $createdTime,
        'latest' => $now
    ];
}

$range = getAvailableDataRange($fb, FB_PAGE_ID);
?>
```

---

## 📝 Debug Mode

เปิดใช้งาน Debug Mode เพื่อดูรายละเอียด:

```php
<?php
// เพิ่มในไฟล์ api_improved.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', './logs/api_errors.log');

// เพิ่มการ log
function logDebug($message) {
    $logFile = './logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// ใช้งาน
logDebug("Starting API call: action=" . $_GET['action']);
?>
```

---

## 🔍 การทดสอบ API ด้วย cURL

```bash
# ทดสอบการเชื่อมต่อ
curl "http://your-domain.com/api_improved.php?action=testConnection"

# ดึงข้อมูล Insights
curl "http://your-domain.com/api_improved.php?action=getInsights&start=2025-10-01&end=2025-10-31"

# ดึงข้อมูลรายเดือน
curl "http://your-domain.com/api_improved.php?action=getMonthlyComparison&months=2025-09,2025-10,2025-11"
```

---

## 📞 ขอความช่วยเหลือ

หากยังแก้ไขปัญหาไม่ได้:

1. ตรวจสอบ Facebook Developer Console: https://developers.facebook.com/
2. ดู API Error Codes: https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling
3. ใช้ Graph API Explorer: https://developers.facebook.com/tools/explorer/
4. ตรวจสอบ Access Token Debugger: https://developers.facebook.com/tools/debug/accesstoken/

---

## ✅ Checklist การแก้ปัญหา

- [ ] ตรวจสอบ Access Token ยังใช้งานได้
- [ ] ตรวจสอบ Permissions ครบถ้วน
- [ ] ใช้ Page Access Token (ไม่ใช่ User Token)
- [ ] ตรวจสอบช่วงวันที่ถูกต้อง
- [ ] ตรวจสอบว่ามีข้อมูลในช่วงนั้น
- [ ] ตรวจสอบ Rate Limiting
- [ ] ลอง Cache ข้อมูล
- [ ] ตรวจสอบ Error Log
- [ ] ทดสอบด้วย Graph API Explorer
- [ ] อ่าน Error Message ให้ละเอียด
