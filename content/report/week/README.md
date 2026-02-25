# Social Media CSV Importer

โปรแกรม PHP+MySQL สำหรับอัพโหลดและนำเข้าข้อมูล CSV จาก Social Media ลงฐานข้อมูล MySQL

## ✨ Features

- รองรับไฟล์ CSV จาก Facebook, Instagram, TikTok
- ตรวจจับ Social Platform อัตโนมัติจาก Permalink
- UI ที่ใช้งานง่าย รองรับ Drag & Drop
- แสดงสถิติแยกตาม Platform
- รองรับภาษาไทย (UTF-8)

## 📁 โครงสร้างไฟล์

```
social_media_import/
├── config.php              # การตั้งค่าฐานข้อมูล
├── database.sql            # SQL สร้างตาราง
├── SocialMediaImporter.php # Class หลักสำหรับนำเข้าข้อมูล
├── index.php               # หน้าอัพโหลดไฟล์
├── view_data.php           # หน้าดูข้อมูล
├── uploads/                # โฟลเดอร์เก็บไฟล์ชั่วคราว
└── README.md               # คู่มือการใช้งาน
```

## 🚀 การติดตั้ง

### 1. สร้างฐานข้อมูล

```bash
mysql -u root -p < database.sql
```

หรือ Import ผ่าน phpMyAdmin

### 2. แก้ไขการตั้งค่าฐานข้อมูล

เปิดไฟล์ `config.php` และแก้ไขค่าต่อไปนี้:

```php
define('DB_HOST', 'localhost');     // Host ของ MySQL
define('DB_NAME', 'social_media_db'); // ชื่อฐานข้อมูล
define('DB_USER', 'root');          // Username
define('DB_PASS', '');              // Password
```

### 3. ตั้งค่า Permission

```bash
chmod 755 uploads/
```

### 4. รันโปรแกรม

วางไฟล์ทั้งหมดใน Web Server (Apache/Nginx) และเปิด `index.php`

## 📊 โครงสร้างฐานข้อมูล

ตาราง `social_posts` ประกอบด้วยคอลัมน์:

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary Key |
| post_id | VARCHAR(50) | รหัสโพสต์ |
| page_id | VARCHAR(50) | รหัสเพจ |
| page_name | VARCHAR(255) | ชื่อเพจ |
| title | TEXT | หัวข้อโพสต์ |
| description | TEXT | รายละเอียด |
| duration_sec | INT | ความยาววิดีโอ (วินาที) |
| publish_time | DATETIME | วันเวลาที่โพสต์ |
| permalink | TEXT | ลิงก์โพสต์ |
| post_type | VARCHAR(50) | ประเภทโพสต์ |
| views | INT | จำนวนวิว |
| reach | INT | การเข้าถึง |
| reactions | INT | จำนวน Reactions |
| comments | INT | จำนวน Comments |
| shares | INT | จำนวน Shares |
| **social** | ENUM | **Facebook, Instagram, TikTok** |
| ... | | และอื่นๆ |

## 🔍 การตรวจจับ Social Platform

โปรแกรมจะตรวจสอบ Permalink เพื่อระบุ Platform:

```php
- facebook.com, fb.com → Facebook
- instagram.com, instagr.am → Instagram  
- tiktok.com → TikTok
```

## 💻 การใช้งาน API

```php
require_once 'SocialMediaImporter.php';

$importer = new SocialMediaImporter();

// นำเข้าข้อมูลจาก CSV
$result = $importer->importCSV('/path/to/file.csv');

// ดึงข้อมูลทั้งหมด
$posts = $importer->getAllPosts(100, 0);

// ดึงข้อมูลตาม Platform
$fbPosts = $importer->getPostsBySocial('Facebook', 50);

// นับจำนวนตาม Platform
$stats = $importer->getCountBySocial();

// ลบข้อมูลทั้งหมด
$importer->truncateTable();
```

## 📝 ตัวอย่างไฟล์ CSV

```csv
Post ID,Page ID,Page name,Title,Description,Duration (sec),Publish time,...,Permalink,...
1.22158E+17,6.1571E+13,MyPage,Title,Desc,27,11/10/2025 21:00,...,https://www.facebook.com/reel/xxx,...
```

## 🔧 Requirements

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- PDO Extension
- mbstring Extension

## 📄 License

MIT License
