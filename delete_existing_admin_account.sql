-- ===================================================================
-- DELETE EXISTING ADMIN ACCOUNT FROM HOSTINGER
-- Purpose: Remove existing admin account before importing new one
-- Generated on: 2025-09-19 (Philippines Time)
-- ===================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;
SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;

-- ===================================================================
-- DELETE EXISTING ADMIN ACCOUNT
-- ===================================================================

-- Delete user roles for existing admin accounts
DELETE FROM `user_roles` 
WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

-- Delete user permissions for existing admin accounts
DELETE FROM `user_permissions` 
WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

-- Delete admin-related data for existing admin accounts
DELETE FROM `admin_action_logs` 
WHERE `admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

DELETE FROM `admin_audit_log` 
WHERE `admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

DELETE FROM `admin_dashboard_preferences` 
WHERE `admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

DELETE FROM `admin_logs` 
WHERE `admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

DELETE FROM `admin_preferences` 
WHERE `admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

DELETE FROM `system_notifications` 
WHERE `target_admin_id` IN (
    SELECT `id` FROM `users` WHERE `role` = 'admin'
);

-- Delete the admin users themselves
DELETE FROM `users` WHERE `role` = 'admin';

-- ===================================================================
-- VERIFICATION QUERIES (Optional - to check if deletion was successful)
-- ===================================================================

-- Check if any admin users remain
SELECT COUNT(*) as remaining_admins FROM `users` WHERE `role` = 'admin';

-- Check remaining users by role
SELECT `role`, COUNT(*) as count FROM `users` GROUP BY `role`;

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
-- 1. Run this script first on Hostinger to delete existing admin accounts
-- 2. Then import the main japanese_lms_hostinger_export_2025-09-19_05-53-40.sql
-- 3. This will give you a clean start with the new adminmark account
-- ===================================================================
