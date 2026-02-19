-- Affiliate System Tables for Onrizo Shop

-- ============================================
-- 1. AFFILIATES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS affiliates (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- 2. AFFILIATE CLICKS/REFERRALS TABLE
-- Tracks every click and sale from affiliates
-- ============================================
CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT(11) NOT NULL,
    product_id INT(11),
    product_name VARCHAR(255),
    order_code VARCHAR(50),
    commission DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
);

-- ============================================
-- 3. AFFILIATE PAYMENTS TABLE
-- Tracks withdrawal requests and payments
-- ============================================
CREATE TABLE IF NOT EXISTS affiliate_payments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT(11) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(50) DEFAULT 'mpesa',
    status VARCHAR(50) DEFAULT 'pending',
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
);

-- ============================================
-- 4. AFFILIATE PRODUCTS TABLE
-- Links products to commission rates
-- ============================================
CREATE TABLE IF NOT EXISTS affiliate_products (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    product_id INT(11) NOT NULL,
    admin_id INT(11),
    commission_percent DECIMAL(5,2) DEFAULT 10,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================
-- 5. AFFILIATE SETTINGS TABLE
-- Global affiliate program settings
-- ============================================
CREATE TABLE IF NOT EXISTS affiliate_settings (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    admin_id INT(11),
    default_commission_percent DECIMAL(5,2) DEFAULT 10,
    min_withdrawal DECIMAL(10,2) DEFAULT 500,
    max_pending_days INT DEFAULT 30,
    auto_confirm_sales TINYINT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES for Performance
-- ============================================
CREATE INDEX IF NOT EXISTS idx_affiliate_email ON affiliates(email);
CREATE INDEX IF NOT EXISTS idx_affiliate_referral_code ON affiliates(referral_code);
CREATE INDEX IF NOT EXISTS idx_clicks_affiliate ON affiliate_clicks(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_clicks_status ON affiliate_clicks(status);
CREATE INDEX IF NOT EXISTS idx_clicks_created ON affiliate_clicks(created_at);
CREATE INDEX IF NOT EXISTS idx_payments_affiliate ON affiliate_payments(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON affiliate_payments(status);
CREATE INDEX IF NOT EXISTS idx_products_admin ON affiliate_products(admin_id);

-- ============================================
-- Sample Data (Optional)
-- ============================================
-- INSERT INTO affiliate_settings (default_commission_percent, min_withdrawal, max_pending_days, status)
-- VALUES (15, 500, 30, 'active');
