-- ===================================================================
-- COMPLETE RBAC PERMISSIONS SYSTEM FOR JAPANESE LMS
-- ===================================================================
-- This script creates all necessary permissions for admin and teacher navigation
-- Based on actual modules found in the codebase
-- ===================================================================

-- Clear existing data (if needed)
DELETE FROM role_template_permissions;
DELETE FROM user_permissions;
DELETE FROM user_roles;
DELETE FROM role_templates;
DELETE FROM permissions;

-- Create permissions table if not exists
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert ALL navigation permissions based on actual modules
INSERT INTO permissions (name, description, category) VALUES
-- ===================================================================
-- ADMIN NAVIGATION PERMISSIONS (Based on admin.php and related files)
-- ===================================================================
('nav_dashboard', 'Access to admin dashboard', 'admin_navigation'),
('nav_course_management', 'Access to course management system', 'admin_navigation'),
('nav_user_management', 'Access to user management system', 'admin_navigation'),
('nav_reports', 'Access to reports section', 'admin_navigation'),
('nav_usage_analytics', 'Access to usage analytics reports', 'admin_navigation'),
('nav_performance_logs', 'Access to performance logs', 'admin_navigation'),
('nav_login_activity', 'Access to login activity reports', 'admin_navigation'),
('nav_security_warnings', 'Access to security warnings', 'admin_navigation'),
('nav_audit_trails', 'Access to audit trails', 'admin_navigation'),
('nav_user_roles_report', 'Access to user roles breakdown report', 'admin_navigation'),
('nav_error_logs', 'Access to system error logs', 'admin_navigation'),
('nav_payments', 'Access to payment history', 'admin_navigation'),
('nav_content_management', 'Access to content management system', 'admin_navigation'),

-- ===================================================================
-- TEACHER NAVIGATION PERMISSIONS (Based on teacher.php and related files)
-- ===================================================================
('nav_teacher_dashboard', 'Access to teacher dashboard', 'teacher_navigation'),
('nav_teacher_courses', 'Access to teacher courses section', 'teacher_navigation'),
('nav_teacher_create_module', 'Access to create new modules', 'teacher_navigation'),
('nav_teacher_placement_test', 'Access to placement test system', 'teacher_navigation'),
('nav_teacher_settings', 'Access to teacher settings', 'teacher_navigation'),
('nav_teacher_content', 'Access to teacher content management', 'teacher_navigation'),
('nav_teacher_students', 'Access to student management', 'teacher_navigation'),
('nav_teacher_reports', 'Access to teacher reports', 'teacher_navigation'),
('nav_teacher_audit', 'Access to teacher audit trail', 'teacher_navigation'),
('nav_teacher_courses_by_category', 'Access to courses by category view', 'teacher_navigation'),

-- ===================================================================
-- HYBRID TEACHER PERMISSIONS (Teachers with admin-like access)
-- ===================================================================
('nav_hybrid_users', 'Access to user management (hybrid teacher)', 'hybrid_permissions'),
('nav_hybrid_reports', 'Access to admin reports (hybrid teacher)', 'hybrid_permissions'),
('nav_hybrid_courses', 'Access to course management (hybrid teacher)', 'hybrid_permissions'),

-- ===================================================================
-- STUDENT NAVIGATION PERMISSIONS
-- ===================================================================
('nav_student_dashboard', 'Access to student dashboard', 'student_navigation'),
('nav_student_courses', 'Access to student courses', 'student_navigation'),
('nav_student_learning', 'Access to learning materials', 'student_navigation'),

-- ===================================================================
-- SYSTEM PERMISSIONS
-- ===================================================================
('system_login', 'Ability to login to the system', 'system'),
('system_logout', 'Ability to logout from the system', 'system'),
('system_profile', 'Ability to view and edit own profile', 'system');

-- Create role templates table if not exists
CREATE TABLE IF NOT EXISTS role_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert role templates
INSERT INTO role_templates (name, description) VALUES
('Full Admin', 'Complete system access with all admin permissions'),
('Basic Admin', 'Limited admin access without sensitive operations'),
('Full Teacher', 'Complete teacher access with all teaching features'),
('Basic Teacher', 'Limited teacher access with basic teaching features'),
('Hybrid Teacher', 'Teacher with admin-like permissions for user and report management'),
('Student', 'Student access to learning materials and courses');

-- Create role_template_permissions table if not exists
CREATE TABLE IF NOT EXISTS role_template_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_template_permission (template_id, permission_id)
);

-- ===================================================================
-- ASSIGN PERMISSIONS TO ROLE TEMPLATES
-- ===================================================================

-- Full Admin: All permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Admin'),
    id
FROM permissions;

-- Basic Admin: Core admin permissions without sensitive operations
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Basic Admin'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_dashboard',
    'nav_course_management',
    'nav_user_management',
    'nav_reports',
    'nav_usage_analytics',
    'nav_user_roles_report',
    'nav_payments',
    'nav_content_management',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Full Teacher: All teacher permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Teacher'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_create_module',
    'nav_teacher_placement_test',
    'nav_teacher_settings',
    'nav_teacher_content',
    'nav_teacher_students',
    'nav_teacher_reports',
    'nav_teacher_audit',
    'nav_teacher_courses_by_category',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Basic Teacher: Limited teacher permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Basic Teacher'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_create_module',
    'nav_teacher_settings',
    'nav_teacher_courses_by_category',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Hybrid Teacher: Teacher permissions + admin-like permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Hybrid Teacher'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_create_module',
    'nav_teacher_placement_test',
    'nav_teacher_settings',
    'nav_teacher_content',
    'nav_teacher_students',
    'nav_teacher_reports',
    'nav_teacher_audit',
    'nav_teacher_courses_by_category',
    'nav_hybrid_users',
    'nav_hybrid_reports',
    'nav_hybrid_courses',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Student: Student permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Student'),
    p.id
FROM permissions p
WHERE p.name IN (
    'nav_student_dashboard',
    'nav_student_courses',
    'nav_student_learning',
    'system_login',
    'system_logout',
    'system_profile'
);

-- Create user_roles table if not exists
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, template_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE
);

-- Create user_permissions table if not exists (for custom permissions)
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_permission (user_id, permission_name)
);

-- Note: Indexes are already created from previous migrations
-- If you need to recreate indexes, run these manually:
-- CREATE INDEX idx_user_roles_user_id ON user_roles(user_id);
-- CREATE INDEX idx_user_roles_template_id ON user_roles(template_id);
-- CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
-- CREATE INDEX idx_user_permissions_name ON user_permissions(permission_name);
-- CREATE INDEX idx_role_template_permissions_template_id ON role_template_permissions(template_id);
-- CREATE INDEX idx_role_template_permissions_permission_id ON role_template_permissions(permission_id);

-- ===================================================================
-- MIGRATE EXISTING USERS TO NEW RBAC SYSTEM
-- ===================================================================

-- Migrate existing users from role column to user_roles table
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT 
    u.id,
    CASE 
        WHEN u.role = 'admin' THEN (SELECT id FROM role_templates WHERE name = 'Full Admin')
        WHEN u.role = 'teacher' THEN (SELECT id FROM role_templates WHERE name = 'Full Teacher')
        WHEN u.role = 'student' THEN (SELECT id FROM role_templates WHERE name = 'Student')
        ELSE NULL
    END
FROM users u
WHERE u.role IN ('admin', 'teacher', 'student')
AND NOT EXISTS (
    SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id
);

-- ===================================================================
-- VERIFICATION QUERIES
-- ===================================================================

-- Check if permissions were created
SELECT 'Permissions created:' as info, COUNT(*) as count FROM permissions;

-- Check if role templates were created
SELECT 'Role templates created:' as info, COUNT(*) as count FROM role_templates;

-- Check if permissions were assigned to templates
SELECT 'Template permissions assigned:' as info, COUNT(*) as count FROM role_template_permissions;

-- Check if users were migrated
SELECT 'Users migrated to RBAC:' as info, COUNT(*) as count FROM user_roles;

-- Show sample permissions by category
SELECT 'Sample permissions by category:' as info;
SELECT category, COUNT(*) as permission_count, 
       GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') as permissions
FROM permissions 
GROUP BY category 
ORDER BY category;

-- Show role templates with permission counts
SELECT 'Role templates with permission counts:' as info;
SELECT rt.name, rt.description, COUNT(rtp.permission_id) as permission_count
FROM role_templates rt
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
GROUP BY rt.id, rt.name, rt.description
ORDER BY rt.name;

-- Complete RBAC system setup finished successfully!
