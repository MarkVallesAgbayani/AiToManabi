-- Helper view to get all user permissions (from templates + custom permissions)
CREATE OR REPLACE VIEW user_permissions_view AS
SELECT DISTINCT
    u.id as user_id,
    u.username,
    u.role,
    COALESCE(up.permission_name, p.name) as permission_name,
    CASE 
        WHEN up.permission_name IS NOT NULL THEN 'custom'
        ELSE 'template'
    END as permission_source,
    rt.name as template_name,
    up.granted_by,
    up.granted_at
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
LEFT JOIN user_permissions up ON u.id = up.user_id
WHERE u.id IS NOT NULL;

-- Helper function to get user's effective permissions
DELIMITER //
CREATE FUNCTION GetUserPermissions(user_id_param INT) 
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result TEXT DEFAULT '';
    
    SELECT GROUP_CONCAT(DISTINCT permission_name ORDER BY permission_name SEPARATOR ',')
    INTO result
    FROM user_permissions_view
    WHERE user_id = user_id_param;
    
    RETURN COALESCE(result, '');
END //
DELIMITER ;

-- Helper function to check if user has specific permission
DELIMITER //
CREATE FUNCTION HasPermission(user_id_param INT, permission_name_param VARCHAR(100)) 
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE permission_count INT DEFAULT 0;
    
    SELECT COUNT(*)
    INTO permission_count
    FROM user_permissions_view
    WHERE user_id = user_id_param 
    AND permission_name = permission_name_param;
    
    RETURN permission_count > 0;
END //
DELIMITER ;

-- Helper function to get user's role templates
DELIMITER //
CREATE FUNCTION GetUserTemplates(user_id_param INT) 
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result TEXT DEFAULT '';
    
    SELECT GROUP_CONCAT(rt.name ORDER BY rt.name SEPARATOR ',')
    INTO result
    FROM user_roles ur
    JOIN role_templates rt ON ur.template_id = rt.id
    WHERE ur.user_id = user_id_param;
    
    RETURN COALESCE(result, '');
END //
DELIMITER ;

-- Index for better performance
CREATE INDEX idx_user_roles_user_id ON user_roles(user_id);
CREATE INDEX idx_user_roles_template_id ON user_roles(template_id);
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_name ON user_permissions(permission_name);

-- Sample queries for common operations:

-- Get all permissions for a specific user
-- SELECT permission_name, permission_source, template_name 
-- FROM user_permissions_view 
-- WHERE user_id = 1;

-- Check if user has admin access
-- SELECT HasPermission(1, 'nav_dashboard') as has_dashboard_access;

-- Get user's role templates
-- SELECT GetUserTemplates(1) as user_templates;

-- Get all users with their permissions summary
-- SELECT 
--     u.id,
--     u.username,
--     u.role,
--     GetUserTemplates(u.id) as templates,
--     GetUserPermissions(u.id) as permissions
-- FROM users u
-- WHERE u.role IN ('admin', 'teacher');
