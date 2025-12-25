-- SQL to update database for PhilSMS dual verification
-- Run these queries in your database

-- 1. Add phone_verified column to users table
ALTER TABLE users ADD COLUMN phone_verified TINYINT(1) DEFAULT 0 AFTER email_verified;

-- 2. Add phone_number column to otps table  
ALTER TABLE otps ADD COLUMN phone_number VARCHAR(20) NULL AFTER email;

-- 3. Update type enum to include SMS verification types
ALTER TABLE otps MODIFY COLUMN type ENUM(
    'registration',
    'login', 
    'password_reset',
    'password_change',
    'sms_registration',
    'sms_verification',
    'email_verification'
) NOT NULL;

-- 4. Add index for phone_number in otps table for better performance
ALTER TABLE otps ADD INDEX idx_phone_number (phone_number);

-- 5. Add index for phone_verified in users table
ALTER TABLE users ADD INDEX idx_phone_verified (phone_verified);

-- SQL to update database for PhilSMS dual verification
-- Your existing users table already has phone_verified field ✅
-- Your existing otps table already supports SMS types ✅

-- 1. Create verification_sessions table for dual verification flow
CREATE TABLE IF NOT EXISTS verification_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    user_data JSON NOT NULL COMMENT 'Stores signup form data temporarily',
    email_otp VARCHAR(6) NULL,
    sms_otp VARCHAR(6) NULL,
    email_verified TINYINT(1) DEFAULT 0,
    sms_verified TINYINT(1) DEFAULT 0,
    email_otp_expires_at TIMESTAMP NULL,
    sms_otp_expires_at TIMESTAMP NULL,
    resend_count INT(2) DEFAULT 0,
    last_resend_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 HOUR),
    INDEX idx_session_token (session_token),
    INDEX idx_email (email),
    INDEX idx_phone_number (phone_number),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add SMS-specific columns to otp_rate_limits if not exists
ALTER TABLE otp_rate_limits 
ADD COLUMN IF NOT EXISTS sms_attempts_count INT(11) DEFAULT 0 AFTER attempts_count,
ADD COLUMN IF NOT EXISTS sms_blocked_until TIMESTAMP NULL AFTER blocked_until;

-- 3. Create sms_logs table for tracking SMS delivery
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    otp_code VARCHAR(6) NULL,
    provider VARCHAR(50) DEFAULT 'PhilSMS',
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    provider_response TEXT NULL,
    provider_message_id VARCHAR(100) NULL,
    cost DECIMAL(10,4) NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_otp_code (otp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;