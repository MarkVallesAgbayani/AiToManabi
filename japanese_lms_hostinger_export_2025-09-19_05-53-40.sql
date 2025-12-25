-- ===================================================================
-- JAPANESE LMS DATABASE EXPORT FOR HOSTINGER
-- Generated on: 2025-09-19 05:53:40 (Philippines Time)
-- Database: japanese_lms
-- Purpose: Fresh export with RBAC preserved, new admin account added
-- ===================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;
SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;

-- Note: Database creation skipped for Hostinger compatibility
-- The database 'u367042766_japanese_lms' should already exist on Hostinger
-- USE `japanese_lms`; -- Commented out for Hostinger

-- -----------------------------------------------------
-- Table structure for `admin_action_logs`
-- -----------------------------------------------------
CREATE TABLE `admin_action_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `admin_action_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_action_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `admin_audit_log`
-- -----------------------------------------------------
CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `admin_audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `admin_dashboard_preferences`
-- -----------------------------------------------------
CREATE TABLE `admin_dashboard_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `theme` enum('light','dark') DEFAULT 'light',
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `notification_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_settings`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_admin_prefs` (`admin_id`),
  CONSTRAINT `admin_dashboard_preferences_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `admin_logs`
-- -----------------------------------------------------
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_detail` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `admin_permissions_by_category`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- -----------------------------------------------------
-- Table structure for `admin_preferences`
-- -----------------------------------------------------
CREATE TABLE `admin_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_visible` tinyint(1) DEFAULT 1,
  `contact_visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id` (`admin_id`),
  KEY `idx_admin_preferences_admin_id` (`admin_id`),
  CONSTRAINT `fk_admin_preferences_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `announcement_banner`
-- -----------------------------------------------------
CREATE TABLE `announcement_banner` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `background_color` varchar(20) DEFAULT '#FFFFFF',
  `text_color` varchar(20) DEFAULT '#1A1A1A',
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `button_color` varchar(20) DEFAULT NULL,
  `button_icon` varchar(50) DEFAULT NULL,
  `discount_value` varchar(20) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `audit_config`
-- -----------------------------------------------------
CREATE TABLE `audit_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `log_level` enum('minimal','standard','detailed') DEFAULT 'standard',
  `retention_days` int(11) DEFAULT 365,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_audit_config` (`action_type`,`resource_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `audit_trail`
-- -----------------------------------------------------
CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `audit_trail_ibfk_1` (`course_id`),
  CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_trail_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `broken_links`
-- -----------------------------------------------------
CREATE TABLE `broken_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` text NOT NULL,
  `reference_page` varchar(255) DEFAULT NULL,
  `reference_module` varchar(255) DEFAULT NULL,
  `first_detected` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_checked` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_code` int(11) NOT NULL,
  `severity` enum('critical','warning') NOT NULL DEFAULT 'warning',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_code` (`status_code`),
  KEY `idx_severity` (`severity`),
  KEY `idx_last_checked` (`last_checked`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `categories`
-- -----------------------------------------------------
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `chapters`
-- -----------------------------------------------------
CREATE TABLE `chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `content_type` enum('text','video') DEFAULT 'text',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `video_url` varchar(500) DEFAULT NULL,
  `video_copyright` text DEFAULT NULL,
  `video_type` enum('url','upload') DEFAULT NULL,
  `video_file_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_chapters_section_id` (`section_id`),
  KEY `idx_chapters_content_type` (`content_type`),
  CONSTRAINT `fk_chapters_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `comprehensive_audit_trail`
-- -----------------------------------------------------
CREATE TABLE `comprehensive_audit_trail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `user_role` enum('student','teacher','admin') NOT NULL,
  `action_type` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','ACCESS','DOWNLOAD','SUBMIT') NOT NULL,
  `action_description` text NOT NULL,
  `resource_type` enum('User Account','Course','Lesson','Chapter','Section','Category','Enrollment','Progress','Quiz','Assignment','Forum Post','Profile','Dashboard','System Config','Materials','Assessment','Application','Payment') NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `resource_name` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `outcome` enum('Success','Failed','Partial') DEFAULT 'Success',
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `old_value_text` text DEFAULT NULL,
  `new_value_text` text DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `device_type` enum('Desktop','Mobile','Tablet') DEFAULT NULL,
  `location_country` varchar(100) DEFAULT NULL,
  `location_city` varchar(100) DEFAULT NULL,
  `location_ip_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`location_ip_info`)),
  `session_id` varchar(255) DEFAULT NULL,
  `request_method` enum('GET','POST','PUT','PATCH','DELETE') DEFAULT NULL,
  `request_url` text DEFAULT NULL,
  `request_headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_headers`)),
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_code` int(11) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `additional_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_context`)),
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `idx_outcome` (`outcome`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_action` (`user_id`,`action_type`),
  KEY `idx_date_user` (`timestamp`,`user_id`),
  KEY `idx_resource_action` (`resource_type`,`action_type`),
  KEY `idx_comprehensive_user_date` (`user_id`,`timestamp`),
  KEY `idx_comprehensive_action_outcome` (`action_type`,`outcome`),
  KEY `idx_comprehensive_resource_date` (`resource_type`,`timestamp`),
  KEY `idx_comprehensive_ip_date` (`ip_address`,`timestamp`),
  CONSTRAINT `comprehensive_audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `course_activity`
-- -----------------------------------------------------
CREATE TABLE `course_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `activity_type` enum('view','complete_section','quiz_attempt','download','forum_post') NOT NULL,
  `activity_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activity_data`)),
  `time_spent_minutes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `idx_user_course` (`user_id`,`course_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `course_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_activity_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `course_category`
-- -----------------------------------------------------
CREATE TABLE `course_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_course_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `course_progress`
-- -----------------------------------------------------
CREATE TABLE `course_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `chapter_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `content_type` enum('course','chapter','section','text','video','quiz') NOT NULL DEFAULT 'course',
  `completed` tinyint(1) DEFAULT 0,
  `student_id` int(11) NOT NULL,
  `completed_sections` int(11) DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  `completion_status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `watch_time_seconds` int(11) DEFAULT 0,
  `total_duration_seconds` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_progress` (`course_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_chapter_id` (`chapter_id`),
  KEY `idx_section_id` (`section_id`),
  KEY `idx_content_type` (`content_type`),
  KEY `idx_completed` (`completed`),
  KEY `idx_completed_at` (`completed_at`),
  KEY `idx_student_course_type` (`student_id`,`course_id`,`content_type`),
  KEY `idx_section_student_type` (`section_id`,`student_id`,`content_type`),
  CONSTRAINT `course_progress_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_progress_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_progress_ibfk_3` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_progress_ibfk_4` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `courses`
-- -----------------------------------------------------
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `course_category_id` int(11) DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the course was archived',
  PRIMARY KEY (`id`),
  KEY `idx_courses_teacher` (`teacher_id`),
  KEY `category_id` (`category_id`),
  KEY `fk_course_category` (`course_category_id`),
  KEY `idx_courses_is_archived_teacher` (`is_archived`,`teacher_id`),
  KEY `idx_courses_archived_at` (`archived_at`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `courses_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_5` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_course_category` FOREIGN KEY (`course_category_id`) REFERENCES `course_category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `current_system_status`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `dashboard_cache`
-- -----------------------------------------------------
CREATE TABLE `dashboard_cache` (
  `cache_key` varchar(255) NOT NULL,
  `cache_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cache_data`)),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cache_key`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `enrollments`
-- -----------------------------------------------------
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`course_id`,`student_id`),
  UNIQUE KEY `course_id` (`course_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_completed_at` (`completed_at`),
  KEY `idx_student_completed` (`student_id`,`completed_at`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `failed_audit_actions`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `failed_login_attempts`
-- -----------------------------------------------------
CREATE TABLE `failed_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` enum('invalid_credentials','account_locked','too_many_attempts') DEFAULT 'invalid_credentials',
  PRIMARY KEY (`id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_attempt_time` (`attempt_time`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `login_logs`
-- -----------------------------------------------------
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_type` varchar(50) DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `otp_cooldowns`
-- -----------------------------------------------------
CREATE TABLE `otp_cooldowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `attempt_type` enum('email','sms') NOT NULL,
  `cooldown_until` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_type` (`user_id`,`attempt_type`),
  KEY `idx_cooldown_until` (`cooldown_until`),
  CONSTRAINT `otp_cooldowns_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `otp_rate_limits`
-- -----------------------------------------------------
CREATE TABLE `otp_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempt_type` enum('email','sms') NOT NULL,
  `attempts_count` int(11) DEFAULT 1,
  `first_attempt_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_blocked` tinyint(1) DEFAULT 0,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`attempt_type`),
  KEY `idx_phone_type` (`phone_number`,`attempt_type`),
  KEY `idx_email_type` (`email`,`attempt_type`),
  KEY `idx_last_attempt` (`last_attempt_at`),
  CONSTRAINT `otp_rate_limits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `otps`
-- -----------------------------------------------------
CREATE TABLE `otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `type` enum('registration','login','password_reset','password_change') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_otps_verification_token` (`verification_token`),
  CONSTRAINT `otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=308 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `page_performance_log`
-- -----------------------------------------------------
CREATE TABLE `page_performance_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_name` varchar(255) NOT NULL,
  `action_name` varchar(255) DEFAULT NULL,
  `full_url` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `load_duration` decimal(8,3) NOT NULL,
  `status` enum('fast','slow','timeout','error') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `memory_usage` varchar(20) DEFAULT NULL,
  `query_count` int(11) DEFAULT 0,
  `response_code` int(3) DEFAULT 200,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_page_name` (`page_name`),
  KEY `idx_load_duration` (`load_duration`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `page_performance_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `payment_details`
-- -----------------------------------------------------
CREATE TABLE `payment_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `payment_details_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `payment_details_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `payment_sessions`
-- -----------------------------------------------------
CREATE TABLE `payment_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checkout_session_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `payment_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `payment_sessions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `payments`
-- -----------------------------------------------------
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `paymongo_id` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `payment_type` varchar(20) NOT NULL DEFAULT 'PAID',
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_status_date` (`payment_status`,`payment_date`),
  KEY `idx_user_status` (`user_id`,`payment_status`),
  KEY `idx_course_status` (`course_id`,`payment_status`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `permissions`
-- -----------------------------------------------------
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `permissions`
LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('1', 'dashboard_view_metrics', 'View dashboard metrics cards (Total Students, Teachers, Modules, Revenue)', 'admin_dashboard', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('2', 'dashboard_view_course_completion', 'View course completion report card', 'admin_dashboard', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('3', 'dashboard_view_user_retention', 'View user retention report card', 'admin_dashboard', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('4', 'dashboard_view_sales_report', 'View sales report card', 'admin_dashboard', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('5', 'course_add_category', 'Add new course category', 'admin_course_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('6', 'course_view_categories', 'View course categories table', 'admin_course_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('7', 'course_edit_category', 'Edit course categories', 'admin_course_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('8', 'course_delete_category', 'Move course categories to trash', 'admin_course_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('9', 'user_add_new', 'Add new user with custom roles', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('10', 'user_reset_password', 'Reset user passwords', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('11', 'user_change_password', 'Change user passwords', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('12', 'user_ban_user', 'Ban/unban users', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('13', 'user_move_to_deleted', 'Move users to deleted status', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('14', 'user_change_role', 'Change user roles', 'admin_user_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('17', 'analytics_export_pdf', 'Export analytics data to PDF', 'admin_usage_analytics', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('18', 'analytics_view_metrics', 'View analytics metrics cards (Active Users, Daily Average, Peak Day, Growth Rate)', 'admin_usage_analytics', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('19', 'analytics_view_active_trends', 'View active users trend card', 'admin_usage_analytics', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('20', 'analytics_view_role_breakdown', 'View user role breakdown card', 'admin_usage_analytics', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('21', 'analytics_view_activity_data', 'View detailed activity data card', 'admin_usage_analytics', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('22', 'user_roles_view_metrics', 'View user roles metric cards (Admins, Teachers, Students)', 'admin_user_roles_report', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('23', 'user_roles_search_filter', 'Search and filter users in roles report', 'admin_user_roles_report', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('26', 'user_roles_export_pdf', 'Export user roles report to PDF', 'admin_user_roles_report', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('27', 'user_roles_view_details', 'View user roles report details', 'admin_user_roles_report', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('28', 'login_activity_view_metrics', 'View login activity metrics (Total Logins Today, Failed Attempts, Broken Links)', 'admin_login_activity', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('29', 'login_activity_view_report', 'View login activity report card', 'admin_login_activity', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('32', 'login_activity_export_pdf', 'Export login activity to PDF', 'admin_login_activity', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('33', 'broken_links_view_report', 'View broken links report card', 'admin_login_activity', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('36', 'broken_links_export_pdf', 'Export broken links to PDF', 'admin_login_activity', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('37', 'security_view_metrics', 'View security metrics (Failed Logins, Suspicious IPs, Admin Actions, New Users)', 'admin_security_warnings', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('38', 'security_view_suspicious_patterns', 'View suspicious login patterns', 'admin_security_warnings', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('39', 'security_view_admin_activity', 'View unusual admin activity', 'admin_security_warnings', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('40', 'security_view_recommendations', 'View security recommendations', 'admin_security_warnings', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('41', 'audit_view_metrics', 'View audit trail metrics (Total Actions, Actions Today, Failed Actions, Active Users)', 'admin_audit_trails', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('42', 'audit_search_filter', 'Search and filter audit trails', 'admin_audit_trails', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('45', 'audit_export_pdf', 'Export audit trails to PDF', 'admin_audit_trails', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('46', 'audit_view_details', 'View audit trail details', 'admin_audit_trails', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('47', 'performance_view_metrics', 'View system performance metrics (System Status, Uptime, Load Time, Total Requests)', 'admin_system_performance', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('48', 'performance_view_uptime_chart', 'View uptime vs downtime chart (7 days)', 'admin_system_performance', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('49', 'performance_view_load_times', 'View average page load times', 'admin_system_performance', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('51', 'error_logs_view_metrics', 'View error log metrics (AI Failures, Response Time, Critical Errors, Total Errors)', 'admin_system_error_logs', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('52', 'error_logs_view_trends', 'View error trends chart (7 days)', 'admin_system_error_logs', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('53', 'error_logs_view_categories', 'View error categories breakdown', 'admin_system_error_logs', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('54', 'error_logs_search_filter', 'Search and filter error logs', 'admin_system_error_logs', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('57', 'error_logs_export_pdf', 'Export error logs to PDF', 'admin_system_error_logs', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('58', 'payment_view_history', 'View payment history table', 'admin_payment_history', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('59', 'content_manage_announcement', 'Manage announcement banner', 'admin_content_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('60', 'content_manage_terms', 'Manage terms and conditions', 'admin_content_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('61', 'content_manage_privacy', 'Manage privacy policy', 'admin_content_management', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('62', 'nav_payment_history', 'Access to payment history management', 'admin_navigation', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('63', 'nav_content_management', 'Access to content management system', 'admin_navigation', '2025-09-15 22:16:20');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('127', 'nav_dashboard', 'Access to admin dashboard', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('128', 'nav_course_management', 'Access to course management system', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('129', 'nav_user_management', 'Access to user management system', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('130', 'nav_reports', 'Access to reports section', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('131', 'nav_usage_analytics', 'Access to usage analytics reports', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('132', 'nav_user_roles_report', 'Access to user roles breakdown report', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('133', 'nav_login_activity', 'Access to login activity reports', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('134', 'nav_security_warnings', 'Access to security warnings', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('135', 'nav_audit_trails', 'Access to audit trails', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('136', 'nav_performance_logs', 'Access to performance logs', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('137', 'nav_error_logs', 'Access to system error logs', 'admin_navigation', '2025-09-15 23:25:17');
INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES ('138', 'nav_payments', 'Access to payment history', 'admin_navigation', '2025-09-15 23:25:17');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

-- -----------------------------------------------------
-- Table structure for `placement_result`
-- -----------------------------------------------------
CREATE TABLE `placement_result` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `total_questions` int(11) DEFAULT 0,
  `correct_answers` int(11) DEFAULT 0,
  `percentage_score` decimal(5,2) DEFAULT 0.00,
  `difficulty_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`difficulty_scores`)),
  `recommended_level` enum('beginner','intermediate','advanced') DEFAULT NULL,
  `recommended_course_id` int(11) DEFAULT NULL,
  `detailed_feedback` text DEFAULT NULL,
  `status` enum('started','in_progress','completed','abandoned') DEFAULT 'started',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  UNIQUE KEY `unique_student_test` (`student_id`,`test_id`),
  KEY `test_id` (`test_id`),
  KEY `recommended_course_id` (`recommended_course_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_recommended_level` (`recommended_level`),
  KEY `idx_score` (`percentage_score`),
  CONSTRAINT `placement_result_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `placement_result_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `placement_test` (`id`) ON DELETE CASCADE,
  CONSTRAINT `placement_result_ibfk_3` FOREIGN KEY (`recommended_course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `placement_session`
-- -----------------------------------------------------
CREATE TABLE `placement_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `session_type` enum('first_login','first_registration','test_attempt') NOT NULL,
  `session_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`session_data`)),
  `status` enum('active','completed','expired') DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_student` (`student_id`),
  KEY `idx_token` (`session_token`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`session_type`),
  CONSTRAINT `placement_session_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `placement_test`
-- -----------------------------------------------------
CREATE TABLE `placement_test` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT 'Japanese Language Placement Test',
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`questions`)),
  `design_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`design_settings`)),
  `page_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`page_content`)),
  `module_assignments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`module_assignments`)),
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0 COMMENT 'Whether the test is archived (0 = not archived, 1 = archived)',
  PRIMARY KEY (`id`),
  KEY `idx_published` (`is_published`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `placement_test_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `placement_test_pages`
-- -----------------------------------------------------
CREATE TABLE `placement_test_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_type` enum('welcome','instructions','questions','completion','custom') NOT NULL,
  `page_key` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `page_order` int(11) NOT NULL DEFAULT 0,
  `question_count` int(11) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_key` (`page_key`),
  KEY `idx_page_order` (`page_order`),
  KEY `idx_page_type` (`page_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `privacy_policy`
-- -----------------------------------------------------
CREATE TABLE `privacy_policy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `progress`
-- -----------------------------------------------------
CREATE TABLE `progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_progress` (`student_id`,`section_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `quiz_answers`
-- -----------------------------------------------------
CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `quiz_attempts`
-- -----------------------------------------------------
CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `total_points` int(11) NOT NULL DEFAULT 0,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `quiz_choices`
-- -----------------------------------------------------
CREATE TABLE `quiz_choices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_choices_question` (`question_id`),
  CONSTRAINT `quiz_choices_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `quiz_questions`
-- -----------------------------------------------------
CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','fill_blank','word_definition','pronunciation','sentence_translation') NOT NULL DEFAULT 'multiple_choice',
  `word_definition_pairs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`word_definition_pairs`)),
  `translation_pairs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`translation_pairs`)),
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `score` int(11) NOT NULL DEFAULT 1,
  `word` varchar(255) DEFAULT NULL COMMENT 'Japanese word for pronunciation',
  `romaji` varchar(255) DEFAULT NULL COMMENT 'Romaji pronunciation',
  `meaning` text DEFAULT NULL COMMENT 'English meaning',
  `audio_url` varchar(500) DEFAULT NULL COMMENT 'Audio file URL for pronunciation',
  `accuracy_threshold` decimal(5,2) DEFAULT 70.00,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  PRIMARY KEY (`id`),
  KEY `idx_questions_quiz` (`quiz_id`),
  CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `quizzes`
-- -----------------------------------------------------
CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `passing_score` int(11) NOT NULL DEFAULT 70,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `time_limit` int(11) DEFAULT NULL,
  `total_points` int(11) DEFAULT 0,
  `max_retakes` int(11) DEFAULT 3 COMMENT 'Maximum retakes allowed (-1=unlimited, 0=none, >0=specific number)',
  PRIMARY KEY (`id`),
  KEY `idx_quizzes_section` (`section_id`),
  CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `recent_error_activities`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `role_template_permissions`
-- -----------------------------------------------------
CREATE TABLE `role_template_permissions` (
  `template_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`template_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_template_permissions_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `role_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_template_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `role_template_permissions`
LOCK TABLES `role_template_permissions` WRITE;
/*!40000 ALTER TABLE `role_template_permissions` DISABLE KEYS */;
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '1', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '2', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '3', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '4', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '5', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '6', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '7', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '8', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '9', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '10', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '11', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '12', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '13', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '14', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '17', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '18', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '19', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '20', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '21', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '22', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '23', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '26', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '27', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '28', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '29', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '32', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '33', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '36', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '37', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '38', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '39', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '40', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '41', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '42', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '45', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '46', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '47', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '48', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '49', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '51', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '52', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '53', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '54', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '57', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '58', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '59', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '60', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '61', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '62', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '63', '2025-09-15 22:17:40');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '127', '2025-09-15 23:25:17');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '128', '2025-09-15 23:25:17');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '129', '2025-09-15 23:25:17');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '130', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '131', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '132', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '133', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '134', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '135', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '136', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '137', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('1', '138', '2025-09-15 23:25:18');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '1', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '2', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '3', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '4', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '5', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '6', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '7', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '8', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '9', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '10', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '11', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '12', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '13', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '14', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '17', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '18', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '19', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '20', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '21', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '22', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '23', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '26', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '27', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '28', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '29', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '32', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '33', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '36', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '37', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '38', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '39', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '40', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '41', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '42', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '45', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '46', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '47', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '48', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '49', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '51', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '52', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '53', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '54', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '57', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '58', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '59', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '60', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '61', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '62', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '63', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '127', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '128', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '129', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '130', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '131', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '132', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '133', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '134', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '135', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '136', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '137', '2025-09-16 01:35:59');
INSERT INTO `role_template_permissions` (`template_id`, `permission_id`, `created_at`) VALUES ('2', '138', '2025-09-16 01:35:59');
/*!40000 ALTER TABLE `role_template_permissions` ENABLE KEYS */;
UNLOCK TABLES;

-- -----------------------------------------------------
-- Table structure for `role_templates`
-- -----------------------------------------------------
CREATE TABLE `role_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `role_templates`
LOCK TABLES `role_templates` WRITE;
/*!40000 ALTER TABLE `role_templates` DISABLE KEYS */;
INSERT INTO `role_templates` (`id`, `name`, `description`, `created_at`) VALUES ('1', 'Full Admin Access', 'Complete administrative access to all system features and reports', '2025-09-15 22:17:39');
INSERT INTO `role_templates` (`id`, `name`, `description`, `created_at`) VALUES ('2', 'Default Permission', 'Full Admin Access - All Permissions', '2025-09-16 01:30:24');
/*!40000 ALTER TABLE `role_templates` ENABLE KEYS */;
UNLOCK TABLES;

-- -----------------------------------------------------
-- Table structure for `sections`
-- -----------------------------------------------------
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sections_course_id` (`course_id`),
  CONSTRAINT `fk_sections_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `student_preferences`
-- -----------------------------------------------------
CREATE TABLE `student_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_visible` tinyint(1) DEFAULT 1,
  `contact_visible` tinyint(1) DEFAULT 1,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_preferences` (`student_id`),
  KEY `idx_student_preferences_student_id` (`student_id`),
  KEY `idx_student_preferences_visible` (`profile_visible`,`contact_visible`),
  CONSTRAINT `student_preferences_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `system_error_log`
-- -----------------------------------------------------
CREATE TABLE `system_error_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `error_type` varchar(100) NOT NULL,
  `module_name` varchar(255) DEFAULT NULL,
  `category` enum('ai_failure','response_time','backend_error','system_error') NOT NULL DEFAULT 'system_error',
  `error_level` enum('notice','warning','error','fatal') NOT NULL DEFAULT 'error',
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'warning',
  `error_message` text NOT NULL,
  `user_query` text DEFAULT NULL COMMENT 'Original user query for AI failures',
  `ai_response` text DEFAULT NULL COMMENT 'AI response (if any)',
  `error_file` varchar(500) DEFAULT NULL,
  `error_line` int(11) DEFAULT NULL,
  `stack_trace` longtext DEFAULT NULL,
  `root_cause` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `status` enum('failed','retried','resolved','logged') NOT NULL DEFAULT 'logged',
  `retry_count` int(11) DEFAULT 0,
  `alert_sent` tinyint(1) DEFAULT 0,
  `request_url` text DEFAULT NULL,
  `endpoint` varchar(500) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL COMMENT 'Response time in milliseconds',
  `expected_response_time_ms` int(11) DEFAULT NULL COMMENT 'Expected response time in milliseconds',
  `request_method` varchar(10) DEFAULT NULL,
  `post_data` longtext DEFAULT NULL,
  `server_vars` longtext DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_error_type` (`error_type`),
  KEY `idx_error_level` (`error_level`),
  KEY `idx_occurred_at` (`occurred_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_category` (`category`),
  KEY `idx_severity` (`severity`),
  KEY `idx_module_name` (`module_name`),
  KEY `idx_status` (`status`),
  KEY `idx_response_time` (`response_time_ms`),
  KEY `idx_category_severity` (`category`,`severity`),
  KEY `idx_occurred_status` (`occurred_at`,`status`),
  CONSTRAINT `system_error_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_error_log_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `system_health_metrics`
-- -----------------------------------------------------
CREATE TABLE `system_health_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_type` enum('cpu','memory','disk','database','response_time') NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(20) NOT NULL,
  `threshold_warning` decimal(10,2) DEFAULT NULL,
  `threshold_critical` decimal(10,2) DEFAULT NULL,
  `status` enum('healthy','warning','critical') NOT NULL DEFAULT 'healthy',
  `server_name` varchar(100) DEFAULT 'main',
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_type` (`metric_type`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `system_notifications`
-- -----------------------------------------------------
CREATE TABLE `system_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `target_admin_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_target_admin` (`target_admin_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `system_notifications_ibfk_1` FOREIGN KEY (`target_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `system_uptime_log`
-- -----------------------------------------------------
CREATE TABLE `system_uptime_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('uptime','downtime') NOT NULL DEFAULT 'uptime',
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `status` enum('active','completed') NOT NULL DEFAULT 'active',
  `error_message` text DEFAULT NULL,
  `root_cause` varchar(255) DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `server_ip` varchar(45) DEFAULT NULL,
  `monitored_by` varchar(100) DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `teacher_notification_preferences`
-- -----------------------------------------------------
CREATE TABLE `teacher_notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `preference_category` varchar(50) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `notification_method` enum('in_app','email') DEFAULT 'in_app',
  `priority_level` enum('critical','high','medium','low') DEFAULT 'medium',
  `frequency` enum('real_time','daily_digest','weekly_summary') DEFAULT 'real_time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_preference` (`teacher_id`,`preference_category`,`preference_key`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_category` (`preference_category`),
  KEY `idx_preferences_teacher_category` (`teacher_id`,`preference_category`),
  CONSTRAINT `teacher_notification_preferences_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `teacher_notification_settings`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `teacher_notifications`
-- -----------------------------------------------------
CREATE TABLE `teacher_notifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `priority` enum('critical','high','medium','low') DEFAULT 'medium',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `related_id` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_category` (`category`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_priority` (`priority`),
  KEY `idx_notifications_teacher_unread` (`teacher_id`,`is_read`,`created_at`),
  KEY `idx_notifications_expires` (`expires_at`),
  CONSTRAINT `teacher_notifications_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `teacher_preferences`
-- -----------------------------------------------------
CREATE TABLE `teacher_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `profile_visible` tinyint(1) DEFAULT 1,
  `contact_visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher` (`teacher_id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_display_name` (`display_name`),
  KEY `idx_profile_visible` (`profile_visible`),
  CONSTRAINT `teacher_preferences_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores teacher profile information and preferences';

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `teacher_question_types`
-- -----------------------------------------------------
CREATE TABLE `teacher_question_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `question_type_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `temp_checkout_mapping`
-- -----------------------------------------------------
CREATE TABLE `temp_checkout_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_id` varchar(255) NOT NULL,
  `checkout_session_id` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `temp_id` (`temp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `temp_password_changes`
-- -----------------------------------------------------
CREATE TABLE `temp_password_changes` (
  `user_id` int(11) NOT NULL,
  `new_password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `temp_password_changes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `terms_conditions`
-- -----------------------------------------------------
CREATE TABLE `terms_conditions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `text_progress`
-- -----------------------------------------------------
CREATE TABLE `text_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_text_progress` (`student_id`,`chapter_id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `section_id` (`section_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `text_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `text_progress_ibfk_2` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`),
  CONSTRAINT `text_progress_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `text_progress_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `unified_progress_summary`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `uptime_statistics`
-- -----------------------------------------------------
CREATE TABLE `uptime_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `uptime_seconds` int(11) DEFAULT 0,
  `downtime_seconds` int(11) DEFAULT 0,
  `downtime_incidents` int(11) DEFAULT 0,
  `uptime_percentage` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`date`),
  KEY `idx_date` (`date`),
  KEY `idx_uptime_percentage` (`uptime_percentage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `user_activity_log`
-- -----------------------------------------------------
CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `resource_type` varchar(100) DEFAULT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` enum('Desktop','Mobile','Tablet') DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_resource_type` (`resource_type`),
  CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2977 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `user_admin_permissions_summary`
-- -----------------------------------------------------
-- Note: This view will be created after all required tables are set up

-- -----------------------------------------------------
-- Table structure for `user_permissions`
-- -----------------------------------------------------
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_name`),
  KEY `template_id` (`template_id`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_user_permissions_user_id` (`user_id`),
  KEY `idx_user_permissions_name` (`permission_name`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `role_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_permissions_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `user_permissions_view`
-- -----------------------------------------------------
CREATE TABLE `user_permissions_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_source` enum('custom','template') NOT NULL,
  `template_name` varchar(100) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_permission_name` (`permission_name`),
  KEY `idx_permission_source` (`permission_source`),
  KEY `idx_template_name` (`template_name`),
  KEY `granted_by` (`granted_by`),
  CONSTRAINT `user_permissions_view_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_view_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `user_roles`
-- -----------------------------------------------------
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`template_id`),
  KEY `idx_user_roles_user_id` (`user_id`),
  KEY `idx_user_roles_template_id` (`template_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `role_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `user_sessions`
-- -----------------------------------------------------
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `time_spent_minutes` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` enum('desktop','mobile','tablet') DEFAULT 'desktop',
  `browser` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive','pending','locked','suspended','banned','password_reset','deleted') NOT NULL DEFAULT 'active',
  `is_first_login` tinyint(1) NOT NULL DEFAULT 1,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `login_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_role_login` (`role`,`last_login_at`),
  KEY `idx_status_updated` (`status`,`updated_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  KEY `idx_phone_number` (`phone_number`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `v_user_activity_analytics`
-- -----------------------------------------------------
CREATE TABLE `v_user_activity_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_activity_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activity_date` date NOT NULL,
  `activity_year` int(11) NOT NULL,
  `activity_month` int(11) NOT NULL,
  `activity_week` int(11) NOT NULL,
  `activity_day_of_week` int(11) NOT NULL,
  `activity_hour` int(11) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_action` (`action`),
  KEY `idx_activity_date` (`activity_date`),
  KEY `idx_activity_year` (`activity_year`),
  KEY `idx_activity_month` (`activity_month`),
  KEY `idx_activity_week` (`activity_week`),
  KEY `idx_activity_day_of_week` (`activity_day_of_week`),
  KEY `idx_activity_hour` (`activity_hour`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `v_user_activity_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Table structure for `video_progress`
-- -----------------------------------------------------
CREATE TABLE `video_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  `watch_time_seconds` int(11) DEFAULT 0,
  `total_duration_seconds` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_video_progress` (`student_id`,`chapter_id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `section_id` (`section_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `video_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `video_progress_ibfk_2` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`),
  CONSTRAINT `video_progress_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `video_progress_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table data truncated for fresh start

-- -----------------------------------------------------
-- Create all views after tables are set up
-- -----------------------------------------------------

-- Create admin_permissions_by_category view
CREATE VIEW `admin_permissions_by_category` AS select `p`.`category` AS `category`,`p`.`name` AS `permission_name`,`p`.`description` AS `description`,count(`rtp`.`template_id`) AS `template_count`,count(`up`.`user_id`) AS `custom_assignment_count` from ((`permissions` `p` left join `role_template_permissions` `rtp` on(`p`.`id` = `rtp`.`permission_id`)) left join `user_permissions` `up` on(`p`.`name` = `up`.`permission_name`)) where `p`.`category` like 'admin_%' group by `p`.`category`,`p`.`name`,`p`.`description` order by `p`.`category`,`p`.`name`;

-- Create current_system_status view
CREATE VIEW `current_system_status` AS select 'uptime' AS `status_type`,coalesce(timestampdiff(SECOND,(select `system_uptime_log`.`start_time` from `system_uptime_log` where `system_uptime_log`.`event_type` = 'uptime' and `system_uptime_log`.`status` = 'active' order by `system_uptime_log`.`start_time` desc limit 1),current_timestamp()),0) AS `current_duration_seconds`,(select `system_uptime_log`.`start_time` from `system_uptime_log` where `system_uptime_log`.`event_type` = 'uptime' and `system_uptime_log`.`status` = 'active' order by `system_uptime_log`.`start_time` desc limit 1) AS `current_start_time`,(select count(0) from `system_uptime_log` where `system_uptime_log`.`event_type` = 'downtime' and cast(`system_uptime_log`.`start_time` as date) = curdate()) AS `downtime_events_today`;

-- Create failed_audit_actions view
CREATE VIEW `failed_audit_actions` AS select `cat`.`id` AS `id`,`cat`.`timestamp` AS `timestamp`,`cat`.`user_id` AS `user_id`,`cat`.`username` AS `username`,`cat`.`user_role` AS `user_role`,`cat`.`action_type` AS `action_type`,`cat`.`action_description` AS `action_description`,`cat`.`resource_type` AS `resource_type`,`cat`.`resource_id` AS `resource_id`,`cat`.`resource_name` AS `resource_name`,`cat`.`ip_address` AS `ip_address`,`cat`.`outcome` AS `outcome`,`cat`.`old_value` AS `old_value`,`cat`.`new_value` AS `new_value`,`cat`.`old_value_text` AS `old_value_text`,`cat`.`new_value_text` AS `new_value_text`,`cat`.`device_info` AS `device_info`,`cat`.`browser_name` AS `browser_name`,`cat`.`operating_system` AS `operating_system`,`cat`.`device_type` AS `device_type`,`cat`.`location_country` AS `location_country`,`cat`.`location_city` AS `location_city`,`cat`.`location_ip_info` AS `location_ip_info`,`cat`.`session_id` AS `session_id`,`cat`.`request_method` AS `request_method`,`cat`.`request_url` AS `request_url`,`cat`.`request_headers` AS `request_headers`,`cat`.`request_payload` AS `request_payload`,`cat`.`response_code` AS `response_code`,`cat`.`response_time_ms` AS `response_time_ms`,`cat`.`error_message` AS `error_message`,`cat`.`additional_context` AS `additional_context`,`u`.`email` AS `user_email` from (`comprehensive_audit_trail` `cat` join `users` `u` on(`cat`.`user_id` = `u`.`id`)) where `cat`.`outcome` = 'Failed' order by `cat`.`timestamp` desc;

-- Create recent_error_activities view
CREATE VIEW `recent_error_activities` AS select `system_error_log`.`id` AS `id`,`system_error_log`.`category` AS `category`,`system_error_log`.`severity` AS `severity`,`system_error_log`.`error_type` AS `error_type`,`system_error_log`.`error_message` AS `error_message`,`system_error_log`.`module_name` AS `module_name`,`system_error_log`.`user_id` AS `user_id`,`system_error_log`.`occurred_at` AS `occurred_at`,`system_error_log`.`status` AS `status`,case when `system_error_log`.`category` = 'ai_failure' then concat('AI Failure: ',coalesce(`system_error_log`.`error_type`,'Unknown')) when `system_error_log`.`category` = 'response_time' then concat('Slow Response: ',coalesce(`system_error_log`.`endpoint`,`system_error_log`.`module_name`,'Unknown')) when `system_error_log`.`category` = 'backend_error' then concat('Backend Error: ',coalesce(`system_error_log`.`module_name`,`system_error_log`.`error_type`,'Unknown')) else concat('System Error: ',coalesce(`system_error_log`.`error_type`,'Unknown')) end AS `activity_message`,case when `system_error_log`.`severity` = 'critical' then 'error' when `system_error_log`.`severity` = 'warning' then 'warning' else 'info' end AS `activity_type` from `system_error_log` where `system_error_log`.`occurred_at` >= current_timestamp() - interval 24 hour order by `system_error_log`.`occurred_at` desc limit 10;

-- Create teacher_notification_settings view
CREATE VIEW `teacher_notification_settings` AS select `tnp`.`teacher_id` AS `teacher_id`,`u`.`username` AS `username`,`u`.`email` AS `email`,`tnp`.`preference_category` AS `preference_category`,`tnp`.`preference_key` AS `preference_key`,`tnp`.`is_enabled` AS `is_enabled`,`tnp`.`notification_method` AS `notification_method`,`tnp`.`priority_level` AS `priority_level`,`tnp`.`frequency` AS `frequency`,`tnp`.`updated_at` AS `updated_at` from (`teacher_notification_preferences` `tnp` join `users` `u` on(`tnp`.`teacher_id` = `u`.`id`)) where `u`.`role` = 'teacher';

-- Create unified_progress_summary view
CREATE VIEW `unified_progress_summary` AS select `cp`.`id` AS `id`,`cp`.`student_id` AS `student_id`,`cp`.`course_id` AS `course_id`,`cp`.`chapter_id` AS `chapter_id`,`cp`.`section_id` AS `section_id`,`cp`.`content_type` AS `content_type`,`cp`.`completed` AS `completed`,`cp`.`completed_sections` AS `completed_sections`,`cp`.`completion_percentage` AS `completion_percentage`,`cp`.`completion_status` AS `completion_status`,`cp`.`last_accessed_at` AS `last_accessed_at`,`cp`.`completed_at` AS `completed_at`,`cp`.`watch_time_seconds` AS `watch_time_seconds`,`cp`.`total_duration_seconds` AS `total_duration_seconds`,`cp`.`created_at` AS `created_at`,`cp`.`updated_at` AS `updated_at`,`c`.`title` AS `course_title`,`ch`.`title` AS `chapter_title`,`s`.`title` AS `section_title`,`u`.`username` AS `student_name`,`u`.`email` AS `student_email`,case when `cp`.`completed_at` is not null and `cp`.`created_at` is not null then to_days(`cp`.`completed_at`) - to_days(`cp`.`created_at`) else NULL end AS `days_to_complete`,case when `cp`.`completed_at` is not null then to_days(current_timestamp()) - to_days(`cp`.`completed_at`) else NULL end AS `days_since_completion` from ((((`course_progress` `cp` left join `courses` `c` on(`cp`.`course_id` = `c`.`id`)) left join `chapters` `ch` on(`cp`.`chapter_id` = `ch`.`id`)) left join `sections` `s` on(`cp`.`section_id` = `s`.`id`)) left join `users` `u` on(`cp`.`student_id` = `u`.`id`));

-- Create user_admin_permissions_summary view
CREATE VIEW `user_admin_permissions_summary` AS select `u`.`id` AS `user_id`,`u`.`username` AS `username`,`u`.`role` AS `role`,`rt`.`name` AS `template_name`,count(distinct `p`.`name`) AS `total_permissions`,group_concat(distinct `p`.`category` order by `p`.`category` ASC separator ',') AS `permission_categories` from ((((`users` `u` left join `user_roles` `ur` on(`u`.`id` = `ur`.`user_id`)) left join `role_templates` `rt` on(`ur`.`template_id` = `rt`.`id`)) left join `role_template_permissions` `rtp` on(`rt`.`id` = `rtp`.`template_id`)) left join `permissions` `p` on(`rtp`.`permission_id` = `p`.`id`)) where `u`.`role` = 'admin' and `p`.`category` like 'admin_%' group by `u`.`id`,`u`.`username`,`u`.`role`,`rt`.`name`;

-- -----------------------------------------------------
-- Adding new admin account: adminmark@aitomanabi.com
-- -----------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password`, `role`, `first_name`, `last_name`, `created_at`, `updated_at`) VALUES
('adminmark', 'adminmark@aitomanabi.com', '$2y$10$Glz/ua3ybICv0ssx3dhVa.fjKZfpwyOs2rgESH1ZSwKyiDg6Qruc.', 'admin', 'Admin', 'Mark', NOW(), NOW());

-- Assign Full Admin Access role template to new admin
INSERT INTO `user_roles` (`user_id`, `template_id`, `created_at`)
SELECT u.id, rt.id, NOW()
FROM `users` u, `role_templates` rt
WHERE u.email = 'adminmark@aitomanabi.com' AND rt.name = 'Full Admin Access';

-- Note: The new admin will have all permissions through the role template
-- This includes all 62+ permissions defined in the system


-- Restore MySQL settings
SET SQL_NOTES=@OLD_SQL_NOTES;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
