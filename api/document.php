<?php
// api_docs.php
session_start();
require_once 'config.php';

// ⚠️ ส่วนความปลอดภัย: เลือกเปิด/ปิดตามต้องการ
// ถ้าต้องการให้ "ทุกคน" ดูคู่มือได้ (เช่น ส่ง Link ให้ Dev ภายนอก) -> ให้ Comment บรรทัดข้างล่างนี้ทิ้งไป
 if (!isset($_SESSION['id'])) { header('Location: login.php'); exit; }

// กำหนด Base URL อัตโนมัติ (หรือจะแก้เป็น String ตรงๆ ก็ได้ เช่น "https://www.weedjai.com/api/")
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// ปรับ Path ให้ชี้ไปที่ folder api (สมมติว่าไฟล์นี้อยู่ที่ /report-new/ และ api อยู่ที่ /report-new/api/ หรือ /api/)
// ถ้า api อยู่ที่ root/api ให้ใช้:
$api_base_url = "$protocol://$host/api/"; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weedjai API Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0288d1;
            --bg-light: #f8f9fa;
            --border: #e0e0e0;
            --code-bg: #282c34;
            --code-text: #abb2bf;
        }
        body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; margin: 0; background: var(--bg-light); }
        
        /* Layout */
        .container { max-width: 1000px; margin: 0 auto; background: white; min-height: 100vh; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
        
        /* Header */
        header { background: var(--primary); color: white; padding: 40px; }
        h1 { margin: 0; font-size: 28px; }
        .subtitle { opacity: 0.8; font-size: 14px; margin-top: 5px; }
        
        /* Content */
        .content { padding: 40px; }
        h2 { color: var(--primary); border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 40px; }
        h3 { color: #444; margin-top: 30px; font-size: 18px; }
        
        /* Boxes */
        .endpoint-box { background: #e3f2fd; border: 1px solid #90caf9; padding: 15px; border-radius: 6px; font-family: monospace; color: #0d47a1; margin: 15px 0; }
        .method { background: #2e7d32; color: white; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; margin-right: 10px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px; }
        th, td { border: 1px solid var(--border); padding: 12px; text-align: left; }
        th { background: #f5f5f5; color: #555; }
        
        /* Code Blocks */
        pre { background: var(--code-bg); color: var(--code-text); padding: 20px; border-radius: 8px; overflow-x: auto; font-family: 'Consolas', monospace; font-size: 13px; }
        code { background: #eee; padding: 2px 6px; border-radius: 4px; color: #c62828; font-family: monospace; }
        
        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .req { background: #ffebee; color: #c62828; }
        .opt { background: #e8f5e9; color: #2e7d32; }
        .btn { padding: 10px 20px; background: #0288d1; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        /* Footer */
        footer { padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
 
<div class="container">
    <header>
        <h1>📖 คู่มือการใช้งาน API (Weedjai System)</h1>
        <div class="subtitle">เวอร์ชัน 1.1 | อัปเดตล่าสุด: <?php echo date('d/m/Y'); ?></div>
    </header><br>
   
    <div class="content">

    <div class="nav-menu">
        <a href="../dashboard.php" class="btn" style="background:#666;">← กลับหน้าหลัก</a> 
        <a href="manage_api_keys.php" class="btn" style="background:#666;">สร้าง api_key</a>
    </div>

        <section>
            <h2>1. ข้อมูลทั่วไป</h2>
            <p>API นี้ใช้สำหรับเชื่อมต่อข้อมูลยอดขายและบิลสินค้า เพื่อนำไปใช้งานต่อในระบบบัญชีหรือ Dashboard ภายนอก</p>
            <p><strong>Base URL:</strong> <code><?php echo $api_base_url; ?></code></p>
        </section>

        <section>
            <h2>2. การยืนยันตัวตน (Authentication)</h2>
            <p>ต้องแนบ Parameter <code>api_key</code> ไปกับทุก Request เพื่อความปลอดภัย</p>
            <table>
                <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>api_key</code></td><td>String</td><td><span class="badge req">Yes</span></td><td>รหัสผ่าน API (ขอได้จาก Admin)</td></tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2>3. รายละเอียด Endpoints</h2>

            <h3>3.1 ดึงสรุปยอดขายรายสาขา</h3>
            <p>ดึงยอดขายรวม แยกตามสาขา ในช่วงวันที่กำหนด</p>
            <div class="endpoint-box"><span class="method">GET</span> /api_sales_summary.php</div>
            <table>
                <thead><tr><th>Parameter</th><th>Required</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>api_key</code></td><td><span class="badge req">Yes</span></td><td>-</td><td>API Key</td></tr>
                    <tr><td><code>date_from</code></td><td><span class="badge opt">No</span></td><td>Today</td><td>วันที่เริ่มต้น (YYYY-MM-DD)</td></tr>
                    <tr><td><code>date_to</code></td><td><span class="badge opt">No</span></td><td>Today</td><td>วันที่สิ้นสุด (YYYY-MM-DD)</td></tr>
                </tbody>
            </table>
            <strong>ตัวอย่าง Response:</strong>
<pre>
{
  "status": "success",
  "period": { "from": "2026-01-27", "to": "2026-01-27" },
  "count": 2,
  "data": [
    {
      "store_code": "001",
      "store_name": "Shop A",
      "bill_count": 15,
      "total_sales": 25000.00
    },
    ...
  ]
}
</pre>

            <hr style="border:0; border-top:1px dashed #ddd; margin:30px 0;">

            <h3>3.2 ดึงรายละเอียดบิล</h3>
            <p>ดึงรายการสินค้าในบิล หรือ ดูรายชื่อบิลทั้งหมดใน 1 วัน</p>
            <div class="endpoint-box"><span class="method">GET</span> /api_transaction.php</div>
            <table>
                <thead><tr><th>Parameter</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>api_key</code></td><td><span class="badge req">Yes</span></td><td>API Key</td></tr>
                    <tr><td><code>ref</code></td><td><span class="badge opt">Conditional</span></td><td>เลขที่บิลที่ต้องการดูสินค้า (เช่น SO-xxx)</td></tr>
                    <tr><td><code>date</code></td><td><span class="badge opt">Conditional</span></td><td>วันที่ต้องการดูรายการบิลทั้งหมด</td></tr>
                </tbody>
            </table>
            
            <div style="background:#fff3cd; color:#856404; padding:10px; border-radius:4px; font-size:13px;">⚠️ หมายเหตุ: ต้องระบุอย่างน้อย 1 ค่า (ref หรือ date)</div>
            <strong>ตัวอย่าง Response:</strong>
<pre>
{
  "status": "success",
  "data": [
    {
      "internal_ref": "SO-202601-001",
      "line_barcode": "885xxxx",
      "item_description": "สินค้า A",
      "qty": 2,
      "base_price": 100.00,
      "tax_incl_total": 200.00
    }
  ]
}
</pre>
            <hr style="border:0; border-top:1px dashed #ddd; margin:30px 0;">

            <h3>3.3 ดึงข้อมูลดิบ (Raw Data)</h3>
            <p>ดึงข้อมูลทุก Transaction อย่างละเอียด (แบ่งหน้าทีละ 1,000 รายการ)</p>
            <div class="endpoint-box"><span class="method">GET</span> /api_daily_sales_raw.php</div>
            <table>
                <thead><tr><th>Parameter</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>api_key</code></td><td><span class="badge req">Yes</span></td><td>API Key</td></tr>
                    <tr><td><code>date_from</code></td><td><span class="badge opt">No</span></td><td>วันที่เริ่มต้น (YYYY-MM-DD)</td></tr>
                    <td><code>date_to</code></td><td><span class="badge opt">No</span></td><td>วันที่สิ้นสุด (YYYY-MM-DD)</td></tr>
                    <tr><td><code>page</code></td><td><span class="badge opt">No</span></td><td>หน้าที่ต้องการ (Default: 1)</td></tr>
                </tbody>
            </table>
            <strong>ตัวอย่าง Response:</strong>
<pre>
{
  "status": "success",
  "pagination": {
    "current_page": 1,
    "per_page": 1000,
    "total_pages": 5,
    "total_records": 4500
  },
  "data": [
    { "internal_ref": "SO-001", "sale_date": "2026-01-01", ... }
  ]
}
</pre>
        </section>

        <section>
            <h2>ตัวอย่างการเชื่อมต่อ </h2>
             <h3>(PHP Example)</h3>
<pre>
&lt;?php
// ตัวอย่างการดึงข้อมูลยอดขาย
$url = "<?php echo $api_base_url; ?>api_sales_summary.php";
$params = [
    "api_key" => "YOUR_API_KEY_HERE",
    "date_from" => "2026-01-01", 
    "date_to"   => "2026-01-31"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "?" . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['status'] == 'success') {
    print_r($data['data']);
} else {
    echo "Error: " . $data['message'];
}
?&gt;
</pre>
        </section>

            </section>

    <section id="examples">
        <h2>ตัวอย่างการเชื่อมต่อ</h2>
        
        <h3>Python</h3>
<pre>
import requests

url = "https://www.weedjai.com/api/api_sales_summary.php"
params = {
    "api_key": "YOUR_API_KEY",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31"
}

try:
    response = requests.get(url, params=params)
    if response.status_code == 200:
        data = response.json()
        print(f"Total Records: {data['count']}")
    else:
        print("Error:", response.text)
except Exception as e:
    print("Connection failed:", e)
</pre>

    </section>
    </div>

    <footer>
        &copy; <?php echo date('Y'); ?> Weedjai System API. All rights reserved.
    </footer>
</div>

</body>
</html>