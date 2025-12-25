-- Complete RBAC Setup for Admin and Teacher Navigation
-- This ensures all necessary permissions and role templates exist

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

-- Insert all navigation permissions
INSERT INTO permissions (name, description, category) VALUES
-- Admin Navigation Permissions
('nav_dashboard', 'Access to admin dashboard', 'admin_navigation'),
('nav_course_management', 'Access to course management', 'admin_navigation'),
('nav_user_management', 'Access to user management', 'admin_navigation'),
('nav_reports', 'Access to all reports and analytics', 'admin_navigation'),
('nav_payments', 'Access to payment history', 'admin_navigation'),
('nav_content_management', 'Access to content management', 'admin_navigation'),
('nav_audit_trails', 'Access to audit trails', 'admin_navigation'),
('nav_security', 'Access to security warnings and logs', 'admin_navigation'),
('nav_system_logs', 'Access to system performance and error logs', 'admin_navigation'),

-- Teacher Navigation Permissions
('nav_teacher_dashboard', 'Access to teacher dashboard', 'teacher_navigation'),
('nav_teacher_courses', 'Access to teacher courses', 'teacher_navigation'),
('nav_teacher_content', 'Access to content creation and management', 'teacher_navigation'),
('nav_teacher_students', 'Access to view and manage students', 'teacher_navigation'),
('nav_teacher_reports', 'Access to teacher reports', 'teacher_navigation'),
('nav_teacher_settings', 'Access to teacher settings', 'teacher_navigation'),

-- Hybrid Teacher Permissions (for teachers with admin-like access)
('nav_hybrid_users', 'Access to user management (hybrid teacher)', 'hybrid_navigation'),
('nav_hybrid_reports', 'Access to admin reports (hybrid teacher)', 'hybrid_navigation');

-- Create role templates table if not exists
CREATE TABLE IF NOT EXISTS role_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role template permissions table if not exists
CREATE TABLE IF NOT EXISTS role_template_permissions (
    template_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (template_id, permission_id),
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Insert role templates
INSERT INTO role_templates (name, description) VALUES
('Full Admin', 'Complete system access with all admin permissions'),
('Basic Admin', 'Essential admin permissions without advanced features'),
('Hybrid Teacher', 'Teacher with admin-like access to users and reports'),
('Full Teacher', 'Complete teacher access with all teaching features'),
('Basic Teacher', 'Essential teacher permissions for course management');

-- Set up template permissions
-- Full Admin: All permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Admin'),
    id
FROM permissions;

-- Basic Admin: Core admin permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Basic Admin'),
    id
FROM permissions 
WHERE name IN (
    'nav_dashboard',
    'nav_course_management', 
    'nav_user_management',
    'nav_reports',
    'nav_payments',
    'nav_content_management'
);

-- Hybrid Teacher: Teacher + some admin permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Hybrid Teacher'),
    id
FROM permissions 
WHERE name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_content', 
    'nav_teacher_students',
    'nav_teacher_reports',
    'nav_teacher_settings',
    'nav_hybrid_users',
    'nav_hybrid_reports'
);

-- Full Teacher: All teacher permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Teacher'),
    id
FROM permissions 
WHERE name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_content',
    'nav_teacher_students', 
    'nav_teacher_reports',
    'nav_teacher_settings'
);

-- Basic Teacher: Essential teacher permissions
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Basic Teacher'),
    id
FROM permissions 
WHERE name IN (
    'nav_teacher_dashboard',
    'nav_teacher_courses',
    'nav_teacher_content',
    'nav_teacher_settings'
);

-- Create user_roles table if not exists
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, template_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_permissions table if not exists
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    template_id INT NULL,
    granted_by INT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_permission (user_id, permission_name)
);

-- Create indexes for performance
CREATE INDEX idx_user_roles_user_id ON user_roles(user_id);
CREATE INDEX idx_user_roles_template_id ON user_roles(template_id);
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_name ON user_permissions(permission_name);

-- Verify setup
SELECT 'RBAC Setup Complete' as status;
SELECT COUNT(*) as total_permissions FROM permissions;
SELECT COUNT(*) as total_templates FROM role_templates;
SELECT rt.name, COUNT(rtp.permission_id) as permission_count 
FROM role_templates rt 
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id 
GROUP BY rt.id, rt.name;
