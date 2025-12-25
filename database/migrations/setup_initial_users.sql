-- Setup Initial Users with RBAC Role Templates
-- This script creates admin and teacher users with proper role assignments

-- First, ensure we have the RBAC setup
SOURCE setup_rbac_permissions.sql;

-- Create admin user if not exists
INSERT IGNORE INTO users (username, email, password, role, created_at) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
('teacher1', 'teacher1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NOW()),
('hybrid_teacher', 'hybrid@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NOW());

-- Get user IDs
SET @admin_id = (SELECT id FROM users WHERE username = 'admin' LIMIT 1);
SET @teacher_id = (SELECT id FROM users WHERE username = 'teacher1' LIMIT 1);
SET @hybrid_teacher_id = (SELECT id FROM users WHERE username = 'hybrid_teacher' LIMIT 1);

-- Get template IDs
SET @full_admin_template_id = (SELECT id FROM role_templates WHERE name = 'Full Admin' LIMIT 1);
SET @full_teacher_template_id = (SELECT id FROM role_templates WHERE name = 'Full Teacher' LIMIT 1);
SET @hybrid_teacher_template_id = (SELECT id FROM role_templates WHERE name = 'Hybrid Teacher' LIMIT 1);

-- Assign role templates to users
-- Admin gets Full Admin template
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT @admin_id, @full_admin_template_id
WHERE @admin_id IS NOT NULL AND @full_admin_template_id IS NOT NULL;

-- Teacher gets Full Teacher template
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT @teacher_id, @full_teacher_template_id
WHERE @teacher_id IS NOT NULL AND @full_teacher_template_id IS NOT NULL;

-- Hybrid teacher gets Hybrid Teacher template
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT @hybrid_teacher_id, @hybrid_teacher_template_id
WHERE @hybrid_teacher_id IS NOT NULL AND @hybrid_teacher_template_id IS NOT NULL;

-- Verify setup
SELECT 'Initial Users Setup Complete' as status;

SELECT 
    u.username,
    u.role,
    GROUP_CONCAT(rt.name) as assigned_templates,
    GROUP_CONCAT(DISTINCT p.name) as permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id
WHERE u.username IN ('admin', 'teacher1', 'hybrid_teacher')
GROUP BY u.id, u.username, u.role;

-- Login credentials for testing:
-- Admin: admin@example.com / password
-- Teacher: teacher1@example.com / password  
-- Hybrid Teacher: hybrid@example.com / password
