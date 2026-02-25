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
