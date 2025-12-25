-- CREATE MISSING "CUSTOM PERMISSIONS" TEMPLATE ON HOSTINGER
-- This will match your working local database setup
-- =======================================================

-- 1. Create the missing "Custom Permissions" template (using ID 9 since 4 exists)
INSERT INTO role_templates (id, name, description, created_at) VALUES 
(3, 'Custom Permissions', 'Custom permissions template - no default permissions', '2025-09-25 17:17:51');

-- 2. Create the missing "Student Manager" template  
INSERT INTO role_templates (id, name, description, created_at) VALUES 
(8, 'Student Manager', 'Focused on student management and progress tracking', '2025-09-25 17:37:03');

-- 3. Verify the templates were created
SELECT * FROM role_templates ORDER BY id;

-- 4. Change custompermission user to use Custom Permissions template (like your local)
UPDATE user_roles 
SET template_id = 4 
WHERE user_id = (SELECT id FROM users WHERE username = 'custompermission');

-- 5. Verify the user assignment
SELECT 
    u.username,
    rt.name as template_name,
    ur.template_id
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_templates rt ON ur.template_id = rt.id
WHERE u.username = 'custompermission';