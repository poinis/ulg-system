# Store Locator - ระบบค้นหาสาขา

ระบบแสดงรายการสาขาพร้อมหน้า Admin สำหรับจัดการข้อมูลสาขา

## 📁 ไฟล์ในโปรเจค

```
store-app/
├── index.php      # หน้าแสดงรายการสาขา (Public)
├── admin.php      # หน้า Admin สำหรับจัดการสาขา
├── config.php     # ไฟล์ตั้งค่าฐานข้อมูล
└── uploads/       # โฟลเดอร์เก็บรูปภาพ (สร้างอัตโนมัติ)
```

## 🚀 การติดตั้ง

### 1. ตั้งค่าฐานข้อมูล

แก้ไขไฟล์ `config.php` ให้ตรงกับฐานข้อมูลของคุณ:

```php
define('DB_HOST', 'localhost');     // Host ฐานข้อมูล
define('DB_NAME', 'ulgmember');     // ชื่อฐานข้อมูล
define('DB_USER', 'root');          // Username
define('DB_PASS', '');              // Password
```

### 2. Import SQL

นำเข้าไฟล์ `stores_sms.sql` เข้าฐานข้อมูล MySQL

### 3. Upload ไฟล์

Upload ทุกไฟล์ไปยัง Web Server ที่รองรับ PHP 7.4+

### 4. ตั้งค่า Permission

```bash
chmod 755 uploads/
```

## 🔐 การเข้าใช้งาน Admin

- URL: `https://your-domain.com/admin.php`
- Username: `admin`
- Password: `admin123`

⚠️ **สำคัญ:** กรุณาเปลี่ยน Username และ Password ในไฟล์ `admin.php` บรรทัด 7-8

```php
$ADMIN_USER = 'your_username';
$ADMIN_PASS = 'your_secure_password';
```

## ✨ ฟีเจอร์

### หน้า Public (index.php)
- แสดงรายการสาขาทั้งหมดแบบ Card
- จัดกลุ่มตามแบรนด์
- ค้นหาสาขาแบบ Real-time
- กรองตามแบรนด์
- Responsive Design รองรับมือถือ
- ปุ่มโทรและนำทางไป Google Maps

### หน้า Admin (admin.php)
- เพิ่ม/แก้ไข/ลบ สาขา
- อัพโหลดรูปภาพสาขา
- เปิด/ปิดการแสดงผลสาขา
- ค้นหาสาขาในตาราง
- Responsive Design รองรับมือถือ

## 📱 Screenshots

หน้า Public:
- Hero section พร้อมช่องค้นหา
- สถิติจำนวนสาขาและแบรนด์
- Filter tabs สำหรับกรองแบรนด์
- Store cards พร้อมรูปภาพและข้อมูล

หน้า Admin:
- ตารางแสดงรายการสาขา
- Modal form สำหรับเพิ่ม/แก้ไข
- Toast notification แจ้งเตือน
- Sidebar navigation

## 🛠 เทคโนโลยีที่ใช้

- PHP 7.4+
- MySQL 8.0
- Pure CSS (ไม่ใช้ Framework)
- Vanilla JavaScript
- Font Awesome 6
- Google Fonts (Inter, Noto Sans Thai)

## 📞 Support

หากมีปัญหาหรือข้อเสนอแนะ สามารถติดต่อได้ที่ผู้พัฒนา
