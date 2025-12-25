-- =====================================================
-- SYSTEM ERROR LOGS ENHANCEMENT
-- =====================================================
-- This extends the existing error logging system for comprehensive
-- AI failure, response time, and backend error tracking
-- =====================================================

-- Check if system_error_log table exists and extend it if needed
-- Add missing columns for AI failures and response time tracking

-- Add new columns to existing system_error_log table
ALTER TABLE `system_error_log` 
ADD COLUMN IF NOT EXISTS `category` ENUM('ai_failure', 'response_time', 'backend_error', 'system_error') NOT NULL DEFAULT 'system_error' AFTER `error_type`,
ADD COLUMN IF NOT EXISTS `severity` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning' AFTER `error_level`,
ADD COLUMN IF NOT EXISTS `module_name` VARCHAR(255) NULL AFTER `error_type`,
ADD COLUMN IF NOT EXISTS `endpoint` VARCHAR(500) NULL AFTER `request_url`,
ADD COLUMN IF NOT EXISTS `response_time_ms` INT(11) NULL COMMENT 'Response time in milliseconds' AFTER `endpoint`,
ADD COLUMN IF NOT EXISTS `expected_response_time_ms` INT(11) NULL COMMENT 'Expected response time in milliseconds' AFTER `response_time_ms`,
ADD COLUMN IF NOT EXISTS `user_query` TEXT NULL COMMENT 'Original user query for AI failures' AFTER `error_message`,
ADD COLUMN IF NOT EXISTS `ai_response` TEXT NULL COMMENT 'AI response (if any)' AFTER `user_query`,
ADD COLUMN IF NOT EXISTS `root_cause` VARCHAR(255) NULL AFTER `stack_trace`,
ADD COLUMN IF NOT EXISTS `device_type` VARCHAR(50) NULL AFTER `user_agent`,
ADD COLUMN IF NOT EXISTS `browser_name` VARCHAR(100) NULL AFTER `device_type`,
ADD COLUMN IF NOT EXISTS `operating_system` VARCHAR(100) NULL AFTER `browser_name`,
ADD COLUMN IF NOT EXISTS `status` ENUM('failed', 'retried', 'resolved', 'logged') NOT NULL DEFAULT 'logged' AFTER `operating_system`,
ADD COLUMN IF NOT EXISTS `retry_count` INT(11) DEFAULT 0 AFTER `status`,
ADD COLUMN IF NOT EXISTS `alert_sent` BOOLEAN DEFAULT FALSE AFTER `retry_count`;

-- Add new indexes for better performance
ALTER TABLE `system_error_log` 
ADD INDEX IF NOT EXISTS `idx_category` (`category`),
ADD INDEX IF NOT EXISTS `idx_severity` (`severity`),
ADD INDEX IF NOT EXISTS `idx_module_name` (`module_name`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_response_time` (`response_time_ms`),
ADD INDEX IF NOT EXISTS `idx_category_severity` (`category`, `severity`),
ADD INDEX IF NOT EXISTS `idx_occurred_status` (`occurred_at`, `status`);

-- =====================================================
-- VIEWS FOR ERROR LOGS DASHBOARD
-- =====================================================

-- View 1: Error Summary Statistics
CREATE OR REPLACE VIEW `error_logs_summary` AS
SELECT 
    COUNT(*) as total_errors,
    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as errors_24h,
    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as errors_7d,
    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as errors_30d,
    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as ai_failures_24h,
    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as ai_failures_7d,
    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as ai_failures_30d,
    COUNT(CASE WHEN severity = 'critical' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as critical_errors_24h,
    COUNT(CASE WHEN category = 'response_time' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as slow_responses_24h,
    AVG(CASE WHEN response_time_ms IS NOT NULL AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN response_time_ms END) as avg_response_time_24h,
    MAX(occurred_at) as last_error_time
FROM `system_error_log`;

-- View 2: Recent Error Activities for Notifications
CREATE OR REPLACE VIEW `recent_error_activities` AS
SELECT 
    id,
    category,
    severity,
    error_type,
    error_message,
    module_name,
    user_id,
    occurred_at,
    status,
    CASE 
        WHEN category = 'ai_failure' THEN CONCAT('AI Failure: ', COALESCE(error_type, 'Unknown'))
        WHEN category = 'response_time' THEN CONCAT('Slow Response: ', COALESCE(endpoint, module_name, 'Unknown'))
        WHEN category = 'backend_error' THEN CONCAT('Backend Error: ', COALESCE(module_name, error_type, 'Unknown'))
        ELSE CONCAT('System Error: ', COALESCE(error_type, 'Unknown'))
    END as activity_message,
    CASE 
        WHEN severity = 'critical' THEN 'error'
        WHEN severity = 'warning' THEN 'warning'
        ELSE 'info'
    END as activity_type
FROM `system_error_log`
WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY occurred_at DESC
LIMIT 10;

-- View 3: Error Trends for Charts
CREATE OR REPLACE VIEW `error_trends_daily` AS
SELECT 
    DATE(occurred_at) as error_date,
    COUNT(*) as total_errors,
    COUNT(CASE WHEN category = 'ai_failure' THEN 1 END) as ai_failures,
    COUNT(CASE WHEN category = 'response_time' THEN 1 END) as response_issues,
    COUNT(CASE WHEN category = 'backend_error' THEN 1 END) as backend_errors,
    COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_errors,
    COUNT(CASE WHEN severity = 'warning' THEN 1 END) as warning_errors,
    COUNT(CASE WHEN severity = 'info' THEN 1 END) as info_errors,
    AVG(CASE WHEN response_time_ms IS NOT NULL THEN response_time_ms END) as avg_response_time
FROM `system_error_log`
WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(occurred_at)
ORDER BY error_date DESC;

-- =====================================================
-- STORED PROCEDURES FOR ERROR LOGGING
-- =====================================================

-- Procedure 1: Log AI Failure
DELIMITER //
CREATE OR REPLACE PROCEDURE LogAIFailure(
    IN p_user_id INT,
    IN p_user_query TEXT,
    IN p_error_type VARCHAR(100),
    IN p_error_message TEXT,
    IN p_root_cause VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_device_info TEXT
)
BEGIN
    DECLARE v_device_type VARCHAR(50) DEFAULT NULL;
    DECLARE v_browser_name VARCHAR(100) DEFAULT NULL;
    DECLARE v_os VARCHAR(100) DEFAULT NULL;
    
    -- Parse device info from user agent (basic parsing)
    IF p_user_agent LIKE '%Mobile%' OR p_user_agent LIKE '%Android%' OR p_user_agent LIKE '%iPhone%' THEN
        SET v_device_type = 'Mobile';
    ELSEIF p_user_agent LIKE '%Tablet%' OR p_user_agent LIKE '%iPad%' THEN
        SET v_device_type = 'Tablet';
    ELSE
        SET v_device_type = 'Desktop';
    END IF;
    
    -- Parse browser name (basic parsing)
    IF p_user_agent LIKE '%Chrome%' THEN SET v_browser_name = 'Chrome';
    ELSEIF p_user_agent LIKE '%Firefox%' THEN SET v_browser_name = 'Firefox';
    ELSEIF p_user_agent LIKE '%Safari%' THEN SET v_browser_name = 'Safari';
    ELSEIF p_user_agent LIKE '%Edge%' THEN SET v_browser_name = 'Edge';
    ELSE SET v_browser_name = 'Other';
    END IF;
    
    -- Parse OS (basic parsing)
    IF p_user_agent LIKE '%Windows%' THEN SET v_os = 'Windows';
    ELSEIF p_user_agent LIKE '%Mac%' THEN SET v_os = 'macOS';
    ELSEIF p_user_agent LIKE '%Linux%' THEN SET v_os = 'Linux';
    ELSEIF p_user_agent LIKE '%Android%' THEN SET v_os = 'Android';
    ELSEIF p_user_agent LIKE '%iOS%' THEN SET v_os = 'iOS';
    ELSE SET v_os = 'Other';
    END IF;
    
    INSERT INTO `system_error_log` (
        category, error_type, severity, error_message, user_query, 
        user_id, ip_address, user_agent, device_type, browser_name, 
        operating_system, root_cause, occurred_at, status
    ) VALUES (
        'ai_failure', p_error_type, 'critical', p_error_message, p_user_query,
        p_user_id, p_ip_address, p_user_agent, v_device_type, v_browser_name,
        v_os, p_root_cause, NOW(), 'failed'
    );
END //
DELIMITER ;

-- Procedure 2: Log Response Time Issue
DELIMITER //
CREATE OR REPLACE PROCEDURE LogResponseTimeIssue(
    IN p_endpoint VARCHAR(500),
    IN p_response_time_ms INT,
    IN p_expected_time_ms INT,
    IN p_user_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_severity ENUM('info', 'warning', 'critical')
)
BEGIN
    DECLARE v_device_type VARCHAR(50) DEFAULT NULL;
    DECLARE v_browser_name VARCHAR(100) DEFAULT NULL;
    DECLARE v_os VARCHAR(100) DEFAULT NULL;
    DECLARE v_error_message TEXT;
    
    -- Create error message
    SET v_error_message = CONCAT('Slow response detected: ', p_endpoint, ' took ', p_response_time_ms, 'ms (expected: ', p_expected_time_ms, 'ms)');
    
    -- Parse device info (reuse logic from above)
    IF p_user_agent LIKE '%Mobile%' OR p_user_agent LIKE '%Android%' OR p_user_agent LIKE '%iPhone%' THEN
        SET v_device_type = 'Mobile';
    ELSEIF p_user_agent LIKE '%Tablet%' OR p_user_agent LIKE '%iPad%' THEN
        SET v_device_type = 'Tablet';
    ELSE
        SET v_device_type = 'Desktop';
    END IF;
    
    IF p_user_agent LIKE '%Chrome%' THEN SET v_browser_name = 'Chrome';
    ELSEIF p_user_agent LIKE '%Firefox%' THEN SET v_browser_name = 'Firefox';
    ELSEIF p_user_agent LIKE '%Safari%' THEN SET v_browser_name = 'Safari';
    ELSEIF p_user_agent LIKE '%Edge%' THEN SET v_browser_name = 'Edge';
    ELSE SET v_browser_name = 'Other';
    END IF;
    
    IF p_user_agent LIKE '%Windows%' THEN SET v_os = 'Windows';
    ELSEIF p_user_agent LIKE '%Mac%' THEN SET v_os = 'macOS';
    ELSEIF p_user_agent LIKE '%Linux%' THEN SET v_os = 'Linux';
    ELSEIF p_user_agent LIKE '%Android%' THEN SET v_os = 'Android';
    ELSEIF p_user_agent LIKE '%iOS%' THEN SET v_os = 'iOS';
    ELSE SET v_os = 'Other';
    END IF;
    
    INSERT INTO `system_error_log` (
        category, error_type, severity, error_message, endpoint,
        response_time_ms, expected_response_time_ms, user_id, ip_address, 
        user_agent, device_type, browser_name, operating_system, 
        occurred_at, status
    ) VALUES (
        'response_time', 'Slow Response', p_severity, v_error_message, p_endpoint,
        p_response_time_ms, p_expected_time_ms, p_user_id, p_ip_address,
        p_user_agent, v_device_type, v_browser_name, v_os,
        NOW(), 'logged'
    );
END //
DELIMITER ;

-- Procedure 3: Log Backend Error
DELIMITER //
CREATE OR REPLACE PROCEDURE LogBackendError(
    IN p_module_name VARCHAR(255),
    IN p_error_type VARCHAR(100),
    IN p_error_message TEXT,
    IN p_error_code VARCHAR(20),
    IN p_stack_trace LONGTEXT,
    IN p_user_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_request_url TEXT,
    IN p_severity ENUM('info', 'warning', 'critical')
)
BEGIN
    INSERT INTO `system_error_log` (
        category, module_name, error_type, severity, error_message,
        stack_trace, user_id, ip_address, request_url, occurred_at, status
    ) VALUES (
        'backend_error', p_module_name, p_error_type, p_severity, p_error_message,
        p_stack_trace, p_user_id, p_ip_address, p_request_url, NOW(), 'logged'
    );
END //
DELIMITER ;

-- =====================================================
-- INSERT SAMPLE DATA FOR TESTING
-- =====================================================

-- Sample AI Failures
INSERT INTO `system_error_log` (category, error_type, severity, error_message, user_query, user_id, ip_address, user_agent, device_type, browser_name, operating_system, root_cause, occurred_at, status) VALUES
('ai_failure', 'No Response', 'critical', 'AI failed to generate response to user query', 'How do I reset my password?', 1192, '192.168.1.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', 'Desktop', 'Chrome', 'Windows', 'Connection timeout to AI service', DATE_SUB(NOW(), INTERVAL 2 HOUR), 'failed'),
('ai_failure', 'Invalid Output', 'warning', 'AI generated malformed response', 'What are the course requirements?', 1045, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', 'Desktop', 'Chrome', 'macOS', 'Model hallucination detected', DATE_SUB(NOW(), INTERVAL 5 HOUR), 'retried'),
('ai_failure', 'Timeout', 'critical', 'AI request timed out after 30 seconds', 'Explain Japanese grammar rules', 1203, '192.168.1.75', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15', 'Mobile', 'Safari', 'iOS', 'High server load', DATE_SUB(NOW(), INTERVAL 1 DAY), 'failed');

-- Sample Response Time Issues
INSERT INTO `system_error_log` (category, error_type, severity, error_message, endpoint, response_time_ms, expected_response_time_ms, user_id, ip_address, user_agent, device_type, browser_name, operating_system, occurred_at, status) VALUES
('response_time', 'Slow Response', 'warning', 'Slow response detected: /api/ai/answer took 4800ms (expected: 2000ms)', '/api/ai/answer', 4800, 2000, 1192, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'Desktop', 'Chrome', 'Windows', DATE_SUB(NOW(), INTERVAL 3 HOUR), 'logged'),
('response_time', 'Timeout', 'critical', 'Request timeout: /api/course/load took 15000ms (expected: 3000ms)', '/api/course/load', 15000, 3000, 1045, '192.168.1.100', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', 'Desktop', 'Chrome', 'Linux', DATE_SUB(NOW(), INTERVAL 6 HOUR), 'failed');

-- Sample Backend Errors
INSERT INTO `system_error_log` (category, module_name, error_type, severity, error_message, stack_trace, user_id, ip_address, request_url, occurred_at, status) VALUES
('backend_error', 'AIController', '500 Internal Server Error', 'critical', 'NullReferenceException: Object reference not set to an instance of an object', 'at AIController.ProcessQuery(String query)\n   at APIEndpoint.HandleRequest()', 1192, '192.168.1.50', '/dashboard/ai-chat', DATE_SUB(NOW(), INTERVAL 4 HOUR), 'logged'),
('backend_error', 'DatabaseService', 'Connection Failed', 'critical', 'Unable to connect to database server', 'MySql.Data.MySqlClient.MySqlException: Unable to connect to any of the specified MySQL hosts', NULL, '127.0.0.1', '/api/users/profile', DATE_SUB(NOW(), INTERVAL 8 HOUR), 'resolved'),
('backend_error', 'PaymentProcessor', 'API Timeout', 'warning', 'Payment gateway API timeout', 'System.TimeoutException: The operation has timed out', 1203, '192.168.1.75', '/payment/process', DATE_SUB(NOW(), INTERVAL 12 HOUR), 'logged');

-- More sample data for better visualization
INSERT INTO `system_error_log` (category, error_type, severity, error_message, user_id, ip_address, occurred_at, status) VALUES
('ai_failure', 'No Response', 'critical', 'AI service unavailable', 1001, '192.168.1.101', DATE_SUB(NOW(), INTERVAL 1 DAY), 'failed'),
('ai_failure', 'Invalid Output', 'warning', 'AI generated incomplete response', 1002, '192.168.1.102', DATE_SUB(NOW(), INTERVAL 1 DAY), 'retried'),
('response_time', 'Slow Response', 'warning', 'Page load exceeded threshold', 1003, '192.168.1.103', DATE_SUB(NOW(), INTERVAL 2 DAY), 'logged'),
('backend_error', 'AuthService', '401 Unauthorized', 'warning', 'Invalid authentication token', 1004, '192.168.1.104', DATE_SUB(NOW(), INTERVAL 2 DAY), 'resolved'),
('backend_error', 'FileUpload', '413 Payload Too Large', 'info', 'File size exceeds maximum limit', 1005, '192.168.1.105', DATE_SUB(NOW(), INTERVAL 3 DAY), 'logged');
