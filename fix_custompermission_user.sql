-- GIVE CUSTOMPERMISSION USER ALL ADMIN PERMISSIONS
-- This will make their 6 permissions become 150+ permissions
-- ===================================================

-- Find the custompermission user ID first
SELECT id, username, role FROM users WHERE username = 'custompermission';

-- Grant ALL permissions to custompermission user
INSERT IGNORE INTO user_permissions (user_id, permission_name, granted_at)
SELECT 
    (SELECT id FROM users WHERE username = 'custompermission' LIMIT 1),
    name,
    NOW()
FROM permissions
WHERE name NOT IN (
    SELECT permission_name 
    FROM user_permissions 
    WHERE user_id = (SELECT id FROM users WHERE username = 'custompermission' LIMIT 1)
);

-- Verification: Check permission count after update
SELECT 
    u.username,
    COUNT(up.permission_name) as total_permissions,
    (SELECT COUNT(*) FROM permissions) as available_permissions,
    CASE 
        WHEN COUNT(up.permission_name) >= (SELECT COUNT(*) FROM permissions) 
        THEN 'COMPLETE ✅'
        ELSE 'INCOMPLETE ❌'
    END as status
FROM users u
LEFT JOIN user_permissions up ON u.id = up.user_id
WHERE u.username = 'custompermission'
GROUP BY u.id, u.username;