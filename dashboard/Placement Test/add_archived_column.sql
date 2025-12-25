-- Add archived column to placement_test table
ALTER TABLE placement_test 
ADD COLUMN archived TINYINT(1) DEFAULT 0 COMMENT 'Whether the test is archived (0 = not archived, 1 = archived)';

-- Update existing records to have archived = 0
UPDATE placement_test SET archived = 0 WHERE archived IS NULL;
