-- HOSTINGER PERMISSION ANALYSIS
-- Check what permissions your admin account actually has
-- =====================================================

-- 1. Check your specific user's permissions
SELECT 
    'Your Direct Permissions' as type,
    permission_name
FROM user_permissions 
WHERE user_id = (SELECT id FROM users WHERE username = 'custompermission' LIMIT 1)
ORDER BY permission_name;

-- 2. Check your role template permissions
SELECT 
    'Template Permissions' as type,
    p.name as permission_name
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_template_permissions rtp ON ur.template_id = rtp.template_id
JOIN permissions p ON rtp.permission_id = p.id
WHERE u.username = 'custompermission'
ORDER BY p.name;

-- 3. Count total permissions available
SELECT 
    'System Status' as type,
    CONCAT(
        'Total Permissions in DB: ', (SELECT COUNT(*) FROM permissions),
        ' | Your Direct: ', (SELECT COUNT(*) FROM user_permissions WHERE user_id = (SELECT id FROM users WHERE username = 'custompermission')),
        ' | Your Template: ', COALESCE((SELECT COUNT(*) FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN role_template_permissions rtp ON ur.template_id = rtp.template_id WHERE u.username = 'custompermission'), 0)
    ) as summary;

-- 4. Check which admin template you're using
SELECT 
    'Your Template Assignment' as type,
    rt.name as template_name,
    COUNT(rtp.permission_id) as template_permission_count
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
WHERE u.username = 'custompermission'
GROUP BY rt.id, rt.name;

-- 5. Show navigation permissions specifically
SELECT 
    'Navigation Permissions' as type,
    p.name as permission_name,
    'MISSING' as status
FROM permissions p
WHERE p.name LIKE 'nav_%'
AND p.name NOT IN (
    SELECT up.permission_name 
    FROM user_permissions up 
    JOIN users u ON up.user_id = u.id 
    WHERE u.username = 'custompermission'
)
AND p.name NOT IN (
    SELECT p2.name
    FROM users u2
    JOIN user_roles ur2 ON u2.id = ur2.user_id
    JOIN role_template_permissions rtp2 ON ur2.template_id = rtp2.template_id
    JOIN permissions p2 ON rtp2.permission_id = p2.id
    WHERE u2.username = 'custompermission'
)
ORDER BY p.name;