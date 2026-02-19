-- Affiliate System Tables for Onrizo Shop - Clean Setup

-- Drop existing tables if they exist
DROP TABLE IF EXISTS affiliate_payments;
DROP TABLE IF EXISTS affiliate_clicks;
DROP TABLE IF EXISTS affiliate_products;
DROP TABLE IF EXISTS affiliate_settings;
DROP TABLE IF EXISTS affiliates;

-- ============================================
-- 1. AFFILIATES TABLE
-- ============================================
CREATE TABLE affiliates (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    referral_code VARCHAR(50) UNIQUE NOT NULL,
    balance DECIMAL(12,2) DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0,
    withdrawn DECIMAL(12,2) DEFAULT 0,
    active_referrals INT DEFAULT 0,
    bank_details TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_referral_code (referral_code)
);

-- ============================================
-- 2. AFFILIATE CLICKS/REFERRALS TABLE
-- ============================================
CREATE TABLE affiliate_clicks (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT(11) NOT NULL,
    product_id INT(11),
    product_name VARCHAR(255),
    order_code VARCHAR(50),
    commission DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- ============================================
-- 3. AFFILIATE PAYMENTS TABLE
-- ============================================
CREATE TABLE affiliate_payments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT(11) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(50) DEFAULT 'mpesa',
    status VARCHAR(50) DEFAULT 'pending',
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_status (status)
);

-- ============================================
-- 4. AFFILIATE PRODUCTS TABLE
-- ============================================
CREATE TABLE affiliate_products (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    product_id INT(11) NOT NULL,
    admin_id INT(11),
    commission_percent DECIMAL(5,2) DEFAULT 10,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_admin (admin_id)
);

-- ============================================
-- 5. AFFILIATE SETTINGS TABLE
-- ============================================
CREATE TABLE affiliate_settings (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    admin_id INT(11),
    default_commission_percent DECIMAL(5,2) DEFAULT 15,
    min_withdrawal DECIMAL(10,2) DEFAULT 500,
    max_pending_days INT DEFAULT 30,
    auto_confirm_sales TINYINT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- Success Message
-- ============================================
-- All tables created successfully!
