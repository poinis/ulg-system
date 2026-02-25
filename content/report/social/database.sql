-- สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS social_media_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE social_media_db;

-- ลบตารางเดิมถ้ามี
DROP TABLE IF EXISTS social_posts;
DROP TABLE IF EXISTS platform_settings;

-- สร้างตาราง social_posts รองรับทุก platform
CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Common fields
    post_id VARCHAR(100),
    account_id VARCHAR(100),
    account_name VARCHAR(255),
    account_username VARCHAR(255),
    title TEXT,
    description TEXT,
    duration_sec INT DEFAULT 0,
    publish_time DATETIME,
    permalink TEXT,
    post_type VARCHAR(50),
    
    -- Engagement metrics (common)
    views INT DEFAULT 0,
    reach INT DEFAULT 0,
    likes INT DEFAULT 0,
    comments INT DEFAULT 0,
    shares INT DEFAULT 0,
    saves INT DEFAULT 0,
    
    -- Facebook specific
    caption_type VARCHAR(50),
    is_crosspost TINYINT DEFAULT 0,
    is_share TINYINT DEFAULT 0,
    languages VARCHAR(100),
    custom_labels VARCHAR(255),
    funded_content_status VARCHAR(100),
    data_comment VARCHAR(255),
    date_type VARCHAR(50),
    reactions INT DEFAULT 0,
    reactions_comments_shares INT DEFAULT 0,
    total_clicks INT DEFAULT 0,
    photo_clicks INT DEFAULT 0,
    other_clicks INT DEFAULT 0,
    link_clicks INT DEFAULT 0,
    video_clicks INT DEFAULT 0,
    negative_feedback INT DEFAULT 0,
    seconds_viewed DECIMAL(15,3) DEFAULT 0,
    average_seconds_viewed DECIMAL(10,3) DEFAULT 0,
    estimated_earnings_usd DECIMAL(10,2) DEFAULT 0,
    ad_cpm_usd DECIMAL(10,2) DEFAULT 0,
    ad_impressions INT DEFAULT 0,
    
    -- Instagram specific
    follows INT DEFAULT 0,
    
    -- TikTok specific
    favorites INT DEFAULT 0,
    
    -- Social platform identifier
    social ENUM('Facebook', 'Instagram', 'TikTok') NOT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_social (social),
    INDEX idx_publish_time (publish_time),
    INDEX idx_account_name (account_name),
    INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตาราง platform_settings สำหรับเก็บ followers
CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('Facebook', 'Instagram', 'TikTok') NOT NULL UNIQUE,
    followers INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มค่าเริ่มต้น
INSERT INTO platform_settings (platform, followers) VALUES 
('Facebook', 50000),
('Instagram', 35000),
('TikTok', 15000)
ON DUPLICATE KEY UPDATE followers = VALUES(followers);

-- สร้างตาราง api_settings สำหรับเก็บ API configurations
CREATE TABLE IF NOT EXISTS api_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (platform, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
