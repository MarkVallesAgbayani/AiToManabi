-- Update quiz_questions table structure to support all 6 question types
-- This script adds the missing ENUM values and JSON fields for complex question data

-- First, let's check if we need to add the question_type column
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'question_type'
);

-- Add question_type column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN question_type ENUM(''multiple_choice'', ''true_false'', ''fill_blank'', ''word_definition'', ''pronunciation'', ''sentence_translation'') NOT NULL DEFAULT ''multiple_choice'' AFTER question_text',
    'SELECT ''question_type column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update the ENUM to include all 6 question types if column exists
SET @sql = IF(@column_exists > 0, 
    'ALTER TABLE quiz_questions MODIFY COLUMN question_type ENUM(''multiple_choice'', ''true_false'', ''fill_blank'', ''word_definition'', ''pronunciation'', ''sentence_translation'') NOT NULL DEFAULT ''multiple_choice''',
    'SELECT ''question_type column was just added'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add JSON fields for complex question data
-- Add word_definition_pairs for word definition questions
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'word_definition_pairs'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN word_definition_pairs JSON NULL AFTER question_type',
    'SELECT ''word_definition_pairs column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add translation_pairs for sentence translation questions
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'translation_pairs'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN translation_pairs JSON NULL AFTER word_definition_pairs',
    'SELECT ''translation_pairs column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pronunciation data fields
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'word'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN word VARCHAR(255) NULL AFTER translation_pairs',
    'SELECT ''word column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'romaji'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN romaji VARCHAR(255) NULL AFTER word',
    'SELECT ''romaji column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'meaning'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN meaning TEXT NULL AFTER romaji',
    'SELECT ''meaning column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'audio_url'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN audio_url VARCHAR(500) NULL AFTER meaning',
    'SELECT ''audio_url column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add accuracy_threshold for pronunciation questions
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'accuracy_threshold'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN accuracy_threshold DECIMAL(5,2) DEFAULT 70.00 AFTER audio_url',
    'SELECT ''accuracy_threshold column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add answers field for fill_blank questions
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'answers'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN answers JSON NULL AFTER accuracy_threshold',
    'SELECT ''answers column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add score field for questions
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'quiz_questions' 
    AND COLUMN_NAME = 'score'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE quiz_questions ADD COLUMN score INT DEFAULT 1 AFTER answers',
    'SELECT ''score column already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the final table structure
DESCRIBE quiz_questions;
