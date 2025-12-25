<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/admin_notifications.php';
require_once 'audit_logger.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get all user permissions to check for access
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);


// Check course management access - either by permission or admin role
$has_course_management_access = false;

// First check if user has course management permissions
if (function_exists('hasAnyPermission')) {
    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category', 'course_restore_category'];
    $has_course_management_access = hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions);
}

// Fallback: Check if user has admin role
if (!$has_course_management_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_course_management_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_course_management_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_course_management_access = true;
    }
}

if (!$has_course_management_access) {
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                handleCreateCategory($pdo);
                break;
            case 'update':
                handleUpdateCategory($pdo);
                break;
            case 'delete':
                handleDeleteCategory($pdo);
                break;
            case 'restore':
                handleRestoreCategory($pdo);
                break;
        }
    }
}

// Function to handle category creation
function handleCreateCategory($pdo) {
    global $error_message, $success_message;
    
    // Check permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'course_add_category')) {
        $error_message = "You don't have permission to add categories.";
        return;
    }
    
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        // Validate input
        if (empty($name)) {
            throw new Exception("Category name is required");
        }

        // Check if category name already exists
        $stmt = $pdo->prepare("SELECT id FROM course_category WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception("A category with this name already exists");
        }

        // Insert new category
        $stmt = $pdo->prepare("
            INSERT INTO course_category (name, description)
            VALUES (?, ?)
        ");
        $stmt->execute([$name, $description]);
        
        $categoryId = $pdo->lastInsertId();
        
        // Log audit entry
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->logEntry([
            'action_type' => 'CREATE',
            'action_description' => 'Created course category: ' . $name,
            'resource_type' => 'Category',
            'resource_id' => 'Category ID: ' . $categoryId,
            'resource_name' => $name,
            'outcome' => 'Success',
            'new_value' => 'Name: ' . $name . ', Description: ' . $description,
            'context' => [
                'category_name' => $name,
                'category_description' => $description,
                'category_id' => $categoryId
            ]
        ]);
        
        $success_message = "Category created successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Function to handle category update
function handleUpdateCategory($pdo) {
    global $error_message, $success_message;
    
    // Check permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'course_edit_category')) {
        $error_message = "You don't have permission to edit categories.";
        return;
    }
    
    try {
        $id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        // Validate input
        if (empty($name)) {
            throw new Exception("Category name is required");
        }

        // Check if name exists for other categories
        $stmt = $pdo->prepare("SELECT id FROM course_category WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            throw new Exception("A category with this name already exists");
        }

        // Get old values for audit log
        $stmt = $pdo->prepare("SELECT name, description FROM course_category WHERE id = ?");
        $stmt->execute([$id]);
        $oldCategory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update category
        $stmt = $pdo->prepare("
            UPDATE course_category 
            SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $id]);
        
        // Log audit entry
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->logEntry([
            'action_type' => 'UPDATE',
            'action_description' => 'Updated course category: ' . $name,
            'resource_type' => 'Category',
            'resource_id' => 'Category ID: ' . $id,
            'resource_name' => $name,
            'outcome' => 'Success',
            'old_value' => 'Name: ' . $oldCategory['name'] . ', Description: ' . $oldCategory['description'],
            'new_value' => 'Name: ' . $name . ', Description: ' . $description,
            'context' => [
                'category_id' => $id,
                'old_name' => $oldCategory['name'],
                'new_name' => $name,
                'old_description' => $oldCategory['description'],
                'new_description' => $description
            ]
        ]);
        
        $success_message = "Category updated successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Function to handle category deletion
function handleDeleteCategory($pdo) {
    global $error_message, $success_message;
    
    // Check permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'course_delete_category')) {
        $error_message = "You don't have permission to delete categories.";
        return;
    }
    
    try {
        $id = (int)$_POST['category_id'];
        $permanent = isset($_POST['permanent']) && $_POST['permanent'] === 'true';

        // Get category info for audit log
        $stmt = $pdo->prepare("SELECT name, description FROM course_category WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($permanent) {
            // Check if category is being used by any courses
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot permanently delete category: It is being used by one or more courses");
            }

            // Permanently delete category
            $stmt = $pdo->prepare("DELETE FROM course_category WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log audit entry
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->logEntry([
                'action_type' => 'DELETE',
                'action_description' => 'Permanently deleted course category: ' . $category['name'],
                'resource_type' => 'Category',
                'resource_id' => 'Category ID: ' . $id,
                'resource_name' => $category['name'],
                'outcome' => 'Success',
                'old_value' => 'Name: ' . $category['name'] . ', Description: ' . $category['description'],
                'context' => [
                    'category_id' => $id,
                    'category_name' => $category['name'],
                    'deletion_type' => 'permanent'
                ]
            ]);
            
            $success_message = "Category permanently deleted!";
        } else {
            // Soft delete category
            $stmt = $pdo->prepare("
                UPDATE course_category 
                SET status = 'deleted', deleted_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            // Log audit entry
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->logEntry([
                'action_type' => 'DELETE',
                'action_description' => 'Soft deleted course category: ' . $category['name'],
                'resource_type' => 'Category',
                'resource_id' => 'Category ID: ' . $id,
                'resource_name' => $category['name'],
                'outcome' => 'Success',
                'old_value' => 'Status: active',
                'new_value' => 'Status: deleted',
                'context' => [
                    'category_id' => $id,
                    'category_name' => $category['name'],
                    'deletion_type' => 'soft'
                ]
            ]);
            
            $success_message = "Category moved to trash!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Function to handle category restoration
function handleRestoreCategory($pdo) {
    global $error_message, $success_message;
    
    // Check permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'course_edit_category')) {
        $error_message = "You don't have permission to restore categories.";
        return;
    }
    
    try {
        $id = (int)$_POST['category_id'];
        
        // Get category info for audit log
        $stmt = $pdo->prepare("SELECT name, description FROM course_category WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            UPDATE course_category 
            SET status = 'active', restored_at = CURRENT_TIMESTAMP, deleted_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        // Log audit entry
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->logEntry([
            'action_type' => 'UPDATE',
            'action_description' => 'Restored course category: ' . $category['name'],
            'resource_type' => 'Category',
            'resource_id' => 'Category ID: ' . $id,
            'resource_name' => $category['name'],
            'outcome' => 'Success',
            'old_value' => 'Status: deleted',
            'new_value' => 'Status: active',
            'context' => [
                'category_id' => $id,
                'category_name' => $category['name'],
                'action' => 'restore'
            ]
        ]);
        
        $success_message = "Category restored successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get categories with status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$categories = [];
try {
    $where_conditions = [];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $pdo->prepare("
        SELECT * FROM course_category 
        $where_clause 
        ORDER BY 
            CASE 
                WHEN status = 'active' THEN 1 
                WHEN status = 'inactive' THEN 2 
                WHEN status = 'deleted' THEN 3 
            END, 
            name
    ");
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
}

// Fetch admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Get current user's permissions for navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Category Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .card-hover {
            transition: all 0.2s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .notification-bell:hover {
            transform: scale(1.1);
        }
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .notification-dropdown.show {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php echo $notificationSystem->renderNotificationAssets(); ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Admin Portal</span>
            </div>
            
            <!-- Admin Profile -->
            <?php require_once __DIR__ . '/includes/sidebar_profile.php'; ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Course Management permissions check
                    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions)): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php 
                    // User Management permissions check
                    $user_permissions = ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_permissions)): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php 
                    // Reports permissions check
                    $reports_permissions = [
                        'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 
                        'analytics_view_role_breakdown', 'analytics_view_activity_data', 
                        'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report', 'nav_security_warnings', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full text-gray-700 hover:bg-gray-100 focus:outline-none" :class="open ? 'bg-primary-50 text-primary-700 font-medium' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>
                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php 
                            // Usage Analytics permissions check
                            $analytics_permissions = ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $analytics_permissions)): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                     <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
       
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php 
                            // User Roles Report permissions check
                            $user_roles_permissions = ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_roles_permissions)): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
      
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Login Activity permissions check
                            $login_activity_permissions = ['login_activity_view_metrics', 'login_activity_view_report'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $login_activity_permissions)): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
        
                            Login Activity
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Security Warnings permissions check
                            $security_permissions = ['nav_security_warnings', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
        
                            Security Warnings
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Audit Trails permissions check
                            $audit_permissions = ['nav_audit_trails', 'audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
        
                            Audit Trails
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['nav_performance_logs', 'performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
      
                            System Performance Logs
                            </a>
                            <?php endif; ?>
                            <?php
                            $errorlogs = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                    $payment_permissions = ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $payment_permissions)): ?>
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Content Management permissions check
                    $content_permissions = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permissions)): ?>
                    <a href="contentmanagement/content_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>
                    <!-- Settings Menu - Available to all admins -->
                    <a href="admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                <!-- Push logout to bottom -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Course Category Management</h1>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Add this before the Categories Table -->
                <div class="mb-6 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <select id="statusFilter" onchange="updateFilters()" class="appearance-none bg-white border border-gray-300 rounded-lg pl-4 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Categories</option>
                                <option value="deleted" <?php echo $status_filter === 'deleted' ? 'selected' : ''; ?>>Deleted Categories</option>
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="relative">
                            <input type="text" 
                                id="searchInput" 
                                placeholder="Search categories..." 
                                value="<?php echo htmlspecialchars($search); ?>"
                                onkeyup="if(event.key === 'Enter') updateFilters()"
                                class="appearance-none bg-white border border-gray-300 rounded-lg pl-4 pr-10 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <button onclick="updateFilters()" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'course_add_category')): ?>
                    <button 
                        onclick="Alpine.store('modal').openCreate()"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add New Category
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Categories Table -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Course Categories</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($categories as $category): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_classes = [
                                                'active' => 'bg-green-100 text-green-800',
                                                'inactive' => 'bg-yellow-100 text-yellow-800',
                                                'deleted' => 'bg-red-100 text-red-800'
                                            ];
                                            $status_icons = [
                                                'active' => '✓',
                                                'inactive' => '⚠',
                                                'deleted' => '×'
                                            ];
                                            ?>
                                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $status_classes[$category['status']]; ?>">
                                                <?php echo $status_icons[$category['status']] . ' ' . ucfirst($category['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                            $date = $category['status'] === 'deleted' ? $category['deleted_at'] : $category['updated_at'];
                                            echo date('Y-m-d H:i', strtotime($date)); 
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-2">
                                                <?php if ($category['status'] !== 'deleted'): ?>
                                                <!-- Edit Button -->
                                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'course_edit_category')): ?>
                                                <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)"
                                                        class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-lg transition-colors"
                                                        title="Edit Category">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                                <!-- Delete Button -->
                                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'course_delete_category')): ?>
                                                <button onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', false)"
                                                        class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-lg transition-colors"
                                                        title="Move to Trash">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <!-- Restore Button -->
                                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'course_restore_category')): ?>
                                                <button onclick="restoreCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                                        class="text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-lg transition-colors"
                                                        title="Restore Category">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                                <!-- Delete Permanently Button -->
                                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'course_delete_category')): ?>
                                                <button onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', true)"
                                                        class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-lg transition-colors"
                                                        title="Delete Permanently">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($categories) === 0): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                            <p>No categories found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Category Modal -->
    <div id="createCategoryModal" 
        x-data="{ show: false }" 
        x-show="show" 
        x-on:open-create-modal.window="show = true"
        x-on:close-create-modal.window="show = false"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-20 flex items-center justify-center" 
        style="display: none;">
        <div class="relative mx-auto p-6 border w-[480px] shadow-lg rounded-lg bg-white" @click.away="show = false">
            <div class="absolute top-4 right-4">
                <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-gray-900 mb-5">Add New Category</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="name">
                            Category Name
                        </label>
                        <input type="text" name="name" id="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                            placeholder="Enter category name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="description">
                            Description
                        </label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                            placeholder="Enter category description"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="show = false"
                            class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-md bg-primary-600 text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                            Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" 
        x-data="{ show: false }" 
        x-show="show" 
        x-on:open-edit-modal.window="show = true"
        x-on:close-edit-modal.window="show = false"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-20 flex items-center justify-center" 
        style="display: none;">
        <div class="relative mx-auto p-6 border w-[480px] shadow-lg rounded-lg bg-white" @click.away="show = false">
            <div class="absolute top-4 right-4">
                <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-gray-900 mb-5">Edit Category</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_name">
                            Category Name
                        </label>
                        <input type="text" name="name" id="edit_name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                            placeholder="Enter category name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_description">
                            Description
                        </label>
                        <textarea name="description" id="edit_description" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                            placeholder="Enter category description"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="show = false"
                            class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-md bg-primary-600 text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                            Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-20">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete Category</h3>
                <p class="text-sm text-gray-500 mb-4">Are you sure you want to delete this category? This action cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <div class="flex justify-end">
                        <button type="button" onclick="document.getElementById('deleteCategoryModal').classList.add('hidden')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md mr-2 hover:bg-gray-600">Cancel</button>
                        <button type="submit"
                            class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialize Alpine.js store for modal management
        document.addEventListener('alpine:init', () => {
            Alpine.store('modal', {
                openCreate() {
                    window.dispatchEvent(new CustomEvent('open-create-modal'));
                },
                closeCreate() {
                    window.dispatchEvent(new CustomEvent('close-create-modal'));
                },
                openEdit() {
                    window.dispatchEvent(new CustomEvent('open-edit-modal'));
                },
                closeEdit() {
                    window.dispatchEvent(new CustomEvent('close-edit-modal'));
                }
            });
        });

        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            window.location.href = `?status=${status}&search=${encodeURIComponent(search)}`;
        }

        function editCategory(category) {
            // Set form values
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description;
            
            // Open modal
            Alpine.store('modal').openEdit();
        }

        function deleteCategory(id, name, permanent = false) {
            const action = permanent ? 'permanently delete' : 'move';
            const location = permanent ? '' : ' to the trash';
            
            Swal.fire({
                title: `Are you sure?`,
                html: `Do you want to ${action} the category "<strong>${name}</strong>"${location}?<br>
                    ${permanent ? '<span class="text-red-600">This action cannot be undone!</span>' : ''}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: permanent ? 'Yes, delete permanently!' : 'Yes, move to trash!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="${id}">
                        <input type="hidden" name="permanent" value="${permanent}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function restoreCategory(id, name) {
            Swal.fire({
                title: 'Restore Category?',
                html: `Do you want to restore the category "<strong>${name}</strong>"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, restore it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="category_id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Notification Bell Functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Real-time clock for Philippines timezone
        function updateRealTimeClock() {
            const now = new Date();
            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
            
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            };
            
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
            }
            
            if (dateElement) {
                dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
            }
        }

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
        });
    </script>
</body>
</html>
