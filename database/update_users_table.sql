-- Make sure we're using the right database
USE japanese_lms;

-- Check if the role column exists, if not add it
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'role'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN role ENUM("admin", "teacher", "student") NOT NULL DEFAULT "student"',
    'ALTER TABLE users MODIFY COLUMN role ENUM("admin", "teacher", "student") NOT NULL DEFAULT "student"'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update any NULL roles to 'student'
UPDATE users SET role = 'student' WHERE role IS NULL; 