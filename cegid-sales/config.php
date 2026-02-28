<?php
/**
 * Cegid Sales Sync System - Configuration
 * Version: 1.0
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ulgcegid');
define('DB_USER', 'ulgcegid');
define('DB_PASS', '#wmIYH3wazaa');
define('DB_CHARSET', 'utf8mb4');

// Cegid REST API Configuration
define('CEGID_BASE_URL', 'https://90643827-retail-ondemand.cegid.cloud/Y2');
define('CEGID_USERNAME', '90643827_001_PROD\\frt');
define('CEGID_PASSWORD', 'adgjm');
define('CEGID_FOLDER_ID', '90643827_001_PROD');

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('EXPORT_DIR', __DIR__ . '/exports/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Store/Branch Names
define('STORE_NAMES', [
    '77003' => 'Store 77003',
    '77010' => 'Store 77010',
    '77002' => 'Store 77002',
    '14070' => 'SW19 CentralwOrld',
    '11020' => 'SUPERDRY LARDPRAO',
    '88001' => 'ULG Event #2',
    '88002' => 'ULG Event #1',
    '14060' => 'HOOGA Central world',
    '29040' => 'TOPOLOGIE SIAM SOI2',
    '29080' => 'DEUS SIAM SOI2',
    '14020' => 'SOUP CENTRAL WORLD',
    '25030' => 'FREITAG SIAM SOI7',
    '27030' => 'FREITAG CHIANGMAI',
    '26030' => 'FREITAG SILOM',
    '20240' => 'SOUP PARAGON',
    '21020' => 'SOUP PATTAYA',
    '22020' => 'SOUP TERMINAL 21',
    '28040' => 'TOPOLOGIE DUSIT CENTRAL PARK',
    '19040' => 'TOPOLOGIE Mega Bangna',
    '11040' => 'TOPOLOGIE LADPRAO',
    '14040' => 'TOPOLOGIE CENTRAL WORLD',
    '18020' => 'SOUP JUNGCEYLON',
    '16090' => 'Thepopupstore Phuket',
    '17020' => 'SOUP EMSPHERE',
    '13050' => 'Surplus Central Village',
    '24010' => 'And Co ThinkPark',
    '23010' => 'And Co OneBkk',
    '19010' => 'Pronto Mega Bangna',
    '20010' => 'Pronto Siam Paragon',
    '12010' => 'Pronto Central Rama 9',
    '15010' => 'Pronto and Co Festival Chiangmai',
    '11010' => 'Pronto Central Lardprao',
    '77000' => 'Pronto Online',
    '10000' => 'Pronto Dc Office',
]);

// Sales Filter — exclude non-sales items to match CSV
// 1. CFL = Cash Float  2. OFF% = Shopping bags  3. REPAIR = Service fee
// 4. is_excluded = voucher/marketing bills (payment < 50% of items)
define('SALES_FILTER', "
    AND t.article_code != 'CFL'
    AND t.article_code NOT LIKE 'OFF%'
    AND t.product_title NOT LIKE '%REPAIR%'
    AND t.is_excluded = 0
");

// Error Reporting (ปิดใน production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create directories if not exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(EXPORT_DIR)) {
    mkdir(EXPORT_DIR, 0755, true);
}
?>
