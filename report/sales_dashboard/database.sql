-- =============================================
-- Sales Dashboard Database
-- =============================================

CREATE DATABASE IF NOT EXISTS sales_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sales_dashboard;

-- ตาราง Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL,
    store INT NOT NULL,
    register VARCHAR(20),
    payment_method VARCHAR(100),
    bill_number INT,
    customer VARCHAR(50),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    amount DECIMAL(15,2) DEFAULT 0,
    ticket_cancelled VARCHAR(10),
    sales_rep VARCHAR(50),
    posting_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (sale_date),
    INDEX idx_store (store),
    INDEX idx_payment_method (payment_method)
) ENGINE=InnoDB;

-- ตาราง Sales Transactions
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL,
    warehouse INT NOT NULL,
    bill_number INT,
    line_number INT,
    item_code VARCHAR(50),
    item_barcode VARCHAR(50),
    item_description VARCHAR(255),
    brand_code VARCHAR(10),
    brand VARCHAR(100),
    item_group VARCHAR(100),
    class VARCHAR(100),
    season VARCHAR(50),
    size VARCHAR(20),
    qty INT DEFAULT 0,
    unit_price DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    total_incl_tax DECIMAL(15,2) DEFAULT 0,
    total_excl_tax DECIMAL(15,2) DEFAULT 0,
    sales_rep VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (sale_date),
    INDEX idx_warehouse (warehouse),
    INDEX idx_brand (brand),
    INDEX idx_bill (bill_number)
) ENGINE=InnoDB;

-- ตาราง Daily Summary (ยอดสรุปรายวัน)
CREATE TABLE IF NOT EXISTS daily_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL UNIQUE,
    spd_offline DECIMAL(15,2) DEFAULT 0,
    spd_online DECIMAL(15,2) DEFAULT 0,
    pronto_offline DECIMAL(15,2) DEFAULT 0,
    pronto_online DECIMAL(15,2) DEFAULT 0,
    freitag DECIMAL(15,2) DEFAULT 0,
    pavement_online DECIMAL(15,2) DEFAULT 0,
    topo_offline DECIMAL(15,2) DEFAULT 0,
    topo_online DECIMAL(15,2) DEFAULT 0,
    izipizi DECIMAL(15,2) DEFAULT 0,
    hooga DECIMAL(15,2) DEFAULT 0,
    soup DECIMAL(15,2) DEFAULT 0,
    sw19 DECIMAL(15,2) DEFAULT 0,
    sw19_lazada DECIMAL(15,2) DEFAULT 0,
    total_offline DECIMAL(15,2) DEFAULT 0,
    total_online DECIMAL(15,2) DEFAULT 0,
    grand_total DECIMAL(15,2) DEFAULT 0,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (sale_date)
) ENGINE=InnoDB;

-- ตาราง Store Details (รายละเอียดแต่ละร้าน)
CREATE TABLE IF NOT EXISTS store_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL,
    brand VARCHAR(50) NOT NULL,
    store_name VARCHAR(50) NOT NULL,
    store_code INT NOT NULL,
    pcs INT DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    extra_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_date (sale_date, brand, store_name),
    INDEX idx_date (sale_date),
    INDEX idx_brand (brand)
) ENGINE=InnoDB;

-- ตาราง Import Log
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_date DATETIME NOT NULL,
    sale_date DATE NOT NULL,
    file_type ENUM('payment', 'sales') NOT NULL,
    filename VARCHAR(255),
    rows_imported INT DEFAULT 0,
    status ENUM('success', 'error') DEFAULT 'success',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
