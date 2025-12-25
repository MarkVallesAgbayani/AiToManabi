-- HOSTINGER: SAFE CONDITIONAL INDEXES
-- Only creates indexes for columns that actually exist
-- Run this safely - it won't break if columns don't exist

-- 1. USERS TABLE - Check and add indexes for existing columns
-- Check if email_verified column exists, then add index
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'users' AND column_name = 'email_verified') > 0,
    'CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified)',
    'SELECT "email_verified column not found in users table" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if phone_number column exists, then add index
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'users' AND column_name = 'phone_number') > 0,
    'CREATE INDEX IF NOT EXISTS idx_users_phone_number ON users(phone_number)',
    'SELECT "phone_number column not found in users table" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if role column exists, then add index
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'users' AND column_name = 'role') > 0,
    'CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)',
    'SELECT "role column not found in users table" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. QUIZ SYSTEM - Check and add indexes for existing columns
-- Check if quiz_attempts table exists and has student_id column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'quiz_attempts' AND column_name = 'student_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_quiz_attempts_student_quiz ON quiz_attempts(student_id, quiz_id)',
    'SELECT "quiz_attempts table or student_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if quiz_questions table exists and has quiz_id column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'quiz_questions' AND column_name = 'quiz_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id)',
    'SELECT "quiz_questions table or quiz_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. OTP SYSTEM - Check and add indexes for existing columns
-- Check if otps table exists and has user_id column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'otps' AND column_name = 'user_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_otps_user_type_expires ON otps(user_id, type, expires_at)',
    'SELECT "otps table or user_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if otps table exists and has email column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'otps' AND column_name = 'email') > 0,
    'CREATE INDEX IF NOT EXISTS idx_otps_email ON otps(email)',
    'SELECT "otps table or email column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. COURSE PROGRESS - Check and add indexes for existing columns
-- Check if course_progress table exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'course_progress') > 0,
    'CREATE INDEX IF NOT EXISTS idx_course_progress_student_course ON course_progress(student_id, course_id)',
    'SELECT "course_progress table not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if enrollments table exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'enrollments') > 0,
    'CREATE INDEX IF NOT EXISTS idx_enrollments_student_course ON enrollments(student_id, course_id)',
    'SELECT "enrollments table not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. CHAPTERS TABLE - Check and add indexes for existing columns
-- Check if chapters table has course_id column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'chapters' AND column_name = 'course_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_chapters_course_id ON chapters(course_id)',
    'SELECT "chapters table or course_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if chapters table has section_id column (alternative structure)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'chapters' AND column_name = 'section_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_chapters_section_id ON chapters(section_id)',
    'SELECT "chapters table or section_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. SECTIONS TABLE - Check and add indexes for existing columns
-- Check if sections table has chapter_id column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'u367042766_aitomanabi_new' 
     AND table_name = 'sections' AND column_name = 'chapter_id') > 0,
    'CREATE INDEX IF NOT EXISTS idx_sections_chapter_id ON sections(chapter_id)',
    'SELECT "sections table or chapter_id column not found" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FINAL MESSAGE
SELECT 'Index creation completed. Check messages above for any missing tables/columns.' as final_status;
