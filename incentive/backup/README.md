# 📋 PRONTO & CO Incentive Checklist System 2026

ระบบ Incentive Checklist สำหรับพนักงาน PRONTO & CO

## 📁 โครงสร้างไฟล์

```
incentive/
├── index.php           # Redirect หน้าแรก
├── login.php           # หน้าเข้าสู่ระบบ
├── logout.php          # ออกจากระบบ
├── checklist.php       # หน้ากรอก Checklist (Mobile)
├── history.php         # ประวัติการส่งงาน
├── leaderboard.php     # อันดับสาขา
├── config.php          # ตั้งค่า Database
├── database.sql        # SQL สร้างตาราง
│
├── admin/
│   ├── dashboard.php   # หน้าหลัก Admin
│   ├── approve.php     # ตรวจสอบ/อนุมัติงาน
│   ├── payroll.php     # คำนวณ Incentive
│   ├── trophy.php      # จัดการ Trophy Bonus
│   ├── settings.php    # ตั้งค่าระบบ
│   └── export.php      # Export Excel
│
├── api/
│   ├── submit_task.php # API ส่งงาน
│   └── review.php      # API อนุมัติ/ปฏิเสธ
│
├── includes/
│   └── functions.php   # Helper Functions
│
└── uploads/
    └── screenshots/    # เก็บรูป Screenshot
```

## 🚀 วิธีติดตั้ง

### 1. อัปโหลดไฟล์
อัปโหลดโฟลเดอร์ `incentive/` ไปยัง Server

### 2. สร้าง Database Tables
รัน SQL จากไฟล์ `database.sql`:
```bash
mysql -u cmbase -p cmbase < database.sql
```

หรือ Copy เนื้อหาใน `database.sql` ไปรันใน phpMyAdmin

### 3. ตั้งค่า config.php
แก้ไข `config.php` ให้ตรงกับ Database ของคุณ (ถ้าต่างจากที่ให้มา)

### 4. ตั้งค่า Permission
```bash
chmod 755 uploads/screenshots
```

### 5. ทดสอบระบบ
เข้าที่ `https://yourdomain.com/incentive/`

## 📱 การใช้งาน

### สำหรับพนักงาน
1. เข้าสู่ระบบด้วย username/password ที่มีอยู่
2. เลือกสาขาของตัวเอง (ครั้งแรก)
3. กรอก Checklist รายวัน
   - TikTok/Reel: วาง Link (+3 คะแนน)
   - Google Maps Update: อัปโหลดภาพ (+1 คะแนน)
   - Google Review: อัปโหลดภาพ (+1 คะแนน)
   - Reply/Q&A: อัปโหลดภาพ (+1 คะแนน)
4. ดูประวัติและอันดับสาขา

### สำหรับ Admin
1. Dashboard: ดูภาพรวม
2. ตรวจสอบงาน: Approve/Reject งานที่ส่ง
3. คำนวณเงิน: ดู Incentive ตามสาขา
4. Trophy Bonus: มอบรางวัลพิเศษ
5. ตั้งค่า: ปรับค่าต่างๆ และจัดการสาขา
6. Export: ดาวน์โหลด Excel สำหรับ Payroll

## 💰 สูตรคำนวณ Incentive

```
Base Incentive = (คะแนนรวมสาขา / 100) × 2,500 บาท
- สูงสุดไม่เกิน 100% (2,500 บาท)
- Budget Cap: 2,200 บาท/คน

Trophy Bonus = 500 บาท/คน ต่อรางวัล
- Most Views (ยอดวิวสูงสุด)
- Most Review Growth (รีวิวเติบโตสูงสุด)
- HQ's Choice (HQ เลือก)
```

## 🔐 บทบาทผู้ใช้
- **admin**: เข้าถึง Admin Panel ได้
- **อื่นๆ** (brand, marketing, etc.): ใช้งาน Checklist ได้อย่างเดียว

## 📞 Support
ติดต่อทีม IT หากพบปัญหา

---
© 2026 PRONTO & CO. All rights reserved.
