<?php
/**
 * Database Configuration
 * QuickCardsGH System
 * By Lamstech Solutions
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'quickcardsgh_db';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
            
            // Set charset separately to avoid constant issues
            $this->conn->exec("SET NAMES utf8mb4");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'quickcardsgh_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application constants
define('APP_NAME', 'QuickCards Ghana');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');
define('SITE_NAME', 'QuickCards Ghana');
define('SITE_DOMAIN', 'quickcardsgh.com');
define('COMPANY_NAME', 'Lamstech Solutions');

// Security constants
define('JWT_SECRET', 'your-secret-key-here-change-in-production');
define('ENCRYPTION_KEY', 'your-encryption-key-here-change-in-production');

// Mobile Money API Configuration (Mock for development)
define('MOMO_API_URL', 'https://api.momo-provider.com/v1/');
define('MOMO_API_KEY', 'your-momo-api-key');
define('MOMO_MERCHANT_ID', 'your-merchant-id');

// SMS Configuration (Mock for development)
define('SMS_API_URL', 'https://api.sms-provider.com/v1/');
define('SMS_API_KEY', 'your-sms-api-key');
define('SMS_SENDER_ID', 'QUICKCARDS');

// Application settings
define('DEFAULT_TIMEZONE', 'Africa/Accra');
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('DATE_FORMAT_DISPLAY', 'd/m/Y H:i');
define('DATE_FORMAT_SHORT', 'd/m/Y');
define('CURRENCY', 'GHS');
define('CURRENCY_SYMBOL', 'Gh¢');

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB for Excel files
define('ALLOWED_FILE_TYPES', ['xlsx', 'xls', 'csv', 'jpg', 'jpeg', 'png', 'pdf']);
define('EXCEL_UPLOAD_PATH', __DIR__ . '/../uploads/excel/');
define('PDF_OUTPUT_PATH', __DIR__ . '/../uploads/pdf/');
define('TEMPLATE_PATH', __DIR__ . '/../templates/');

// QuickCardsGH Specific Configuration
define('REFERENCE_PREFIX', 'QCG');
define('MIN_REFERENCE_LENGTH', 8);
define('PDF_ENABLED', true);

// Contact Information
define('CONTACT_PHONE', '0549616253');
define('CONTACT_EMAIL', 'support@quickcardsgh.com');
define('CONTACT_WHATSAPP', '0549616253');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Create upload directories if they don't exist
if (!file_exists(EXCEL_UPLOAD_PATH)) {
    mkdir(EXCEL_UPLOAD_PATH, 0755, true);
}
if (!file_exists(PDF_OUTPUT_PATH)) {
    mkdir(PDF_OUTPUT_PATH, 0755, true);
}
if (!file_exists(TEMPLATE_PATH)) {
    mkdir(TEMPLATE_PATH, 0755, true);
}
?>
