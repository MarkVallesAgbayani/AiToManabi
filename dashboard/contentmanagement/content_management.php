<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
require_once '../../config/database.php';
require_once '../../includes/rbac_helper.php';
require_once '../../includes/admin_notifications.php';
require_once '../audit_logger.php';
require_once '../includes/admin_profile_functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Check if user has permission to manage content or is admin
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'])) {
    header('Location: ../../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_announcement':
                // Check permission to edit content
                if (!hasPermission($pdo, $_SESSION['user_id'], 'content_management_edit')) {
                    $error_message = "You don't have permission to edit content.";
                    break;
                }
                
                $data = [
                    'content' => trim($_POST['content']),
                    'background_color' => $_POST['background_color'],
                    'text_color' => $_POST['text_color'],
                    'button_text' => trim($_POST['button_text']),
                    'button_url' => trim($_POST['button_url']),
                    'button_color' => $_POST['button_color'],
                    'button_icon' => $_POST['button_icon'],
                    'discount_value' => trim($_POST['discount_value']),
                    'discount_type' => $_POST['discount_type'],
                    'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                try {
                    // Check if thepor's an existing announcement
                    $stmt = $pdo->prepare("SELECT id FROM announcement_banner LIMIT 1");
                    $stmt->execute();
                    $existing = $stmt->fetch();

                    if ($existing) {
                        // Update existing announcement
                        $sql = "UPDATE announcement_banner SET 
                            content = :content,
                            background_color = :background_color,
                            text_color = :text_color,
                            button_text = :button_text,
                            button_url = :button_url,
                            button_color = :button_color,
                            button_icon = :button_icon,
                            discount_value = :discount_value,
                            discount_type = :discount_type,
                            start_date = :start_date,
                            end_date = :end_date,
                            is_published = :is_published
                            WHERE id = " . $existing['id'];
                    } else {
                        // Create new announcement
                        $sql = "INSERT INTO announcement_banner 
                            (content, background_color, text_color, button_text, button_url, 
                            button_color, button_icon, discount_value, discount_type, 
                            start_date, end_date, is_published)
                            VALUES 
                            (:content, :background_color, :text_color, :button_text, :button_url,
                            :button_color, :button_icon, :discount_value, :discount_type,
                            :start_date, :end_date, :is_published)";
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);

                    // Log audit entry for announcement update
                    $auditLogger = new AuditLogger($pdo);
                    $auditLogger->logEntry([
                        'action_type' => 'UPDATE',
                        'action_description' => 'Updated announcement banner',
                        'resource_type' => 'Content',
                        'resource_id' => 'Announcement Banner',
                        'resource_name' => 'Announcement Banner',
                        'outcome' => 'Success',
                        'new_value' => 'Content: ' . substr($data['content'], 0, 100) . (strlen($data['content']) > 100 ? '...' : '') . ', Published: ' . ($data['is_published'] ? 'Yes' : 'No'),
                        'context' => [
                            'content_length' => strlen($data['content']),
                            'background_color' => $data['background_color'],
                            'text_color' => $data['text_color'],
                            'button_text' => $data['button_text'],
                            'button_url' => $data['button_url'],
                            'discount_value' => $data['discount_value'],
                            'discount_type' => $data['discount_type'],
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'],
                            'is_published' => $data['is_published'],
                            'action_type' => $existing ? 'update_existing' : 'create_new'
                        ]
                    ]);

                    $success_message = "Announcement updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating announcement: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch current announcement
$stmt = $pdo->prepare("SELECT * FROM announcement_banner ORDER BY updated_at DESC LIMIT 1");
$stmt->execute();
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch admin information
$admin_profile = getAdminProfile($pdo, $_SESSION['user_id']);

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Log audit entry for accessing content management
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed content management dashboard',
    'resource_type' => 'Dashboard',
    'resource_id' => 'Content Management',
    'resource_name' => 'Content Management Dashboard',
    'outcome' => 'Success',
    'context' => [
        'has_announcement' => $announcement ? 'Yes' : 'No',
        'announcement_published' => $announcement && $announcement['is_published'] ? 'Yes' : 'No',
        'user_permissions' => 'content_management_view'
    ]
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Announcements</title>
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
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
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
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Admin Portal</span>
            </div>
            
<!-- Admin Profile -->
<?php 
$display_name = getAdminDisplayName($admin_profile);
$picture = getAdminProfilePicture($admin_profile);
?>
<div class="p-4 border-b flex items-center space-x-3">
<?php if ($picture['has_image']): ?>
    <img src="../../<?php echo htmlspecialchars($picture['image_path']); ?>" 
         alt="Profile Picture" 
         class="w-12 h-12 rounded-full object-cover shadow-md sidebar-profile-picture"
         onerror="console.error('Failed to load image:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder" style="display: none;">
        <?php echo htmlspecialchars($picture['initial']); ?>
    </div>
<?php else: ?>
    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder">
        <?php echo htmlspecialchars($picture['initial']); ?>
    </div>
<?php endif; ?>
    <div class="flex-1 min-w-0">
        <div class="font-medium sidebar-display-name truncate"><?php echo htmlspecialchars($display_name); ?></div>
        <div class="text-sm text-gray-500 sidebar-role">Administrator</div>
    </div>
</div>


            <!-- Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['dashboard_view_course_completion', 'dashboard_view_metrics', 'dashboard_view_sales_report', 'dashboard_view_user_retention'])): ?>
                    <a href="../admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'])): ?>
                    <a href="../course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'])): ?>
                    <a href="../users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report',
                        'audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 
                        'audit_view_details', 'performance_logs_view', 'performance_logs_export', 
                        'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times',
                        'error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 
                        'error_logs_view_categories', 'error_logs_search_filter'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full text-gray-700 hover:bg-gray-100 focus:outline-none" :class="open ? 'bg-primary-50 text-primary-700 font-medium' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>    
                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'])): ?>
                            <a href="../usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
      
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'])): ?>
                            <a href="../user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
        
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view', 'audit_trails_view', 'security_warnings_view'])): ?>
                            <a href="../login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
        
                            Login Activity
                            </a>
                            <?php endif; ?>
                            <?php
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'])): ?>
                            <a href="../security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
         
                            Security Warnings
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['audit_trails_view', 'login_activity_view', 'security_warnings_view'])): ?>
                            <a href="../audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
                               
                             Audit Trails
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['performance_logs_view', 'performance_logs_export', 'performance_logs_analyze'])): ?>
                            <a href="../performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
        
                            System Performance Logs
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['error_logs_view', 'error_logs_export', 'error_logs_analyze'])): ?>
                            <a href="../error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
       
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_view_history')): ?>
                    <a href="../payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>
                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <!-- Settings Menu - Available to all admins -->
                    <a href="../admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                <!-- Push logout to bottom -->
                <div class="mt-auto pt-4">
                    <a href="../../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Content Management</h1>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('Content Management Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <?php if (isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Content Management Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_manage_announcement')): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden card-hover cursor-pointer"
                         onclick="window.location.href='announcement_banner.php'"
                         role="button"
                         tabindex="0"
                         @keydown.enter="window.location.href='announcement_banner.php'"
                         @keydown.space.prevent="window.location.href='announcement_banner.php'">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Announcement Banner</h3>
                                        <p class="text-sm text-gray-500">
                                            <?php if ($announcement && $announcement['is_published']): ?>
                                                Active announcement: <?php echo htmlspecialchars(substr($announcement['content'], 0, 50)) . (strlen($announcement['content']) > 50 ? '...' : ''); ?>
                                            <?php else: ?>
                                                No active announcement
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Terms & Conditions Card -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_manage_terms')): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden card-hover cursor-pointer"
                         onclick="window.location.href='edit_terms.php'"
                         role="button"
                         tabindex="0"
                         @keydown.enter="window.location.href='edit_terms.php'"
                         @keydown.space.prevent="window.location.href='edit_terms.php'">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Terms & Conditions</h3>
                                        <p class="text-sm text-gray-500">Manage and edit your website's terms and conditions</p>
                                    </div>
                                </div>
                                <div class="text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Privacy Policy Card -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_manage_privacy')): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden card-hover cursor-pointer"
                         onclick="window.location.href='edit_privacy.php'"
                         role="button"
                         tabindex="0"
                         @keydown.enter="window.location.href='edit_privacy.php'"
                         @keydown.space.prevent="window.location.href='edit_privacy.php'">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Privacy Policy</h3>
                                        <p class="text-sm text-gray-500">Manage and edit your website's privacy policy</p>
                                    </div>
                                </div>
                                <div class="text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- No Permission Message -->
                <?php if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'])): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Access Restricted</h3>
                    <p class="text-gray-600">You don't have permission to manage any content. Please contact your administrator to request access to content management features.</p>
                </div>
                <?php endif; ?>

                <script>
                    // Add keyboard navigation for the cards
                    document.addEventListener('DOMContentLoaded', function() {
                        const cards = document.querySelectorAll('[role="button"]');
                        cards.forEach(card => {
                            card.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    this.click();
                                }
                            });
                        });
                    });
                    
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
                </script>

                <!-- Modal -->
                <div x-show="showModal" 
                     class="fixed inset-0 z-50 overflow-y-auto"
                     style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full"
                             @click.away="showModal = false">
                            <form method="POST" x-data="{
                                content: '<?php echo addslashes($announcement['content'] ?? ''); ?>',
                                bgColor: '<?php echo $announcement['background_color'] ?? '#FFFFFF'; ?>',
                                textColor: '<?php echo $announcement['text_color'] ?? '#1A1A1A'; ?>',
                                buttonText: '<?php echo addslashes($announcement['button_text'] ?? ''); ?>',
                                buttonUrl: '<?php echo addslashes($announcement['button_url'] ?? ''); ?>',
                                buttonColor: '<?php echo $announcement['button_color'] ?? '#0EA5E9'; ?>',
                                buttonIcon: '<?php echo $announcement['button_icon'] ?? ''; ?>',
                                discountValue: '<?php echo addslashes($announcement['discount_value'] ?? ''); ?>',
                                discountType: '<?php echo $announcement['discount_type'] ?? 'percentage'; ?>',
                                startDate: '<?php echo $announcement['start_date'] ?? ''; ?>',
                                endDate: '<?php echo $announcement['end_date'] ?? ''; ?>',
                                isPublished: <?php echo $announcement['is_published'] ?? 0; ?>,
                                charCount: function() { return this.content.length; },
                                maxChars: 200
                            }">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div class="w-full">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                                Edit Announcement
                                            </h3>

                                            <!-- Live Preview -->
                                            <div class="mb-6 p-4 rounded-lg border"
                                                 :style="`background: linear-gradient(135deg, ${bgColor}1A, ${bgColor}00); color: ${textColor};`">
                                                <div class="flex items-center">
                                                    <span x-text="content || 'Your announcement text here...'"></span>
                                                    <template x-if="buttonText && buttonUrl">
                                                        <a href="#" class="ml-3 px-3 py-1 rounded-full text-white text-sm"
                                                           :style="`background-color: ${buttonColor}`">
                                                            <template x-if="buttonIcon">
                                                                <i :class="buttonIcon" class="mr-1"></i>
                                                            </template>
                                                            <span x-text="buttonText"></span>
                                                        </a>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Content -->
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Announcement Text
                                                    <span class="text-xs text-gray-500 ml-1" x-text="`${charCount()}/${maxChars}`"></span>
                                                </label>
                                                <textarea name="content" 
                                                          x-model="content"
                                                          @input="if(charCount() > maxChars) content = content.substring(0, maxChars)"
                                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                          rows="3"
                                                          required></textarea>
                                            </div>

                                            <!-- Colors -->
                                            <div class="grid grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Background Color
                                                    </label>
                                                    <input type="color" 
                                                           name="background_color" 
                                                           x-model="bgColor"
                                                           class="h-10 w-full rounded-md border-gray-300">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Text Color
                                                    </label>
                                                    <input type="color" 
                                                           name="text_color" 
                                                           x-model="textColor"
                                                           class="h-10 w-full rounded-md border-gray-300">
                                                </div>
                                            </div>

                                            <!-- Button Settings -->
                                            <div class="grid grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Button Text
                                                    </label>
                                                    <input type="text" 
                                                           name="button_text" 
                                                           x-model="buttonText"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Button URL
                                                    </label>
                                                    <input type="url" 
                                                           name="button_url" 
                                                           x-model="buttonUrl"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Button Color
                                                    </label>
                                                    <input type="color" 
                                                           name="button_color" 
                                                           x-model="buttonColor"
                                                           class="h-10 w-full rounded-md border-gray-300">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Button Icon (FontAwesome class)
                                                    </label>
                                                    <input type="text" 
                                                           name="button_icon" 
                                                           x-model="buttonIcon"
                                                           placeholder="fas fa-tag"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                            </div>

                                            <!-- Discount Settings -->
                                            <div class="grid grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Discount Value
                                                    </label>
                                                    <input type="text" 
                                                           name="discount_value" 
                                                           x-model="discountValue"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Discount Type
                                                    </label>
                                                    <select name="discount_type" 
                                                            x-model="discountType"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                        <option value="percentage">Percentage</option>
                                                        <option value="fixed">Fixed Amount</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Schedule -->
                                            <div class="grid grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Start Date (optional)
                                                    </label>
                                                    <input type="datetime-local" 
                                                           name="start_date" 
                                                           x-model="startDate"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        End Date (optional)
                                                    </label>
                                                    <input type="datetime-local" 
                                                           name="end_date" 
                                                           x-model="endDate"
                                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                            </div>

                                            <!-- Publishing -->
                                            <div class="flex items-center mb-4">
                                                <input type="checkbox" 
                                                       name="is_published" 
                                                       x-model="isPublished"
                                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                                <label class="ml-2 block text-sm text-gray-900">
                                                    Publish Announcement
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <input type="hidden" name="action" value="update_announcement">
                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_management_edit')): ?>
                                    <button type="submit" 
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Save Changes
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            @click="showModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions Modal -->
                <div x-show="showModal" 
                     class="fixed inset-0 z-50 overflow-y-auto"
                     style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full"
                             @click.away="showModal = false">
                            <form method="POST" action="/admin/edit_terms.php" x-data="{ termsAccepted: false }" @submit.prevent="termsAccepted ? $el.submit() : $event.preventDefault()">
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div class="w-full">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                                Edit Terms & Conditions
                                            </h3>

                                            <!-- Terms & Conditions Content -->
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Terms & Conditions Content
                                                </label>
                                                <textarea name="terms_content" 
                                                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                          rows="10"
                                                          required></textarea>
                                            </div>

                                            <!-- Terms Acceptance -->
                                            <div class="flex items-start mb-4">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" 
                                                           id="terms_acceptance"
                                                           x-model="termsAccepted"
                                                           class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="terms_acceptance" class="font-medium text-gray-700">
                                                        I have read and agree to the 
                                                        <a href="/terms" target="_blank" class="text-primary-600 hover:text-primary-500">
                                                            Terms & Conditions
                                                        </a>
                                                    </label>
                                                    <p class="text-red-600 mt-1" x-show="!termsAccepted">
                                                        You must accept the Terms & Conditions to proceed.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_management_edit')): ?>
                                    <button type="submit" 
                                            :class="{'opacity-50 cursor-not-allowed': !termsAccepted, 'hover:bg-primary-700': termsAccepted}"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Save Changes
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            @click="showModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <style>
        /* Toggle Switch Styles */
        input:checked ~ .dot {
            transform: translateX(100%);
            background-color: #0ea5e9;
        }
        input:checked ~ div:first-of-type {
            background-color: #bae6fd;
        }
        .dot {
            transition: all 0.3s ease-in-out;
        }
    </style>
    
    <script>
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
    <script>
// Listen for profile updates from admin settings page
window.addEventListener('storage', function(e) {
    if (e.key === 'admin_profile_updated') {
        location.reload();
    }
});

// Function to update profile display in real-time
function updateProfileDisplay(displayName, profilePicturePath) {
    // Update display name
    const displayNameEl = document.querySelector('.sidebar-display-name');
    if (displayNameEl) {
        displayNameEl.textContent = displayName;
    }
    
    // Update profile picture
    if (profilePicturePath) {
        const pictureEl = document.querySelector('.sidebar-profile-picture');
        const placeholderEl = document.querySelector('.sidebar-profile-placeholder');
        
        if (pictureEl && placeholderEl) {
            pictureEl.src = '../../' + profilePicturePath;
            pictureEl.style.display = 'block';
            placeholderEl.style.display = 'none';
        }
    }
}
</script>

</body>
</html>
