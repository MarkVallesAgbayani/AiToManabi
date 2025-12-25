-- HOSTINGER: ADD MISSING INDEXES FOR 400+ STUDENTS
-- Based on your current database schema analysis

-- 1. USERS TABLE - Critical for login/registration performance
CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_users_phone_number ON users(phone_number);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- 2. QUIZ SYSTEM - Missing indexes for quiz performance
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_student_quiz ON quiz_attempts(student_id, quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_completed_at ON quiz_attempts(completed_at);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);

-- 3. COURSE PROGRESS - Critical for student progress tracking
CREATE INDEX IF NOT EXISTS idx_course_progress_student_course ON course_progress(student_id, course_id);
CREATE INDEX IF NOT EXISTS idx_course_progress_status ON course_progress(completion_status);
CREATE INDEX IF NOT EXISTS idx_enrollments_student_course ON enrollments(student_id, course_id);

-- 4. OTP SYSTEM - Critical for authentication performance
CREATE INDEX IF NOT EXISTS idx_otps_user_type_expires ON otps(user_id, type, expires_at);
CREATE INDEX IF NOT EXISTS idx_otps_email ON otps(email);
CREATE INDEX IF NOT EXISTS idx_otps_created_at ON otps(created_at);

-- 5. SESSIONS - Important for concurrent user handling
CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id);

-- 6. ACTIVITY LOGGING - For monitoring and analytics
CREATE INDEX IF NOT EXISTS idx_activity_log_user_id ON activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at);

-- 7. COURSE STRUCTURE - For course navigation performance

-- 8. PAYMENT SYSTEM - For transaction performance
CREATE INDEX IF NOT EXISTS idx_payments_user_id ON payments(user_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(payment_status);
CREATE INDEX IF NOT EXISTS idx_payment_sessions_user_id ON payment_sessions(user_id);

-- VERIFICATION QUERY - Run this after adding indexes
-- SELECT table_name, index_name, column_name 
-- FROM information_schema.statistics 
-- WHERE table_schema = 'u367042766_aitomanabi_new' 
-- AND index_name LIKE 'idx_%'
-- ORDER BY table_name, index_name;
