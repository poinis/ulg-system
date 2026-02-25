-- =====================================================
-- PRONTO & CO Incentive Checklist System - Database Schema
-- =====================================================

-- ตารางสาขา (Branches)
CREATE TABLE IF NOT EXISTS `incentive_branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_code` VARCHAR(20) NOT NULL UNIQUE,
    `branch_name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางผูก User กับ Branch
CREATE TABLE IF NOT EXISTS `incentive_user_branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_branch` (`user_id`, `branch_id`),
    FOREIGN KEY (`branch_id`) REFERENCES `incentive_branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Task Types (ประเภทงาน)
CREATE TABLE IF NOT EXISTS `incentive_task_types` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Daily Submissions (การส่งงานรายวัน)
CREATE TABLE IF NOT EXISTS `incentive_submissions` (
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
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`branch_id`) REFERENCES `incentive_branches`(`id`),
    FOREIGN KEY (`task_type_id`) REFERENCES `incentive_task_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Monthly Summary (สรุปรายเดือนต่อสาขา)
CREATE TABLE IF NOT EXISTS `incentive_monthly_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL, -- Format: 2026-01
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
    UNIQUE KEY `branch_month` (`branch_id`, `year_month`),
    FOREIGN KEY (`branch_id`) REFERENCES `incentive_branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Trophy Winners (รางวัลพิเศษ)
CREATE TABLE IF NOT EXISTS `incentive_trophy_winners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,
    `trophy_type` ENUM('most_views', 'most_review_growth', 'hq_choice') NOT NULL,
    `bonus_per_person` DECIMAL(10,2) DEFAULT 500.00,
    `notes` TEXT,
    `awarded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `branch_month_trophy` (`branch_id`, `year_month`, `trophy_type`),
    FOREIGN KEY (`branch_id`) REFERENCES `incentive_branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Settings (การตั้งค่า)
CREATE TABLE IF NOT EXISTS `incentive_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255),
    `updated_by` INT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT Default Data
-- =====================================================

-- Task Types (4 ประเภทตาม Brief)
INSERT INTO `incentive_task_types` (`task_code`, `task_name`, `task_name_th`, `description`, `points`, `input_type`, `icon`, `sort_order`) VALUES
('tiktok_reel', 'TikTok / Reel', 'TikTok / Reel', 'โพสต์วิดีโอ TikTok หรือ Instagram Reel', 3, 'link', '🎬', 1),
('google_maps_update', 'Google Maps Update', 'อัปเดต Google Maps', 'โพสต์อัปเดตข่าวสารลง Google Maps', 1, 'image', '📍', 2),
('google_review', 'Google Review', 'รีวิว Google', 'ลูกค้ารีวิว 5 ดาว', 1, 'image', '⭐', 3),
('reply_qa', 'Reply / Q&A', 'ตอบคอมเมนต์ / Q&A', 'ตอบคอมเมนต์ลูกค้า หรือ ตั้งคำถาม-ตอบเองใน Maps', 1, 'image', '💬', 4)
ON DUPLICATE KEY UPDATE `task_name` = VALUES(`task_name`);

-- Default Settings
INSERT INTO `incentive_settings` (`setting_key`, `setting_value`, `description`) VALUES
('max_base_incentive', '2500', 'Base Incentive สูงสุด (บาท)'),
('target_points', '100', 'เป้าหมายคะแนนต่อเดือน'),
('trophy_bonus_per_person', '500', 'โบนัส Trophy ต่อคน (บาท)'),
('budget_cap_per_person', '2200', 'Budget Cap ต่อคน (บาท)')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Sample Branches (เพิ่มสาขาตัวอย่าง)
INSERT INTO `incentive_branches` (`branch_code`, `branch_name`) VALUES
('HQ', 'Headquarters'),
('SIAM', 'Siam Paragon'),
('CENTRAL', 'Central World'),
('MEGA', 'Mega Bangna'),
('ICON', 'ICONSIAM')
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);
