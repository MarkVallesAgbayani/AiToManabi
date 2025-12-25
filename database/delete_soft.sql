-- Add soft delete columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL COMMENT 'Timestamp when the user was soft deleted';
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by INT NULL COMMENT 'Admin user ID who deleted this user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS deletion_reason TEXT NULL COMMENT 'Reason for deleting the user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS restoration_deadline TIMESTAMP NULL COMMENT 'Deadline for restoration before permanent deletion';

-- Add foreign key constraint for deleted_by
ALTER TABLE users ADD CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON users(deleted_at);
CREATE INDEX IF NOT EXISTS idx_users_deleted_by ON users(deleted_by);
CREATE INDEX IF NOT EXISTS idx_users_restoration_deadline ON users(restoration_deadline);

-- Add composite index for soft delete queries
CREATE INDEX IF NOT EXISTS idx_users_soft_delete ON users(deleted_at, status);