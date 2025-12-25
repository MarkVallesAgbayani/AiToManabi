-- Missing Permissions for Admin Files
-- These permissions are used in the code but missing from the permissions table

-- Audit Trails Missing Permissions (aliases for existing permissions)
INSERT INTO permissions (name, description, category) VALUES
('audit_trails_export', 'Export audit trails to PDF', 'admin_audit_trails'),
('audit_trails_view_metrics', 'View audit trail metrics cards', 'admin_audit_trails');

-- Performance Logs Missing Permissions (aliases for existing permissions)
INSERT INTO permissions (name, description, category) VALUES
('performance_logs_export', 'Export performance logs to PDF', 'admin_system_performance'),
('performance_logs_view_metrics', 'View performance metrics cards', 'admin_system_performance');

-- Error Logs Missing Permissions (aliases for existing permissions)
INSERT INTO permissions (name, description, category) VALUES
('error_logs_export', 'Export error logs to PDF', 'admin_system_error_logs');

-- Payment History Missing Permissions (completely missing)
INSERT INTO permissions (name, description, category) VALUES
('payment_view_metrics', 'View payment metrics and revenue data', 'admin_payment_history');

-- Content Management Missing Permissions (completely missing)
INSERT INTO permissions (name, description, category) VALUES
('content_management_edit', 'Edit content management settings', 'admin_content_management'),
('content_management_view', 'View content management system', 'admin_content_management'),
('content_management_publish', 'Publish content changes', 'admin_content_management');

-- Additional missing permissions that might be needed
INSERT INTO permissions (name, description, category) VALUES
('audit_trails_view', 'View audit trails', 'admin_audit_trails'),
('performance_logs_view', 'View performance logs', 'admin_system_performance'),
('performance_logs_analyze', 'Analyze performance data', 'admin_system_performance'),
('error_logs_view', 'View error logs', 'admin_system_error_logs'),
('error_logs_analyze', 'Analyze error logs', 'admin_system_error_logs'),
('login_activity_view', 'View login activity', 'admin_login_activity'),
('security_warnings_view', 'View security warnings', 'admin_security_warnings');
