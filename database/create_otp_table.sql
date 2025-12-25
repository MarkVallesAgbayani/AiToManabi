-- =====================================================
-- OTP (One-Time Password) Table Creation
-- =====================================================
-- This table stores OTP codes for email verification,
-- password reset, and other authentication purposes
-- =====================================================

USE japanese_lms;

-- Create OTPs table
CREATE TABLE IF NOT EXISTS `otps` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `otp_code` VARCHAR(6) NOT NULL,
    `verification_token` VARCHAR(64) NULL,
    `type` ENUM('registration', 'login', 'password_reset', 'email_verification') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_used` BOOLEAN DEFAULT FALSE,
    `used_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_otp_code` (`otp_code`),
    INDEX `idx_verification_token` (`verification_token`),
    INDEX `idx_type` (`type`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_is_used` (`is_used`),
    INDEX `idx_user_type` (`user_id`, `type`),
    INDEX `idx_email_type` (`email`, `type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add email_verified column to users table if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email_verified` BOOLEAN DEFAULT FALSE AFTER `role`;

-- Add phone_number column to users table if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `phone_number` VARCHAR(20) NULL AFTER `email_verified`;

-- Add middle_name column to users table if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `middle_name` VARCHAR(50) NULL AFTER `last_name`;

-- Create index for email_verified for faster queries
CREATE INDEX IF NOT EXISTS `idx_email_verified` ON `users` (`email_verified`);

-- Create index for phone_number for faster queries
CREATE INDEX IF NOT EXISTS `idx_phone_number` ON `users` (`phone_number`);

-- Clean up any existing expired OTPs (optional)
-- DELETE FROM `otps` WHERE `expires_at` < NOW() OR `is_used` = TRUE;

-- Show table structure for verification
DESCRIBE `otps`;
DESCRIBE `users`;
