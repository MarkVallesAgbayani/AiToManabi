-- ===============================================
-- COMPLETE SQL QUERY TO ADD ALL TEACHER PERMISSIONS
-- TO DEFAULT TEACHER TEMPLATE (ID: 5)
-- ===============================================

-- Add all missing teacher permissions to Default Teacher template
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 371); -- active_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 382); -- active_students_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 343); -- add_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 341); -- add_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 355); -- archived
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 358); -- archived_course_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 350); -- archived_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 393); -- average_enrollment_days
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 370); -- average_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 403); -- average_progress_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 378); -- average_score
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 368); -- complete_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 408); -- completion_breakdown
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 399); -- completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 407); -- completion_timeline
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 359); -- courses
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 351); -- create_new_draft
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 347); -- create_new_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 1); -- dashboard_view_metrics
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 282); -- dashboard_view_teachers_card
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 405); -- delayed_completions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 346); -- delete_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 354); -- delete_permanently
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 374); -- detailed_progress_tracking
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 392); -- drop_off_rate
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 357); -- edit_course_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 344); -- edit_level
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 348); -- edit_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 388); -- engagement_monitoring
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 401); -- export_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 390); -- export_pdf_engagement
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 377); -- export_pdf_quiz
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 367); -- export_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 400); -- filter_completion_reports
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 389); -- filter_engagement_monitoring
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 376); -- filter_search
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 369); -- in_progress
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 391); -- login_frequency
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 373); -- module_completion
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 406); -- module_completion_breakdown
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 396); -- module_engagement
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 337); -- module_performance_analytics
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 397); -- most_engaged_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 352); -- my_drafts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 404); -- on_time_completions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 402); -- overall_completion_rate
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 384); -- performance_trend
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 360); -- placement_test
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 340); -- preview_module
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 361); -- preview_placement
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 372); -- progress_distribution
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 366); -- progress_tracking
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 349); -- published_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 385); -- quiz_difficulty_analysis
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 375); -- quiz_performance
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 394); -- recent_enrollments
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 398); -- recent_enrollments_card
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 387); -- recent_quiz_attempt
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 353); -- restore_to_drafts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 362); -- search_and_filter
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 365); -- student_profiles
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 338); -- student_progress_overview
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 395); -- time_spent_learning
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 386); -- top_performer
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 379); -- total_attempts
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 383); -- total_quiz_students
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 356); -- unpublished_modules
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 22); -- user_roles_view_metrics
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 363); -- view_profile_button
INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (4, 364); -- view_progress_button

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
LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id AND rtp.template_id = 5
WHERE (p.name LIKE '%teacher%' OR p.category LIKE '%teacher%' OR p.description LIKE '%teacher%')
  AND rtp.permission_id IS NULL
ORDER BY p.name;