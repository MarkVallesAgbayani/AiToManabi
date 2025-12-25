-- Add ban-related columns to users table
-- This script adds columns to track ban and unban information

-- Add ban-related columns (using IF NOT EXISTS to avoid errors if columns already exist)
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason TEXT NULL COMMENT 'Reason for banning the user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_at TIMESTAMP NULL COMMENT 'Timestamp when the user was banned';
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_by INT NULL COMMENT 'Admin user ID who banned this user';

-- Add unban-related columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS unbanned_at TIMESTAMP NULL COMMENT 'Timestamp when the user was unbanned';
ALTER TABLE users ADD COLUMN IF NOT EXISTS unbanned_by INT NULL COMMENT 'Admin user ID who unbanned this user';

-- Add foreign key constraints (only if they don't exist)
-- Note: These might fail if constraints already exist, which is fine
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_banned_by');
SET @sql = IF(@constraint_exists = 0, 
              'ALTER TABLE users ADD CONSTRAINT fk_users_banned_by FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL', 
              'SELECT "Constraint already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_unbanned_by');
SET @sql = IF(@constraint_exists = 0, 
              'ALTER TABLE users ADD CONSTRAINT fk_users_unbanned_by FOREIGN KEY (unbanned_by) REFERENCES users(id) ON DELETE SET NULL', 
              'SELECT "Constraint already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for better performance (only if they don't exist)
CREATE INDEX IF NOT EXISTS idx_users_banned_at ON users(banned_at);
CREATE INDEX IF NOT EXISTS idx_users_banned_by ON users(banned_by);
CREATE INDEX IF NOT EXISTS idx_users_unbanned_at ON users(unbanned_at);
CREATE INDEX IF NOT EXISTS idx_users_unbanned_by ON users(unbanned_by);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
