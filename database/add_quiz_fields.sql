-- Add missing fields to quizzes table for retake functionality
-- This is safe to run multiple times as it uses IF NOT EXISTS

-- Add max_retakes field (default 3, -1 for unlimited, 0 for no retakes)
ALTER TABLE quizzes 
ADD COLUMN IF NOT EXISTS max_retakes INT DEFAULT 3 COMMENT 'Maximum retakes allowed (-1=unlimited, 0=none, >0=specific number)';

-- Add time_limit field (in minutes, NULL for no limit)
ALTER TABLE quizzes 
ADD COLUMN IF NOT EXISTS time_limit INT DEFAULT NULL COMMENT 'Time limit in minutes (NULL for no limit)';

-- Add passing_score field (percentage, default 70)
ALTER TABLE quizzes 
ADD COLUMN IF NOT EXISTS passing_score INT DEFAULT 70 COMMENT 'Minimum score percentage to pass';

-- Add total_points field (total possible points)
ALTER TABLE quizzes 
ADD COLUMN IF NOT EXISTS total_points INT DEFAULT 0 COMMENT 'Total possible points for the quiz';

-- Update existing quizzes to have default values if they're NULL
UPDATE quizzes SET max_retakes = 3 WHERE max_retakes IS NULL;
UPDATE quizzes SET passing_score = 70 WHERE passing_score IS NULL;
UPDATE quizzes SET total_points = 0 WHERE total_points IS NULL;
