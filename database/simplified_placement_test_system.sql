-- Simplified Placement Test System
-- This script creates a simplified placement test system with just 1 main table
-- plus placement_result and placement_session tables as requested

USE japanese_lms;

-- Step 1: Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Step 2: Drop all existing placement test tables
DROP TABLE IF EXISTS placement_page_questions;
DROP TABLE IF EXISTS placement_test_answers;
DROP TABLE IF EXISTS placement_test_results;
DROP TABLE IF EXISTS placement_test_sessions;
DROP TABLE IF EXISTS placement_test_pages;
DROP TABLE IF EXISTS placement_question_choices;
DROP TABLE IF EXISTS placement_questions;
DROP TABLE IF EXISTS placement_test_design;
DROP TABLE IF EXISTS placement_test_images;
DROP TABLE IF EXISTS placement_tests;
DROP TABLE IF EXISTS placement_difficulty_modules;

-- Step 3: Create the single main placement_test table with all functionalities
CREATE TABLE placement_test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Test Configuration
    title VARCHAR(255) NOT NULL DEFAULT 'Japanese Language Placement Test',
    description TEXT,
    instructions TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    
    -- Question Data (stored as JSON for flexibility)
    questions JSON, -- Array of question objects with choices, difficulty, etc.
    
    -- Design Settings (stored as JSON)
    design_settings JSON, -- Colors, fonts, custom CSS, etc.
    
    -- Page Content (stored as JSON)
    page_content JSON, -- Welcome, instructions, completion pages
    
    -- Module Assignments (stored as JSON)
    module_assignments JSON, -- Which modules are assigned to which difficulty levels
    
    -- Metadata
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_published (is_published),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create placement_result table for student answers
CREATE TABLE placement_result (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    
    -- Test Session Info
    session_token VARCHAR(64) UNIQUE NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    -- Student Answers (stored as JSON)
    answers JSON, -- Array of student answers with question_id, choice_id, answer_text, etc.
    
    -- Results and Analysis
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    percentage_score DECIMAL(5,2) DEFAULT 0,
    
    -- Difficulty Breakdown (stored as JSON)
    difficulty_scores JSON, -- {"beginner": {"correct": 5, "total": 8}, "intermediate": {...}, "advanced": {...}}
    
    -- Recommendations
    recommended_level ENUM('beginner', 'intermediate', 'advanced') NULL,
    recommended_course_id INT NULL,
    detailed_feedback TEXT,
    
    -- Status
    status ENUM('started', 'in_progress', 'completed', 'abandoned') DEFAULT 'started',
    
    -- Metadata
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES placement_test(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_course_id) REFERENCES courses(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_student_test (student_id, test_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_recommended_level (recommended_level),
    INDEX idx_score (percentage_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Create placement_session table for student first-time registration/login
CREATE TABLE placement_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    session_token VARCHAR(64) UNIQUE NOT NULL,
    
    -- Session Type
    session_type ENUM('first_login', 'first_registration', 'test_attempt') NOT NULL,
    
    -- Session Data (stored as JSON for flexibility)
    session_data JSON, -- Any additional session information
    
    -- Status
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',
    
    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    -- Metadata
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_token (session_token),
    INDEX idx_status (status),
    INDEX idx_type (session_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Insert default placement test with sample data structure
INSERT INTO placement_test (
    title,
    description,
    instructions,
    questions,
    design_settings,
    page_content,
    module_assignments,
    created_by
) VALUES (
    'Japanese Language Placement Test',
    'Comprehensive assessment to determine your Japanese language proficiency level',
    'This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey. Please answer all questions to the best of your ability.',
    
    -- Sample questions structure (JSON)
    JSON_ARRAY(
        JSON_OBJECT(
            'id', 1,
            'question_text', 'What does "こんにちは" mean?',
            'question_type', 'multiple_choice',
            'difficulty_level', 'beginner',
            'points', 1,
            'explanation', 'こんにちは means "hello" or "good afternoon" in Japanese.',
            'image_path', NULL,
            'audio_path', NULL,
            'choices', JSON_ARRAY(
                JSON_OBJECT('id', 1, 'text', 'Hello', 'is_correct', true),
                JSON_OBJECT('id', 2, 'text', 'Goodbye', 'is_correct', false),
                JSON_OBJECT('id', 3, 'text', 'Thank you', 'is_correct', false),
                JSON_OBJECT('id', 4, 'text', 'Please', 'is_correct', false)
            )
        ),
        JSON_OBJECT(
            'id', 2,
            'question_text', 'Which particle is used to indicate the subject of a sentence?',
            'question_type', 'multiple_choice',
            'difficulty_level', 'intermediate',
            'points', 1,
            'explanation', 'The particle が is used to indicate the subject of a sentence.',
            'image_path', NULL,
            'audio_path', NULL,
            'choices', JSON_ARRAY(
                JSON_OBJECT('id', 1, 'text', 'を (wo)', 'is_correct', false),
                JSON_OBJECT('id', 2, 'text', 'が (ga)', 'is_correct', true),
                JSON_OBJECT('id', 3, 'text', 'に (ni)', 'is_correct', false),
                JSON_OBJECT('id', 4, 'text', 'で (de)', 'is_correct', false)
            )
        )
    ),
    
    -- Design settings (JSON)
    JSON_OBJECT(
        'header_color', '#1f2937',
        'header_text_color', '#ffffff',
        'background_color', '#f5f5f5',
        'accent_color', '#dc2626',
        'font_family', 'Inter',
        'button_color', '#dc2626',
        'custom_css', NULL
    ),
    
    -- Page content (JSON)
    JSON_OBJECT(
        'welcome', '<h1 style="text-align: center; color: #2563eb; margin-bottom: 1rem;">🎯 Welcome to Japanese Language Placement Test</h1><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey.</p><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Please answer all questions to the best of your ability.</p>',
        'instructions', '<h2 style="text-align: center; color: #059669; margin-bottom: 1.5rem;">📋 Are you ready?</h2><p style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1rem;">Before we begin, here are some important instructions:</p><ul style="font-size: 1.1em; line-height: 1.6; margin-bottom: 1.5rem;"><li style="margin-bottom: 0.5rem;">This test contains multiple-choice questions</li><li style="margin-bottom: 0.5rem;">Select the answer that best represents your knowledge</li><li style="margin-bottom: 0.5rem;">Take your time and answer honestly</li></ul>',
        'completion', '<div style="text-align: center; margin-bottom: 2rem;"><h2 style="color: #059669; font-size: 2.5em; margin-bottom: 1rem;">🎉 Test Completed!</h2><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Thank you for completing the Japanese Language Placement Test.</p><p style="font-size: 1.2em; line-height: 1.6; margin-bottom: 1rem;">Your results will be analyzed and you will receive a recommended starting module based on your performance.</p></div>'
    ),
    
    -- Module assignments (JSON) - You can assign modules to difficulty levels
    JSON_OBJECT(
        'beginner', JSON_ARRAY(
            JSON_OBJECT('course_id', 1, 'title', 'Basic Japanese', 'is_primary', true),
            JSON_OBJECT('course_id', 2, 'title', 'Hiragana & Katakana', 'is_primary', false)
        ),
        'intermediate', JSON_ARRAY(
            JSON_OBJECT('course_id', 3, 'title', 'Intermediate Grammar', 'is_primary', true),
            JSON_OBJECT('course_id', 4, 'title', 'Kanji Basics', 'is_primary', false)
        ),
        'advanced', JSON_ARRAY(
            JSON_OBJECT('course_id', 5, 'title', 'Advanced Japanese', 'is_primary', true),
            JSON_OBJECT('course_id', 6, 'title', 'Business Japanese', 'is_primary', false)
        )
    ),
    
    -- Created by the first admin/teacher user
    (SELECT id FROM users WHERE role IN ('admin', 'teacher') ORDER BY id ASC LIMIT 1)
);

-- Step 7: Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 8: Verify the new structure
SELECT 'SUCCESS: Simplified placement test system created!' as status;
SELECT 'Main table: placement_test (contains everything in JSON format)' as main_table;
SELECT 'Results table: placement_result (student answers and results)' as results_table;
SELECT 'Session table: placement_session (student session tracking)' as session_table;
SELECT 'All functionalities preserved with simplified structure!' as benefits;

-- Show the new table structures
DESCRIBE placement_test;
DESCRIBE placement_result;
DESCRIBE placement_session;
