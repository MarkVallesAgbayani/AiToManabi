-- Update placement_test_pages table for enhanced functionality
-- Run this script in your japanese_lms database

USE japanese_lms;

-- Step 1: Add the question_count column after page_order
ALTER TABLE placement_test_pages 
ADD COLUMN question_count INT DEFAULT 0 AFTER page_order;

-- Step 2: Update the page_type enum to include 'questions'
ALTER TABLE placement_test_pages 
MODIFY COLUMN page_type ENUM('welcome', 'instructions', 'questions', 'completion', 'custom') NOT NULL;

-- Step 3: Create the placement_page_questions table to link questions to pages
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

-- Step 4: Verify the updates
SELECT 'Updated placement_test_pages structure:' as info;
DESCRIBE placement_test_pages;

SELECT 'New placement_page_questions table:' as info;
DESCRIBE placement_page_questions;

-- Step 5: Show what page types are now available
SELECT 'Available page types:' as info;
SHOW COLUMNS FROM placement_test_pages LIKE 'page_type';

SELECT 'Database update completed successfully!' as status;
