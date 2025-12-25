-- ===================================================================
-- TEACHER NOTIFICATION PREFERENCES SYSTEM
-- ===================================================================
-- This creates a comprehensive notification preferences system for teachers
-- ===================================================================

-- 1. CREATE TEACHER NOTIFICATION PREFERENCES TABLE
CREATE TABLE IF NOT EXISTS `teacher_notification_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `preference_category` VARCHAR(50) NOT NULL,
    `preference_key` VARCHAR(100) NOT NULL,
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `notification_method` ENUM('in_app', 'email') DEFAULT 'in_app',
    `priority_level` ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
    `frequency` ENUM('real_time', 'daily_digest', 'weekly_summary') DEFAULT 'real_time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_teacher_preference` (`teacher_id`, `preference_category`, `preference_key`),
    INDEX `idx_teacher_id` (`teacher_id`),
    INDEX `idx_category` (`preference_category`),
    
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CREATE NOTIFICATIONS TABLE FOR READ/UNREAD STATUS
CREATE TABLE IF NOT EXISTS `teacher_notifications` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `action_url` VARCHAR(500) NULL,
    `priority` ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `related_id` VARCHAR(100) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    
    INDEX `idx_teacher_id` (`teacher_id`),
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_category` (`category`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_priority` (`priority`),
    
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. INSERT DEFAULT NOTIFICATION PREFERENCES FOR ALL TEACHERS
-- This will be populated when teachers first access settings
INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'new_enrollments' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'high' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'course_completions' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'high' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'quiz_completions' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'low_performance_alerts' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'high' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'struggling_students' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'high' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_progress' as preference_category,
    'weekly_progress_summaries' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'weekly_summary' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_engagement' as preference_category,
    'inactive_students' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'daily_digest' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'student_engagement' as preference_category,
    'high_performing_students' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'course_management' as preference_category,
    'course_milestones' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'course_management' as preference_category,
    'course_status_changes' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'high' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'system_administrative' as preference_category,
    'security_alerts' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'critical' as priority_level,
    'real_time' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'reporting_analytics' as preference_category,
    'daily_activity_summaries' as preference_key,
    FALSE as is_enabled,
    'in_app' as notification_method,
    'low' as priority_level,
    'daily_digest' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO `teacher_notification_preferences` (`teacher_id`, `preference_category`, `preference_key`, `is_enabled`, `notification_method`, `priority_level`, `frequency`) 
SELECT 
    u.id as teacher_id,
    'reporting_analytics' as preference_category,
    'weekly_engagement_reports' as preference_key,
    TRUE as is_enabled,
    'in_app' as notification_method,
    'medium' as priority_level,
    'weekly_summary' as frequency
FROM users u 
WHERE u.role = 'teacher'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- 4. CREATE INDEXES FOR BETTER PERFORMANCE
CREATE INDEX IF NOT EXISTS `idx_notifications_teacher_unread` ON `teacher_notifications` (`teacher_id`, `is_read`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_notifications_expires` ON `teacher_notifications` (`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_preferences_teacher_category` ON `teacher_notification_preferences` (`teacher_id`, `preference_category`);

-- 5. CREATE VIEW FOR EASY NOTIFICATION PREFERENCE QUERIES
CREATE OR REPLACE VIEW `teacher_notification_settings` AS
SELECT 
    tnp.teacher_id,
    u.username,
    u.email,
    tnp.preference_category,
    tnp.preference_key,
    tnp.is_enabled,
    tnp.notification_method,
    tnp.priority_level,
    tnp.frequency,
    tnp.updated_at
FROM teacher_notification_preferences tnp
JOIN users u ON tnp.teacher_id = u.id
WHERE u.role = 'teacher';

-- 6. CREATE STORED PROCEDURE FOR GETTING TEACHER NOTIFICATIONS WITH PREFERENCES
DELIMITER //
CREATE PROCEDURE GetTeacherNotificationsWithPreferences(IN p_teacher_id INT, IN p_limit INT)
BEGIN
    SELECT 
        tn.id,
        tn.notification_type,
        tn.category,
        tn.title,
        tn.message,
        tn.action_url,
        tn.priority,
        tn.is_read,
        tn.read_at,
        tn.related_id,
        tn.created_at,
        tnp.is_enabled as preference_enabled,
        tnp.priority_level as preference_priority,
        tnp.frequency as preference_frequency
    FROM teacher_notifications tn
    LEFT JOIN teacher_notification_preferences tnp ON (
        tn.teacher_id = tnp.teacher_id 
        AND tn.notification_type = tnp.preference_key
        AND tn.category = tnp.preference_category
    )
    WHERE tn.teacher_id = p_teacher_id
        AND (tnp.is_enabled IS NULL OR tnp.is_enabled = TRUE)
        AND (tn.expires_at IS NULL OR tn.expires_at > NOW())
    ORDER BY 
        CASE tn.priority 
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        tn.created_at DESC
    LIMIT p_limit;
END //
DELIMITER ;

-- 7. CREATE TRIGGER TO AUTO-CLEANUP EXPIRED NOTIFICATIONS
DELIMITER //
CREATE TRIGGER cleanup_expired_notifications
BEFORE INSERT ON teacher_notifications
FOR EACH ROW
BEGIN
    -- Auto-delete notifications older than 30 days
    DELETE FROM teacher_notifications 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;
