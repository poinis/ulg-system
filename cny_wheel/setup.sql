-- =============================================
-- Chinese New Year Lucky Wheel - Database Setup
-- =============================================

-- Prize queue table - predefined prizes in order per shop
CREATE TABLE IF NOT EXISTS `cny_prizes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shop_name` varchar(100) NOT NULL COMMENT 'ชื่อร้าน',
  `prize_order` int NOT NULL COMMENT 'ลำดับรางวัล',
  `prize_name` varchar(100) NOT NULL COMMENT 'ชื่อรางวัล',
  `is_claimed` tinyint(1) DEFAULT 0 COMMENT '0=ยังไม่ถูกรับ, 1=ถูกรับแล้ว',
  `claimed_by` int DEFAULT NULL COMMENT 'user_id ที่รับรางวัล',
  `claimed_at` datetime DEFAULT NULL,
  `bill_number` varchar(100) DEFAULT NULL COMMENT 'เลขที่บิล',
  PRIMARY KEY (`id`),
  KEY `idx_shop_order` (`shop_name`, `prize_order`),
  KEY `idx_shop_claimed` (`shop_name`, `is_claimed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Spin log table
CREATE TABLE IF NOT EXISTS `cny_spin_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `shop_name` varchar(100) NOT NULL,
  `bill_number` varchar(100) NOT NULL,
  `prize_name` varchar(100) NOT NULL,
  `prize_id` int DEFAULT NULL COMMENT 'FK to cny_prizes.id (NULL if default 15%)',
  `spun_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop` (`shop_name`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- Insert predefined prizes for each shop
-- =============================================

-- Central Ladprao (40 prizes)
INSERT INTO `cny_prizes` (`shop_name`, `prize_order`, `prize_name`) VALUES
('Central Ladprao', 1, '20%'), ('Central Ladprao', 2, '15%'), ('Central Ladprao', 3, 'เสื้อ'),
('Central Ladprao', 4, '30%'), ('Central Ladprao', 5, 'หมวก'), ('Central Ladprao', 6, '15%'),
('Central Ladprao', 7, '15%'), ('Central Ladprao', 8, 'เสื้อ'), ('Central Ladprao', 9, '15%'),
('Central Ladprao', 10, '20%'), ('Central Ladprao', 11, 'เสื้อ'), ('Central Ladprao', 12, '15%'),
('Central Ladprao', 13, '50%'), ('Central Ladprao', 14, 'เสื้อ'), ('Central Ladprao', 15, 'หมวก'),
('Central Ladprao', 16, '15%'), ('Central Ladprao', 17, '20%'), ('Central Ladprao', 18, '15%'),
('Central Ladprao', 19, 'เสื้อ'), ('Central Ladprao', 20, '15%'), ('Central Ladprao', 21, '30%'),
('Central Ladprao', 22, 'หมวก'), ('Central Ladprao', 23, '15%'), ('Central Ladprao', 24, 'เสื้อ'),
('Central Ladprao', 25, '20%'), ('Central Ladprao', 26, '15%'), ('Central Ladprao', 27, 'หมวก'),
('Central Ladprao', 28, '15%'), ('Central Ladprao', 29, '15%'), ('Central Ladprao', 30, 'เสื้อ'),
('Central Ladprao', 31, '15%'), ('Central Ladprao', 32, 'หมวก'), ('Central Ladprao', 33, '15%'),
('Central Ladprao', 34, '15%'), ('Central Ladprao', 35, 'เสื้อ'), ('Central Ladprao', 36, '20%'),
('Central Ladprao', 37, '15%'), ('Central Ladprao', 38, 'เสื้อ'), ('Central Ladprao', 39, '15%'),
('Central Ladprao', 40, 'เสื้อ');

-- Mega Bangna (40 prizes)
INSERT INTO `cny_prizes` (`shop_name`, `prize_order`, `prize_name`) VALUES
('Mega Bangna', 1, '20%'), ('Mega Bangna', 2, '15%'), ('Mega Bangna', 3, 'เสื้อ'),
('Mega Bangna', 4, '30%'), ('Mega Bangna', 5, 'หมวก'), ('Mega Bangna', 6, '15%'),
('Mega Bangna', 7, '15%'), ('Mega Bangna', 8, 'เสื้อ'), ('Mega Bangna', 9, '15%'),
('Mega Bangna', 10, '20%'), ('Mega Bangna', 11, 'เสื้อ'), ('Mega Bangna', 12, '15%'),
('Mega Bangna', 13, '15%'), ('Mega Bangna', 14, 'เสื้อ'), ('Mega Bangna', 15, 'หมวก'),
('Mega Bangna', 16, '50%'), ('Mega Bangna', 17, '20%'), ('Mega Bangna', 18, '15%'),
('Mega Bangna', 19, 'เสื้อ'), ('Mega Bangna', 20, '15%'), ('Mega Bangna', 21, '30%'),
('Mega Bangna', 22, 'หมวก'), ('Mega Bangna', 23, '15%'), ('Mega Bangna', 24, 'เสื้อ'),
('Mega Bangna', 25, '20%'), ('Mega Bangna', 26, '15%'), ('Mega Bangna', 27, 'หมวก'),
('Mega Bangna', 28, '15%'), ('Mega Bangna', 29, '15%'), ('Mega Bangna', 30, 'เสื้อ'),
('Mega Bangna', 31, '15%'), ('Mega Bangna', 32, 'หมวก'), ('Mega Bangna', 33, '15%'),
('Mega Bangna', 34, '15%'), ('Mega Bangna', 35, 'เสื้อ'), ('Mega Bangna', 36, '20%'),
('Mega Bangna', 37, '15%'), ('Mega Bangna', 38, 'เสื้อ'), ('Mega Bangna', 39, '15%'),
('Mega Bangna', 40, 'เสื้อ');

-- Central Festival Chiangmai (40 prizes)
INSERT INTO `cny_prizes` (`shop_name`, `prize_order`, `prize_name`) VALUES
('Central Festival Chiangmai', 1, '20%'), ('Central Festival Chiangmai', 2, '15%'), ('Central Festival Chiangmai', 3, 'เสื้อ'),
('Central Festival Chiangmai', 4, '30%'), ('Central Festival Chiangmai', 5, 'หมวก'), ('Central Festival Chiangmai', 6, '15%'),
('Central Festival Chiangmai', 7, '15%'), ('Central Festival Chiangmai', 8, 'เสื้อ'), ('Central Festival Chiangmai', 9, '15%'),
('Central Festival Chiangmai', 10, '20%'), ('Central Festival Chiangmai', 11, 'เสื้อ'), ('Central Festival Chiangmai', 12, '50%'),
('Central Festival Chiangmai', 13, '15%'), ('Central Festival Chiangmai', 14, 'เสื้อ'), ('Central Festival Chiangmai', 15, 'หมวก'),
('Central Festival Chiangmai', 16, '15%'), ('Central Festival Chiangmai', 17, '20%'), ('Central Festival Chiangmai', 18, '15%'),
('Central Festival Chiangmai', 19, 'เสื้อ'), ('Central Festival Chiangmai', 20, '15%'), ('Central Festival Chiangmai', 21, '30%'),
('Central Festival Chiangmai', 22, 'หมวก'), ('Central Festival Chiangmai', 23, '15%'), ('Central Festival Chiangmai', 24, 'เสื้อ'),
('Central Festival Chiangmai', 25, '20%'), ('Central Festival Chiangmai', 26, '15%'), ('Central Festival Chiangmai', 27, 'หมวก'),
('Central Festival Chiangmai', 28, '15%'), ('Central Festival Chiangmai', 29, '15%'), ('Central Festival Chiangmai', 30, 'เสื้อ'),
('Central Festival Chiangmai', 31, '15%'), ('Central Festival Chiangmai', 32, 'หมวก'), ('Central Festival Chiangmai', 33, '15%'),
('Central Festival Chiangmai', 34, '15%'), ('Central Festival Chiangmai', 35, 'เสื้อ'), ('Central Festival Chiangmai', 36, '20%'),
('Central Festival Chiangmai', 37, '15%'), ('Central Festival Chiangmai', 38, 'เสื้อ'), ('Central Festival Chiangmai', 39, '15%'),
('Central Festival Chiangmai', 40, 'เสื้อ');

-- Central Rama 9 (20 prizes)
INSERT INTO `cny_prizes` (`shop_name`, `prize_order`, `prize_name`) VALUES
('Central Rama 9', 1, '20%'), ('Central Rama 9', 2, '15%'), ('Central Rama 9', 3, 'เสื้อ'),
('Central Rama 9', 4, '30%'), ('Central Rama 9', 5, 'หมวก'), ('Central Rama 9', 6, '50%'),
('Central Rama 9', 7, '15%'), ('Central Rama 9', 8, 'เสื้อ'), ('Central Rama 9', 9, '20%'),
('Central Rama 9', 10, '15%'), ('Central Rama 9', 11, 'เสื้อ'), ('Central Rama 9', 12, '15%'),
('Central Rama 9', 13, '15%'), ('Central Rama 9', 14, 'เสื้อ'), ('Central Rama 9', 15, 'หมวก'),
('Central Rama 9', 16, '15%'), ('Central Rama 9', 17, '20%'), ('Central Rama 9', 18, '15%'),
('Central Rama 9', 19, 'เสื้อ'), ('Central Rama 9', 20, 'หมวก');

-- Siam Paragon (20 prizes)
INSERT INTO `cny_prizes` (`shop_name`, `prize_order`, `prize_name`) VALUES
('Siam Paragon', 1, '20%'), ('Siam Paragon', 2, '15%'), ('Siam Paragon', 3, 'เสื้อ'),
('Siam Paragon', 4, '30%'), ('Siam Paragon', 5, 'หมวก'), ('Siam Paragon', 6, '15%'),
('Siam Paragon', 7, '50%'), ('Siam Paragon', 8, 'เสื้อ'), ('Siam Paragon', 9, '20%'),
('Siam Paragon', 10, '15%'), ('Siam Paragon', 11, 'เสื้อ'), ('Siam Paragon', 12, '15%'),
('Siam Paragon', 13, '15%'), ('Siam Paragon', 14, 'เสื้อ'), ('Siam Paragon', 15, 'หมวก'),
('Siam Paragon', 16, '15%'), ('Siam Paragon', 17, '20%'), ('Siam Paragon', 18, '15%'),
('Siam Paragon', 19, 'เสื้อ'), ('Siam Paragon', 20, 'หมวก');
