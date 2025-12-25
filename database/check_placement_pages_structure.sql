-- Check current structure of placement_test_pages table
DESCRIBE placement_test_pages;

-- Or alternatively:
SHOW COLUMNS FROM placement_test_pages;

-- Check if the table exists and what columns it has
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'japanese_lms' 
AND TABLE_NAME = 'placement_test_pages'
ORDER BY ORDINAL_POSITION;
