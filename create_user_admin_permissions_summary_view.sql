-- ===================================================================
-- CREATE USER ADMIN PERMISSIONS SUMMARY VIEW
-- Purpose: Create the user_admin_permissions_summary view on Hostinger
-- Generated on: 2025-09-19 (Philippines Time)
-- ===================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;
SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;

-- ===================================================================
-- DROP EXISTING VIEW (if it exists)
-- ===================================================================

DROP VIEW IF EXISTS `user_admin_permissions_summary`;

-- ===================================================================
-- CREATE USER ADMIN PERMISSIONS SUMMARY VIEW
-- ===================================================================

CREATE VIEW `user_admin_permissions_summary` AS 
SELECT 
    `u`.`id` AS `user_id`,
    `u`.`username` AS `username`,
    `u`.`role` AS `role`,
    `rt`.`name` AS `template_name`,
    COUNT(DISTINCT `p`.`name`) AS `total_permissions`,
    GROUP_CONCAT(DISTINCT `p`.`category` ORDER BY `p`.`category` ASC SEPARATOR ',') AS `permission_categories`
FROM (
    (
        (
            (
                `users` `u` 
                LEFT JOIN `user_roles` `ur` ON (`u`.`id` = `ur`.`user_id`)
            ) 
            LEFT JOIN `role_templates` `rt` ON (`ur`.`template_id` = `rt`.`id`)
        ) 
        LEFT JOIN `role_template_permissions` `rtp` ON (`rt`.`id` = `rtp`.`template_id`)
    ) 
    LEFT JOIN `permissions` `p` ON (`rtp`.`permission_id` = `p`.`id`)
) 
WHERE `u`.`role` = 'admin' 
AND `p`.`category` LIKE 'admin_%' 
GROUP BY `u`.`id`, `u`.`username`, `u`.`role`, `rt`.`name`;

-- ===================================================================
-- TEST THE VIEW
-- ===================================================================

-- Test query to verify the view works
SELECT * FROM `user_admin_permissions_summary` LIMIT 5;

-- Count total admin users
SELECT COUNT(*) as total_admin_users FROM `user_admin_permissions_summary`;

-- ===================================================================
-- Restore MySQL settings
-- ===================================================================
SET SQL_NOTES=@OLD_SQL_NOTES;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- ===================================================================
-- INSTRUCTIONS FOR USE:
-- 1. Run this script on your Hostinger database
-- 2. This will create the user_admin_permissions_summary view
-- 3. You can then query: SELECT * FROM user_admin_permissions_summary;
-- ===================================================================
