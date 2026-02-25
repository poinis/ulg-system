# 📊 Sales Dashboard

ระบบ Dashboard ยอดขายรายวัน - PHP + MySQL

## 🚀 การติดตั้ง

### 1. สร้าง Database
```bash
mysql -u root -p < database.sql
```

### 2. แก้ไข config.php
```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'sales_dashboard');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Email (Gmail)
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');  // ใช้ App Password
define('EMAIL_RECIPIENTS', [
    'online@prontodenim.com',
]);
```

### 3. ตั้งค่า Permission
```bash
chmod 755 uploads/
```

### 4. อัพโหลดไปยัง Server
อัพโหลดทุกไฟล์ไปยัง web root หรือ subdirectory

## 📁 โครงสร้างไฟล์

```
sales_dashboard/
├── config.php          # ตั้งค่า Database & Email
├── database.sql        # SQL สร้างตาราง
├── index.php           # หน้าหลัก Dashboard
├── upload.php          # หน้า Upload CSV
├── import.php          # Process Import
├── calculate.php       # คำนวณยอดขาย
├── send_email.php      # ส่ง Email รายงาน
├── export.php          # Export Excel
├── assets/
│   └── style.css       # CSS Styles
└── uploads/            # โฟลเดอร์เก็บไฟล์ CSV
```

## 🔧 วิธีใช้งาน

1. เข้า `http://your-domain/sales_dashboard/`
2. กด **Upload CSV**
3. เลือกไฟล์ **Payment** และ **Sale Transaction**
4. กด **Upload & Import**
5. ระบบคำนวณยอดอัตโนมัติ
6. ดูผลลัพธ์ที่หน้า Dashboard

## 📧 ตั้งค่า Gmail

1. เปิด 2-Step Verification: https://myaccount.google.com/security
2. สร้าง App Password: https://myaccount.google.com/apppasswords
3. ใส่ App Password ใน config.php

## 📱 Features

- ✅ Upload CSV (Payment + Sale Transaction)
- ✅ คำนวณยอดขายอัตโนมัติ
- ✅ Dashboard แสดงสรุปรายวัน/รายเดือน
- ✅ ตารางหน้าตา Excel
- ✅ Export CSV
- ✅ ส่ง Email รายงาน

## 🔒 Security

- ตั้งค่า `.htaccess` ป้องกันเข้าถึง uploads/
- ใช้ prepared statements ป้องกัน SQL Injection
- Validate ไฟล์ก่อน import
