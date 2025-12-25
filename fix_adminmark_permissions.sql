-- ===================================================================
-- FIX ADMINMARK PERMISSIONS - SAFE UPDATE SCRIPT
-- Purpose: Update adminmark user to have all 62 permissions
-- Generated on: 2025-09-19 (Philippines Time)
-- ===================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;
SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;

-- ===================================================================
-- SAFETY CHECK - Verify adminmark user exists
-- ===================================================================

SELECT 
    u.id as user_id,
    u.username,
    u.email,
    u.role,
    rt.name as current_template,
    COUNT(DISTINCT p.id) as current_permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE u.email = 'adminmark@aitomanabi.com'
GROUP BY u.id, u.username, u.email, u.role, rt.name;

-- ===================================================================
-- STEP 1: Remove current role assignment for adminmark
-- ===================================================================

DELETE FROM `user_roles` 
WHERE `user_id` = (
    SELECT `id` FROM `users` WHERE `email` = 'adminmark@aitomanabi.com'
);

-- ===================================================================
-- STEP 2: Assign "Full Admin Access" role template to adminmark
-- ===================================================================

INSERT INTO `user_roles` (`user_id`, `template_id`, `created_at`)
SELECT u.id, rt.id, NOW()
FROM `users` u, `role_templates` rt
WHERE u.email = 'adminmark@aitomanabi.com' 
AND rt.name = 'Full Admin Access';

-- ===================================================================
-- VERIFICATION - Check if adminmark now has all permissions
-- ===================================================================

SELECT 
    u.id as user_id,
    u.username,
    u.email,
    u.role,
    rt.name as template_name,
    COUNT(DISTINCT p.id) as total_permissions,
    GROUP_CONCAT(DISTINCT p.category ORDER BY p.category ASC SEPARATOR ',') as permission_categories
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE u.email = 'adminmark@aitomanabi.com'
GROUP BY u.id, u.username, u.email, u.role, rt.name;

-- ===================================================================
-- FINAL CHECK - Should show 62 permissions
-- ===================================================================

SELECT 
    'adminmark' as username,
    COUNT(DISTINCT p.id) as total_permissions,
    CASE 
        WHEN COUNT(DISTINCT p.id) = 62 THEN 'SUCCESS: adminmark has all 62 permissions!'
        ELSE CONCAT('WARNING: adminmark has only ', COUNT(DISTINCT p.id), ' permissions')
    END as status
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_templates rt ON ur.template_id = rt.id
JOIN role_template_permissions rtp ON rt.id = rtp.template_id
JOIN permissions p ON rtp.permission_id = p.id
WHERE u.email = 'adminmark@aitomanabi.com';

-- ===================================================================
-- Restore MySQL settings
-- ===================================================================
SET SQL_NOTES=@OLD_SQL_NOTES;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- ===================================================================
-- INSTRUCTIONS:
-- 1. Run this script on your Hostinger database
-- 2. Check the verification queries to ensure adminmark gets 62 permissions
-- 3. The final check should show "SUCCESS: adminmark has all 62 permissions!"
-- ===================================================================
