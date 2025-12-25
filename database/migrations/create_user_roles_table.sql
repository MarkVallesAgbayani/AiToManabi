-- Create user_roles table to link users to role templates
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, template_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing users from role column to user_roles table
-- First, ensure we have the necessary role templates
INSERT IGNORE INTO role_templates (name, description) VALUES
('Full Admin', 'Complete system access with all admin permissions'),
('Full Teacher', 'Complete teacher access with all teaching features'),
('Student', 'Basic student access to courses and learning materials');

-- Get template IDs for migration
SET @full_admin_template_id = (SELECT id FROM role_templates WHERE name = 'Full Admin' LIMIT 1);
SET @full_teacher_template_id = (SELECT id FROM role_templates WHERE name = 'Full Teacher' LIMIT 1);
SET @student_template_id = (SELECT id FROM role_templates WHERE name = 'Student' LIMIT 1);

-- Insert admin users into user_roles
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT id, @full_admin_template_id
FROM users 
WHERE role = 'admin' 
AND @full_admin_template_id IS NOT NULL;

-- Insert teacher users into user_roles
INSERT IGNORE INTO user_roles (user_id, template_id)
SELECT id, @full_teacher_template_id
FROM users 
WHERE role = 'teacher' 
AND @full_teacher_template_id IS NOT NULL;

-- Insert student users into user_roles (optional - uncomment if you want students assigned)
-- INSERT IGNORE INTO user_roles (user_id, template_id)
-- SELECT id, @student_template_id
-- FROM users 
-- WHERE role = 'student' 
-- AND @student_template_id IS NOT NULL;

-- Verify migration results
SELECT 
    'Migration Summary' as info,
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users,
    COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teacher_users,
    COUNT(CASE WHEN role = 'student' THEN 1 END) as student_users
FROM users;

SELECT 
    'User Roles Assignment' as info,
    COUNT(*) as total_assignments,
    rt.name as template_name,
    COUNT(*) as user_count
FROM user_roles ur
JOIN role_templates rt ON ur.template_id = rt.id
GROUP BY rt.id, rt.name;

-- Show any users that weren't assigned (for debugging)
SELECT 
    'Unassigned Users' as info,
    u.id,
    u.username,
    u.role,
    CASE 
        WHEN u.role = 'admin' AND @full_admin_template_id IS NULL THEN 'Full Admin template not found'
        WHEN u.role = 'teacher' AND @full_teacher_template_id IS NULL THEN 'Full Teacher template not found'
        WHEN u.role = 'student' AND @student_template_id IS NULL THEN 'Student template not found'
        ELSE 'Unknown issue'
    END as reason
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
WHERE ur.user_id IS NULL
AND u.role IN ('admin', 'teacher', 'student');
