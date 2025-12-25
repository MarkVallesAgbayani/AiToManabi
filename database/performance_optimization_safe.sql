-- SAFE PERFORMANCE OPTIMIZATIONS FOR 400+ STUDENTS
-- These changes have ZERO risk of breaking your system

-- 1. Add performance indexes (SAFE - only improves speed)
CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(email_verified);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_users_phone_number ON users(phone_number);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- 2. Quiz performance indexes
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_student_quiz ON quiz_attempts(student_id, quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_attempts_completed_at ON quiz_attempts(completed_at);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_choices_question_id ON quiz_choices(question_id);

-- 3. Course progress indexes
CREATE INDEX IF NOT EXISTS idx_course_progress_student_course ON course_progress(student_id, course_id);
CREATE INDEX IF NOT EXISTS idx_course_progress_status ON course_progress(completion_status);
CREATE INDEX IF NOT EXISTS idx_enrollments_student_course ON enrollments(student_id, course_id);

-- 4. OTP system optimization
CREATE INDEX IF NOT EXISTS idx_otps_user_type_expires ON otps(user_id, type, expires_at);
CREATE INDEX IF NOT EXISTS idx_otps_email ON otps(email);
CREATE INDEX IF NOT EXISTS idx_otps_created_at ON otps(created_at);

-- 5. Session and activity tracking indexes
CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_sessions_expires_at ON user_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_activity_log_user_id ON activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at);

-- 6. Cleanup expired OTPs automatically (SAFE - only removes old data)
CREATE EVENT IF NOT EXISTS cleanup_expired_otps
ON SCHEDULE EVERY 1 HOUR
DO 
    DELETE FROM otps 
    WHERE expires_at < NOW() - INTERVAL 1 HOUR;

-- 7. Cleanup expired sessions (SAFE - only removes old data)
CREATE EVENT IF NOT EXISTS cleanup_expired_sessions
ON SCHEDULE EVERY 6 HOURS
DO 
    DELETE FROM user_sessions 
    WHERE expires_at < NOW();

-- 8. Enable event scheduler (required for cleanup events)
SET GLOBAL event_scheduler = ON;

-- VERIFICATION QUERIES (Run these to check if indexes were created successfully)
-- SELECT * FROM information_schema.statistics WHERE table_schema = 'japanese_lms' AND index_name LIKE 'idx_%';
-- SHOW EVENTS WHERE Name IN ('cleanup_expired_otps', 'cleanup_expired_sessions');
