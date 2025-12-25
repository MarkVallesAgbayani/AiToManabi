# Dashboard Permissions System Guide

## Overview
This guide explains how to use the new permission-based dashboard card system that has been implemented for the admin dashboard. The system allows you to control which dashboard cards and analytics sections are visible to different users based on their permissions.

## New Permissions Created

### Summary Card Permissions
- `dashboard_view_students_card` - View Total Students card
- `dashboard_view_teachers_card` - View Total Teachers card  
- `dashboard_view_modules_card` - View Total Modules card
- `dashboard_view_revenue_card` - View Total Revenue card

### Analytics Section Permissions
- `dashboard_view_course_completion` - View Course Completion Report section
- `dashboard_view_user_retention` - View User Retention Report section
- `dashboard_view_sales_reports` - View Sales Reports section

### Detailed Analytics Permissions
- `dashboard_view_completion_metrics` - View course completion metrics and charts
- `dashboard_view_retention_metrics` - View user retention metrics and charts
- `dashboard_view_sales_metrics` - View sales metrics and revenue charts

## How It Works

### Permission Check Logic
Each dashboard card and section now includes a permission check:
```php
<?php if (shouldShowAllNavigation($pdo, $_SESSION['user_id']) || hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_students_card')): ?>
    <!-- Dashboard card content -->
<?php endif; ?>
```

### Fallback Behavior
- Users with "Default Permission" or "Default Admin" role templates see all cards (existing behavior)
- Users without specific permissions won't see the restricted cards
- The system gracefully handles missing permissions

## SQL Queries to Implement

### 1. Create the Permissions
Run the SQL queries in `dashboard_permissions_sql.sql` to:
- Insert all new dashboard permissions
- Assign permissions to existing role templates
- Create new specialized role templates

### 2. Assign Permissions to Users

#### Option A: Assign Role Template
```sql
-- Assign Dashboard Viewer role to user ID 5
INSERT INTO user_roles (user_id, template_id) 
VALUES (5, (SELECT id FROM role_templates WHERE name = 'Dashboard Viewer'));
```

#### Option B: Assign Individual Permissions
```sql
-- Give specific permissions to user ID 5
INSERT INTO user_permissions (user_id, permission_name) VALUES 
(5, 'dashboard_view_students_card'),
(5, 'dashboard_view_teachers_card'),
(5, 'dashboard_view_modules_card');
```

### 3. Check User Permissions
```sql
-- See what dashboard permissions a user has
SELECT 
    u.username,
    rt.name as role_template,
    GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as dashboard_permissions
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN role_templates rt ON ur.template_id = rt.id
LEFT JOIN role_template_permissions rtp ON rt.id = rtp.template_id
LEFT JOIN permissions p ON rtp.permission_id = p.id AND p.category = 'Dashboard'
WHERE u.id = 5 AND p.name IS NOT NULL
GROUP BY u.id, u.username, rt.name;
```

## New Role Templates Created

### Dashboard Viewer
- Can view basic dashboard metrics
- Permissions: students, teachers, modules cards, course completion, retention metrics
- **Cannot** view revenue/financial data

### Financial Dashboard Viewer  
- Can view financial metrics and sales reports
- Permissions: revenue card, sales reports, sales metrics, course completion
- **Cannot** view user-specific data

## Usage Examples

### Example 1: Create a Read-Only Admin
```sql
-- Create user with limited dashboard access
INSERT INTO user_roles (user_id, template_id) 
VALUES (10, (SELECT id FROM role_templates WHERE name = 'Dashboard Viewer'));
```

### Example 2: Create a Financial Analyst
```sql
-- Create user who can only see financial data
INSERT INTO user_roles (user_id, template_id) 
VALUES (11, (SELECT id FROM role_templates WHERE name = 'Financial Dashboard Viewer'));
```

### Example 3: Custom Permission Set
```sql
-- Give user only student and teacher card access
INSERT INTO user_permissions (user_id, permission_name) VALUES 
(12, 'dashboard_view_students_card'),
(12, 'dashboard_view_teachers_card');
```

## Security Benefits

1. **Data Privacy**: Sensitive financial information is only visible to authorized users
2. **Role-Based Access**: Different user types see only relevant information
3. **Granular Control**: Fine-grained permissions for specific dashboard elements
4. **Audit Trail**: All permission changes are tracked in the database

## Maintenance

### Adding New Dashboard Cards
1. Create new permission in database
2. Add permission check around the card in admin.php
3. Assign permission to appropriate role templates

### Removing Permissions
```sql
-- Remove all dashboard permissions from a user
DELETE FROM user_permissions 
WHERE user_id = 5 AND permission_name LIKE 'dashboard_%';
```

### Updating Role Templates
```sql
-- Add new permission to existing role template
INSERT INTO role_template_permissions (template_id, permission_id)
SELECT 
    (SELECT id FROM role_templates WHERE name = 'Dashboard Viewer'),
    (SELECT id FROM permissions WHERE name = 'dashboard_view_new_card');
```

## Troubleshooting

### User Can't See Any Cards
- Check if user has "Default Permission" or "Default Admin" role
- Verify user has at least one dashboard permission
- Check if user is logged in with correct session

### Card Shows But Data is Missing
- Verify the underlying data queries are working
- Check if user has permission for the specific card
- Ensure database connections are working

### Permission Not Working
- Verify permission exists in database
- Check if permission is assigned to user's role template
- Confirm user has custom permission if not using role template

## Best Practices

1. **Principle of Least Privilege**: Only give users permissions they need
2. **Regular Audits**: Periodically review user permissions
3. **Test Changes**: Always test permission changes in development first
4. **Documentation**: Keep track of custom permission assignments
5. **Backup**: Backup permission data before making bulk changes

## Files Modified

- `dashboard/admin.php` - Added permission checks to all dashboard cards and sections
- `dashboard_permissions_sql.sql` - SQL queries to create and assign permissions
- `dashboard_permissions_guide.md` - This documentation file

The permission system is now fully implemented and ready for use. Users will only see dashboard cards and sections they have permission to view, providing better security and user experience.
