# Social Media Data Importer

โปรแกรม PHP+MySQL สำหรับนำเข้าข้อมูลจาก **Facebook**, **Instagram** และ **TikTok** ลงฐานข้อมูล MySQL

## ✨ Features

- ✅ รองรับ **Facebook CSV** (จาก Meta Business Suite)
- ✅ รองรับ **Instagram CSV** (จาก Meta Business Suite)  
- ✅ รองรับ **TikTok Excel** (.xlsx จาก TikTok Analytics)
- ✅ ตรวจจับ Platform อัตโนมัติจากรูปแบบไฟล์
- ✅ อัพโหลดหลายไฟล์พร้อมกัน
- ✅ UI ที่ใช้งานง่าย รองรับ Drag & Drop
- ✅ แสดงสถิติแยกตาม Platform
- ✅ รองรับภาษาไทย (UTF-8)

## 📁 โครงสร้างไฟล์

```
social_media_import_v2/
├── config.php              # การตั้งค่าฐานข้อมูล
├── database.sql            # SQL สร้างตาราง
├── SocialMediaImporter.php # Class หลักสำหรับนำเข้าข้อมูล
├── SimpleXLSX.php          # Library อ่านไฟล์ Excel
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

## 📊 รูปแบบไฟล์ที่รองรับ

### Facebook CSV
คอลัมน์: Post ID, Page ID, Page name, Title, Description, Duration (sec), Publish time, Caption type, Permalink, Is crosspost, Is share, Post type, Languages, Custom labels, Funded content status, Data comment, Date, Views, Reach, Reactions/Comments/Shares, Reactions, Comments, Shares, Total clicks, Photo Clicks, Other Clicks, Link Clicks, Video Clicks, Negative feedback, Seconds viewed, Average Seconds viewed, Estimated earnings (USD), Ad CPM (USD), Ad impressions

### Instagram CSV
คอลัมน์: Post ID, Account ID, Account username, Account name, Description, Duration (sec), Publish time, Permalink, Post type, Data comment, Date, Views, Reach, Likes, Shares, Follows, Comments, Saves

### TikTok Excel (.xlsx)
คอลัมน์: Video title, Video link, Post time, Video views, Likes, Comments, Shares, Add to Favorites

## 💻 การใช้งาน API

```php
require_once 'SocialMediaImporter.php';

$importer = new SocialMediaImporter();

// นำเข้าข้อมูลจากไฟล์ (auto-detect)
$result = $importer->importFile('/path/to/file.csv');
// หรือ
$result = $importer->importFile('/path/to/file.xlsx');

// ดึงข้อมูลทั้งหมด
$posts = $importer->getAllPosts(100, 0);

// ดึงข้อมูลตาม Platform
$fbPosts = $importer->getPostsBySocial('Facebook', 50);
$igPosts = $importer->getPostsBySocial('Instagram', 50);
$ttPosts = $importer->getPostsBySocial('TikTok', 50);

// นับจำนวนตาม Platform
$stats = $importer->getCountBySocial();

// สถิติรวม
$summary = $importer->getStatsSummary();

// ลบข้อมูลทั้งหมด
$importer->truncateTable();

// ลบเฉพาะ Platform
$importer->deleteByPlatform('TikTok');
```

## 🔧 Requirements

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- PDO Extension
- mbstring Extension
- ZipArchive Extension (สำหรับอ่าน Excel)

## 📝 หมายเหตุ

- ไฟล์ CSV ต้องเป็น UTF-8
- TikTok ใช้ไฟล์ Excel (.xlsx) ไม่ใช่ CSV
- ระบบจะตรวจจับ Platform อัตโนมัติจากรูปแบบคอลัมน์

## 📄 License

MIT License
