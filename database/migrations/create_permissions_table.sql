-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default permissions grouped by category
-- User Management permissions
INSERT INTO permissions (name, description, category) VALUES
('view_users', 'View user list and details', 'user_management'),
('create_users', 'Create new users', 'user_management'),
('edit_users', 'Edit user details', 'user_management'),
('delete_users', 'Delete users', 'user_management'),
('manage_roles', 'Manage user roles and permissions', 'user_management');

-- Course Management permissions
INSERT INTO permissions (name, description, category) VALUES
('view_courses', 'View course list and details', 'course_management'),
('create_courses', 'Create new courses', 'course_management'),
('edit_courses', 'Edit course details', 'course_management'),
('delete_courses', 'Delete courses', 'course_management'),
('manage_enrollments', 'Manage course enrollments', 'course_management');

-- Content Management permissions
INSERT INTO permissions (name, description, category) VALUES
('view_content', 'View course content', 'content_management'),
('create_content', 'Create course content', 'content_management'),
('edit_content', 'Edit course content', 'content_management'),
('delete_content', 'Delete course content', 'content_management'),
('manage_media', 'Manage course media files', 'content_management');

-- Report Management permissions
INSERT INTO permissions (name, description, category) VALUES
('view_reports', 'View system reports', 'report_management'),
('generate_reports', 'Generate new reports', 'report_management'),
('export_reports', 'Export reports', 'report_management');

-- System Management permissions
INSERT INTO permissions (name, description, category) VALUES
('view_settings', 'View system settings', 'system_management'),
('edit_settings', 'Edit system settings', 'system_management'),
('view_audit_logs', 'View audit logs', 'system_management'),
('manage_backups', 'Manage system backups', 'system_management');

-- Create role_permissions table for default role templates
CREATE TABLE IF NOT EXISTS role_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_template_permissions (
    template_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (template_id, permission_id),
    FOREIGN KEY (template_id) REFERENCES role_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Insert default role templates
INSERT INTO role_templates (name, description) VALUES
('Default Admin', 'Full system access with all permissions'),
('Basic Teacher', 'Basic teaching permissions'),
('Advanced Teacher', 'Extended teaching permissions with some admin capabilities');

-- Modify user_permissions table to add audit fields
ALTER TABLE user_permissions
ADD COLUMN granted_by INT NULL AFTER permission_name,
ADD COLUMN granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL; 