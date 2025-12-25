-- Create user_sessions table for tracking login sessions and time spent
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    time_spent_minutes INT DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    browser VARCHAR(100),
    operating_system VARCHAR(100),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_login_time (login_time),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create course_activity table for tracking course-specific engagement
CREATE TABLE IF NOT EXISTS course_activity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    activity_type ENUM('view', 'complete_section', 'quiz_attempt', 'download', 'forum_post') NOT NULL,
    activity_data JSON NULL,
    time_spent_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for demonstration
INSERT IGNORE INTO user_sessions (user_id, session_id, login_time, logout_time, time_spent_minutes, ip_address, user_agent, device_type, browser, operating_system, is_active) VALUES
(1, 'sess_001', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 60, '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'desktop', 'Chrome', 'Windows 10', FALSE),
(2, 'sess_002', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 30, '192.168.1.2', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'mobile', 'Safari', 'iOS 14', TRUE),
(3, 'sess_003', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), 45, '192.168.1.3', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', 'desktop', 'Chrome', 'macOS', FALSE),
(4, 'sess_004', DATE_SUB(NOW(), INTERVAL 30 MINUTE), NULL, 15, '192.168.1.4', 'Mozilla/5.0 (Android 10; Mobile; rv:68.0) Gecko/68.0 Firefox/68.0', 'mobile', 'Firefox', 'Android 10', TRUE),
(5, 'sess_005', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR), 90, '192.168.1.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0', 'desktop', 'Firefox', 'Windows 10', FALSE);

-- Insert sample course activity data
INSERT IGNORE INTO course_activity (user_id, course_id, session_id, activity_type, time_spent_minutes, created_at) VALUES
(1, 1, 'sess_001', 'view', 15, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 1, 'sess_001', 'complete_section', 20, DATE_SUB(NOW(), INTERVAL 105 MINUTE)),
(1, 1, 'sess_001', 'quiz_attempt', 25, DATE_SUB(NOW(), INTERVAL 80 MINUTE)),
(2, 2, 'sess_002', 'view', 10, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 2, 'sess_002', 'complete_section', 20, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(3, 1, 'sess_003', 'view', 20, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 1, 'sess_003', 'complete_section', 25, DATE_SUB(NOW(), INTERVAL 150 MINUTE)),
(4, 3, 'sess_004', 'view', 15, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(5, 2, 'sess_005', 'view', 30, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 2, 'sess_005', 'complete_section', 35, DATE_SUB(NOW(), INTERVAL 210 MINUTE)),
(5, 2, 'sess_005', 'quiz_attempt', 25, DATE_SUB(NOW(), INTERVAL 185 MINUTE));
