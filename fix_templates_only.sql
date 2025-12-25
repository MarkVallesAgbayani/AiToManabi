-- Fix both admin templates to include ALL permissions
INSERT IGNORE INTO role_template_permissions (template_id, permission_id)
SELECT 1, id FROM permissions;
