-- Add archive functionality to courses table
-- Run this SQL script to add the necessary columns for course archiving

-- Add is_archived column (default 0 for existing courses)
ALTER TABLE courses ADD COLUMN is_archived TINYINT(1) DEFAULT 0;

-- Add archived_at column to track when courses were archived
ALTER TABLE courses ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL;

-- Add index for better performance when querying archived courses
CREATE INDEX idx_courses_archived ON courses(is_archived, teacher_id);

-- Add index for archived_at for sorting
CREATE INDEX idx_courses_archived_at ON courses(archived_at DESC);

-- Update existing courses to ensure they are not archived
UPDATE courses SET is_archived = 0 WHERE is_archived IS NULL;
