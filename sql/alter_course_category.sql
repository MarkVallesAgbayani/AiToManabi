ALTER TABLE course_category
ADD COLUMN status ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN restored_at TIMESTAMP NULL DEFAULT NULL;

-- Update existing records to have 'active' status
UPDATE course_category SET status = 'active' WHERE status IS NULL; 