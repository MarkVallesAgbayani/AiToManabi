-- ===================================================================
-- REMOVE CSV AND EXCEL EXPORT PERMISSIONS
-- ===================================================================
-- This script removes all CSV and Excel export permissions from the system
-- while keeping PDF export functionality intact
-- ===================================================================

-- Remove CSV and Excel export permissions from permissions table
DELETE FROM permissions WHERE name IN (
    'analytics_export_csv',
    'analytics_export_excel',
    'user_roles_export_csv',
    'user_roles_export_excel',
    'login_activity_export_excel',
    'login_activity_export_csv',
    'broken_links_export_excel',
    'broken_links_export_csv',
    'audit_export_csv',
    'audit_export_excel',
    'performance_export_logs',
    'error_logs_export_csv',
    'error_logs_export_excel'
);

-- Remove these permissions from role template permissions
DELETE FROM role_template_permissions WHERE permission_id IN (
    SELECT id FROM permissions WHERE name IN (
        'analytics_export_csv',
        'analytics_export_excel',
        'user_roles_export_csv',
        'user_roles_export_excel',
        'login_activity_export_excel',
        'login_activity_export_csv',
        'broken_links_export_excel',
        'broken_links_export_csv',
        'audit_export_csv',
        'audit_export_excel',
        'performance_export_logs',
        'error_logs_export_csv',
        'error_logs_export_excel'
    )
);

-- Remove these permissions from user permissions
DELETE FROM user_permissions WHERE permission_name IN (
    'analytics_export_csv',
    'analytics_export_excel',
    'user_roles_export_csv',
    'user_roles_export_excel',
    'login_activity_export_excel',
    'login_activity_export_csv',
    'broken_links_export_excel',
    'broken_links_export_csv',
    'audit_export_csv',
    'audit_export_excel',
    'performance_export_logs',
    'error_logs_export_csv',
    'error_logs_export_excel'
);

-- Update permission descriptions to reflect PDF-only export
UPDATE permissions 
SET description = 'View usage analytics and generate PDF reports'
WHERE name = 'nav_usage_analytics';

UPDATE permissions 
SET description = 'View user roles breakdown and generate PDF reports'
WHERE name = 'nav_user_roles_report';

UPDATE permissions 
SET description = 'Monitor login activity and generate PDF reports'
WHERE name = 'nav_login_activity';

UPDATE permissions 
SET description = 'View audit trails and generate PDF reports'
WHERE name = 'nav_audit_trails';

UPDATE permissions 
SET description = 'Monitor system performance and generate PDF reports'
WHERE name = 'nav_performance_logs';

UPDATE permissions 
SET description = 'View system error logs and generate PDF reports'
WHERE name = 'nav_error_logs';

-- Keep PDF export permissions intact
-- These should remain in the system:
-- analytics_export_pdf
-- user_roles_export_pdf
-- login_activity_export_pdf
-- broken_links_export_pdf
-- audit_export_pdf
-- error_logs_export_pdf

-- Note: CSV and Excel export permissions have been successfully removed
-- PDF export permissions remain intact for all report modules
