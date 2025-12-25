-- ===================================================================
-- UPDATE PERMISSIONS TABLE FOR ENHANCED RBAC SYSTEM
-- ===================================================================
-- This script will clean up and update permissions for Teacher/Admin only
-- Removes student permissions and creates hybrid role structure
-- ===================================================================

USE japanese_lms;

-- 1. REMOVE ALL STUDENT PERMISSIONS
DELETE FROM permissions WHERE category = 'student_navigation' OR name LIKE '%student%';

-- 2. REMOVE OLD HYBRID PERMISSIONS
DELETE FROM permissions WHERE name IN ('nav_hybrid_users', 'nav_hybrid_reports');

-- 3. CLEAR ALL EXISTING PERMISSIONS TO START FRESH
DELETE FROM permissions;

-- 4. INSERT NEW STREAMLINED PERMISSIONS

-- ADMIN PERMISSIONS
INSERT INTO permissions (name, description, category) VALUES
-- Admin Core Navigation
('nav_dashboard', 'Dashboard Access', 'admin_core'),
('nav_course_management', 'Course Management', 'admin_core'),
('nav_user_management', 'User Management', 'admin_core'),
('nav_reports', 'Reports & Analytics', 'admin_core'),
('nav_content_management', 'Content Management', 'admin_core'),
('nav_payments', 'Payment History', 'admin_core'),

-- Admin System Management
('nav_security_warnings', 'Security Warnings', 'admin_system'),
('nav_performance_logs', 'System Performance Logs', 'admin_system'),
('nav_audit_trails', 'Audit Trails', 'admin_system'),
('nav_login_activity', 'Login Activity', 'admin_system'),
('nav_user_roles_report', 'User Roles Breakdowns', 'admin_system'),
('nav_error_logs', 'Error Logs', 'admin_system'),
('nav_usage_analytics', 'User Roles Reports', 'admin_system'),

-- TEACHER PERMISSIONS
-- Teacher Core Navigation
('nav_teacher_dashboard', 'Teacher Dashboard', 'teacher_core'),
('nav_teacher_courses', 'Courses', 'teacher_core'),
('nav_teacher_create_module', 'Create New Modules', 'teacher_core'),
('nav_teacher_placement_test', 'Placement Test Access', 'teacher_core'),
('nav_teacher_settings', 'Settings', 'teacher_core'),
('nav_teacher_courses_by_category', 'Courses by Category', 'teacher_core'),

-- Teacher Management
('nav_teacher_manage_questions', 'Manage Questions', 'teacher_management'),
('nav_teacher_difficulty_levels', 'Configure Difficulty Levels', 'teacher_management'),
('nav_teacher_student_results', 'View Student Results', 'teacher_management'),
('nav_teacher_analytics', 'Analytics Statistics', 'teacher_management'),
('nav_teacher_design_preview', 'Design Preview', 'teacher_management'),

-- HYBRID PERMISSIONS (Cross-role access)
-- Hybrid Admin (Admin + Teacher capabilities)
('nav_hybrid_admin_teacher_dashboard', 'Teacher Dashboard (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_teacher_courses', 'Courses (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_create_module', 'Create New Modules (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_placement_test', 'Placement Test Access (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_manage_questions', 'Manage Questions (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_difficulty_levels', 'Configure Difficulty Levels (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_student_results', 'View Student Results (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_analytics', 'Analytics Statistics (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_design_preview', 'Design Preview (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_teacher_settings', 'Teacher Settings (Hybrid Admin)', 'hybrid_admin'),
('nav_hybrid_admin_courses_by_category', 'Courses by Category (Hybrid Admin)', 'hybrid_admin'),

-- Hybrid Teacher (Teacher + Admin capabilities) 
('nav_hybrid_teacher_dashboard', 'Dashboard Access (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_course_management', 'Course Management (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_user_management', 'User Management (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_reports', 'Reports & Analytics (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_content_management', 'Content Management (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_payments', 'Payment History (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_security_warnings', 'Security Warnings (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_performance_logs', 'System Performance Logs (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_audit_trails', 'Audit Trails (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_login_activity', 'Login Activity (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_user_roles_report', 'User Roles Breakdowns (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_error_logs', 'Error Logs (Hybrid Teacher)', 'hybrid_teacher'),
('nav_hybrid_teacher_usage_analytics', 'User Roles Reports (Hybrid Teacher)', 'hybrid_teacher'),

-- SYSTEM PERMISSIONS (Common to all)
('system_login', 'System Login', 'system'),
('system_logout', 'System Logout', 'system'),
('system_profile', 'Profile Management', 'system');

-- 5. UPDATE ROLE TEMPLATES
DELETE FROM role_templates;

INSERT INTO role_templates (name, description) VALUES
('Default Admin', 'Standard administrative access with core admin permissions'),
('Default Teacher', 'Standard teaching access with core teacher permissions'),
('Hybrid Admin', 'Admin with additional teacher module access'),
('Hybrid Teacher', 'Teacher with additional admin system access');

-- 6. SET UP TEMPLATE PERMISSIONS

-- Default Admin: Core admin permissions only
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Default Admin'),
    id
FROM permissions 
WHERE category IN ('admin_core', 'admin_system', 'system');

-- Default Teacher: Core teacher permissions only  
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Default Teacher'),
    id
FROM permissions 
WHERE category IN ('teacher_core', 'teacher_management', 'system');

-- Hybrid Admin: Admin permissions + Hybrid admin permissions (teacher capabilities)
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Hybrid Admin'),
    id
FROM permissions 
WHERE category IN ('admin_core', 'admin_system', 'hybrid_admin', 'system');

-- Hybrid Teacher: Teacher permissions + Hybrid teacher permissions (admin capabilities)
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Hybrid Teacher'),
    id
FROM permissions 
WHERE category IN ('teacher_core', 'teacher_management', 'hybrid_teacher', 'system');

-- 7. CLEAN UP USER ROLES AND PERMISSIONS
-- Remove any existing student role assignments
DELETE FROM user_roles WHERE template_id IN (
    SELECT id FROM role_templates WHERE name LIKE '%Student%'
);

DELETE FROM user_permissions WHERE permission_name LIKE '%student%';

-- 8. UPDATE EXISTING USER ROLE ASSIGNMENTS
-- Update existing admin users to use Default Admin template
UPDATE user_roles ur 
JOIN role_templates rt ON ur.template_id = rt.id 
SET ur.template_id = (SELECT id FROM role_templates WHERE name = 'Default Admin')
WHERE rt.name IN ('Full Admin', 'Basic Admin');

-- Update existing teacher users to use Default Teacher template
UPDATE user_roles ur 
JOIN role_templates rt ON ur.template_id = rt.id 
SET ur.template_id = (SELECT id FROM role_templates WHERE name = 'Default Teacher')
WHERE rt.name IN ('Full Teacher', 'Basic Teacher', 'Hybrid Teacher');

-- 9. REMOVE OLD ROLE TEMPLATES
DELETE FROM role_templates WHERE name IN ('Full Admin', 'Basic Admin', 'Full Teacher', 'Basic Teacher');

-- 10. VERIFICATION
SELECT 'RBAC Update Complete' as status;
SELECT 
    category,
    COUNT(*) as permission_count
FROM permissions 
GROUP BY category 
ORDER BY category;

SELECT 
    name,
    description
FROM role_templates 
ORDER BY name;
