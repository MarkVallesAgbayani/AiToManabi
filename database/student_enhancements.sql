-- Student profile enhancements
ALTER TABLE users
ADD COLUMN student_id VARCHAR(20) UNIQUE NULL AFTER id,
ADD COLUMN learning_level ENUM('N5', 'N4', 'N3', 'N2', 'N1') NULL,
ADD COLUMN learning_streak INT DEFAULT 0,
ADD COLUMN last_activity_date DATE NULL;

-- Create student statistics table
CREATE TABLE student_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    total_study_time INT DEFAULT 0, -- in minutes
    completed_lessons INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    points_earned INT DEFAULT 0,
    last_login_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create student achievements table
CREATE TABLE student_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create student assessment results table
CREATE TABLE student_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    assessment_type ENUM('quiz', 'test', 'final') NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_student_id ON users(student_id);
CREATE INDEX idx_student_stats ON student_statistics(student_id);
CREATE INDEX idx_student_achievements ON student_achievements(student_id);
CREATE INDEX idx_student_assessments ON student_assessments(student_id, course_id);

-- Trigger to create student statistics record when new student registers
DELIMITER //
CREATE TRIGGER after_student_register 
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.role = 'student' THEN
        -- Generate a unique student ID (Year + Random Number)
        SET @new_student_id = CONCAT(DATE_FORMAT(NOW(), '%Y'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
        
        -- Update the user with the student ID
        UPDATE users SET student_id = @new_student_id WHERE id = NEW.id;
        
        -- Create initial statistics record
        INSERT INTO student_statistics (student_id) VALUES (NEW.id);
    END IF;
END//
DELIMITER ; 