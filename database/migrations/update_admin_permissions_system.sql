-- ===================================================================
-- UPDATED ADMIN PERMISSIONS SYSTEM
-- ===================================================================
-- This script updates the permissions system with comprehensive admin permissions
-- Based on the detailed admin requirements provided
-- ===================================================================

-- First, let's add the new admin-specific permissions
-- We'll keep existing permissions and add new ones

-- ===================================================================
-- DASHBOARD PERMISSIONS
-- ===================================================================
INSERT IGNORE INTO permissions (name, description, category) VALUES
('dashboard_view_metrics', 'View dashboard metrics cards (Total Students, Teachers, Modules, Revenue)', 'admin_dashboard'),
('dashboard_view_course_completion', 'View course completion report card', 'admin_dashboard'),
('dashboard_view_user_retention', 'View user retention report card', 'admin_dashboard'),
('dashboard_view_sales_report', 'View sales report card', 'admin_dashboard'),

-- ===================================================================
-- COURSE MANAGEMENT PERMISSIONS
-- ===================================================================
('course_add_category', 'Add new course category', 'admin_course_management'),
('course_view_categories', 'View course categories table', 'admin_course_management'),
('course_edit_category', 'Edit course categories', 'admin_course_management'),
('course_delete_category', 'Move course categories to trash', 'admin_course_management'),

-- ===================================================================
-- USER MANAGEMENT PERMISSIONS
-- ===================================================================
('user_add_new', 'Add new user with custom roles', 'admin_user_management'),
('user_reset_password', 'Reset user passwords', 'admin_user_management'),
('user_change_password', 'Change user passwords', 'admin_user_management'),
('user_ban_user', 'Ban/unban users', 'admin_user_management'),
('user_move_to_deleted', 'Move users to deleted status', 'admin_user_management'),
('user_change_role', 'Change user roles', 'admin_user_management'),

-- ===================================================================
-- USAGE ANALYTICS PERMISSIONS
-- ===================================================================
('analytics_export_csv', 'Export analytics data to CSV', 'admin_usage_analytics'),
('analytics_export_excel', 'Export analytics data to Excel', 'admin_usage_analytics'),
('analytics_export_pdf', 'Export analytics data to PDF', 'admin_usage_analytics'),
('analytics_view_metrics', 'View analytics metrics cards (Active Users, Daily Average, Peak Day, Growth Rate)', 'admin_usage_analytics'),
('analytics_view_active_trends', 'View active users trend card', 'admin_usage_analytics'),
('analytics_view_role_breakdown', 'View user role breakdown card', 'admin_usage_analytics'),
('analytics_view_activity_data', 'View detailed activity data card', 'admin_usage_analytics'),

-- ===================================================================
-- USER ROLES REPORT PERMISSIONS
-- ===================================================================
('user_roles_view_metrics', 'View user roles metric cards (Admins, Teachers, Students)', 'admin_user_roles_report'),
('user_roles_search_filter', 'Search and filter users in roles report', 'admin_user_roles_report'),
('user_roles_export_csv', 'Export user roles report to CSV', 'admin_user_roles_report'),
('user_roles_export_excel', 'Export user roles report to Excel', 'admin_user_roles_report'),
('user_roles_export_pdf', 'Export user roles report to PDF', 'admin_user_roles_report'),
('user_roles_view_details', 'View user roles report details', 'admin_user_roles_report'),

-- ===================================================================
-- LOGIN ACTIVITY & BROKEN LINKS REPORT PERMISSIONS
-- ===================================================================
('login_activity_view_metrics', 'View login activity metrics (Total Logins Today, Failed Attempts, Broken Links)', 'admin_login_activity'),
('login_activity_view_report', 'View login activity report card', 'admin_login_activity'),
('login_activity_export_excel', 'Export login activity to Excel', 'admin_login_activity'),
('login_activity_export_csv', 'Export login activity to CSV', 'admin_login_activity'),
('login_activity_export_pdf', 'Export login activity to PDF', 'admin_login_activity'),
('broken_links_view_report', 'View broken links report card', 'admin_login_activity'),
('broken_links_export_excel', 'Export broken links to Excel', 'admin_login_activity'),
('broken_links_export_csv', 'Export broken links to CSV', 'admin_login_activity'),
('broken_links_export_pdf', 'Export broken links to PDF', 'admin_login_activity'),

-- ===================================================================
-- SECURITY WARNINGS PERMISSIONS
-- ===================================================================
('security_view_metrics', 'View security metrics (Failed Logins, Suspicious IPs, Admin Actions, New Users)', 'admin_security_warnings'),
('security_view_suspicious_patterns', 'View suspicious login patterns', 'admin_security_warnings'),
('security_view_admin_activity', 'View unusual admin activity', 'admin_security_warnings'),
('security_view_recommendations', 'View security recommendations', 'admin_security_warnings'),

-- ===================================================================
-- AUDIT TRAILS PERMISSIONS
-- ===================================================================
('audit_view_metrics', 'View audit trail metrics (Total Actions, Actions Today, Failed Actions, Active Users)', 'admin_audit_trails'),
('audit_search_filter', 'Search and filter audit trails', 'admin_audit_trails'),
('audit_export_csv', 'Export audit trails to CSV', 'admin_audit_trails'),
('audit_export_excel', 'Export audit trails to Excel', 'admin_audit_trails'),
('audit_export_pdf', 'Export audit trails to PDF', 'admin_audit_trails'),
('audit_view_details', 'View audit trail details', 'admin_audit_trails'),

-- ===================================================================
-- SYSTEM PERFORMANCE & ERROR LOGS PERMISSIONS
-- ===================================================================
('performance_view_metrics', 'View system performance metrics (System Status, Uptime, Load Time, Total Requests)', 'admin_system_performance'),
('performance_view_uptime_chart', 'View uptime vs downtime chart (7 days)', 'admin_system_performance'),
('performance_view_load_times', 'View average page load times', 'admin_system_performance'),
('performance_export_logs', 'Export performance logs (CSV, Excel, PDF)', 'admin_system_performance'),

-- ===================================================================
-- SYSTEM ERROR LOGS PERMISSIONS
-- ===================================================================
('error_logs_view_metrics', 'View error log metrics (AI Failures, Response Time, Critical Errors, Total Errors)', 'admin_system_error_logs'),
('error_logs_view_trends', 'View error trends chart (7 days)', 'admin_system_error_logs'),
('error_logs_view_categories', 'View error categories breakdown', 'admin_system_error_logs'),
('error_logs_search_filter', 'Search and filter error logs', 'admin_system_error_logs'),
('error_logs_export_csv', 'Export error logs to CSV', 'admin_system_error_logs'),
('error_logs_export_excel', 'Export error logs to Excel', 'admin_system_error_logs'),
('error_logs_export_pdf', 'Export error logs to PDF', 'admin_system_error_logs'),

-- ===================================================================
-- PAYMENT HISTORY PERMISSIONS
-- ===================================================================
('payment_view_history', 'View payment history table', 'admin_payment_history'),

-- ===================================================================
-- CONTENT MANAGEMENT PERMISSIONS
-- ===================================================================
('content_manage_announcement', 'Manage announcement banner', 'admin_content_management'),
('content_manage_terms', 'Manage terms and conditions', 'admin_content_management'),
('content_manage_privacy', 'Manage privacy policy', 'admin_content_management');

-- ===================================================================
-- UPDATE EXISTING NAVIGATION PERMISSIONS TO MATCH NEW STRUCTURE
-- ===================================================================

-- Update existing navigation permissions to be more specific
UPDATE permissions SET 
    description = 'Access to admin dashboard with all metrics and reports',
    category = 'admin_navigation'
WHERE name = 'nav_dashboard';

UPDATE permissions SET 
    description = 'Access to course management system with categories',
    category = 'admin_navigation'
WHERE name = 'nav_course_management';

UPDATE permissions SET 
    description = 'Access to user management system with role management',
    category = 'admin_navigation'
WHERE name = 'nav_user_management';

UPDATE permissions SET 
    description = 'Access to usage analytics and reports',
    category = 'admin_navigation'
WHERE name = 'nav_usage_analytics';

UPDATE permissions SET 
    description = 'Access to user roles breakdown report',
    category = 'admin_navigation'
WHERE name = 'nav_user_roles_report';

UPDATE permissions SET 
    description = 'Access to login activity and broken links reports',
    category = 'admin_navigation'
WHERE name = 'nav_login_activity';

UPDATE permissions SET 
    description = 'Access to security warnings and monitoring',
    category = 'admin_navigation'
WHERE name = 'nav_security_warnings';

UPDATE permissions SET 
    description = 'Access to audit trails and system logs',
    category = 'admin_navigation'
WHERE name = 'nav_audit_trails';

UPDATE permissions SET 
    description = 'Access to system performance monitoring',
    category = 'admin_navigation'
WHERE name = 'nav_performance_logs';

UPDATE permissions SET 
    description = 'Access to system error logs and monitoring',
    category = 'admin_navigation'
WHERE name = 'nav_error_logs';

-- Add missing navigation permissions
INSERT IGNORE INTO permissions (name, description, category) VALUES
('nav_payment_history', 'Access to payment history management', 'admin_navigation'),
('nav_content_management', 'Access to content management system', 'admin_navigation');

-- ===================================================================
-- CREATE ADMIN ROLE TEMPLATE WITH ALL PERMISSIONS
-- ===================================================================

-- Create a comprehensive admin template
INSERT IGNORE INTO role_templates (name, description) VALUES
('Full Admin Access', 'Complete administrative access to all system features and reports');

-- Get the template ID (handle both new insert and existing template)
SET @admin_template_id = (
    SELECT id FROM role_templates WHERE name = 'Full Admin Access' LIMIT 1
);

-- Assign all admin permissions to the template
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT @admin_template_id, p.id
FROM permissions p
WHERE p.category IN (
    'admin_dashboard',
    'admin_course_management', 
    'admin_user_management',
    'admin_usage_analytics',
    'admin_user_roles_report',
    'admin_login_activity',
    'admin_security_warnings',
    'admin_audit_trails',
    'admin_system_performance',
    'admin_system_error_logs',
    'admin_payment_history',
    'admin_content_management',
    'admin_navigation'
);

-- ===================================================================
-- UPDATE EXISTING ADMIN USERS TO USE THE NEW TEMPLATE
-- ===================================================================

-- Assign the new admin template to existing admin users (only if they don't already have a template)
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT u.id, @admin_template_id
FROM users u
WHERE u.role = 'admin'
AND u.id NOT IN (SELECT user_id FROM user_roles WHERE user_id = u.id);

-- ===================================================================
-- CREATE HELPER VIEWS FOR EASIER PERMISSION MANAGEMENT
-- ===================================================================

-- Create a view for admin permissions grouped by category
CREATE OR REPLACE VIEW admin_permissions_by_category AS
SELECT 
    p.category,
    p.name as permission_name,
    p.description,
    COUNT(rtp.template_id) as template_count,
    COUNT(up.user_id) as custom_assignment_count
FROM permissions p
LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id
LEFT JOIN user_permissions up ON p.name = up.permission_name
WHERE p.category LIKE 'admin_%'
GROUP BY p.category, p.name, p.description
ORDER BY p.category, p.name;

-- Create a view for user permission summary
CREATE OR REPLACE VIEW user_admin_permissions_summary AS
SELECT 
    u.id as user_id,
    u.username,
    u.role,
    rt.name as template_name,
    COUNT(DISTINCT p.name) as total_permissions,
    GROUP_CONCAT(DISTINCT p.category ORDER BY p.category) as permission_categories
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE u.role = 'admin' AND p.category LIKE 'admin_%'
GROUP BY u.id, u.username, u.role, rt.name;

-- ===================================================================
-- VERIFICATION QUERIES
-- ===================================================================

-- Show all admin permission categories
SELECT DISTINCT category, COUNT(*) as permission_count
FROM permissions 
WHERE category LIKE 'admin_%'
GROUP BY category
ORDER BY category;

-- Show admin template permissions
SELECT rt.name as template_name, COUNT(rtp.permission_id) as permission_count
FROM role_templates rt
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE rt.name LIKE '%Admin%' AND p.category LIKE 'admin_%'
GROUP BY rt.id, rt.name;
