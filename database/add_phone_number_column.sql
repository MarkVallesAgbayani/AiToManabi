-- Add phone_number column to users table for SMS OTP functionality
-- Run this script to add phone number support to your existing users table

USE japanese_lms;

-- Add phone_number column if it doesn't exist
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'phone_number'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) NULL AFTER email',
    'SELECT "Phone number column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for phone number lookups
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_phone_number'
);

SET @index_sql := IF(
    @index_exists = 0,
    'ALTER TABLE users ADD INDEX idx_phone_number (phone_number)',
    'SELECT "Phone number index already exists" as message'
);

PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;

-- Add rate limiting table for OTP attempts
CREATE TABLE IF NOT EXISTS otp_rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(255),
    attempt_type ENUM('email', 'sms') NOT NULL,
    attempts_count INT DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, attempt_type),
    INDEX idx_phone_type (phone_number, attempt_type),
    INDEX idx_email_type (email, attempt_type),
    INDEX idx_last_attempt (last_attempt_at)
);

-- Add cooldown tracking table
CREATE TABLE IF NOT EXISTS otp_cooldowns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attempt_type ENUM('email', 'sms') NOT NULL,
    cooldown_until TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_type (user_id, attempt_type),
    INDEX idx_cooldown_until (cooldown_until)
);

SELECT 'Database schema updated successfully for SMS OTP functionality' as status;
