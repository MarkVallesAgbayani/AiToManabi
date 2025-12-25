-- Add images column to placement_test table
ALTER TABLE placement_test 
ADD COLUMN images TEXT COMMENT 'JSON array of image metadata and paths';

-- Update existing records to have empty images array
UPDATE placement_test 
SET images = '[]' 
WHERE images IS NULL;

-- Optional: Add index for better performance
CREATE INDEX idx_placement_test_images ON placement_test(images(100));
