-- Fix placement test setup - create missing records
-- Run this script to fix the preview issue

USE japanese_lms;

-- Step 1: Create the placement test record
INSERT INTO placement_tests (
    id,
    title,
    description,
    instructions,
    max_questions,
    time_limit_minutes,
    is_active,
    passing_score,
    created_by,
    created_at
) VALUES (
    1,
    'Japanese Language Placement Test',
    'Comprehensive assessment to determine your Japanese language proficiency level',
    'This test will help us determine your current Japanese language level and recommend the best starting module for your learning journey.',
    20,
    60,
    1,
    70.00,
    (SELECT id FROM users WHERE role IN ('admin', 'teacher') ORDER BY id ASC LIMIT 1),
    NOW()
);

-- Step 2: Create the design settings record
INSERT INTO placement_test_design (
    test_id,
    logo_image,
    header_color,
    header_type,
    background_color,
    background_type,
    accent_color,
    font_family,
    welcome_content,
    instructions_content,
    completion_content,
    is_published,
    created_at
) VALUES (
    1,
    NULL,
    '#1f2937',
    'solid',
    '#f5f5f5',
    'solid',
    '#dc2626',
    'Inter',
    '<h2>Welcome to the Japanese Language Placement Test</h2><p>This comprehensive assessment will help determine your current proficiency level and recommend the perfect starting point for your Japanese learning journey.</p><p>The test is designed to evaluate your knowledge across different aspects of the Japanese language.</p>',
    '<h3>Test Instructions</h3><ul><li>Answer all questions to the best of your ability</li><li>Take your time - there is no rush</li><li>Choose the most appropriate answer for each question</li><li>Your results will help us recommend the right course level for you</li><li>You can review your answers before submitting</li></ul><p><strong>Good luck!</strong></p>',
    '<h2>Test Complete!</h2><p>Thank you for completing the placement test. Your results have been processed and we will recommend the most suitable course level based on your performance.</p><p>Please check your dashboard for course recommendations.</p>',
    0,
    NOW()
);

-- Step 3: Verify the fix worked
SELECT 'Verification - Test record:' as check_type;
SELECT id, title, is_active FROM placement_tests WHERE id = 1;

SELECT 'Verification - Design record:' as check_type;
SELECT test_id, header_color, accent_color, is_published FROM placement_test_design WHERE test_id = 1;

SELECT 'Verification - Final query test:' as check_type;
SELECT pt.id, pt.title, pt.is_active, ptd.header_color, ptd.is_published
FROM placement_tests pt 
LEFT JOIN placement_test_design ptd ON pt.id = ptd.test_id 
WHERE pt.id = 1 AND pt.is_active = 1;

SELECT 'SUCCESS: Placement test is now ready for preview!' as status;
