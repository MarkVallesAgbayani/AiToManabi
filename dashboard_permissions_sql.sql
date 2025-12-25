-- Dashboard Card Permissions SQL Queries
-- Run these queries to add permissions for dashboard cards

-- 1. Insert new dashboard card permissions
INSERT INTO permissions (name, description, category) VALUES
-- Summary Cards Permissions
('dashboard_view_teachers_card', 'View Total Teachers card on dashboard', 'Dashboard'),
('dashboard_view_modules_card', 'View Total Modules card on dashboard', 'Dashboard'),
('dashboard_view_revenue_card', 'View Total Revenue card on dashboard', 'Dashboard'),

-- Detailed Analytics Permissions
('dashboard_view_completion_metrics', 'View course completion metrics and charts', 'Dashboard'),
('dashboard_view_retention_metrics', 'View user retention metrics and charts', 'Dashboard'),
('dashboard_view_sales_metrics', 'View sales metrics and revenue charts', 'Dashboard');

-- 2. Assign permissions to Default Admin role template
-- First, get the template ID for 'Default Admin'
SET @default_admin_template_id = (SELECT id FROM role_templates WHERE name = 'Default Admin');

-- Insert permissions for Default Admin role template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @default_admin_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'dashboard_view_students_card',
    'dashboard_view_teachers_card', 
    'dashboard_view_modules_card',
    'dashboard_view_revenue_card',
    'dashboard_view_course_completion',
    'dashboard_view_user_retention',
    'dashboard_view_sales_reports',
    'dashboard_view_completion_metrics',
    'dashboard_view_retention_metrics',
    'dashboard_view_sales_metrics'
);

-- 3. Assign permissions to Default Permission role template
-- First, get the template ID for 'Default Permission'
SET @default_permission_template_id = (SELECT id FROM role_templates WHERE name = 'Default Permission');

-- Insert permissions for Default Permission role template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @default_permission_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'dashboard_view_students_card',
    'dashboard_view_teachers_card', 
    'dashboard_view_modules_card',
    'dashboard_view_revenue_card',
    'dashboard_view_course_completion',
    'dashboard_view_user_retention',
    'dashboard_view_sales_reports',
    'dashboard_view_completion_metrics',
    'dashboard_view_retention_metrics',
    'dashboard_view_sales_metrics'
);

-- 4. Create a new role template for Dashboard Viewer (limited access)
INSERT INTO role_templates (name, description) VALUES
('Dashboard Viewer', 'Can view basic dashboard metrics and reports');

-- Get the new template ID
SET @dashboard_viewer_template_id = LAST_INSERT_ID();

-- Assign limited permissions to Dashboard Viewer role template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @dashboard_viewer_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'dashboard_view_students_card',
    'dashboard_view_teachers_card', 
    'dashboard_view_modules_card',
    'dashboard_view_course_completion',
    'dashboard_view_retention_metrics'
);

-- 5. Create a new role template for Financial Dashboard Viewer (revenue access)
INSERT INTO role_templates (name, description) VALUES
('Financial Dashboard Viewer', 'Can view financial metrics and sales reports');

-- Get the new template ID
SET @financial_viewer_template_id = LAST_INSERT_ID();

-- Assign financial permissions to Financial Dashboard Viewer role template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT @financial_viewer_template_id, p.id
FROM permissions p
WHERE p.name IN (
    'dashboard_view_revenue_card',
    'dashboard_view_sales_reports',
    'dashboard_view_sales_metrics',
    'dashboard_view_course_completion'
);

-- 6. Query to check which users have dashboard permissions
SELECT 
    u.id,
    u.username,
    u.role,
    rt.name as role_template,
    GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as dashboard_permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id AND p.category = 'Dashboard'
WHERE p.name IS NOT NULL
GROUP BY u.id, u.username, u.role, rt.name
ORDER BY u.username;

-- 7. Query to assign dashboard permissions to a specific user
-- Replace @user_id with the actual user ID
-- Example: SET @user_id = 1;

-- Assign Dashboard Viewer role to a user
-- INSERT INTO user_roles (user_id, template_id) VALUES (@user_id, @dashboard_viewer_template_id);

-- Or assign specific permissions to a user
-- INSERT INTO user_permissions (user_id, permission_name) VALUES 
-- (@user_id, 'dashboard_view_students_card'),
-- (@user_id, 'dashboard_view_teachers_card'),
-- (@user_id, 'dashboard_view_modules_card');

-- 8. Query to remove dashboard permissions from a user
-- DELETE FROM user_permissions WHERE user_id = @user_id AND permission_name LIKE 'dashboard_%';

-- 9. Query to see all dashboard permissions available
SELECT 
    p.id,
    p.name,
    p.description,
    p.category,
    COUNT(rtp.template_id) as assigned_to_templates
FROM permissions p
LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id
WHERE p.category = 'Dashboard'
GROUP BY p.id, p.name, p.description, p.category
ORDER BY p.name;
