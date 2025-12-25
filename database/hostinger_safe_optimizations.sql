-- HOSTINGER-SAFE PERFORMANCE OPTIMIZATIONS
-- These are 100% safe for shared hosting environments

-- 1. Add indexes (SAFE - only improves query speed)
CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_users_phone_number ON users(phone_number);

-- 2. Quiz system indexes
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_student_quiz ON quiz_attempts(student_id, quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_choices_question_id ON quiz_choices(question_id);

-- 3. Course progress indexes
CREATE INDEX IF NOT EXISTS idx_course_progress_student_course ON course_progress(student_id, course_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_student_course ON enrollments(student_id, course_id);

-- 4. OTP system indexes
CREATE INDEX IF NOT EXISTS idx_otps_user_type_expires ON otps(user_id, type, expires_at);
CREATE INDEX IF NOT EXISTS idx_otps_email ON otps(email);

-- 5. Clean up old OTPs (manual cleanup - safe for Hostinger)
-- Run this query weekly to clean up expired OTPs
-- DELETE FROM otps WHERE expires_at < NOW() - INTERVAL 1 DAY;

-- 6. Clean up old sessions (manual cleanup - safe for Hostinger)  
-- Run this query weekly to clean up expired sessions
-- DELETE FROM user_sessions WHERE expires_at < NOW();

-- VERIFICATION: Check if indexes were created
-- SELECT table_name, index_name, column_name 
-- FROM information_schema.statistics 
-- WHERE table_schema = 'u367042766_japanese_lms' 
-- AND index_name LIKE 'idx_%';
