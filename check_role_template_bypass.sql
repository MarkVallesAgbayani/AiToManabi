-- CHECK IF USER HAS DEFAULT PERMISSION ROLE TEMPLATE
-- This might be why you can see all navigation
-- ===============================================

-- Check your user's role template assignment
SELECT 
    u.username,
    u.role,
    rt.name as template_name,
    ur.template_id
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id  
LEFT JOIN role_templates rt ON ur.template_id = rt.id
WHERE u.username IN ('custompermission', 'lasttest123', 'lasttina');

-- Check if shouldShowAllNavigation would return true
SELECT 
    u.username,
    CASE 
        WHEN rt.name IN ('Default Permission', 'Default Admin') 
        THEN 'TRUE - Will show all navigation ❌'
        ELSE 'FALSE - Will use actual permissions ✅'
    END as show_all_navigation
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
WHERE u.username IN ('custompermission', 'lasttest123', 'lasttina');