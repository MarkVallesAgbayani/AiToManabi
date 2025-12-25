-- Placement Test System Database Schema
-- This file creates all necessary tables for the placement test system

-- Create placement_tests table (test configurations)
CREATE TABLE IF NOT EXISTS placement_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT 'Japanese Language Placement Test',
    description TEXT,
    instructions TEXT,
    max_questions INT DEFAULT 20,
    time_limit_minutes INT DEFAULT 60,
    is_active BOOLEAN DEFAULT TRUE,
    passing_score DECIMAL(5,2) DEFAULT 70.00,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_questions table
CREATE TABLE IF NOT EXISTS placement_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'fill_blank', 'short_answer') DEFAULT 'multiple_choice',
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    points INT DEFAULT 1,
    explanation TEXT,
    image_path VARCHAR(255),
    audio_path VARCHAR(255),
    order_index INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES placement_tests(id) ON DELETE CASCADE,
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_question_choices table
CREATE TABLE IF NOT EXISTS placement_question_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES placement_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_sessions table (track student test sessions)
CREATE TABLE IF NOT EXISTS placement_test_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    session_token VARCHAR(64) UNIQUE NOT NULL,
    status ENUM('started', 'in_progress', 'completed', 'abandoned') DEFAULT 'started',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    total_questions INT DEFAULT 0,
    answered_questions INT DEFAULT 0,
    current_question_index INT DEFAULT 0,
    time_remaining_seconds INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES placement_tests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_test (student_id, test_id),
    INDEX idx_status (status),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_answers table (student responses)
CREATE TABLE IF NOT EXISTS placement_test_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    choice_id INT NULL, -- For multiple choice questions
    answer_text TEXT NULL, -- For text-based answers
    is_correct BOOLEAN DEFAULT FALSE,
    points_earned DECIMAL(5,2) DEFAULT 0,
    time_spent_seconds INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES placement_test_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES placement_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (choice_id) REFERENCES placement_question_choices(id) ON DELETE SET NULL,
    UNIQUE KEY unique_session_question (session_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_results table (final results and recommendations)
CREATE TABLE IF NOT EXISTS placement_test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    total_points DECIMAL(8,2) NOT NULL,
    max_possible_points DECIMAL(8,2) NOT NULL,
    percentage_score DECIMAL(5,2) NOT NULL,
    difficulty_breakdown JSON, -- {"beginner": {"correct": 5, "total": 8}, "intermediate": {...}, "advanced": {...}}
    recommended_level ENUM('beginner', 'intermediate', 'advanced'),
    recommended_course_id INT NULL,
    completion_time_seconds INT,
    detailed_feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES placement_test_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES placement_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_course_id) REFERENCES courses(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_result (student_id, test_id),
    INDEX idx_score (percentage_score),
    INDEX idx_level (recommended_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_design table (customization settings)
CREATE TABLE IF NOT EXISTS placement_test_design (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    logo_image VARCHAR(255),
    header_color VARCHAR(7) DEFAULT '#1f2937',
    header_type ENUM('solid', 'gradient') DEFAULT 'solid',
    header_gradient VARCHAR(100),
    background_color VARCHAR(7) DEFAULT '#f5f5f5',
    background_type ENUM('solid', 'gradient') DEFAULT 'solid',
    background_gradient VARCHAR(100),
    accent_color VARCHAR(7) DEFAULT '#dc2626',
    font_family VARCHAR(50) DEFAULT 'Inter',
    custom_css TEXT,
    welcome_content TEXT,
    instructions_content TEXT,
    completion_content TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES placement_tests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_test_design (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_difficulty_modules table (link difficulty levels to course modules)
CREATE TABLE IF NOT EXISTS placement_difficulty_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    course_id INT NOT NULL,
    is_primary_recommendation BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_difficulty (difficulty_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_pages table (dynamic pages for placement test)
CREATE TABLE IF NOT EXISTS placement_test_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    page_type ENUM('welcome', 'instructions', 'questions', 'completion', 'custom') NOT NULL,
    page_key VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    page_order INT NOT NULL DEFAULT 0,
    question_count INT DEFAULT 0,
    is_required BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES placement_tests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_test_page_key (test_id, page_key),
    INDEX idx_test_order (test_id, page_order),
    INDEX idx_page_type (page_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_page_questions table (assigns questions to specific pages)
CREATE TABLE IF NOT EXISTS placement_page_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    question_id INT NOT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES placement_test_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES placement_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_page_question (page_id, question_id),
    INDEX idx_page_order (page_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create placement_test_images table (store page-specific images)
CREATE TABLE IF NOT EXISTS placement_test_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_page_key (page_key),
    INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default placement test (using the first admin/teacher user)
INSERT INTO placement_tests (title, description, instructions, created_by) 
SELECT 
    'Japanese Language Placement Test',
    'Comprehensive assessment to determine your Japanese language proficiency level',
    'This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey. Please answer all questions to the best of your ability.',
    u.id
FROM users u 
WHERE u.role IN ('admin', 'teacher') 
ORDER BY u.id ASC 
LIMIT 1
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Insert default design settings (using the placement test ID)
INSERT INTO placement_test_design (
    test_id, 
    welcome_content, 
    instructions_content, 
    completion_content
) 
SELECT 
    pt.id,
    '<h1 style="text-align: center; color: #2563eb; margin-bottom: 1rem;">🎯 Welcome to Japanese Language Placement Test</h1><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey.</p><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Please answer all questions to the best of your ability. You can only take this test once.</p><div style="background: #f0f9ff; border-left: 4px solid #2563eb; padding: 1rem; margin: 1rem 0; border-radius: 0 8px 8px 0;"><p style="margin: 0; font-weight: 600; color: #1e40af;">💡 Tip: Take your time and answer honestly for the most accurate placement.</p></div>',
    '<h2 style="text-align: center; color: #059669; margin-bottom: 1.5rem;">📋 Are you ready?</h2><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Before we begin, here are some important instructions:</p><ul style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1.5rem;"><li style="margin-bottom: 0.5rem;">This test contains multiple-choice questions</li><li style="margin-bottom: 0.5rem;">Select the answer that best represents your knowledge</li><li style="margin-bottom: 0.5rem;">You can navigate between questions using Previous/Next buttons</li><li style="margin-bottom: 0.5rem;">Once you complete the test, you cannot retake it</li><li style="margin-bottom: 0.5rem;">Take your time and answer honestly</li></ul><div style="background: #fef3c7; border: 2px solid #f59e0b; padding: 1rem; margin: 1rem 0; border-radius: 8px; text-align: center;"><p style="margin: 0; font-weight: 600; color: #92400e;">Click Start Test when you are ready to begin!</p></div>',
    '<div style="text-align: center; margin-bottom: 2rem;"><h2 style="color: #059669; font-size: 2.5em; margin-bottom: 1rem;">🎉 Test Completed!</h2><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Thank you for completing the Japanese Language Placement Test.</p><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Your results will be analyzed and you will receive a recommended starting module based on your performance.</p></div><div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; padding: 2rem; margin: 1rem 0; text-align: center;"><p style="margin: 0; font-size: 1.3em; font-weight: 600; color: #1e40af;">Good luck with your Japanese learning journey!</p></div>'
FROM placement_tests pt 
WHERE pt.title = 'Japanese Language Placement Test'
LIMIT 1
ON DUPLICATE KEY UPDATE test_id = VALUES(test_id);

-- Insert default pages for the placement test
INSERT INTO placement_test_pages (test_id, page_type, page_key, title, content, page_order)
SELECT 
    pt.id,
    'welcome',
    'welcome',
    'Welcome Page',
    '<h1 style="text-align: center; color: #2563eb; margin-bottom: 1rem;">🎯 Welcome to Japanese Language Placement Test</h1><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey.</p><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Please answer all questions to the best of your ability. You can only take this test once.</p><div style="background: #f0f9ff; border-left: 4px solid #2563eb; padding: 1rem; margin: 1rem 0; border-radius: 0 8px 8px 0;"><p style="margin: 0; font-weight: 600; color: #1e40af;">💡 Tip: Take your time and answer honestly for the most accurate placement.</p></div>',
    1
FROM placement_tests pt 
WHERE pt.title = 'Japanese Language Placement Test'
LIMIT 1
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO placement_test_pages (test_id, page_type, page_key, title, content, page_order)
SELECT 
    pt.id,
    'instructions',
    'instructions',
    'Instructions',
    '<h2 style="text-align: center; color: #059669; margin-bottom: 1.5rem;">📋 Are you ready?</h2><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Before we begin, here are some important instructions:</p><ul style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1.5rem;"><li style="margin-bottom: 0.5rem;">This test contains multiple-choice questions</li><li style="margin-bottom: 0.5rem;">Select the answer that best represents your knowledge</li><li style="margin-bottom: 0.5rem;">You can navigate between questions using Previous/Next buttons</li><li style="margin-bottom: 0.5rem;">Once you complete the test, you cannot retake it</li><li style="margin-bottom: 0.5rem;">Take your time and answer honestly</li></ul><div style="background: #fef3c7; border: 2px solid #f59e0b; padding: 1rem; margin: 1rem 0; border-radius: 8px; text-align: center;"><p style="margin: 0; font-weight: 600; color: #92400e;">Click Start Test when you are ready to begin!</p></div>',
    2
FROM placement_tests pt 
WHERE pt.title = 'Japanese Language Placement Test'
LIMIT 1
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO placement_test_pages (test_id, page_type, page_key, title, content, page_order)
SELECT 
    pt.id,
    'completion',
    'completion',
    'Test Complete',
    '<div style="text-align: center; margin-bottom: 2rem;"><h2 style="color: #059669; font-size: 2.5em; margin-bottom: 1rem;">🎉 Test Completed!</h2><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Thank you for completing the Japanese Language Placement Test.</p><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Your results will be analyzed and you will receive a recommended starting module based on your performance.</p></div><div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; padding: 2rem; margin: 1rem 0; text-align: center;"><p style="margin: 0; font-size: 1.3em; font-weight: 600; color: #1e40af;">Good luck with your Japanese learning journey!</p></div>',
    999
FROM placement_tests pt 
WHERE pt.title = 'Japanese Language Placement Test'
LIMIT 1
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Create indexes for better performance
CREATE INDEX idx_placement_questions_difficulty ON placement_questions(difficulty_level, is_active);
CREATE INDEX idx_placement_sessions_student_status ON placement_test_sessions(student_id, status);
CREATE INDEX idx_placement_results_score ON placement_test_results(percentage_score DESC);
CREATE INDEX idx_placement_results_level ON placement_test_results(recommended_level);
