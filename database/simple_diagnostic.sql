-- Simple diagnostic - run these queries one by one in phpMyAdmin

-- Query 1: Check if test exists
SELECT * FROM placement_tests WHERE id = 1 AND is_active = 1;

-- Query 2: Check if design exists  
SELECT * FROM placement_test_design WHERE test_id = 1;

-- Query 3: Test the exact PHP query
SELECT pt.*, ptd.* 
FROM placement_tests pt 
LEFT JOIN placement_test_design ptd ON pt.id = ptd.test_id 
WHERE pt.id = 1 AND pt.is_active = 1;
