-- Clean up placement test system - Remove all data, keep structure
-- Run this script to clean up existing data and prepare for fresh setup

USE japanese_lms;

-- Step 1: Clean up existing data from all placement test tables
DELETE FROM placement_test_answers;
DELETE FROM placement_test_results;
DELETE FROM placement_test_sessions;
DELETE FROM placement_test_pages;
DELETE FROM placement_test_design;
DELETE FROM placement_test_images;

-- Step 2: Create the main placement_tests table if it doesn't exist
CREATE TABLE IF NOT EXISTS placement_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT 'AiToManabi Placement Test',
    description TEXT,
    instructions TEXT,
    max_questions INT DEFAULT 20,
    is_active BOOLEAN DEFAULT TRUE,
    is_published BOOLEAN DEFAULT FALSE,
    passing_score DECIMAL(5,2) DEFAULT 70.00,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create or update a single placement test record (completely empty, ready for your configuration)
INSERT INTO placement_tests (
    id,
    title,
    description,
    instructions,
    max_questions,
    is_active,
    is_published,
    passing_score,
    created_by
) VALUES (
    1,
    '', -- Empty title - you will set this
    '', -- Empty description - you will set this
    '', -- Empty instructions - you will set this
    20,
    1,
    0, -- Not published yet
    70.00,
    (SELECT id FROM users WHERE role IN ('admin', 'teacher') ORDER BY id ASC LIMIT 1)
) ON DUPLICATE KEY UPDATE
    title = '', -- Reset to empty
    description = '', -- Reset to empty
    instructions = '', -- Reset to empty
    max_questions = 20,
    is_active = 1,
    is_published = 0, -- Not published yet
    passing_score = 70.00,
    updated_at = CURRENT_TIMESTAMP;

-- Step 4: Create or update completely empty design settings record
INSERT INTO placement_test_design (
    test_id,
    header_color,
    header_text_color,
    footer_color,
    footer_text_color,
    button_color,
    background_color,
    page_background_color,
    font_family,
    welcome_content,
    instructions_content,
    completion_content,
    is_published
) VALUES (
    1,
    '', -- Empty header color - you will set this
    '', -- Empty header text color - you will set this
    '', -- Empty footer color - you will set this
    '', -- Empty footer text color - you will set this
    '', -- Empty button color - you will set this
    '', -- Empty background color - you will set this
    '', -- Empty page background color - you will set this
    '', -- Empty font family - you will set this
    '', -- Empty welcome content - you will add this via Pages Management
    '', -- Empty instructions content - you will add this via Pages Management
    '', -- Empty completion content - you will add this via Pages Management
    0
) ON DUPLICATE KEY UPDATE
    header_color = '', -- Reset to empty
    header_text_color = '', -- Reset to empty
    footer_color = '', -- Reset to empty
    footer_text_color = '', -- Reset to empty
    button_color = '', -- Reset to empty
    background_color = '', -- Reset to empty
    page_background_color = '', -- Reset to empty
    font_family = '', -- Reset to empty
    welcome_content = '', -- Reset to empty
    instructions_content = '', -- Reset to empty
    completion_content = '', -- Reset to empty
    is_published = 0,
    updated_at = CURRENT_TIMESTAMP;

-- Step 5: Verify the setup
SELECT 'SUCCESS: Placement test system cleaned up and ready for your configuration!' as status;
SELECT 'Test ID: 1 created with completely empty content' as test_info;
SELECT 'Design settings: All fields empty - you will set titles, colors, content, etc.' as design_info;
SELECT 'Ready for you to configure everything via Pages Management interface' as next_step;
