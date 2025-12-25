INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('dashboard_view_revenue_trends', 'View revenue trends report card', 'admin_dashboard', '2025-10-18 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('payment_export_invoice_pdf', 'Export payment invoice to PDF', 'admin_payment_history', '2025-10-18 00:00:00');

-- Add permission to all admin users
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'payment_export_invoice_pdf'
FROM users u 
WHERE u.role = 'admin' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'payment_export_invoice_pdf'
);



-- Add permission to all admin users
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'dashboard_view_revenue_trends'
FROM users u 
WHERE u.role = 'admin' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'dashboard_view_revenue_trends'
);

-- Teacher Dashboard Add this to hostinger done na
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('module_performance_analytics', 'View Module Performance Analytics', 'teacher_dashboard', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('student_progress_overview', 'View Student Progress Overview', 'teacher_dashboard', '2025-10-19 00:00:00');

-- Add permission to all teacher users done na
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'module_performance_analytics'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'module_performance_analytics'
);

-- Add permission to all teacher users done na
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'student_progress_overview'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'student_progress_overview'
);



-- Add permission to all teacher users done na
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_active_modules'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_active_modules'
);

-- Add permission to all teacher users done na
INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_active_students'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_active_students'
);

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_completion_rate'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_completion_rate'
);

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_published_modules'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_published_modules'
);

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_quick_actions'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_quick_actions'
);

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_learning_analytics'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_learning_analytics'
);

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'teacher_dashboard_view_recent_activities'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'teacher_dashboard_view_recent_activities'
);




INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('preview_module', 'View Preview Modules', 'teacher_course_management', '2025-10-19 00:00:00');


INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'preview_module'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'preview_module'
);



INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'add_quiz'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'add_quiz'
);

--Five permissions for create new module
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('add_quiz', 'Add Quiz', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('add_level', 'Add Module Levels', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('edit_level', 'Edit Module Levels', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('delete_level', 'Delete Module Levels', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('create_new_module', 'Access Nav Create New Module', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('preview_module', 'View Preview Modules', 'teacher_course_management', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'preview_module'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'preview_module'
);


--for teacher drafts
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('edit_modules', 'Edit Modules', 'teacher_drafts', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('published_modules', 'Published Modules', 'teacher_drafts', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('archived_modules', 'Archived Modules', 'teacher_drafts', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('create_new_draft', 'Create New Draft Modules', 'teacher_drafts', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('my_drafts', 'Access to Nav My Drafts', 'teacher_drafts', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'my_drafts'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'my_drafts'
);


--for teacher archived
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('restore_to_drafts', 'Restore Module to Drafts', 'teacher_archived', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('delete_permanently', 'Delete Modules Permanently', 'teacher_archived', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('archived', 'Access to Nav Archived', 'teacher_archived', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'archived'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'archived'
);

--for teacher courses
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('unpublished_modules', 'Unpublished Modules (Courses)', 'teacher_courses', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('edit_course_module', 'Edit Modules', 'teacher_courses', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('archived_course_module', 'Archived Modules', 'teacher_courses', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('courses', 'Access to Nav Courses', 'teacher_courses', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'courses'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'courses'
);


--placement test
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('placement_test', 'Access to Nav Placement Test', 'teacher_placement_test', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('preview_placement', 'Preview Placement Test', 'teacher_placement_test', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'preview_placement'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'preview_placement'
);


--teacher student profiles
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('search_and_filter', 'Search & Filter Students', 'teacher_student_profiles', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('view_profile_button', 'View Profile', 'teacher_student_profiles', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('view_progress_button', 'View Progress', 'teacher_student_profiles', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('student_profiles', 'Access to Nav Student Profiles', 'teacher_student_profiles', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'student_profiles'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'student_profiles'
);


--progess tracking
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('progress_tracking', 'Access to Nav Progress Tracking', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('export_progress', 'Export Progress Button', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('complete_modules', 'View Completed Modules Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('in_progress', 'View In Progress Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('average_progress', 'View Average Progress Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('active_students', 'View Active Students Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('progress_distribution', 'View Progress Distribution Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('module_completion', 'View Module Completion Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('detailed_progress_tracking', 'View Detailed Progress Tracking Card', 'teacher_progress_tracking', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'detailed_progress_tracking'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'detailed_progress_tracking'
);

--Quiz Peformance
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('quiz_performance', 'Access to Nav Quiz Performance', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('filter_search', 'Access to Filter Options', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('export_pdf_quiz', 'Access to Export PDF Button', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('average_score', 'Access to Average Score Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('total_attempts', 'Access to Total Attempts Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('active_students_quiz', 'Access to Active Students Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('total_quiz_students', 'Access to Total Quiz Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('performance_trend', 'Access to Performance Trend Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('quiz_difficulty_analysis', 'Access to Quiz Difficulty Analysis Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('top_performer', 'Access to Top Performers Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('recent_quiz_attempt', 'Access to Recent Quiz Attempts Card', 'teacher_quiz_performance', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'recent_quiz_attempt'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'recent_quiz_attempt'
);


--engagement monitoring
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('engagement_monitoring', 'Access to Nav Engagement Monitoring', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('filter_engagement_monitoring', 'Access to Filter Options', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('export_pdf_engagement', 'Access to Export PDF Button', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('login_frequency', 'Access to Login Frequency Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('drop_off_rate', 'Access to Drop-off Rate Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('average_enrollment_days', 'Access to Avg Enrollment Days Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('recent_enrollments', 'Access to Recent Enrollments Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('time_spent_learning', 'Access to Time Spent Learning Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('module_engagement', 'Access to Module Engagement Analysis Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('most_engaged_students', 'Access to Most Engaged Students Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('recent_enrollments_card', 'Access to Recent Enrollments Card', 'teacher_engagement_monitoring', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'recent_enrollments_card'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'recent_enrollments_card'
);


---completion reports
INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('completion_reports', 'Access to Nav Completion Reports', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('filter_completion_reports', 'Access to Filter Options', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('export_completion_reports', 'Access to Export PDF Button', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('overall_completion_rate', 'Access to Overall Completion Rate Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('average_progress_completion_reports', 'Access to Average Progress Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('on_time_completions', 'Access to On-time Completions Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('delayed_completions', 'Access to Delayed Completions Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('module_completion_breakdown', 'Access to Module Completion Breakdown Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('completion_timeline', 'Access to Completion Timeline Card', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('completion_breakdown', 'Access to Module Completion Breakdown Table', 'teacher_completion_reports', '2025-10-19 00:00:00');

INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'completion_breakdown'
FROM users u 
WHERE u.role = 'teacher' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'completion_breakdown'
);



INSERT INTO `user_permissions` (`user_id`, `permission_name`) 
SELECT u.id, 'course_restore_category'
FROM users u 
WHERE u.role = 'admin' 
AND NOT EXISTS (
    SELECT 1 FROM user_permissions up 
    WHERE up.user_id = u.id 
    AND up.permission_name = 'course_restore_category'
);



INSERT INTO `permissions` (`name`, `description`, `category`, `created_at`) VALUES 
('course_restore_category', 'Restore Course Category', 'admin_course_management', '2025-10-19 00:00:00');

INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (1, 339); -- view course restore category permission for admin template

