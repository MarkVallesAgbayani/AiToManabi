-- Student Preferences Table
-- This table stores student-specific profile preferences and settings

CREATE TABLE IF NOT EXISTS student_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    display_name VARCHAR(100) NULL,
    profile_picture VARCHAR(255) NULL,
    bio TEXT NULL,
    phone VARCHAR(20) NULL,
    profile_visible BOOLEAN DEFAULT TRUE,
    contact_visible BOOLEAN DEFAULT TRUE,
    notification_preferences JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_preferences (student_id)
);

-- Add indexes for better performance
CREATE INDEX idx_student_preferences_student_id ON student_preferences(student_id);
CREATE INDEX idx_student_preferences_visible ON student_preferences(profile_visible, contact_visible);

-- Insert default preferences for existing students
INSERT IGNORE INTO student_preferences (student_id, display_name, profile_visible, contact_visible)
SELECT id, username, TRUE, TRUE
FROM users 
WHERE role = 'student' 
AND id NOT IN (SELECT student_id FROM student_preferences);
