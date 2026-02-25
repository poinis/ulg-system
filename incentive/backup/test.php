<?php
/**
 * PRONTO Incentive System - Installer
 * รันไฟล์นี้ครั้งเดียวเพื่อสร้างตาราง Database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>🚀 PRONTO Incentive System - Installer</h2>";
echo "<pre>";

// SQL statements
$sqls = [
    // 1. ตารางสาขา
    "CREATE TABLE IF NOT EXISTS `incentive_branches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_code` VARCHAR(20) NOT NULL UNIQUE,
        `branch_name` VARCHAR(100) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 2. ตารางผูก User กับ Branch
    "CREATE TABLE IF NOT EXISTS `incentive_user_branches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `branch_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `user_branch` (`user_id`, `branch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 3. ตาราง Task Types
    "CREATE TABLE IF NOT EXISTS `incentive_task_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `task_code` VARCHAR(50) NOT NULL UNIQUE,
        `task_name` VARCHAR(100) NOT NULL,
        `task_name_th` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `points` INT NOT NULL DEFAULT 1,
        `input_type` ENUM('link', 'image') NOT NULL,
        `icon` VARCHAR(50) DEFAULT '📋',
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 4. ตาราง Submissions
    "CREATE TABLE IF NOT EXISTS `incentive_submissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `branch_id` INT NOT NULL,
        `task_type_id` INT NOT NULL,
        `submission_date` DATE NOT NULL,
        `link_url` VARCHAR(500) DEFAULT NULL,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `reviewed_by` INT DEFAULT NULL,
        `reviewed_at` DATETIME DEFAULT NULL,
        `reject_reason` VARCHAR(255) DEFAULT NULL,
        `points_earned` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `submission_date`),
        INDEX `idx_branch_date` (`branch_id`, `submission_date`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 5. ตาราง Monthly Summary
    "CREATE TABLE IF NOT EXISTS `incentive_monthly_summary` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_id` INT NOT NULL,
        `year_month` VARCHAR(7) NOT NULL,
        `total_points` INT DEFAULT 0,
        `payout_ratio` DECIMAL(5,4) DEFAULT 0,
        `base_incentive` DECIMAL(10,2) DEFAULT 0,
        `trophy_bonus` DECIMAL(10,2) DEFAULT 0,
        `total_payout` DECIMAL(10,2) DEFAULT 0,
        `is_finalized` TINYINT(1) DEFAULT 0,
        `finalized_by` INT DEFAULT NULL,
        `finalized_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `branch_month` (`branch_id`, `year_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 6. ตาราง Trophy Winners
    "CREATE TABLE IF NOT EXISTS `incentive_trophy_winners` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_id` INT NOT NULL,
        `year_month` VARCHAR(7) NOT NULL,
        `trophy_type` ENUM('most_views', 'most_review_growth', 'hq_choice') NOT NULL,
        `bonus_per_person` DECIMAL(10,2) DEFAULT 500.00,
        `notes` TEXT,
        `awarded_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `branch_month_trophy` (`branch_id`, `year_month`, `trophy_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // 7. ตาราง Settings
    "CREATE TABLE IF NOT EXISTS `incentive_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(50) NOT NULL UNIQUE,
        `setting_value` VARCHAR(255) NOT NULL,
        `description` VARCHAR(255),
        `updated_by` INT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

// Run create tables
echo "📦 Creating tables...\n\n";
$success = 0;
$failed = 0;

foreach ($sqls as $i => $sql) {
    $tableNum = $i + 1;
    if ($conn->query($sql)) {
        echo "✅ Table $tableNum created successfully\n";
        $success++;
    } else {
        echo "❌ Table $tableNum FAILED: " . $conn->error . "\n";
        $failed++;
    }
}

// Insert default data
echo "\n📝 Inserting default data...\n\n";

// Task Types
$taskTypes = [
    ['tiktok_reel', 'TikTok / Reel', 'TikTok / Reel', 'โพสต์วิดีโอ TikTok หรือ Instagram Reel', 3, 'link', '🎬', 1],
    ['google_maps_update', 'Google Maps Update', 'อัปเดต Google Maps', 'โพสต์อัปเดตข่าวสารลง Google Maps', 1, 'image', '📍', 2],
    ['google_review', 'Google Review', 'รีวิว Google', 'ลูกค้ารีวิว 5 ดาว', 1, 'image', '⭐', 3],
    ['reply_qa', 'Reply / Q&A', 'ตอบคอมเมนต์ / Q&A', 'ตอบคอมเมนต์ลูกค้า หรือ ตั้งคำถาม-ตอบเองใน Maps', 1, 'image', '💬', 4],
];

foreach ($taskTypes as $t) {
    $stmt = $conn->prepare("INSERT IGNORE INTO incentive_task_types (task_code, task_name, task_name_th, description, points, input_type, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissi", $t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7]);
    $stmt->execute();
}
echo "✅ Task types added\n";

// Settings
$settings = [
    ['max_base_incentive', '2500', 'Base Incentive สูงสุด (บาท)'],
    ['target_points', '100', 'เป้าหมายคะแนนต่อเดือน'],
    ['trophy_bonus_per_person', '500', 'โบนัส Trophy ต่อคน (บาท)'],
    ['budget_cap_per_person', '2200', 'Budget Cap ต่อคน (บาท)'],
];

foreach ($settings as $s) {
    $stmt = $conn->prepare("INSERT IGNORE INTO incentive_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
    $stmt->execute();
}
echo "✅ Settings added\n";

// Sample Branches
$branches = [
    ['HQ', 'Headquarters'],
    ['SIAM', 'Siam Paragon'],
    ['CENTRAL', 'Central World'],
    ['MEGA', 'Mega Bangna'],
    ['ICON', 'ICONSIAM'],
];

foreach ($branches as $b) {
    $stmt = $conn->prepare("INSERT IGNORE INTO incentive_branches (branch_code, branch_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $b[0], $b[1]);
    $stmt->execute();
}
echo "✅ Sample branches added\n";

// Verify
echo "\n📊 Verification...\n\n";
$result = $conn->query("SHOW TABLES LIKE 'incentive_%'");
echo "✅ Incentive tables created: " . $result->num_rows . " tables\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM incentive_task_types");
$row = $result->fetch_assoc();
echo "✅ Task types: " . $row['cnt'] . "\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM incentive_branches");
$row = $result->fetch_assoc();
echo "✅ Branches: " . $row['cnt'] . "\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM incentive_settings");
$row = $result->fetch_assoc();
echo "✅ Settings: " . $row['cnt'] . "\n";

echo "</pre>";

echo "<h3 style='color: green;'>🎉 Installation Complete!</h3>";
echo "<p><a href='login.php' style='font-size: 18px;'>👉 Go to Login Page</a></p>";
echo "<p style='color: red;'><strong>⚠️ Security:</strong> Delete this install.php file after installation!</p>";
?>