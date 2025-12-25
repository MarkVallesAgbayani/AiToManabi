-- ===============================================
-- COMPLETE SQL QUERY TO ADD ALL TEACHER PERMISSIONS
-- TO DEFAULT TEACHER TEMPLATE (ID: 4) - HOSTINGER VERSION
-- ===============================================

-- Clear existing permissions for template 4 first (optional)
-- DELETE FROM role_template_permissions WHERE template_id = 4;

-- Add all teacher navigation permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 161); -- nav_teacher_dashboard
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 162); -- nav_teacher_courses
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 163); -- nav_teacher_create_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 164); -- nav_teacher_drafts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 165); -- nav_teacher_archive
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 166); -- nav_teacher_student_management
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 167); -- nav_teacher_placement_test
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 168); -- nav_teacher_settings
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 169); -- nav_teacher_content
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 170); -- nav_teacher_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 171); -- nav_teacher_audit
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 172); -- nav_teacher_courses_by_category

-- Add teacher dashboard permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 173); -- teacher_dashboard_view_active_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 174); -- teacher_dashboard_view_active_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 175); -- teacher_dashboard_view_completion_rate
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 176); -- teacher_dashboard_view_published_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 177); -- teacher_dashboard_view_learning_analytics
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 178); -- teacher_dashboard_view_quick_actions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 179); -- teacher_dashboard_view_recent_activities
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 272); -- module_performance_analytics
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 273); -- student_progress_overview

-- Add teacher placement test permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 212); -- teacher_placement_test_create
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 213); -- teacher_placement_test_edit
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 214); -- teacher_placement_test_delete
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 215); -- teacher_placement_test_publish
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 292); -- placement_test
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 293); -- preview_placement

-- Add teacher course management permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 274); -- preview_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 275); -- add_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 276); -- add_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 277); -- edit_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 278); -- delete_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 279); -- create_new_module

-- Add teacher drafts permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 280); -- edit_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 281); -- published_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 282); -- archived_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 283); -- create_new_draft
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 284); -- my_drafts

-- Add teacher archived permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 285); -- restore_to_drafts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 286); -- delete_permanently
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 287); -- archived

-- Add teacher courses permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 288); -- unpublished_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 289); -- edit_course_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 290); -- archived_course_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 291); -- courses

-- Add teacher student profiles permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 294); -- search_and_filter
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 295); -- view_profile_button
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 296); -- view_progress_button
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 297); -- student_profiles

-- Add teacher progress tracking permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 298); -- progress_tracking
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 299); -- export_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 300); -- complete_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 301); -- in_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 302); -- average_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 303); -- active_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 304); -- progress_distribution
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 305); -- module_completion
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 306); -- detailed_progress_tracking

-- Add teacher quiz performance permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 307); -- quiz_performance
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 308); -- filter_search
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 309); -- export_pdf_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 310); -- average_score
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 311); -- total_attempts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 312); -- active_students_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 313); -- total_quiz_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 314); -- performance_trend
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 315); -- quiz_difficulty_analysis
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 316); -- top_performer
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 317); -- recent_quiz_attempt

-- Add teacher engagement monitoring permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 318); -- engagement_monitoring
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 319); -- filter_engagement_monitoring
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 320); -- export_pdf_engagement
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 321); -- login_frequency
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 322); -- drop_off_rate
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 323); -- average_enrollment_days
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 324); -- recent_enrollments
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 325); -- time_spent_learning
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 326); -- module_engagement
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 327); -- most_engaged_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 328); -- recent_enrollments_card

-- Add teacher completion reports permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 329); -- completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 330); -- filter_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 331); -- export_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 332); -- overall_completion_rate
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 333); -- average_progress_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 334); -- on_time_completions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 335); -- delayed_completions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 336); -- module_completion_breakdown
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 337); -- completion_timeline
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 338); -- completion_breakdown

-- ===============================================
-- VERIFICATION QUERIES
-- ===============================================

-- Check total permissions count after additions
SELECT 'Total permissions in Default Teacher template:' as info, COUNT(*) as count 
FROM role_template_permissions WHERE template_id = 4;

-- List all permissions now in Default Teacher template
SELECT 'All permissions in Default Teacher:' as info, p.name 
FROM role_template_permissions rtp 
JOIN permissions p ON rtp.permission_id = p.id 
WHERE rtp.template_id = 4
ORDER BY p.name;

-- Check if any teacher permissions are still missing
SELECT 'Missing teacher permissions:' as info, p.name 
FROM permissions p 
LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id AND rtp.template_id = 4
WHERE (p.name LIKE '%teacher%' OR p.category LIKE '%teacher%' OR p.description LIKE '%teacher%')
  AND rtp.permission_id IS NULL
ORDER BY p.name;