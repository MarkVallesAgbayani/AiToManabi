-- Migration script to add question page support to placement test system
-- Run this script to update existing database to support question pages

-- Check if question_count column exists, if not add it
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
  AND table_name = 'placement_test_pages' 
  AND column_name = 'question_count';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE placement_test_pages ADD COLUMN question_count INT DEFAULT 0 AFTER page_order',
    'SELECT "Column question_count already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update page_type enum to include 'questions'
ALTER TABLE placement_test_pages 
MODIFY COLUMN page_type ENUM('welcome', 'instructions', 'questions', 'completion', 'custom') NOT NULL;

-- Create placement_page_questions table if it doesn't exist
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

-- Update existing question counts for any existing question pages
UPDATE placement_test_pages p 
SET question_count = (
    SELECT COUNT(*) 
    FROM placement_page_questions ppq 
    WHERE ppq.page_id = p.id
) 
WHERE p.page_type = 'questions';

SELECT 'Migration completed successfully' as status;
