<?php
/**
 * RBAC Helper Functions
 * Provides functions to work with the Role-Based Access Control system
 */

/**
 * Get all effective permissions for a user (from templates + custom permissions)
 */
function getUserEffectivePermissions($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name as permission_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN role_template_permissions rtp ON ur.template_id = rtp.template_id
        LEFT JOIN permissions p ON rtp.permission_id = p.id
        LEFT JOIN user_permissions up ON u.id = up.user_id
        WHERE u.id = ? AND (p.name IS NOT NULL OR up.permission_name IS NOT NULL)
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Check if user has a specific permission
 */
function hasPermission($pdo, $user_id, $permission_name) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM (
            -- Check template permissions
            SELECT 1
            FROM user_roles ur
            JOIN role_template_permissions rtp ON ur.template_id = rtp.template_id
            JOIN permissions p ON rtp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name = ?
            
            UNION
            
            -- Check custom permissions
            SELECT 1
            FROM user_permissions up
            WHERE up.user_id = ? AND up.permission_name = ?
        ) as combined_permissions
    ");
    $stmt->execute([$user_id, $permission_name, $user_id, $permission_name]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get user's role templates
 */
function getUserRoleTemplates($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT rt.name, rt.description
        FROM user_roles ur
        JOIN role_templates rt ON ur.template_id = rt.id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if user is a hybrid teacher (has admin-like permissions)
 */
function isHybridTeacher($pdo, $user_id) {
    return hasPermission($pdo, $user_id, 'nav_hybrid_users') || 
           hasPermission($pdo, $user_id, 'nav_hybrid_reports');
}

/**
 * Check if user is a hybrid admin (has both admin and teacher permissions)
 */
function isHybridAdmin($pdo, $user_id) {
    // Check if user has Hybrid Admin role template
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_roles ur
        JOIN role_templates rt ON ur.template_id = rt.id
        WHERE ur.user_id = ? AND rt.name = 'Hybrid Admin'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if user can access teacher modules (Hybrid Admin or Teacher)
 */
function canAccessTeacherModules($pdo, $user_id) {
    return isHybridAdmin($pdo, $user_id) || 
           isHybridTeacher($pdo, $user_id) ||
           hasPermission($pdo, $user_id, 'nav_teacher_dashboard');
}

/**
 * Check if user can access admin modules (Admin or Hybrid Admin)
 */
function canAccessAdminModules($pdo, $user_id) {
    return isHybridAdmin($pdo, $user_id) || 
           hasPermission($pdo, $user_id, 'nav_dashboard');
}

/**
 * Check if user has Default Permission role (shows all navigation)
 */
function hasDefaultPermissionRole($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_roles ur
        JOIN role_templates rt ON ur.template_id = rt.id
        WHERE ur.user_id = ? AND (rt.name = 'Default Permission' OR rt.name = 'Default Admin')
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if user should see all navigation menus (Default Permission role)
 */
function shouldShowAllNavigation($pdo, $user_id) {
    return hasDefaultPermissionRole($pdo, $user_id);
}

/**
 * Get permissions that the logged-in user can assign to others
 * (Only show permissions the current user has)
 */
function getAssignablePermissions($pdo, $current_user_id) {
    $current_user_permissions = getUserEffectivePermissions($pdo, $current_user_id);
    
    // If user has no permissions, return empty array
    if (empty($current_user_permissions)) {
        return [];
    }
    
    // Get all permissions that the current user has
    $placeholders = str_repeat('?,', count($current_user_permissions) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id, name, description, category
        FROM permissions
        WHERE name IN ($placeholders)
        ORDER BY category, name
    ");
    $stmt->execute($current_user_permissions);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get available role templates for assignment
 */
function getAvailableRoleTemplates($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, description
        FROM role_templates
        ORDER BY name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Assign role template to user
 */
function assignRoleTemplate($pdo, $user_id, $template_id, $granted_by = null) {
    try {
        error_log("assignRoleTemplate called: user_id=$user_id, template_id=$template_id");
        
        // Check if we're already in a transaction
        $inTransaction = $pdo->inTransaction();
        
        if (!$inTransaction) {
            $pdo->beginTransaction();
        }
        
        // Remove existing role assignments for this user
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        error_log("Removed existing role assignments for user $user_id");
        
        // Assign new role template
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, template_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $template_id]);
        error_log("Assigned template $template_id to user $user_id");
        
        // Only commit if we started the transaction
        if (!$inTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (!$inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error assigning role template: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign custom permissions to user
 */
function assignCustomPermissions($pdo, $user_id, $permissions, $granted_by = null) {
    try {
        // Check if we're already in a transaction
        $inTransaction = $pdo->inTransaction();
        
        if (!$inTransaction) {
            $pdo->beginTransaction();
        }
        
        // Remove existing custom permissions for this user
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Add new custom permissions
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_permissions (user_id, permission_name, granted_by) 
                VALUES (?, ?, ?)
            ");
            foreach ($permissions as $permission) {
                $stmt->execute([$user_id, $permission, $granted_by]);
            }
        }
        
        // Only commit if we started the transaction
        if (!$inTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (!$inTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error assigning custom permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's current permissions summary for display
 */
function getUserPermissionsSummary($pdo, $user_id) {
    $permissions = getUserEffectivePermissions($pdo, $user_id);
    $templates = getUserRoleTemplates($pdo, $user_id);
    
    return [
        'permissions' => $permissions,
        'templates' => array_column($templates, 'name'),
        'is_hybrid_teacher' => isHybridTeacher($pdo, $user_id),
        'is_hybrid_admin' => isHybridAdmin($pdo, $user_id),
        'can_access_teacher_modules' => canAccessTeacherModules($pdo, $user_id),
        'can_access_admin_modules' => canAccessAdminModules($pdo, $user_id)
    ];
}

/**
 * Get all permissions from the database grouped by category
 */
function getAllPermissionsByCategory($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, description, category
        FROM permissions
        ORDER BY category, name
    ");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grouped = [];
    foreach ($permissions as $permission) {
        $category = $permission['category'];
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $permission;
    }
    
    return $grouped;
}

/**
 * Get all permissions as a flat array
 */
function getAllPermissionsFlat($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, description, category
        FROM permissions
        ORDER BY category, name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if user has any permission from a list of permissions
 */
function hasAnyPermission($pdo, $user_id, $permission_names) {
    if (empty($permission_names)) {
        return false;
    }
    
    $placeholders = str_repeat('?,', count($permission_names) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM (
            -- Check template permissions
            SELECT 1
            FROM user_roles ur
            JOIN role_template_permissions rtp ON ur.template_id = rtp.template_id
            JOIN permissions p ON rtp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name IN ($placeholders)
            
            UNION
            
            -- Check custom permissions
            SELECT 1
            FROM user_permissions up
            WHERE up.user_id = ? AND up.permission_name IN ($placeholders)
        ) as combined_permissions
    ");
    
    $params = array_merge([$user_id], $permission_names, [$user_id], $permission_names);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}
?>
