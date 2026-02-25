# 🚀 คู่มือเริ่มต้นใช้งานด่วน

## ✅ ข้อมูลของคุณ (ใส่เรียบร้อยแล้ว)

```
App ID: 862629473011673
App Secret: ffdd223393467333a53f384aeae0609f
Page ID: 489861450880996
Access Token: EAAMQjpNPx9k... (ใส่ในไฟล์แล้ว)
```

---

## 📦 ไฟล์ทั้งหมดที่ต้อง Upload

ทั้งหมด 5 ไฟล์:

1. ✅ **facebook_insights.php** - ไฟล์หลัก (มี Class)
2. ✅ **api_improved.php** - API endpoint (ใส่ข้อมูลแล้ว)
3. ✅ **monthly_report.html** - หน้ารายงาน
4. ✅ **test_connection.php** - ไฟล์ทดสอบ (NEW!)
5. ⚪ **api.php** - API เดิม (ใช้หรือไม่ใช้ก็ได้)

---

## 🎯 ขั้นตอนการใช้งาน (3 ขั้นตอน)

### ขั้นตอนที่ 1: Upload ไฟล์

Upload ทั้ง 4 ไฟล์หลักไปที่ web server ของคุณ:
- facebook_insights.php
- api_improved.php
- monthly_report.html
- test_connection.php

ตัวอย่าง path:
```
/public_html/facebook/
├── facebook_insights.php
├── api_improved.php
├── monthly_report.html
└── test_connection.php
```

### ขั้นตอนที่ 2: ทดสอบการเชื่อมต่อ

เปิดเบราว์เซอร์และไปที่:
```
http://your-domain.com/facebook/test_connection.php
```

ควรเห็น:
- ✅ Access Token ใช้งานได้
- ✅ ดึงข้อมูลเพจสำเร็จ
- ✅ ดึงข้อมูล Insights สำเร็จ
- ✅ ดึงโพสต์สำเร็จ

**หากมีข้อผิดพลาด:**
- ❌ สีแดง = มีปัญหา (อ่านข้อความแนะนำ)
- ⚠️ สีส้ม = คำเตือน (อาจไม่มีข้อมูล)

### ขั้นตอนที่ 3: เปิดหน้ารายงาน

เปิดเบราว์เซอร์และไปที่:
```
http://your-domain.com/facebook/monthly_report.html
```

**วิธีใช้:**
1. คลิก "ทดสอบการเชื่อมต่อ" อีกครั้ง
2. เลือกเดือนที่ต้องการ (ค่าเริ่มต้น 3 เดือนล่าสุด)
3. คลิก "โหลดรายงาน"
4. รอสักครู่ ระบบจะแสดงตาราง
5. คลิก "Export เป็น Excel" เพื่อดาวน์โหลด

---

## 📊 ข้อมูลที่จะได้

รายงานจะแสดง:

| รายการ | คำอธิบาย |
|--------|----------|
| Total Reach | ยอดมูลรวม (จำนวนคนที่เห็น) |
| Engagement Rate | อัตราการมีส่วนร่วม |
| Conversion Engagement (Linkclick) | คลิกลิงก์ |
| Conversion Engagement (Profile) | เข้าชมโปรไฟล์ |
| Net New Followers | ผู้ติดตามใหม่สุทธิ |
| Unfollows | ยกเลิกติดตาม |
| **การเปลี่ยนแปลง %** | เปรียบเทียบกับเดือนแรก |

---

## 🔧 แก้ปัญหาเบื้องต้น

### ปัญหา 1: "ไม่สามารถเชื่อมต่อได้"

**วิธีแก้:**
```php
// ตรวจสอบว่า PHP มี cURL
<?php
if (function_exists('curl_version')) {
    echo "cURL: เปิดใช้งาน ✅";
} else {
    echo "cURL: ปิดใช้งาน ❌ (ติดต่อ hosting)";
}

// ตรวจสอบว่า allow_url_fopen เปิดอยู่
if (ini_get('allow_url_fopen')) {
    echo "allow_url_fopen: เปิด ✅";
} else {
    echo "allow_url_fopen: ปิด ❌ (ติดต่อ hosting)";
}
?>
```

### ปัญหา 2: "Access Token ไม่ถูกต้อง"

Token อาจหมดอายุ ให้:
1. ไปที่ https://developers.facebook.com/tools/explorer/
2. เลือกแอป "ULGreport"
3. Generate Access Token ใหม่
4. คัดลอก Token
5. แก้ไขในไฟล์ `api_improved.php` บรรทัดที่ 16

### ปัญหา 3: "ไม่มีข้อมูล"

อาจเป็นเพราะ:
- เพจยังใหม่ ไม่มีข้อมูลย้อนหลัง
- เลือกเดือนที่เพจยังไม่มี
- Facebook ยังไม่ประมวลผลข้อมูล

**วิธีแก้:** 
- ลองเลือกเดือนปัจจุบัน
- ลองช่วง 7-30 วันล่าสุด

### ปัญหา 4: "Rate Limit"

เรียก API บ่อยเกินไป (>200 calls/ชม.)

**วิธีแก้:**
- รอ 1 ชั่วโมงแล้วลองใหม่
- อย่าโหลดข้อมูลบ่อยเกินไป
- ใช้ Cache (มีในคู่มือขั้นสูง)

---

## 📞 ติดต่อและความช่วยเหลือ

หากยังมีปัญหา:

1. **ดู Error Log**
   ```
   เปิด test_connection.php แล้วดูข้อความ error
   ```

2. **ตรวจสอบ Token**
   - ไปที่: https://developers.facebook.com/tools/debug/accesstoken/
   - ใส่ Access Token
   - ตรวจสอบ Expires, Scopes, Valid

3. **ตรวจสอบ Permissions**
   ต้องมี:
   - ✅ pages_show_list
   - ✅ pages_read_engagement
   - ✅ pages_read_user_content

---

## 🎓 เคล็ดลับ

### 💡 ทำให้ Token ไม่หมดอายุ

Token ปัจจุบันจะหมดอายุใน 60 วัน ถ้าต้องการให้ไม่หมดอายุ:

1. ใช้ Page Access Token (ไม่ใช่ User Token)
2. ต้องเป็น Page Admin
3. ใช้ Long-lived Token

### 💡 ตั้งเวลาดึงข้อมูลอัตโนมัติ

ใช้ Cron Job:
```bash
# ดึงข้อมูลทุกวัน เวลา 02:00
0 2 * * * /usr/bin/php /path/to/your/auto_fetch.php
```

### 💡 Export เป็น PDF

เปิด `monthly_report.html` แล้ว:
1. กด Ctrl+P (Windows) หรือ Cmd+P (Mac)
2. เลือก "Save as PDF"
3. Save

---

## ✅ Checklist

ก่อนเริ่มใช้งาน ตรวจสอบ:

- [ ] Upload ไฟล์ครบทั้ง 4 ไฟล์
- [ ] เปิด test_connection.php ได้
- [ ] เห็นสีเขียวทั้งหมด
- [ ] เปิด monthly_report.html ได้
- [ ] กดปุ่ม "ทดสอบการเชื่อมต่อ" สำเร็จ
- [ ] กดปุ่ม "โหลดรายงาน" ได้
- [ ] เห็นตารางข้อมูล
- [ ] Export Excel ได้

---

## 🎉 เสร็จแล้ว!

ตอนนี้คุณพร้อมใช้งานแล้ว! 

หากต้องการความช่วยเหลือเพิ่มเติม:
- อ่าน `TROUBLESHOOTING.md` สำหรับปัญหาขั้นสูง
- อ่าน `README.md` สำหรับคู่มือฉบับเต็ม
- ดู Facebook Developer Docs

**มีความสุขกับการใช้งาน! 🚀**
