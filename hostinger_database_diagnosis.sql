-- COMPREHENSIVE HOSTINGER DATABASE DIAGNOSIS
-- Compare with your working local database
-- =============================================

-- 1. BASIC PERMISSION COUNTS
SELECT 'Total Permissions in Database' as check_type, COUNT(*) as count FROM permissions;

-- 2. TEMPLATE ANALYSIS
SELECT 
    'Role Templates' as check_type,
    rt.id,
    rt.name,
    COUNT(rtp.permission_id) as permission_count,
    ROUND((COUNT(rtp.permission_id) * 100.0 / (SELECT COUNT(*) FROM permissions)), 2) as completion_percentage
FROM role_templates rt
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
GROUP BY rt.id, rt.name
ORDER BY rt.id;

-- 3. YOUR SPECIFIC USERS ANALYSIS  
SELECT 
    'User Analysis' as check_type,
    u.username,
    u.role,
    ur.template_id,
    rt.name as template_name,
    COUNT(DISTINCT up.permission_name) as direct_permissions,
    COALESCE(tp.template_permissions, 0) as template_permissions,
    COUNT(DISTINCT up.permission_name) + COALESCE(tp.template_permissions, 0) as total_permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN user_permissions up ON u.id = up.user_id
LEFT JOIN (
    SELECT ur2.user_id, COUNT(rtp2.permission_id) as template_permissions
    FROM user_roles ur2
    JOIN role_template_permissions rtp2 ON ur2.template_id = rtp2.template_id
    GROUP BY ur2.user_id
) tp ON u.id = tp.user_id
WHERE u.role = 'admin'
GROUP BY u.id, u.username, u.role, ur.template_id, rt.name, tp.template_permissions
ORDER BY total_permissions DESC;

-- 4. PERMISSION CATEGORIES CHECK
SELECT 
    'Permission Categories' as check_type,
    category,
    COUNT(*) as permission_count
FROM permissions 
GROUP BY category 
ORDER BY permission_count DESC;

-- 5. MISSING NAVIGATION PERMISSIONS
SELECT 
    'Missing Nav Permissions' as check_type,
    'nav_' + category as expected_permission,
    'MISSING' as status
FROM (
    SELECT DISTINCT 
        CASE 
            WHEN category LIKE 'admin_%' THEN REPLACE(category, 'admin_', '')
            WHEN category LIKE 'teacher_%' THEN REPLACE(category, 'teacher_', '')
            ELSE category 
        END as category
    FROM permissions
) cat
WHERE CONCAT('nav_', cat.category) NOT IN (
    SELECT name FROM permissions WHERE name LIKE 'nav_%'
)
ORDER BY expected_permission;

-- 6. COMPARE WITH EXPECTED ADMIN PERMISSIONS
SELECT 
    'Admin Permission Check' as check_type,
    expected_perm,
    CASE 
        WHEN expected_perm IN (SELECT name FROM permissions) 
        THEN 'EXISTS ✅' 
        ELSE 'MISSING ❌' 
    END as status
FROM (
    SELECT 'nav_dashboard' as expected_perm UNION ALL
    SELECT 'nav_course_management' UNION ALL
    SELECT 'nav_user_management' UNION ALL
    SELECT 'nav_reports' UNION ALL
    SELECT 'nav_payments' UNION ALL
    SELECT 'nav_content_management' UNION ALL
    SELECT 'dashboard_view_metrics' UNION ALL
    SELECT 'dashboard_view_course_completion' UNION ALL
    SELECT 'user_add_new' UNION ALL
    SELECT 'user_reset_password' UNION ALL
    SELECT 'user_ban_user' UNION ALL
    SELECT 'course_add_category' UNION ALL
    SELECT 'course_edit_category' UNION ALL
    SELECT 'course_delete_category'
) expected_perms;

-- 7. FINAL SUMMARY
SELECT 
    'DATABASE HEALTH SUMMARY' as summary_type,
    CONCAT(
        'Total Permissions: ', (SELECT COUNT(*) FROM permissions),
        ' | Admin Templates: ', (SELECT COUNT(*) FROM role_templates WHERE name LIKE '%admin%'),
        ' | Admin Users: ', (SELECT COUNT(*) FROM users WHERE role = 'admin'),
        ' | Categories: ', (SELECT COUNT(DISTINCT category) FROM permissions)
    ) as summary;