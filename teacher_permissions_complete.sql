-- Complete Teacher Permissions SQL
-- This file creates all teacher permissions following the admin permission structure
-- Run this to add comprehensive teacher permissions to your system

-- ==============================================
-- TEACHER NAVIGATION PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Teacher Dashboard Navigation
('nav_teacher_dashboard', 'Access to teacher dashboard', 'teacher_navigation'),
('nav_teacher_courses', 'Access to teacher courses management', 'teacher_navigation'),
('nav_teacher_create_module', 'Access to create new modules', 'teacher_navigation'),
('nav_teacher_drafts', 'Access to teacher drafts management', 'teacher_navigation'),
('nav_teacher_archive', 'Access to teacher archive management', 'teacher_navigation'),
('nav_teacher_student_management', 'Access to student management system', 'teacher_navigation'),
('nav_teacher_placement_test', 'Access to placement test management', 'teacher_navigation'),
('nav_teacher_settings', 'Access to teacher settings', 'teacher_navigation'),
('nav_teacher_content', 'Access to teacher content management', 'teacher_navigation'),
('nav_teacher_reports', 'Access to teacher reports', 'teacher_navigation'),
('nav_teacher_audit', 'Access to teacher audit trails', 'teacher_navigation'),
('nav_teacher_courses_by_category', 'Access to courses by category view', 'teacher_navigation');

-- ==============================================
-- TEACHER DASHBOARD PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Dashboard Cards
('teacher_dashboard_view_active_modules', 'View active modules card on teacher dashboard', 'teacher_dashboard'),
('teacher_dashboard_view_active_students', 'View active students card on teacher dashboard', 'teacher_dashboard'),
('teacher_dashboard_view_completion_rate', 'View module completion rate card on teacher dashboard', 'teacher_dashboard'),
('teacher_dashboard_view_published_modules', 'View published modules card on teacher dashboard', 'teacher_dashboard'),

-- Dashboard Analytics
('teacher_dashboard_view_learning_analytics', 'View learning progress analytics section', 'teacher_dashboard'),
('teacher_dashboard_view_quick_actions', 'View quick actions section on dashboard', 'teacher_dashboard'),
('teacher_dashboard_view_recent_activities', 'View recent activities section on dashboard', 'teacher_dashboard');

-- ==============================================
-- TEACHER COURSE MANAGEMENT PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Course Creation & Editing
('teacher_course_create', 'Create new courses/modules', 'teacher_course_management'),
('teacher_course_edit', 'Edit existing courses/modules', 'teacher_course_management'),
('teacher_course_delete', 'Delete courses/modules', 'teacher_course_management'),
('teacher_course_publish', 'Publish courses/modules', 'teacher_course_management'),
('teacher_course_unpublish', 'Unpublish courses/modules', 'teacher_course_management'),

-- Course Organization
('teacher_course_archive', 'Archive courses/modules', 'teacher_course_management'),
('teacher_course_restore', 'Restore archived courses/modules', 'teacher_course_management'),
('teacher_course_duplicate', 'Duplicate existing courses/modules', 'teacher_course_management'),

-- Course Content Management
('teacher_course_add_sections', 'Add sections to courses', 'teacher_course_management'),
('teacher_course_edit_sections', 'Edit course sections', 'teacher_course_management'),
('teacher_course_delete_sections', 'Delete course sections', 'teacher_course_management'),
('teacher_course_add_chapters', 'Add chapters to sections', 'teacher_course_management'),
('teacher_course_edit_chapters', 'Edit course chapters', 'teacher_course_management'),
('teacher_course_delete_chapters', 'Delete course chapters', 'teacher_course_management'),

-- Course Media & Files
('teacher_course_upload_images', 'Upload images for courses', 'teacher_course_management'),
('teacher_course_upload_files', 'Upload files for courses', 'teacher_course_management'),
('teacher_course_manage_media', 'Manage course media files', 'teacher_course_management'),

-- Course Settings
('teacher_course_set_categories', 'Set course categories', 'teacher_course_management'),
('teacher_course_set_difficulty', 'Set course difficulty levels', 'teacher_course_management'),
('teacher_course_set_prerequisites', 'Set course prerequisites', 'teacher_course_management');

-- ==============================================
-- TEACHER STUDENT MANAGEMENT PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Student Overview
('teacher_student_view_profiles', 'View student profiles', 'teacher_student_management'),
('teacher_student_view_enrollments', 'View student enrollments', 'teacher_student_management'),
('teacher_student_search_filter', 'Search and filter students', 'teacher_student_management'),

-- Student Progress Tracking
('teacher_student_track_progress', 'Track student learning progress', 'teacher_student_management'),
('teacher_student_view_progress_details', 'View detailed student progress', 'teacher_student_management'),
('teacher_student_export_progress', 'Export student progress data', 'teacher_student_management'),

-- Student Performance Analytics
('teacher_student_view_quiz_performance', 'View student quiz performance', 'teacher_student_management'),
('teacher_student_view_engagement', 'View student engagement metrics', 'teacher_student_management'),
('teacher_student_view_completion_reports', 'View student completion reports', 'teacher_student_management'),

-- Student Communication
('teacher_student_send_messages', 'Send messages to students', 'teacher_student_management'),
('teacher_student_view_messages', 'View messages from students', 'teacher_student_management'),
('teacher_student_manage_notifications', 'Manage student notifications', 'teacher_student_management');

-- ==============================================
-- TEACHER PLACEMENT TEST PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Placement Test Management
('teacher_placement_test_create', 'Create placement tests', 'teacher_placement_test'),
('teacher_placement_test_edit', 'Edit placement tests', 'teacher_placement_test'),
('teacher_placement_test_delete', 'Delete placement tests', 'teacher_placement_test'),
('teacher_placement_test_publish', 'Publish placement tests', 'teacher_placement_test'),

-- Placement Test Content
('teacher_placement_test_add_questions', 'Add questions to placement tests', 'teacher_placement_test'),
('teacher_placement_test_edit_questions', 'Edit placement test questions', 'teacher_placement_test'),
('teacher_placement_test_delete_questions', 'Delete placement test questions', 'teacher_placement_test'),
('teacher_placement_test_upload_images', 'Upload images for placement tests', 'teacher_placement_test'),

-- Placement Test Results
('teacher_placement_test_view_results', 'View placement test results', 'teacher_placement_test'),
('teacher_placement_test_analyze_results', 'Analyze placement test results', 'teacher_placement_test'),
('teacher_placement_test_export_results', 'Export placement test results', 'teacher_placement_test');

-- ==============================================
-- TEACHER REPORTS & ANALYTICS PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Course Analytics
('teacher_reports_view_course_analytics', 'View course performance analytics', 'teacher_reports'),
('teacher_reports_view_enrollment_trends', 'View enrollment trends', 'teacher_reports'),
('teacher_reports_view_completion_rates', 'View course completion rates', 'teacher_reports'),

-- Student Analytics
('teacher_reports_view_student_analytics', 'View student performance analytics', 'teacher_reports'),
('teacher_reports_view_engagement_metrics', 'View student engagement metrics', 'teacher_reports'),
('teacher_reports_view_learning_paths', 'View student learning paths', 'teacher_reports'),

-- Export & Sharing
('teacher_reports_export_pdf', 'Export reports to PDF', 'teacher_reports'),
('teacher_reports_export_excel', 'Export reports to Excel', 'teacher_reports'),
('teacher_reports_share_reports', 'Share reports with others', 'teacher_reports');

-- ==============================================
-- TEACHER SETTINGS PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Profile Management
('teacher_settings_edit_profile', 'Edit teacher profile information', 'teacher_settings'),
('teacher_settings_upload_avatar', 'Upload profile avatar', 'teacher_settings'),
('teacher_settings_change_password', 'Change teacher password', 'teacher_settings'),

-- Notification Settings
('teacher_settings_manage_notifications', 'Manage notification preferences', 'teacher_settings'),
('teacher_settings_email_preferences', 'Manage email notification preferences', 'teacher_settings'),
('teacher_settings_system_alerts', 'Manage system alert preferences', 'teacher_settings'),

-- Teaching Preferences
('teacher_settings_teaching_preferences', 'Manage teaching preferences', 'teacher_settings'),
('teacher_settings_course_defaults', 'Set default course settings', 'teacher_settings'),
('teacher_settings_grading_preferences', 'Manage grading preferences', 'teacher_settings');

-- ==============================================
-- TEACHER CONTENT MANAGEMENT PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Content Creation
('teacher_content_create_lessons', 'Create lesson content', 'teacher_content_management'),
('teacher_content_create_quizzes', 'Create quiz content', 'teacher_content_management'),
('teacher_content_create_assignments', 'Create assignment content', 'teacher_content_management'),

-- Content Management
('teacher_content_edit_content', 'Edit existing content', 'teacher_content_management'),
('teacher_content_delete_content', 'Delete content', 'teacher_content_management'),
('teacher_content_organize_content', 'Organize content structure', 'teacher_content_management'),

-- Media Management
('teacher_content_upload_media', 'Upload media files', 'teacher_content_management'),
('teacher_content_manage_media_library', 'Manage media library', 'teacher_content_management'),
('teacher_content_optimize_media', 'Optimize media files', 'teacher_content_management');

-- ==============================================
-- TEACHER AUDIT & LOGGING PERMISSIONS
-- ==============================================
INSERT INTO permissions (name, description, category) VALUES
-- Activity Tracking
('teacher_audit_view_own_activities', 'View own teaching activities', 'teacher_audit'),
('teacher_audit_view_student_interactions', 'View student interaction logs', 'teacher_audit'),
('teacher_audit_view_course_changes', 'View course modification history', 'teacher_audit'),

-- Audit Reports
('teacher_audit_export_activity_logs', 'Export activity logs', 'teacher_audit'),
('teacher_audit_view_performance_metrics', 'View teaching performance metrics', 'teacher_audit');

-- ==============================================
-- CREATE TEACHER ROLE TEMPLATES
-- ==============================================

-- Create Default Teacher role template
INSERT INTO role_templates (name, description) VALUES
('Default Teacher', 'Full access to all teacher features and permissions');

-- Get the template ID
SET @default_teacher_template_id = LAST_INSERT_ID();

-- Assign all teacher permissions to Default Teacher template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @default_teacher_template_id, p.id
FROM permissions p
WHERE p.category IN (
    'teacher_navigation',
    'teacher_dashboard', 
    'teacher_course_management',
    'teacher_student_management',
    'teacher_placement_test',
    'teacher_reports',
    'teacher_settings',
    'teacher_content_management',
    'teacher_audit'
);

-- Create Limited Teacher role template
INSERT INTO role_templates (name, description) VALUES
('Limited Teacher', 'Basic teacher access with limited permissions');

-- Get the template ID
SET @limited_teacher_template_id = LAST_INSERT_ID();

-- Assign limited permissions to Limited Teacher template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @limited_teacher_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_settings',
    'teacher_dashboard_view_active_modules',
    'teacher_dashboard_view_active_students',
    'teacher_course_create',
    'teacher_course_edit',
    'teacher_student_view_profiles',
    'teacher_student_track_progress',
    'teacher_settings_edit_profile'
);

-- Create Course Creator role template
INSERT INTO role_templates (name, description) VALUES
('Course Creator', 'Focused on course creation and content management');

-- Get the template ID
SET @course_creator_template_id = LAST_INSERT_ID();

-- Assign course creation permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @course_creator_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_create_module',
    'nav_teacher_drafts',
    'nav_teacher_archive',
    'nav_teacher_content',
    'nav_teacher_settings',
    'teacher_dashboard_view_active_modules',
    'teacher_dashboard_view_published_modules',
    'teacher_course_create',
    'teacher_course_edit',
    'teacher_course_publish',
    'teacher_course_archive',
    'teacher_course_add_sections',
    'teacher_course_add_chapters',
    'teacher_course_upload_images',
    'teacher_content_create_lessons',
    'teacher_content_create_quizzes',
    'teacher_content_upload_media',
    'teacher_settings_edit_profile'
);

-- Create Student Manager role template
INSERT INTO role_templates (name, description) VALUES
('Student Manager', 'Focused on student management and progress tracking');

-- Get the template ID
SET @student_manager_template_id = LAST_INSERT_ID();

-- Assign student management permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @student_manager_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_student_management',
    'nav_teacher_reports',
    'nav_teacher_settings',
    'teacher_dashboard_view_active_students',
    'teacher_dashboard_view_completion_rate',
    'teacher_dashboard_view_learning_analytics',
    'teacher_student_view_profiles',
    'teacher_student_track_progress',
    'teacher_student_view_quiz_performance',
    'teacher_student_view_engagement',
    'teacher_student_view_completion_reports',
    'teacher_student_export_progress',
    'teacher_reports_view_student_analytics',
    'teacher_reports_view_engagement_metrics',
    'teacher_reports_export_pdf',
    'teacher_settings_edit_profile'
);

-- ==============================================
-- QUERIES TO VERIFY PERMISSIONS
-- ==============================================

-- Simple query to see all teacher permissions (without template count)
SELECT 
    p.id,
    p.name,
    p.description,
    p.category
FROM permissions p
WHERE p.category LIKE 'teacher_%'
ORDER BY p.category, p.name;

-- Query to see all teacher permissions with template assignments
SELECT 
    p.id,
    p.name,
    p.description,
    p.category,
    COUNT(rtp.template_id) as assigned_to_templates
FROM permissions p
LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id
WHERE p.category LIKE 'teacher_%'
GROUP BY p.id, p.name, p.description, p.category
ORDER BY p.category, p.name;

-- Query to see teacher role templates and their permissions
SELECT 
    rt.id,
    rt.name as template_name,
    rt.description,
    COUNT(rtp.permission_id) as permission_count,
    GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as permissions
FROM role_templates rt
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE rt.name LIKE '%Teacher%' OR rt.name LIKE '%Creator%' OR rt.name LIKE '%Manager%'
GROUP BY rt.id, rt.name, rt.description
ORDER BY rt.name;

-- Query to assign teacher permissions to existing users
-- Replace @user_id with actual user ID
-- Example: SET @user_id = 1;

-- Assign Default Teacher role to a user
-- INSERT INTO user_roles (user_id, template_id) VALUES (@user_id, @default_teacher_template_id);

-- Or assign specific teacher permissions to a user
-- INSERT INTO user_permissions (user_id, permission_name) VALUES 
-- (@user_id, 'nav_teacher_dashboard'),
-- (@user_id, 'teacher_dashboard_view_active_modules'),
-- (@user_id, 'teacher_course_create');

-- ==============================================
-- NOTES
-- ==============================================
/*
This SQL file creates a comprehensive teacher permission system with:

1. NAVIGATION PERMISSIONS (12 permissions)
   - All teacher navigation items

2. DASHBOARD PERMISSIONS (7 permissions)
   - Dashboard cards and analytics sections

3. COURSE MANAGEMENT PERMISSIONS (18 permissions)
   - Complete course creation, editing, and management

4. STUDENT MANAGEMENT PERMISSIONS (12 permissions)
   - Student profiles, progress tracking, and analytics

5. PLACEMENT TEST PERMISSIONS (11 permissions)
   - Placement test creation and management

6. REPORTS & ANALYTICS PERMISSIONS (9 permissions)
   - Teacher-specific reporting capabilities

7. SETTINGS PERMISSIONS (9 permissions)
   - Teacher profile and preference management

8. CONTENT MANAGEMENT PERMISSIONS (9 permissions)
   - Content creation and media management

9. AUDIT & LOGGING PERMISSIONS (5 permissions)
   - Activity tracking and audit capabilities

TOTAL: 92 teacher permissions organized into 9 categories

ROLE TEMPLATES CREATED:
- Default Teacher (full access)
- Limited Teacher (basic access)
- Course Creator (content-focused)
- Student Manager (student-focused)

This follows the same structure as your admin permissions system
and provides granular control over teacher capabilities.
*/
