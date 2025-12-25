-- ===================================================================
-- COMPREHENSIVE AUDIT TRAIL SYSTEM
-- ===================================================================
-- This creates a unified audit system that tracks ALL user actions
-- across your Japanese Learning Platform
-- ===================================================================

-- 1. CREATE COMPREHENSIVE AUDIT TRAIL TABLE
-- This will be the main table for all audit entries
CREATE TABLE IF NOT EXISTS `comprehensive_audit_trail` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `user_id` INT NOT NULL,
    `username` VARCHAR(255) NOT NULL, -- Store username for quick access
    `user_role` ENUM('student', 'teacher', 'admin') NOT NULL,
    `action_type` ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'ACCESS', 'DOWNLOAD', 'SUBMIT') NOT NULL,
    `action_description` TEXT NOT NULL,
    `resource_type` ENUM('User Account', 'Course', 'Lesson', 'Chapter', 'Section', 'Category', 'Enrollment', 'Progress', 'Quiz', 'Assignment', 'Forum Post', 'Profile', 'Dashboard', 'System Config', 'Materials', 'Assessment', 'Application', 'Payment') NOT NULL,
    `resource_id` VARCHAR(255) NULL, -- Can store "Course ID: 45" or "User ID: 123"
    `resource_name` VARCHAR(500) NULL, -- Human readable resource name
    `ip_address` VARCHAR(45) NOT NULL,
    `outcome` ENUM('Success', 'Failed', 'Partial') DEFAULT 'Success',
    `old_value` JSON NULL, -- Store old values as JSON for complex data
    `new_value` JSON NULL, -- Store new values as JSON for complex data
    `old_value_text` TEXT NULL, -- Human readable old value
    `new_value_text` TEXT NULL, -- Human readable new value
    `device_info` TEXT NULL, -- User agent string
    `browser_name` VARCHAR(100) NULL, -- Parsed browser name
    `operating_system` VARCHAR(100) NULL, -- Parsed OS
    `device_type` ENUM('Desktop', 'Mobile', 'Tablet') NULL,
    `location_country` VARCHAR(100) NULL,
    `location_city` VARCHAR(100) NULL,
    `location_ip_info` JSON NULL, -- Store full IP geolocation data
    `session_id` VARCHAR(255) NULL,
    `request_method` ENUM('GET', 'POST', 'PUT', 'PATCH', 'DELETE') NULL,
    `request_url` TEXT NULL,
    `request_headers` JSON NULL,
    `request_payload` JSON NULL,
    `response_code` INT NULL,
    `response_time_ms` INT NULL,
    `error_message` TEXT NULL,
    `additional_context` JSON NULL, -- Store any extra context data
    
    -- Indexes for performance
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_username` (`username`),
    INDEX `idx_action_type` (`action_type`),
    INDEX `idx_resource_type` (`resource_type`),
    INDEX `idx_outcome` (`outcome`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_user_action` (`user_id`, `action_type`),
    INDEX `idx_date_user` (`timestamp`, `user_id`),
    INDEX `idx_resource_action` (`resource_type`, `action_type`),
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CREATE AUDIT CONFIGURATION TABLE
-- Store settings for what actions to log
CREATE TABLE IF NOT EXISTS `audit_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action_type` VARCHAR(50) NOT NULL,
    `resource_type` VARCHAR(50) NOT NULL,
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `log_level` ENUM('minimal', 'standard', 'detailed') DEFAULT 'standard',
    `retention_days` INT DEFAULT 365,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_audit_config` (`action_type`, `resource_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CREATE AUDIT SUMMARY TABLE
-- For faster dashboard queries
CREATE TABLE IF NOT EXISTS `audit_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `date` DATE NOT NULL,
    `user_id` INT NOT NULL,
    `total_actions` INT DEFAULT 0,
    `create_actions` INT DEFAULT 0,
    `read_actions` INT DEFAULT 0,
    `update_actions` INT DEFAULT 0,
    `delete_actions` INT DEFAULT 0,
    `failed_actions` INT DEFAULT 0,
    `unique_resources` INT DEFAULT 0,
    `session_count` INT DEFAULT 0,
    `last_activity` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_daily_summary` (`date`, `user_id`),
    INDEX `idx_date` (`date`),
    INDEX `idx_user_id` (`user_id`),
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. INSERT DEFAULT AUDIT CONFIGURATION
INSERT INTO `audit_config` (`action_type`, `resource_type`, `is_enabled`, `log_level`) VALUES
-- User Account Actions
('CREATE', 'User Account', TRUE, 'detailed'),
('UPDATE', 'User Account', TRUE, 'detailed'),
('DELETE', 'User Account', TRUE, 'detailed'),
('LOGIN', 'User Account', TRUE, 'standard'),
('LOGOUT', 'User Account', TRUE, 'minimal'),

-- Course Actions
('CREATE', 'Course', TRUE, 'detailed'),
('READ', 'Course', TRUE, 'minimal'),
('UPDATE', 'Course', TRUE, 'detailed'),
('DELETE', 'Course', TRUE, 'detailed'),
('ACCESS', 'Course', TRUE, 'standard'),

-- Lesson Actions
('CREATE', 'Lesson', TRUE, 'detailed'),
('READ', 'Lesson', TRUE, 'minimal'),
('UPDATE', 'Lesson', TRUE, 'detailed'),
('DELETE', 'Lesson', TRUE, 'detailed'),

-- System Actions
('ACCESS', 'Dashboard', TRUE, 'minimal'),
('UPDATE', 'System Config', TRUE, 'detailed'),
('CREATE', 'Category', TRUE, 'standard'),
('UPDATE', 'Category', TRUE, 'standard'),
('DELETE', 'Category', TRUE, 'detailed'),

-- Student Actions
('CREATE', 'Enrollment', TRUE, 'standard'),
('UPDATE', 'Progress', TRUE, 'minimal'),
('SUBMIT', 'Assignment', TRUE, 'standard'),
('DOWNLOAD', 'Materials', TRUE, 'minimal'),

-- Assessment Actions
('CREATE', 'Quiz', TRUE, 'detailed'),
('UPDATE', 'Quiz', TRUE, 'detailed'),
('SUBMIT', 'Assessment', TRUE, 'standard');

-- 5. CREATE STORED PROCEDURES FOR AUDIT LOGGING

DELIMITER //

-- Procedure to log audit entries with automatic parsing
CREATE PROCEDURE IF NOT EXISTS LogAuditEntry(
    IN p_user_id INT,
    IN p_action_type VARCHAR(20),
    IN p_action_description TEXT,
    IN p_resource_type VARCHAR(50),
    IN p_resource_id VARCHAR(255),
    IN p_resource_name VARCHAR(500),
    IN p_ip_address VARCHAR(45),
    IN p_outcome VARCHAR(20),
    IN p_old_value_text TEXT,
    IN p_new_value_text TEXT,
    IN p_user_agent TEXT,
    IN p_session_id VARCHAR(255),
    IN p_request_method VARCHAR(10),
    IN p_request_url TEXT,
    IN p_additional_context JSON
)
BEGIN
    DECLARE v_username VARCHAR(255);
    DECLARE v_user_role VARCHAR(20);
    DECLARE v_browser_name VARCHAR(100);
    DECLARE v_operating_system VARCHAR(100);
    DECLARE v_device_type VARCHAR(20);
    
    -- Get user info
    SELECT username, role INTO v_username, v_user_role 
    FROM users WHERE id = p_user_id;
    
    -- Parse user agent (simplified)
    SET v_browser_name = CASE
        WHEN p_user_agent LIKE '%Chrome%' THEN 'Chrome'
        WHEN p_user_agent LIKE '%Firefox%' THEN 'Firefox'
        WHEN p_user_agent LIKE '%Safari%' AND p_user_agent NOT LIKE '%Chrome%' THEN 'Safari'
        WHEN p_user_agent LIKE '%Edge%' THEN 'Edge'
        ELSE 'Unknown'
    END;
    
    SET v_operating_system = CASE
        WHEN p_user_agent LIKE '%Windows NT 10%' THEN 'Windows 10'
        WHEN p_user_agent LIKE '%Windows NT 6.1%' THEN 'Windows 7'
        WHEN p_user_agent LIKE '%Mac OS X%' THEN 'macOS'
        WHEN p_user_agent LIKE '%Android%' THEN 'Android'
        WHEN p_user_agent LIKE '%iPhone%' THEN 'iOS'
        WHEN p_user_agent LIKE '%Linux%' THEN 'Linux'
        ELSE 'Unknown'
    END;
    
    SET v_device_type = CASE
        WHEN p_user_agent LIKE '%Mobile%' OR p_user_agent LIKE '%iPhone%' THEN 'Mobile'
        WHEN p_user_agent LIKE '%Tablet%' OR p_user_agent LIKE '%iPad%' THEN 'Tablet'
        ELSE 'Desktop'
    END;
    
    -- Insert audit entry
    INSERT INTO comprehensive_audit_trail (
        user_id, username, user_role, action_type, action_description,
        resource_type, resource_id, resource_name, ip_address, outcome,
        old_value_text, new_value_text, device_info, browser_name,
        operating_system, device_type, session_id, request_method,
        request_url, additional_context
    ) VALUES (
        p_user_id, v_username, v_user_role, p_action_type, p_action_description,
        p_resource_type, p_resource_id, p_resource_name, p_ip_address, p_outcome,
        p_old_value_text, p_new_value_text, p_user_agent, v_browser_name,
        v_operating_system, v_device_type, p_session_id, p_request_method,
        p_request_url, p_additional_context
    );
    
    -- Update daily summary
    INSERT INTO audit_summary (date, user_id, total_actions, last_activity)
    VALUES (CURDATE(), p_user_id, 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_actions = total_actions + 1,
        last_activity = NOW(),
        create_actions = create_actions + (p_action_type = 'CREATE'),
        read_actions = read_actions + (p_action_type = 'READ'),
        update_actions = update_actions + (p_action_type = 'UPDATE'),
        delete_actions = delete_actions + (p_action_type = 'DELETE'),
        failed_actions = failed_actions + (p_outcome = 'Failed');
        
END//

DELIMITER ;

-- 6. CREATE FUNCTION TO GET USER ACTIVITY SUMMARY
DELIMITER //

CREATE FUNCTION IF NOT EXISTS GetUserActivityScore(p_user_id INT, p_days INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_score DECIMAL(5,2) DEFAULT 0.0;
    DECLARE v_total_actions INT DEFAULT 0;
    DECLARE v_unique_days INT DEFAULT 0;
    DECLARE v_avg_daily_actions DECIMAL(5,2) DEFAULT 0.0;
    
    -- Get total actions in last N days
    SELECT COUNT(*) INTO v_total_actions
    FROM comprehensive_audit_trail 
    WHERE user_id = p_user_id 
    AND timestamp >= DATE_SUB(NOW(), INTERVAL p_days DAY);
    
    -- Get unique active days
    SELECT COUNT(DISTINCT DATE(timestamp)) INTO v_unique_days
    FROM comprehensive_audit_trail 
    WHERE user_id = p_user_id 
    AND timestamp >= DATE_SUB(NOW(), INTERVAL p_days DAY);
    
    -- Calculate score (0-100)
    IF v_unique_days > 0 THEN
        SET v_avg_daily_actions = v_total_actions / v_unique_days;
        SET v_score = LEAST(100, (v_unique_days * 10) + (v_avg_daily_actions * 2));
    END IF;
    
    RETURN v_score;
END//

DELIMITER ;

-- 7. CREATE VIEWS FOR COMMON AUDIT QUERIES

-- View for recent audit activities
CREATE OR REPLACE VIEW recent_audit_activities AS
SELECT 
    cat.id,
    cat.timestamp,
    cat.username,
    cat.user_role,
    cat.action_type,
    cat.action_description,
    cat.resource_type,
    cat.resource_name,
    cat.ip_address,
    cat.outcome,
    cat.device_type,
    cat.browser_name,
    cat.operating_system,
    CONCAT(cat.browser_name, ' on ', cat.operating_system) as device_info,
    cat.location_city,
    cat.location_country
FROM comprehensive_audit_trail cat
ORDER BY cat.timestamp DESC;

-- View for failed actions
CREATE OR REPLACE VIEW failed_audit_actions AS
SELECT 
    cat.*,
    u.email as user_email
FROM comprehensive_audit_trail cat
JOIN users u ON cat.user_id = u.id
WHERE cat.outcome = 'Failed'
ORDER BY cat.timestamp DESC;

-- View for admin actions
CREATE OR REPLACE VIEW admin_audit_actions AS
SELECT 
    cat.*
FROM comprehensive_audit_trail cat
WHERE cat.user_role = 'admin'
ORDER BY cat.timestamp DESC;

-- View for daily activity summary
CREATE OR REPLACE VIEW daily_activity_summary AS
SELECT 
    DATE(timestamp) as activity_date,
    COUNT(*) as total_actions,
    COUNT(DISTINCT user_id) as unique_users,
    SUM(CASE WHEN action_type = 'CREATE' THEN 1 ELSE 0 END) as create_actions,
    SUM(CASE WHEN action_type = 'READ' THEN 1 ELSE 0 END) as read_actions,
    SUM(CASE WHEN action_type = 'UPDATE' THEN 1 ELSE 0 END) as update_actions,
    SUM(CASE WHEN action_type = 'DELETE' THEN 1 ELSE 0 END) as delete_actions,
    SUM(CASE WHEN outcome = 'Failed' THEN 1 ELSE 0 END) as failed_actions,
    COUNT(DISTINCT ip_address) as unique_ips
FROM comprehensive_audit_trail
GROUP BY DATE(timestamp)
ORDER BY activity_date DESC;

-- 8. CREATE CLEANUP PROCEDURE
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CleanupOldAuditEntries()
BEGIN
    DECLARE v_retention_days INT DEFAULT 365;
    
    -- Get default retention period
    SELECT MAX(retention_days) INTO v_retention_days 
    FROM audit_config WHERE is_enabled = TRUE;
    
    -- Delete old entries
    DELETE FROM comprehensive_audit_trail 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL v_retention_days DAY);
    
    -- Delete old summary entries
    DELETE FROM audit_summary 
    WHERE date < DATE_SUB(CURDATE(), INTERVAL v_retention_days DAY);
    
END//

DELIMITER ;

-- 9. CREATE EVENT FOR AUTOMATIC CLEANUP (Optional)
-- Uncomment to enable automatic cleanup
/*
CREATE EVENT IF NOT EXISTS audit_cleanup_event
ON SCHEDULE EVERY 1 WEEK
DO
  CALL CleanupOldAuditEntries();
*/

-- ===================================================================
-- SAMPLE DATA FOR TESTING
-- ===================================================================

-- Insert some sample audit entries (replace with actual data migration)
/*
CALL LogAuditEntry(
    1, -- user_id (admin)
    'UPDATE',
    'Updated user role from teacher to admin',
    'User Account',
    'User ID: 156',
    'Mark Agbayani',
    '192.168.1.100',
    'Success',
    'teacher',
    'admin',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
    'sess_abc123',
    'POST',
    '/admin/users/update',
    '{"module": "user_management", "section": "role_update"}'
);
*/

-- ===================================================================
-- MIGRATION QUERIES
-- ===================================================================

-- Migrate existing audit_trail data to new comprehensive table
/*
INSERT INTO comprehensive_audit_trail (
    user_id, username, user_role, action_type, action_description,
    resource_type, resource_id, ip_address, outcome, timestamp
)
SELECT 
    at.user_id,
    u.username,
    u.role,
    'UPDATE' as action_type,
    at.action as action_description,
    'Course' as resource_type,
    CONCAT('Course ID: ', at.course_id) as resource_id,
    '0.0.0.0' as ip_address,
    'Success' as outcome,
    at.created_at as timestamp
FROM audit_trail at
JOIN users u ON at.user_id = u.id
WHERE at.course_id IS NOT NULL;
*/

-- Migrate admin_audit_log data
/*
INSERT INTO comprehensive_audit_trail (
    user_id, username, user_role, action_type, action_description,
    resource_type, ip_address, outcome, timestamp, device_info,
    additional_context
)
SELECT 
    aal.admin_id as user_id,
    u.username,
    u.role as user_role,
    'UPDATE' as action_type,
    aal.action as action_description,
    'System Config' as resource_type,
    aal.ip_address,
    'Success' as outcome,
    aal.created_at as timestamp,
    aal.user_agent as device_info,
    aal.details as additional_context
FROM admin_audit_log aal
JOIN users u ON aal.admin_id = u.id;
*/

-- ===================================================================
-- INDEXES FOR PERFORMANCE
-- ===================================================================

-- Additional performance indexes
CREATE INDEX IF NOT EXISTS `idx_comprehensive_user_date` ON `comprehensive_audit_trail` (`user_id`, `timestamp`);
CREATE INDEX IF NOT EXISTS `idx_comprehensive_action_outcome` ON `comprehensive_audit_trail` (`action_type`, `outcome`);
CREATE INDEX IF NOT EXISTS `idx_comprehensive_resource_date` ON `comprehensive_audit_trail` (`resource_type`, `timestamp`);
CREATE INDEX IF NOT EXISTS `idx_comprehensive_ip_date` ON `comprehensive_audit_trail` (`ip_address`, `timestamp`);

-- ===================================================================
-- NOTES
-- ===================================================================
-- 1. Run this script to create the comprehensive audit system
-- 2. Update your application code to use LogAuditEntry() procedure
-- 3. Use the views for common queries
-- 4. Enable the cleanup event if you want automatic maintenance
-- 5. Migrate existing data using the migration queries
-- ===================================================================
