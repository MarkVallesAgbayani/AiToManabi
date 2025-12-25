-- ===================================================================
-- UPDATE USERS TABLE AND SETUP RBAC SYSTEM FOR JAPANESE_LMS
-- ===================================================================
-- This script will update your users table and set up the complete RBAC system
-- Run this script in your japanese_lms database
-- ===================================================================

-- Make sure we're using the right database
USE japanese_lms;

-- 1. UPDATE USERS TABLE STRUCTURE
-- Add missing columns that the RBAC system expects

-- Add status column if not exists
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'status'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN status ENUM("active", "inactive", "pending", "locked", "suspended", "banned", "password_reset", "deleted") NOT NULL DEFAULT "active" AFTER role',
    'SELECT "Status column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deleted_at column if not exists
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'deleted_at'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL AFTER updated_at',
    'SELECT "Deleted_at column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add last_login_at column if not exists
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'last_login_at'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL AFTER deleted_at',
    'SELECT "Last_login_at column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add login_attempts column if not exists
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'japanese_lms'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'login_attempts'
);

SET @sqlstmt := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN login_attempts INT NOT NULL DEFAULT 0 AFTER last_login_at',
    'SELECT "Login_attempts column already exists" as message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. CREATE RBAC TABLES

-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role_templates table
CREATE TABLE IF NOT EXISTS role_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role_template_permissions table
CREATE TABLE IF NOT EXISTS role_template_permissions (
    template_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (template_id, permission_id),
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Create user_roles table
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, template_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_permissions table
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

-- 3. INSERT NAVIGATION PERMISSIONS
INSERT IGNORE INTO permissions (name, description, category) VALUES
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

-- 4. INSERT ROLE TEMPLATES
INSERT IGNORE INTO role_templates (name, description) VALUES
('Full Admin', 'Complete system access with all admin permissions'),
('Basic Admin', 'Essential admin permissions without advanced features'),
('Hybrid Teacher', 'Teacher with admin-like access to users and reports'),
('Full Teacher', 'Complete teacher access with all teaching features'),
('Basic Teacher', 'Essential teacher permissions for course management');

-- 5. SET UP TEMPLATE PERMISSIONS

-- Full Admin: All permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Full Admin'),
    id
FROM permissions;

-- Basic Admin: Core admin permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
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
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
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
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
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
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
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

-- 6. CREATE INDEXES FOR PERFORMANCE
CREATE INDEX IF NOT EXISTS idx_user_roles_user_id ON user_roles(user_id);
CREATE INDEX IF NOT EXISTS idx_user_roles_template_id ON user_roles(template_id);
CREATE INDEX IF NOT EXISTS idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_permissions_name ON user_permissions(permission_name);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON users(deleted_at);

-- 7. MIGRATE EXISTING USERS TO RBAC SYSTEM

-- Get template IDs
SET @full_admin_template_id = (SELECT id FROM role_templates WHERE name = 'Full Admin' LIMIT 1);
SET @full_teacher_template_id = (SELECT id FROM role_templates WHERE name = 'Full Teacher' LIMIT 1);
SET @hybrid_teacher_template_id = (SELECT id FROM role_templates WHERE name = 'Hybrid Teacher' LIMIT 1);

-- Assign role templates to existing users
-- Admin users get Full Admin template
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT id, @full_admin_template_id
FROM users 
WHERE role = 'admin' 
AND @full_admin_template_id IS NOT NULL;

-- Teacher users get Full Teacher template
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT id, @full_teacher_template_id
FROM users 
WHERE role = 'teacher' 
AND @full_teacher_template_id IS NOT NULL;

-- 8. CREATE OR UPDATE ADMIN USER FOR TESTING
-- This ensures you have a working admin account

INSERT IGNORE INTO users (username, email, password, role, status, created_at) VALUES
('admin', 'admin@japanese-lms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW());

-- Get the admin user ID
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin' AND email = 'admin@japanese-lms.com' LIMIT 1);

-- Assign Full Admin template to the admin user
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT @admin_user_id, @full_admin_template_id
WHERE @admin_user_id IS NOT NULL AND @full_admin_template_id IS NOT NULL;

-- 9. VERIFY SETUP
SELECT 'RBAC Setup Complete' as status;
SELECT COUNT(*) as total_permissions FROM permissions;
SELECT COUNT(*) as total_templates FROM role_templates;
SELECT COUNT(*) as total_user_roles FROM user_roles;

-- Show admin user details
SELECT 
    u.username,
    u.email,
    u.role,
    u.status,
    GROUP_CONCAT(rt.name) as assigned_templates,
    GROUP_CONCAT(DISTINCT p.name) as permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE u.username = 'admin'
GROUP BY u.id, u.username, u.email, u.role, u.status;

-- Show all users with their RBAC assignments
SELECT 
    u.username,
    u.email,
    u.role,
    u.status,
    GROUP_CONCAT(rt.name) as assigned_templates,
    GROUP_CONCAT(DISTINCT p.name) as permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
GROUP BY u.id, u.username, u.email, u.role, u.status
ORDER BY u.role, u.username;

-- ===================================================================
-- LOGIN CREDENTIALS FOR TESTING:
-- Admin: admin@japanese-lms.com / password
-- ===================================================================
