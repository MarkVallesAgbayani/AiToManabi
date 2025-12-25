-- Remove placement_tests table dependency from placement_test_pages
-- This script updates the placement_test_pages table to work independently

-- First, check if placement_test_pages table exists
-- If it exists, we need to modify it to remove the test_id dependency

-- Step 1: Remove foreign key constraint if it exists
SET FOREIGN_KEY_CHECKS = 0;

-- Step 2: Drop dependent tables first to avoid foreign key constraint issues
DROP TABLE IF EXISTS placement_page_questions;
DROP TABLE IF EXISTS placement_test_answers;
DROP TABLE IF EXISTS placement_test_results;
DROP TABLE IF EXISTS placement_test_sessions;

-- Step 3: Now drop the placement_test_pages table
DROP TABLE IF EXISTS placement_test_pages;

-- Step 4: Create new placement_test_pages table without test_id dependency
CREATE TABLE IF NOT EXISTS placement_test_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_type ENUM('welcome', 'instructions', 'questions', 'completion', 'custom') NOT NULL,
    page_key VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    image_path VARCHAR(255),
    page_order INT NOT NULL DEFAULT 0,
    question_count INT DEFAULT 0,
    is_required BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_page_order (page_order),
    INDEX idx_page_type (page_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 7: Optional - Drop placement_tests table if you want to completely remove it
-- Uncomment the following line if you want to remove the placement_tests table entirely
-- DROP TABLE IF EXISTS placement_tests;

-- Step 8: Optional - Drop placement_test_design table if you want to completely remove it
-- Uncomment the following line if you want to remove the placement_test_design table entirely
-- DROP TABLE IF EXISTS placement_test_design;

-- Note: The placement_test_pages table now works independently without requiring a placement_tests table
-- You can add, edit, and manage pages directly through the Pages Management system
