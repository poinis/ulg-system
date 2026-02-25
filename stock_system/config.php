<?php
// ============================================================
// config.php — Replenishment System for TOPOLOGIE
// DB: cmbase  |  ตารางเดิม: stores, daily_sales
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'cmbase');
define('DB_USER',    'cmbase');
define('DB_PASS',    '#wmIYH3wazaa');
define('DB_CHARSET', 'utf8mb4');

// ─── Brand Filter ─────────────────────────────────────────────
define('DS_BRAND',  'TOPOLOGIE');     // brand ใน daily_sales
define('CSV_BRAND', 'TLG');           // brand column ใน CEGID CSV ที่ filter
define('CSV_FAMILY', 'TLG');          // family ใน replenish_products

// ─── Target days ──────────────────────────────────────────────
define('TF_TARGET_DAYS',     10);   // TF Best: เป้า stock พอขาย 10 วัน
define('REFILL_TARGET_DAYS', 14);   // Refill DC→สาขา: เป้า 14 วัน
define('DEFAULT_RATE_DAYS',  28);   // ช่วงดึง daily_sales ย้อนหลัง (วัน)

// ─── Week Cover Thresholds ────────────────────────────────────
define('WC_CRITICAL',  1.0);   // < 1w  → 🚨 เติมด่วน
define('WC_WARNING',   1.5);   // < 1.5w → ⚠️ เติมทันที
define('WC_STOP',      2.5);   // > 2.5w → ⛔ หยุดเติม / โอนออกได้

// ─── Transfer Rules ───────────────────────────────────────────
define('WC_TRANSFER_ALL',    4.0);   // WC < 4w → โอนออกได้หมด ไม่ต้องเก็บขั้นต่ำ
define('HOLDING_XFER_ALL',  30);    // Company holding < 30d → โอนออกหมดได้

// ─── Top Seller Config ────────────────────────────────────────
define('TOP_1_20_WEEKS',   8);
define('TOP_21_40_WEEKS',  6);
define('TOP_41_60_WEEKS',  4);
define('NOTICE_1_20',    6.5);
define('NOTICE_21_40',   5.0);
define('NOTICE_41_60',   3.0);

// ─── DC store_code values (daily_sales.store_code) ────────────
define('DC_DS_CODES',   ['02000']);           // Pronto Dc Office
define('WEB_DS_CODES',  ['02009']);           // Pronto Online
// store_code_new values ใน stock CSV ที่เป็น DC
define('DC_CEGID_CODES', ['10000','77000','77002','77003','77010']);

// ─── Upload ───────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

define('APP_NAME',    'Replenish — TOPOLOGIE');
define('APP_VERSION', '3.0.0');

date_default_timezone_set('Asia/Bangkok');