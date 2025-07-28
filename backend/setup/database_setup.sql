-- QuickCardsGH Database Setup
-- Create database and tables for the quickcardsgh system
-- By Lamstech Solutions

CREATE DATABASE IF NOT EXISTS quickcardsgh_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quickcardsgh_db;

-- Users table (for admin management)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operator') DEFAULT 'operator',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Service types table
CREATE TABLE IF NOT EXISTS service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    admin_price DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Pincode inventory table (for uploaded WAEC pincodes)
CREATE TABLE IF NOT EXISTS pincode_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id INT NOT NULL,
    exam_type_id INT,
    serial_number VARCHAR(50) UNIQUE NOT NULL,
    pin_code VARCHAR(20) NOT NULL,
    voucher_code VARCHAR(50),
    batch_id VARCHAR(50),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('available', 'sold', 'expired', 'damaged') DEFAULT 'available',
    sold_at TIMESTAMP NULL,
    sold_to_phone VARCHAR(15) NULL,
    purchase_reference VARCHAR(20) NULL,
    expires_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id) ON DELETE SET NULL,
    INDEX idx_serial_number (serial_number),
    INDEX idx_pin_code (pin_code),
    INDEX idx_status (status),
    INDEX idx_batch_id (batch_id),
    INDEX idx_purchase_reference (purchase_reference)
);

-- Purchase references table (for unique retrieval system)
CREATE TABLE IF NOT EXISTS purchase_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(20) UNIQUE NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    transaction_id INT NOT NULL,
    service_type_id INT NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    INDEX idx_reference_code (reference_code),
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status)
);

-- Batch uploads table (for tracking Excel uploads)
CREATE TABLE IF NOT EXISTS batch_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) UNIQUE NOT NULL,
    service_type_id INT NOT NULL,
    exam_type_id INT,
    filename VARCHAR(255) NOT NULL,
    total_records INT DEFAULT 0,
    successful_imports INT DEFAULT 0,
    failed_imports INT DEFAULT 0,
    upload_status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    uploaded_by INT,
    upload_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_batch_id (batch_id),
    INDEX idx_upload_status (upload_status)
);

-- Exam types table (for WAEC, BECE, etc.)
CREATE TABLE IF NOT EXISTS exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    year VARCHAR(4),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_type (service_type_id, code, year)
);

-- Pricing tiers table
CREATE TABLE IF NOT EXISTS pricing_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id INT NOT NULL,
    min_quantity INT NOT NULL,
    max_quantity INT,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE
);

-- Mobile money providers table
CREATE TABLE IF NOT EXISTS momo_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    api_endpoint VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    print_id VARCHAR(50) UNIQUE NOT NULL,
    service_type_id INT NOT NULL,
    exam_type_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    momo_provider_id INT NOT NULL,
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_reference VARCHAR(100),
    momo_transaction_id VARCHAR(100),
    customer_ip VARCHAR(45),
    user_agent TEXT,
    status ENUM('active', 'used', 'expired', 'cancelled') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id),
    FOREIGN KEY (momo_provider_id) REFERENCES momo_providers(id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_print_id (print_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Sold checkers/vouchers table (references inventory)
CREATE TABLE IF NOT EXISTS checkers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    inventory_id INT NOT NULL,
    purchase_reference VARCHAR(20) NOT NULL,
    checker_code VARCHAR(50) UNIQUE NOT NULL,
    serial_number VARCHAR(50) NOT NULL,
    pin_code VARCHAR(20) NOT NULL,
    voucher_code VARCHAR(50),
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES pincode_inventory(id) ON DELETE CASCADE,
    INDEX idx_checker_code (checker_code),
    INDEX idx_serial_number (serial_number),
    INDEX idx_purchase_reference (purchase_reference),
    INDEX idx_status (status)
);

-- SMS logs table
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT,
    phone_number VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    provider_response TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Payment logs table
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    request_data JSON,
    response_data JSON,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default data
INSERT INTO service_types (name, code, description, admin_price, selling_price) VALUES
('WAEC Results Checker', 'WAEC', 'West African Examinations Council Results Checker', 15.00, 18.00),
('SHS Placement Checker', 'SHS', 'Senior High School Placement Checker', 7.00, 10.00),
('UCC Admission Forms', 'UCC', 'University of Cape Coast Admission Forms', 200.00, 250.00);

INSERT INTO exam_types (service_type_id, name, code, year) VALUES
(1, '2024 BECE', '2024-BECE', '2024'),
(1, 'OLD BECE', 'OLD-BECE', NULL),
(1, '2024 WASSCE', '2024-WASSCE', '2024'),
(1, 'OLD WASSCE', 'OLD-WASSCE', NULL),
(1, 'SSCE', 'SSCE', NULL),
(1, 'ABCE', 'ABCE', NULL),
(1, 'GBCE', 'GBCE', NULL),
(2, '2023 SHS Placement', '2023-SHS', '2023');

INSERT INTO pricing_tiers (service_type_id, min_quantity, max_quantity, unit_price, total_price) VALUES
-- WAEC pricing
(1, 1, 5, 18.00, 18.00),
(1, 2, 5, 18.00, 36.00),
(1, 3, 5, 18.00, 54.00),
(1, 4, 5, 18.00, 72.00),
(1, 5, 5, 18.00, 90.00),
(1, 10, 50, 16.00, 160.00),
(1, 15, 50, 16.00, 240.00),
(1, 20, 50, 16.00, 320.00),
(1, 30, 50, 16.00, 480.00),
(1, 50, 50, 16.00, 800.00),
(1, 100, 200, 15.50, 1550.00),
(1, 150, 200, 15.50, 2325.00),
(1, 200, 200, 15.50, 3100.00),
-- SHS pricing
(2, 1, 1, 10.00, 10.00),
(2, 2, 2, 10.00, 20.00),
(2, 3, 3, 10.00, 30.00),
(2, 5, 200, 7.50, 37.50),
(2, 10, 200, 7.50, 75.00),
(2, 20, 200, 7.50, 150.00),
(2, 50, 200, 7.50, 375.00),
(2, 100, 200, 7.50, 750.00),
(2, 200, 200, 7.50, 1500.00),
-- UCC pricing
(3, 1, 1, 231.30, 231.30),
(3, 1, 1, 334.10, 334.10);

INSERT INTO momo_providers (name, code) VALUES
('MTN Mobile Money', 'MTN'),
('AirtelTigo Money', 'AIRTEL'),
('Tigo Cash', 'TIGO'),
('Vodafone Cash', 'VODAFONE');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'QuickCards Ghana', 'Website name'),
('site_domain', 'quickcardsgh.com', 'Website domain'),
('contact_phone', '0549616253', 'Contact phone number'),
('contact_email', 'support@quickcardsgh.com', 'Contact email address'),
('contact_whatsapp', '0549616253', 'WhatsApp contact number'),
('company_name', 'Lamstech Solutions', 'Company name'),
('checker_expiry_days', '365', 'Number of days before checkers expire'),
('max_retrieval_attempts', '5', 'Maximum retrieval attempts per day'),
('sms_enabled', '1', 'Enable SMS notifications'),
('email_enabled', '0', 'Enable email notifications'),
('pdf_enabled', '1', 'Enable PDF generation'),
('reference_prefix', 'QCG', 'Purchase reference prefix'),
('min_reference_length', '8', 'Minimum reference code length');

-- Create default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@quickcardsgh.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
