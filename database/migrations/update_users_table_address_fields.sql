-- ===================================================================
-- UPDATE USERS TABLE: REPLACE LOCATION WITH ADDRESS FIELDS
-- ===================================================================
-- This script will safely update the users table to replace the single
-- 'location' column with three separate address fields:
-- - address_line1 (VARCHAR(255))
-- - address_line2 (VARCHAR(255)) 
-- - city (VARCHAR(100))
-- ===================================================================

-- Make sure we're using the right database
USE japanese_lms;

-- Start transaction for safety
START TRANSACTION;

-- Step 1: Add the new address columns
-- Check if address_line1 column exists, if not add it
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'address_line1'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN address_line1 VARCHAR(255) NULL AFTER suffix',
    'SELECT "Address Line 1 column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if address_line2 column exists, if not add it
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'address_line2'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN address_line2 VARCHAR(255) NULL AFTER address_line1',
    'SELECT "Address Line 2 column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if city column exists, if not add it
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'city'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL AFTER address_line2',
    'SELECT "City column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Migrate existing location data to new address fields
-- This will only affect admin and teacher users as requested
-- For existing location data, we'll put it in address_line1
UPDATE users 
SET address_line1 = location 
WHERE location IS NOT NULL 
AND location != '' 
AND role IN ('admin', 'teacher');

-- Step 3: Verify the migration was successful
-- Count how many admin/teacher users had location data migrated
SELECT 
    COUNT(*) as migrated_records,
    'Admin and Teacher users with location data migrated to address_line1' as description
FROM users 
WHERE address_line1 IS NOT NULL 
AND role IN ('admin', 'teacher');

-- Step 4: Show current address field status
SELECT 
    role,
    COUNT(*) as total_users,
    COUNT(address_line1) as has_address_line1,
    COUNT(address_line2) as has_address_line2,
    COUNT(city) as has_city,
    COUNT(location) as has_old_location
FROM users 
GROUP BY role
ORDER BY role;

-- Step 5: Optional - Remove the old location column after verification
-- UNCOMMENT THE FOLLOWING LINES ONLY AFTER VERIFYING THE MIGRATION WAS SUCCESSFUL
-- AND YOU'RE SURE YOU WANT TO REMOVE THE OLD LOCATION COLUMN

-- Check if location column exists before attempting to drop it
-- SET @exists := (
--     SELECT COUNT(*)
--     FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE TABLE_SCHEMA = 'japanese_lms'
--     AND TABLE_NAME = 'users'
--     AND COLUMN_NAME = 'location'
-- );
-- 
-- SET @sqlstmt := IF(
--     @exists > 0,
--     'ALTER TABLE users DROP COLUMN location',
--     'SELECT "Location column does not exist" as message'
-- );
-- 
-- PREPARE stmt FROM @sqlstmt;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- Commit the transaction
COMMIT;

-- Show final table structure
DESCRIBE users;

-- Success message
SELECT 'Users table successfully updated with address fields!' as status;
