# TOPOLOGIE Stock Management & Transfer Optimization System

## โครงสร้างไฟล์

```
stock_system/
├── config.php                    # ตั้งค่า DB + Business Rules
├── schema.sql                    # MySQL Schema + Store Data
├── index.php                     # Main Dashboard (Bootstrap)
├── upload.php                    # CSV Upload Page
├── api.php                       # REST API (JSON)
├── classes/
│   ├── Database.php              # PDO Singleton Connection
│   ├── SalesAnalytics.php        # คำนวณ Sell Rate / Week Cover / Rankings
│   └── TransferOptimizer.php     # TF Best Plan / Refill Plan / Rules
└── uploads/                      # CSV files (auto-created)
```

---

## การติดตั้ง

### 1. MySQL Setup
```sql
-- Run schema.sql ใน MySQL
mysql -u root -p < schema.sql
```

### 2. Config
แก้ไข `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 3. PHP Requirements
- PHP 8.0+
- Extensions: PDO, PDO_MySQL, mbstring, fileinfo

### 4. Web Server
- Apache/Nginx + PHP-FPM
- หรือ `php -S localhost:8000` สำหรับ development

---

## การใช้งาน

### Step 1: Upload CSV
1. ไปที่ `upload.php`
2. เลือกไฟล์ CSV จาก CEGID (รองรับ UTF-16 LE)
3. เลือก "ช่วงข้อมูลยอดขาย" (แนะนำ 28 วัน = 4 สัปดาห์)
4. กด Import CSV

### Step 2: Dashboard
1. ไปที่ `index.php`
2. ดู Stat Cards: Critical / Warning / Overstock
3. กด **Calculate Plans** — ระบบจะ:
   - คำนวณ Top Sellers (rank 1-60)
   - สร้าง TF Best Plans (สาขา → สาขา)
   - สร้าง Refill Plans (DC → สาขา)

### Step 3: Approve / Reject
- ไปที่ Transfer Planner หรือ Refill Planner
- กด Approve / Reject ทีละ plan
- หรือ Approve All ทั้งหมด

### Step 4: Export
- Export CSV: Stock Summary / Transfer Plans / Reorder Alerts

---

## Business Rules

| Rule | Detail |
|------|--------|
| TF Best Target | 10 วัน (โอนระหว่างสาขา) |
| Refill Target | 14 วัน (เติมจาก DC) |
| Week Cover < 1 | 🚨 เติมด่วน |
| Week Cover 1-1.5 | ⚠️ เติมทันที |
| Week Cover > 2.5 | ⛔ หยุดเติม / โอนออกได้ |
| Top 1-20 | เก็บ 8 weeks | Notice < 6.5w |
| Top 21-40 | เก็บ 6 weeks | Notice < 5w |
| Top 41-60 | เก็บ 4 weeks | Notice < 3w |
| ห้ามโอนจาก | DC, DISPLAY, WEBSITE |
| Top Seller | โอนได้แค่ส่วนเกิน 2 weeks |

---

## API Endpoints

```
GET  api.php?action=dashboard_stats&upload_id=N     # Stats overview
GET  api.php?action=stock_summary&upload_id=N       # Stock table data
GET  api.php?action=transfer_plans&upload_id=N      # Transfer plans
GET  api.php?action=reorder_alerts&upload_id=N      # Reorder alerts
GET  api.php?action=store_ranking&upload_id=N&barcode=X  # Store ranking
POST api.php?action=calculate&upload_id=N           # Generate plans
POST api.php?action=approve  (body: id=N)           # Approve plan
POST api.php?action=reject   (body: id=N&reason=X)  # Reject plan
GET  api.php?action=export&type=stock               # Export CSV
GET  api.php?action=export&type=transfer_plans      # Export CSV
GET  api.php?action=export&type=reorder_alerts      # Export CSV
```

---

## CSV Format (CEGID)

| Column | Description |
|--------|-------------|
| Store | รหัสสาขา |
| Barcode | EAN Barcode |
| GQ_ARTICLE | Article Code |
| GA_FAMILLENIV1 | Brand Family (กรอง TLG = TOPOLOGIE) |
| Physical | Stock On Hand |
| Sale | ยอดขายในช่วง Period Days |
| Transfer | โอนเข้า/ออก |

---

## SalesAnalytics Class

```php
$analytics = new SalesAnalytics($uploadId);

// Daily sell rate (units/day)
$rate = $analytics->calculateDailySellRate($barcode, $storeId);

// Week cover
$wc = $analytics->calculateWeekCover($barcode, $storeId);

// Need quantity (14 days)
$need = $analytics->calculateNeedQty($barcode, $storeId, 14);

// Store ranking by sell rate (High → Low)
$ranking = $analytics->getStorePerformanceRanking($barcode);

// Dashboard stats
$stats = $analytics->getDashboardStats();

// Reorder alerts
$alerts = $analytics->getReorderAlerts();
```

## TransferOptimizer Class

```php
$optimizer = new TransferOptimizer($uploadId);

// TF Best (store → store, target 10d)
$plans = $optimizer->generateTFBestPlan($barcode);

// Refill (DC → store, target 14d)
$result = $optimizer->generateRefillPlan($barcode);
// $result = ['dc_stock_total', 'dc_stock_remain', 'items' => [...]]

// Validate rules
$check = $optimizer->validateTransferRules($fromStore, $toStore, $barcode);
// $check = ['valid' => bool, 'reason' => string]

// Generate & save ALL plans
$result = $optimizer->generateAndSaveAllPlans();
```
