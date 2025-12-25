-- HOSTINGER: ESSENTIAL INDEXES ONLY
-- These are the most critical indexes for 400+ students
-- Safe to run - will only create indexes for existing tables/columns

-- 1. USERS TABLE - Most critical for login/registration
CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified);
CREATE INDEX IF NOT EXISTS idx_users_phone_number ON users(phone_number);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);

-- 2. QUIZ SYSTEM - Critical for quiz performance
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_student_quiz ON quiz_attempts(student_id, quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_choices_question_id ON quiz_choices(question_id);

-- 3. OTP SYSTEM - Critical for authentication
CREATE INDEX IF NOT EXISTS idx_otps_user_type_expires ON otps(user_id, type, expires_at);
CREATE INDEX IF NOT EXISTS idx_otps_email ON otps(email);
CREATE INDEX IF NOT EXISTS idx_otps_created_at ON otps(created_at);

-- 4. COURSE PROGRESS - Important for student tracking
CREATE INDEX IF NOT EXISTS idx_course_progress_student_course ON course_progress(student_id, course_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_student_course ON enrollments(student_id, course_id);

-- 5. COURSE STRUCTURE - For navigation performance
CREATE INDEX IF NOT EXISTS idx_chapters_course_id ON chapters(course_id);
CREATE INDEX IF NOT EXISTS idx_sections_chapter_id ON sections(chapter_id);

-- 6. PAYMENT SYSTEM - If you have payments
CREATE INDEX IF NOT EXISTS idx_payments_user_id ON payments(user_id);
CREATE INDEX IF NOT EXISTS idx_payment_sessions_user_id ON payment_sessions(user_id);

-- VERIFICATION: Check what indexes were created
SELECT table_name, index_name, column_name 
FROM information_schema.statistics 
WHERE table_schema = 'u367042766_aitomanabi_new' 
AND index_name LIKE 'idx_%'
ORDER BY table_name, index_name;
