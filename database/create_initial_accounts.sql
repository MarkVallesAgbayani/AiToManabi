-- ===================================================================
-- CREATE INITIAL ADMIN AND TEACHER ACCOUNTS
-- ===================================================================
-- This script creates only admin and teacher accounts after database truncate
-- Run this AFTER running truncate_all_tables.sql
-- ===================================================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- 1. CREATE ADMIN ACCOUNT
-- ===================================================================

INSERT INTO users (username, email, password, role, first_name, last_name, created_at) VALUES
('admin', 'admin@aitomanabi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', NOW());

-- Get the admin user ID
SET @admin_id = LAST_INSERT_ID();

-- ===================================================================
-- 2. CREATE TEACHER ACCOUNT
-- ===================================================================

INSERT INTO users (username, email, password, role, first_name, last_name, created_at) VALUES
('teacher', 'teacher@aitomanabi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Demo', 'Teacher', NOW());

-- Get the teacher user ID
SET @teacher_id = LAST_INSERT_ID();

-- ===================================================================
-- 3. CREATE RBAC PERMISSIONS AND ROLES
-- ===================================================================

-- Insert basic permissions
INSERT INTO permissions (name, description, category) VALUES
('nav_dashboard', 'Access to admin dashboard', 'admin_navigation'),
('nav_course_management', 'Access to course management system', 'admin_navigation'),
('nav_user_management', 'Access to user management system', 'admin_navigation'),
('nav_teacher_dashboard', 'Access to teacher dashboard', 'teacher_navigation'),
('nav_teacher_courses', 'Access to teacher courses section', 'teacher_navigation'),
('nav_teacher_placement_test', 'Access to placement test system', 'teacher_navigation'),
('system_login', 'Ability to login to the system', 'system'),
('system_logout', 'Ability to logout from the system', 'system'),
('system_profile', 'Ability to view and edit own profile', 'system');

-- Insert role templates
INSERT INTO role_templates (name, description) VALUES
('Full Admin', 'Complete system access with all admin permissions'),
('Full Teacher', 'Complete teacher access with all teaching features');

-- Assign permissions to role templates
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Admin'),
    id
FROM permissions;

INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Teacher'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_placement_test',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Assign users to role templates
INSERT INTO user_roles (user_id, template_id) VALUES
(@admin_id, (SELECT id FROM role_templates WHERE name = 'Full Admin')),
(@teacher_id, (SELECT id FROM role_templates WHERE name = 'Full Teacher'));

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- 4. DISPLAY CREATED ACCOUNTS
-- ===================================================================

SELECT '✅ ACCOUNTS CREATED SUCCESSFULLY!' AS status;
SELECT '📋 Login Credentials:' AS info;

SELECT 
    'Admin Account' AS account_type,
    'admin' AS username,
    'admin@aitomanabi.com' AS email,
    'password' AS password,
    'admin' AS role
UNION ALL
SELECT 
    'Teacher Account' AS account_type,
    'teacher' AS username,
    'teacher@aitomanabi.com' AS email,
    'password' AS password,
    'teacher' AS role;

SELECT '🎯 NEXT STEPS:' AS info;
SELECT '1. Login with admin account to access admin dashboard' AS step1;
SELECT '2. Login with teacher account to access teacher dashboard' AS step2;
SELECT '3. Change default passwords for security' AS step3;
