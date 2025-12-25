-- =====================================================
-- SYSTEM PERFORMANCE MONITORING DATABASE STRUCTURE
-- =====================================================

-- Table 1: System Uptime/Downtime Tracking
CREATE TABLE IF NOT EXISTS `system_uptime_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_type` ENUM('uptime', 'downtime') NOT NULL DEFAULT 'uptime',
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NULL,
    `duration_seconds` INT(11) NULL,
    `status` ENUM('active', 'completed') NOT NULL DEFAULT 'active',
    `error_message` TEXT NULL,
    `root_cause` VARCHAR(255) NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `server_ip` VARCHAR(45) NULL,
    `monitored_by` VARCHAR(100) DEFAULT 'system',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_start_time` (`start_time`),
    INDEX `idx_status` (`status`),
    INDEX `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Page Load Performance Tracking
CREATE TABLE IF NOT EXISTS `page_performance_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `page_name` VARCHAR(255) NOT NULL,
    `action_name` VARCHAR(255) NULL,
    `full_url` TEXT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `load_duration` DECIMAL(8,3) NOT NULL, -- in seconds with millisecond precision
    `status` ENUM('fast', 'slow', 'timeout', 'error') NOT NULL,
    `user_id` INT(11) NULL,
    `session_id` VARCHAR(128) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `device_type` VARCHAR(50) NULL,
    `browser` VARCHAR(100) NULL,
    `os` VARCHAR(100) NULL,
    `memory_usage` VARCHAR(20) NULL,
    `query_count` INT(11) DEFAULT 0,
    `response_code` INT(3) DEFAULT 200,
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_page_name` (`page_name`),
    INDEX `idx_load_duration` (`load_duration`),
    INDEX `idx_status` (`status`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_start_time` (`start_time`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: System Health Metrics
CREATE TABLE IF NOT EXISTS `system_health_metrics` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `metric_type` ENUM('cpu', 'memory', 'disk', 'database', 'response_time') NOT NULL,
    `metric_value` DECIMAL(10,2) NOT NULL,
    `metric_unit` VARCHAR(20) NOT NULL, -- %, MB, GB, ms, etc.
    `threshold_warning` DECIMAL(10,2) DEFAULT NULL,
    `threshold_critical` DECIMAL(10,2) DEFAULT NULL,
    `status` ENUM('healthy', 'warning', 'critical') NOT NULL DEFAULT 'healthy',
    `server_name` VARCHAR(100) DEFAULT 'main',
    `recorded_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_metric_type` (`metric_type`),
    INDEX `idx_recorded_at` (`recorded_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Error Logs
CREATE TABLE IF NOT EXISTS `system_error_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `error_type` VARCHAR(100) NOT NULL,
    `error_level` ENUM('notice', 'warning', 'error', 'fatal') NOT NULL DEFAULT 'error',
    `error_message` TEXT NOT NULL,
    `error_file` VARCHAR(500) NULL,
    `error_line` INT(11) NULL,
    `stack_trace` LONGTEXT NULL,
    `user_id` INT(11) NULL,
    `session_id` VARCHAR(128) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_url` TEXT NULL,
    `request_method` VARCHAR(10) NULL,
    `post_data` LONGTEXT NULL,
    `server_vars` LONGTEXT NULL,
    `occurred_at` DATETIME NOT NULL,
    `resolved_at` DATETIME NULL,
    `resolved_by` INT(11) NULL,
    `resolution_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_error_type` (`error_type`),
    INDEX `idx_error_level` (`error_level`),
    INDEX `idx_occurred_at` (`occurred_at`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VIEWS FOR EASY DATA ACCESS
-- =====================================================

-- View 1: Current System Status
CREATE OR REPLACE VIEW `current_system_status` AS
SELECT 
    'uptime' as status_type,
    COALESCE(
        TIMESTAMPDIFF(SECOND, 
            (SELECT start_time FROM system_uptime_log 
             WHERE event_type = 'uptime' AND status = 'active' 
             ORDER BY start_time DESC LIMIT 1), 
            NOW()
        ), 0
    ) as current_duration_seconds,
    (SELECT start_time FROM system_uptime_log 
     WHERE event_type = 'uptime' AND status = 'active' 
     ORDER BY start_time DESC LIMIT 1) as current_start_time,
    (SELECT COUNT(*) FROM system_uptime_log 
     WHERE event_type = 'downtime' AND DATE(start_time) = CURDATE()) as downtime_events_today;

-- View 2: Performance Summary (Last 24 Hours)
CREATE OR REPLACE VIEW `performance_summary_24h` AS
SELECT 
    COUNT(*) as total_requests,
    AVG(load_duration) as avg_load_time,
    MIN(load_duration) as min_load_time,
    MAX(load_duration) as max_load_time,
    COUNT(CASE WHEN status = 'fast' THEN 1 END) as fast_requests,
    COUNT(CASE WHEN status = 'slow' THEN 1 END) as slow_requests,
    COUNT(CASE WHEN status = 'error' THEN 1 END) as error_requests,
    ROUND(COUNT(CASE WHEN status = 'fast' THEN 1 END) * 100.0 / COUNT(*), 2) as fast_percentage
FROM page_performance_log 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- View 3: System Uptime Statistics
CREATE OR REPLACE VIEW `uptime_statistics` AS
SELECT 
    DATE(start_time) as date,
    SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END) as uptime_seconds,
    SUM(CASE WHEN event_type = 'downtime' THEN COALESCE(duration_seconds, 0) END) as downtime_seconds,
    COUNT(CASE WHEN event_type = 'downtime' THEN 1 END) as downtime_incidents,
    ROUND(
        SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END) * 100.0 / 
        (SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END) + 
         SUM(CASE WHEN event_type = 'downtime' THEN COALESCE(duration_seconds, 0) END)), 2
    ) as uptime_percentage
FROM system_uptime_log 
WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(start_time)
ORDER BY date DESC;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Procedure 1: Log System Uptime Start
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LogSystemUptime()
BEGIN
    DECLARE last_event_type VARCHAR(20);
    DECLARE last_event_id INT;
    
    -- Get the last event
    SELECT event_type, id INTO last_event_type, last_event_id
    FROM system_uptime_log 
    WHERE status = 'active'
    ORDER BY start_time DESC 
    LIMIT 1;
    
    -- If last event was downtime, close it
    IF last_event_type = 'downtime' THEN
        UPDATE system_uptime_log 
        SET end_time = NOW(),
            duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
            status = 'completed'
        WHERE id = last_event_id;
    END IF;
    
    -- Start new uptime event (only if not already active)
    IF last_event_type != 'uptime' THEN
        INSERT INTO system_uptime_log (event_type, start_time, status, server_ip, monitored_by)
        VALUES ('uptime', NOW(), 'active', 
                COALESCE(@server_ip, '127.0.0.1'), 
                COALESCE(@monitored_by, 'auto-system'));
    END IF;
END //
DELIMITER ;

-- Procedure 2: Log System Downtime
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LogSystemDowntime(
    IN error_msg TEXT,
    IN cause_desc VARCHAR(255),
    IN severity_level ENUM('low', 'medium', 'high', 'critical')
)
BEGIN
    DECLARE last_event_type VARCHAR(20);
    DECLARE last_event_id INT;
    
    -- Get the last event
    SELECT event_type, id INTO last_event_type, last_event_id
    FROM system_uptime_log 
    WHERE status = 'active'
    ORDER BY start_time DESC 
    LIMIT 1;
    
    -- If last event was uptime, close it
    IF last_event_type = 'uptime' THEN
        UPDATE system_uptime_log 
        SET end_time = NOW(),
            duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
            status = 'completed'
        WHERE id = last_event_id;
    END IF;
    
    -- Start new downtime event
    INSERT INTO system_uptime_log (
        event_type, start_time, status, error_message, root_cause, severity, 
        server_ip, monitored_by
    )
    VALUES (
        'downtime', NOW(), 'active', error_msg, cause_desc, severity_level,
        COALESCE(@server_ip, '127.0.0.1'), 
        COALESCE(@monitored_by, 'auto-system')
    );
END //
DELIMITER ;

-- Procedure 3: Log Page Performance
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LogPagePerformance(
    IN page VARCHAR(255),
    IN action_desc VARCHAR(255),
    IN url TEXT,
    IN duration DECIMAL(8,3),
    IN user_id_param INT,
    IN session_id_param VARCHAR(128),
    IN ip VARCHAR(45),
    IN user_agent_param TEXT
)
BEGIN
    DECLARE perf_status VARCHAR(20);
    DECLARE device_info VARCHAR(50);
    DECLARE browser_info VARCHAR(100);
    DECLARE os_info VARCHAR(100);
    
    -- Determine performance status
    SET perf_status = CASE 
        WHEN duration <= 3.0 THEN 'fast'
        WHEN duration <= 10.0 THEN 'slow'
        ELSE 'timeout'
    END;
    
    -- Parse user agent for device info (basic parsing)
    SET device_info = CASE
        WHEN user_agent_param LIKE '%Mobile%' OR user_agent_param LIKE '%Android%' OR user_agent_param LIKE '%iPhone%' THEN 'Mobile'
        WHEN user_agent_param LIKE '%Tablet%' OR user_agent_param LIKE '%iPad%' THEN 'Tablet'
        ELSE 'Desktop'
    END;
    
    SET browser_info = CASE
        WHEN user_agent_param LIKE '%Chrome%' THEN 'Chrome'
        WHEN user_agent_param LIKE '%Firefox%' THEN 'Firefox'
        WHEN user_agent_param LIKE '%Safari%' AND user_agent_param NOT LIKE '%Chrome%' THEN 'Safari'
        WHEN user_agent_param LIKE '%Edge%' THEN 'Edge'
        ELSE 'Other'
    END;
    
    SET os_info = CASE
        WHEN user_agent_param LIKE '%Windows%' THEN 'Windows'
        WHEN user_agent_param LIKE '%Mac OS%' THEN 'macOS'
        WHEN user_agent_param LIKE '%Linux%' THEN 'Linux'
        WHEN user_agent_param LIKE '%Android%' THEN 'Android'
        WHEN user_agent_param LIKE '%iOS%' THEN 'iOS'
        ELSE 'Other'
    END;
    
    -- Insert performance log
    INSERT INTO page_performance_log (
        page_name, action_name, full_url, start_time, end_time, load_duration,
        status, user_id, session_id, ip_address, user_agent, device_type,
        browser, os, response_code
    )
    VALUES (
        page, action_desc, url, 
        DATE_SUB(NOW(), INTERVAL duration SECOND), NOW(), duration,
        perf_status, user_id_param, session_id_param, ip, user_agent_param,
        device_info, browser_info, os_info, 200
    );
END //
DELIMITER ;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Sample Uptime/Downtime Events
INSERT INTO system_uptime_log (event_type, start_time, end_time, duration_seconds, status, root_cause, severity) VALUES
('uptime', '2025-01-13 08:00:00', '2025-01-13 14:30:00', 23400, 'completed', NULL, 'low'),
('downtime', '2025-01-13 14:30:00', '2025-01-13 15:00:00', 1800, 'completed', 'Database connection timeout', 'high'),
('uptime', '2025-01-13 15:00:00', '2025-01-14 02:15:00', 40500, 'completed', NULL, 'low'),
('downtime', '2025-01-14 02:15:00', '2025-01-14 02:45:00', 1800, 'completed', 'Server maintenance', 'medium'),
('uptime', '2025-01-14 02:45:00', NULL, NULL, 'active', NULL, 'low');

-- Sample Page Performance Data
INSERT INTO page_performance_log (page_name, action_name, start_time, end_time, load_duration, status, user_id, ip_address, device_type, browser, os) VALUES
('Dashboard', 'Load Admin Dashboard', '2025-01-14 09:00:00', '2025-01-14 09:00:02', 2.150, 'fast', 1, '192.168.1.100', 'Desktop', 'Chrome', 'Windows'),
('Course List', 'View All Courses', '2025-01-14 09:05:00', '2025-01-14 09:05:01', 1.800, 'fast', 2, '192.168.1.101', 'Mobile', 'Safari', 'iOS'),
('Payment', 'Subscribe → Payment', '2025-01-14 09:10:00', '2025-01-14 09:10:04', 4.200, 'slow', 3, '192.168.1.102', 'Desktop', 'Firefox', 'Windows'),
('User Profile', 'Update Profile', '2025-01-14 09:15:00', '2025-01-14 09:15:06', 6.500, 'slow', 4, '192.168.1.103', 'Tablet', 'Chrome', 'Android'),
('Login', 'User Authentication', '2025-01-14 09:20:00', '2025-01-14 09:20:01', 1.200, 'fast', 5, '192.168.1.104', 'Desktop', 'Edge', 'Windows'),
('Audit Trails', 'Load Audit Report', '2025-01-14 09:25:00', '2025-01-14 09:25:03', 3.100, 'slow', 1, '192.168.1.100', 'Desktop', 'Chrome', 'Windows'),
('Security Warnings', 'Load Security Dashboard', '2025-01-14 09:30:00', '2025-01-14 09:30:02', 2.800, 'fast', 1, '192.168.1.100', 'Desktop', 'Chrome', 'Windows'),
('Course Creation', 'Create New Course', '2025-01-14 09:35:00', '2025-01-14 09:35:05', 5.300, 'slow', 2, '192.168.1.101', 'Mobile', 'Safari', 'iOS'),
('File Upload', 'Upload Course Material', '2025-01-14 09:40:00', '2025-01-14 09:40:08', 8.100, 'slow', 2, '192.168.1.101', 'Mobile', 'Safari', 'iOS'),
('Database Query', 'Generate Report', '2025-01-14 09:45:00', '2025-01-14 09:45:12', 12.400, 'timeout', 1, '192.168.1.100', 'Desktop', 'Chrome', 'Windows');

-- Sample System Health Metrics
INSERT INTO system_health_metrics (metric_type, metric_value, metric_unit, threshold_warning, threshold_critical, status, recorded_at) VALUES
('cpu', 25.50, '%', 70.00, 90.00, 'healthy', '2025-01-14 10:00:00'),
('memory', 1024.00, 'MB', 2048.00, 3072.00, 'healthy', '2025-01-14 10:00:00'),
('disk', 15.25, 'GB', 5.00, 2.00, 'healthy', '2025-01-14 10:00:00'),
('database', 150.00, 'ms', 500.00, 1000.00, 'healthy', '2025-01-14 10:00:00'),
('response_time', 250.00, 'ms', 1000.00, 3000.00, 'healthy', '2025-01-14 10:00:00');

-- Sample Error Logs
INSERT INTO system_error_log (error_type, error_level, error_message, error_file, error_line, user_id, ip_address, request_url, occurred_at) VALUES
('PHP Warning', 'warning', 'Undefined array key "formatted"', '/dashboard/audit-trails.php', 437, 1, '192.168.1.100', '/dashboard/audit-trails.php', '2025-01-14 08:30:00'),
('Database Error', 'error', 'Connection timeout after 30 seconds', '/includes/database.php', 25, NULL, '192.168.1.105', '/login.php', '2025-01-14 14:30:00'),
('File System Error', 'error', 'Permission denied writing to uploads directory', '/modules/file_upload.php', 156, 2, '192.168.1.101', '/upload-course-material', '2025-01-14 16:45:00');

-- Initialize system uptime tracking
CALL LogSystemUptime();
