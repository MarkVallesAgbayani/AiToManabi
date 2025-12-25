-- ===================================================================
-- UPDATE TEACHER_PREFERENCES TABLE
-- ===================================================================
-- This script will clean up the teacher_preferences table and ensure
-- it has only the necessary columns for profile management
-- ===================================================================

USE japanese_lms;

-- First, let's see what columns currently exist
-- (This is just for reference - we'll recreate the table)

-- Drop the existing table and recreate it with only necessary columns
DROP TABLE IF EXISTS `teacher_preferences`;

-- Create the new, clean teacher_preferences table
CREATE TABLE `teacher_preferences` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `teacher_id` INT NOT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `display_name` VARCHAR(100) DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `languages` VARCHAR(255) DEFAULT NULL,
    `profile_visible` BOOLEAN DEFAULT TRUE,
    `contact_visible` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_teacher` (`teacher_id`),
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_teacher_id` ON `teacher_preferences`(`teacher_id`);
CREATE INDEX `idx_display_name` ON `teacher_preferences`(`display_name`);
CREATE INDEX `idx_profile_visible` ON `teacher_preferences`(`profile_visible`);

-- Insert a comment to document the table purpose
ALTER TABLE `teacher_preferences` COMMENT = 'Stores teacher profile information and preferences';

-- Show the final table structure
DESCRIBE `teacher_preferences`;
