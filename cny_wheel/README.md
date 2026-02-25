# 🧧 ระบบกงล้อจับรางวัลตรุษจีน 2569

## 📁 ไฟล์ทั้งหมด

```
cny_wheel/
├── config.php      # ตั้งค่า Database + Shop mapping
├── setup.sql       # SQL สร้างตาราง + insert รางวัล
├── wheel.php       # หน้ากงล้อหลัก (สำหรับร้านค้า)
├── spin.php        # API หมุนวงล้อ (AJAX)
├── admin.php       # หน้าสรุปรางวัล (Admin)
├── reset.php       # API รีเซ็ตรางวัล
└── README.md       # คู่มือนี้
```

## 🚀 วิธีติดตั้ง

### 1. อัพไฟล์ทั้งหมดขึ้น server
วางไว้ในโฟลเดอร์ที่ต้องการ เช่น `/cny_wheel/`

### 2. แก้ไข config.php
```php
$db_host = 'localhost';         // หรือ IP ของ MySQL server
$db_name = 'cmbase';            // ชื่อ database
$db_user = 'your_username';     // username MySQL
$db_pass = 'your_password';     // password MySQL
```

### 3. รัน setup.sql
```bash
mysql -u root -p cmbase < setup.sql
```
หรือ import ผ่าน Navicat / phpMyAdmin

### 4. ตั้งค่า shop_mapping ใน config.php
Map username ในระบบกับชื่อร้านที่ตรงกับรางวัล:

```php
$shop_mapping = [
    'Central Ladprao'            => ['prontoclp', 'SpdClp', 'TOPOLOGIE CLP'],
    'Mega Bangna'                => ['prontomega', 'Topologie-megabangna', 'SPD_MGB'],
    'Central Festival Chiangmai' => ['andco_thinkpark'],
    'Central Rama 9'             => ['Prontorama9', 'Test'],
    'Siam Paragon'               => ['Paragon', 'Topologie/SPD PRG'],
];
```
**⚠️ สำคัญ:** ปรับ username ให้ตรงกับระบบ login จริงของคุณ

### 5. ตรวจสอบ Session
ระบบนี้ใช้ `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['role']`
ตรวจสอบว่าระบบ login ของคุณ set session เหล่านี้

## 📋 การใช้งาน

### สำหรับร้านค้า (role: shop)
1. Login เข้าระบบ
2. เข้าหน้า `wheel.php`
3. กรอกเลขที่บิลลูกค้า
4. กดปุ่ม "หมุนวงล้อ!"
5. กงล้อจะหมุนและแสดงรางวัล

### สำหรับ Admin (role: admin, owner)
1. Login เข้าระบบ
2. เข้าหน้า `admin.php`
3. ดูสรุปรางวัลแต่ละร้าน
4. ดูประวัติการหมุน / Export CSV
5. รีเซ็ตรางวัล (ทั้งหมด หรือ แยกร้าน)

## 🎰 ระบบรางวัล
- รางวัลจะถูกแจก **เรียงตามลำดับ** ที่กำหนดไว้ในไฟล์ Excel
- แต่ละร้านมีรางวัลเป็นของตัวเอง
- เมื่อรางวัลหมด ลูกค้าจะได้ **ส่วนลด 15%** ทุกคน
- ป้องกันเลขบิลซ้ำ (บิลเดียวกันหมุนได้ครั้งเดียว)

## 🎡 รางวัลบนกงล้อ
| รางวัล | สี |
|--------|-----|
| ส่วนลด 50% | แดง |
| ส่วนลด 30% | ม่วง |
| ส่วนลด 20% | เขียว |
| ส่วนลด 15% | ทอง |
| เสื้อ | ส้ม |
| หมวก | น้ำเงิน |

## 🔧 Database Tables

### cny_prizes
เก็บรางวัลที่กำหนดไว้ล่วงหน้า เรียงตาม prize_order

### cny_spin_log
เก็บประวัติการหมุนทั้งหมด (prize_id = NULL คือรางวัลหมดแล้ว ได้ 15% default)
