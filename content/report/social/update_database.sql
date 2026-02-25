-- ไฟล์สำหรับอัปเดตฐานข้อมูลเดิม (ถ้ามีตาราง social_posts อยู่แล้ว)
-- รันไฟล์นี้ถ้าไม่ต้องการลบข้อมูลเดิม

USE social_media_db;

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
ON DUPLICATE KEY UPDATE platform = platform;

-- แสดงข้อมูลที่เพิ่ม
SELECT * FROM platform_settings;
