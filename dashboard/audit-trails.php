<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'audit_database_functions.php';
require_once 'ip_address_utils.php';
require_once '../includes/admin_notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission to view audit trails
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'])) {
    header('Location: ../index.php');
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$outcome_filter = isset($_GET['outcome_filter']) ? $_GET['outcome_filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Export functionality
if (isset($_GET['export'])) {
    // Check export permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'audit_export_pdf')) {
        header('Location: ../index.php');
        exit();
    }
    
    $export_type = $_GET['export'];
    // Use centralized report generator for PDF export
    require_once 'reports.php';
    $reportGenerator = new ReportGenerator($pdo);
    $filters = [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'user_filter' => $user_filter,
        'action_filter' => $action_filter,
        'outcome_filter' => $outcome_filter,
        'search' => $search
    ];
    $reportGenerator->generateAuditTrailsReport($filters, 'pdf');
    exit();
}

// All audit data functions are now defined in audit_database_functions.php

// Get audit statistics (now using real data)
function getAuditStatistics($pdo) {
    return getRealAuditStatistics($pdo);
}

// Export function
function exportAuditData($pdo, $type, $date_from, $date_to, $user_filter, $action_filter, $outcome_filter, $search) {}

// Get data
$filters = [
    'date_from' => $date_from,
    'date_to' => $date_to,
    'user_filter' => $user_filter,
    'action_filter' => $action_filter,
    'outcome_filter' => $outcome_filter,
    'search' => $search
];
$auditData = getRealAuditData($pdo, $offset, $limit, $filters);
$totalRecords = getTotalAuditRecords($pdo, $filters);
$totalPages = ceil($totalRecords / $limit);
$statistics = getAuditStatistics($pdo);

// Get recent activities for notifications
$recentFilters = ['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')];
$recentActivities = getRealAuditData($pdo, 0, 5, $recentFilters);

// Get admin info for header
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trails - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/audit-trails.css" rel="stylesheet">
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
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['dashboard_view_course_completion', 'dashboard_view_metrics', 'dashboard_view_sales_report', 'dashboard_view_user_retention'])): ?>
                    <a href="admin.php?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'])): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'])): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <div x-data="{ open: true }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full bg-primary-50 text-primary-700 font-medium focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'])): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                           <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'])): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                       <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
     
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view', 'login_activity_view_metrics', 'login_activity_view_report', 'login_activity_export_pdf'])): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
    
                            Login Activity
                            </a>
                            <?php endif; ?>

                            <?php 
                            // Security Warnings permissions check
                            $security_permissions = ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
    
                            Security Warnings
                            </a>
                            <?php endif; ?>

                            <?php 
                            // Audit Trails permissions check
                            $audit_permissions = ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
    
                            Audit Trails
                            </a>
                            <?php endif; ?>

                             <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
    
                            System Performance Logs
                            </a>
                            <?php endif; ?>

                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
    
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    // Payment History permissions check
                    $payment_permissions = ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $payment_permissions)): ?>
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins-icon lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>

                   <?php
                    $content_permission = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permission)): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                    <div class="flex items-center gap-4">
                        <!-- Mobile Menu Toggle -->
                        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <h1 class="text-2xl font-semibold text-gray-900">Audit Trails</h1>
                    </div>
                    
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
                <!-- Export Success Message -->
                <?php if (isset($_GET['export_success'])): ?>
                <div class="alert alert-success mb-6">
                    📊 Export completed successfully! Your file has been downloaded.
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'audit_view_metrics')): ?>
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-value text-blue-600"><?php echo number_format($statistics['total_actions']); ?></div>
                        <div class="stat-label">Total Actions</div>
                        <div class="stat-trend trend-up">↗ +12% this month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value text-green-600"><?php echo $statistics['actions_today']; ?></div>
                        <div class="stat-label">Actions Today</div>
                        <div class="stat-trend trend-up">↗ +5 from yesterday</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value text-red-600"><?php echo $statistics['failed_actions']; ?></div>
                        <div class="stat-label">Failed Actions</div>
                        <div class="stat-trend trend-down">↘ -3% this week</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value text-purple-600"><?php echo $statistics['unique_users']; ?></div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-trend trend-up">↗ +7 new users</div>
                    </div>
                </div>
                <?php endif; ?>

<!-- Filter Controls -->
<?php if (hasPermission($pdo, $_SESSION['user_id'], 'audit_search_filter')): ?>
<div class="filter-container">
    <form method="GET" class="space-y-4">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Filters & Search</h3>
            <!-- Export Buttons - Right corner -->
            <div class="export-buttons">
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'audit_export_pdf')): ?>
                <button type="button" onclick="exportData('pdf')" class="export-btn export-pdf inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                        <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                        <path d="M10 9H8"/>
                        <path d="M16 13H8"/>
                        <path d="M16 17H8"/>
                    </svg>
                    Export PDF
                </button>
                <?php endif; ?>
            </div>
        </div>

                        
                        <div class="flex gap-4 items-end">
    <div class="search-container">
        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="search" placeholder="Search actions, users, resources..." 
               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
    </div>
    
    <!-- Search Button -->
    <button type="submit" class="flex items-center px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        Search
    </button>
    
    <!-- Reset Button -->
    <a href="audit-trails.php" class="flex items-center px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-9-9v3"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 3v6h-6"/>
        </svg>
        Reset
    </a>
</div>


                        <div class="filter-grid">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                                <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="filter-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="filter-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                                <input type="text" name="user_filter" placeholder="Username or ID" 
                                       value="<?php echo htmlspecialchars($user_filter); ?>" class="filter-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Action Type</label>
                                <select name="action_filter" class="filter-select">
                                    <option value="">All Actions</option>
                                    <option value="CREATE" <?php echo $action_filter === 'CREATE' ? 'selected' : ''; ?>>Create</option>
                                    <option value="READ" <?php echo $action_filter === 'READ' ? 'selected' : ''; ?>>Read</option>
                                    <option value="UPDATE" <?php echo $action_filter === 'UPDATE' ? 'selected' : ''; ?>>Update</option>
                                    <option value="DELETE" <?php echo $action_filter === 'DELETE' ? 'selected' : ''; ?>>Delete</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Outcome</label>
                                <select name="outcome_filter" class="filter-select">
                                    <option value="">All Outcomes</option>
                                    <option value="Success" <?php echo $outcome_filter === 'Success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="Failed" <?php echo $outcome_filter === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Results Per Page</label>
                                <select name="limit" class="filter-select" onchange="this.form.submit()">
                                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10 rows</option>
                                    <option value="20" <?php echo $limit === 20 ? 'selected' : ''; ?>>20 rows</option>
                                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50 rows</option>
                                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 rows</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Audit Trails Table -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'audit_view_details')): ?>
                <div class="audit-table-container">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Timestamp (UTC)</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Resource</th>
                                <th>IP Address</th>
                                <th>Outcome</th>
                                <th>Changes</th>
                                <th>Device</th>
                                <th>Location</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditData as $record): ?>
                            <tr>
                                <td>
                                    <div class="text-sm font-mono text-gray-900">
                                        <?php echo date('Y-m-d H:i:s', strtotime($record['timestamp'])); ?> UTC
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php 
                                        $timeAgo = time() - strtotime($record['timestamp']);
                                        if ($timeAgo < 60) echo "Just now";
                                        elseif ($timeAgo < 3600) echo floor($timeAgo/60) . "m ago";
                                        elseif ($timeAgo < 86400) echo floor($timeAgo/3600) . "h ago";
                                        else echo floor($timeAgo/86400) . "d ago";
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($record['username']); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($record['user_id']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-badge action-<?php echo strtolower($record['action_type']); ?>">
                                        <?php
                                        $icons = [
                                            'CREATE' => '🟢',
                                            'READ' => '🔵',
                                            'UPDATE' => '🟡',
                                            'DELETE' => '🔴',
                                            'DOWNLOAD' => '📄',
                                            'EXPORT' => '📊'
                                        ];
                                        echo $icons[$record['action_type']] ?? '⚪';
                                        ?>
                                        <?php echo $record['action_type']; ?>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($record['action_description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($record['resource_type']); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($record['resource_id']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <?php 
                                        $ip = $record['ip_address'];
                                        $ipInfo = IPAddressUtils::getIPInfo($ip);
                                        ?>
                                        <div class="font-mono text-sm text-gray-900 flex items-center gap-2">
                                            <span class="<?php echo htmlspecialchars($ipInfo['class'] ?? 'text-gray-600'); ?>"><?php echo htmlspecialchars($ipInfo['icon'] ?? '❓'); ?></span>
                                            <span><?php echo htmlspecialchars($ipInfo['formatted'] ?? $ip ?? 'Unknown IP'); ?></span>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($ipInfo['version'] ?? 'Unknown'); ?>
                                            </span>
                                            <span class="text-xs px-2 py-1 rounded-full <?php 
                                                $ipType = $ipInfo['type'] ?? 'Unknown';
                                                echo $ipType === 'Public' ? 'bg-green-100 text-green-800' : 
                                                    ($ipType === 'Private' ? 'bg-orange-100 text-orange-800' : 
                                                    ($ipType === 'Loopback' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'));
                                            ?>">
                                                <?php echo htmlspecialchars($ipType); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (isset($record['location']) && $record['location'] !== 'Unknown Location'): ?>
                                            <span class="text-xs text-gray-500 mt-1">📍 <?php echo htmlspecialchars($record['location']); ?></span>
                                        <?php endif; ?>
                                        
                                        <span class="text-xs text-gray-400 mt-1" title="<?php echo htmlspecialchars($ipInfo['description'] ?? 'Unknown IP type'); ?>">
                                            <?php echo htmlspecialchars($ipInfo['description'] ?? 'Unknown IP type'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="outcome-badge outcome-<?php echo strtolower($record['outcome']); ?>">
                                        <?php echo $record['outcome'] === 'Success' ? '✅' : '❌'; ?>
                                        <?php echo $record['outcome']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['old_value'] || $record['new_value']): ?>
                                        <div class="text-xs">
                                            <?php if ($record['old_value']): ?>
                                                <div class="text-red-600">- <?php echo htmlspecialchars($record['old_value']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($record['new_value']): ?>
                                                <div class="text-green-600">+ <?php echo htmlspecialchars($record['new_value']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <div class="text-xs text-gray-900">
                                            <?php 
                                            $deviceInfo = htmlspecialchars($record['device_info'] ?? 'Unknown Device');
                                            // Add device type icon
                                            $deviceIcon = '💻'; // default desktop
                                            if (isset($record['device_type'])) {
                                                switch ($record['device_type']) {
                                                    case 'Mobile': $deviceIcon = '📱'; break;
                                                    case 'Tablet': $deviceIcon = '📱'; break;
                                                    default: $deviceIcon = '💻'; break;
                                                }
                                            } elseif (stripos($deviceInfo, 'mobile') !== false || stripos($deviceInfo, 'iphone') !== false) {
                                                $deviceIcon = '📱';
                                            } elseif (stripos($deviceInfo, 'tablet') !== false || stripos($deviceInfo, 'ipad') !== false) {
                                                $deviceIcon = '📱';
                                            }
                                            echo $deviceIcon . ' ' . $deviceInfo;
                                            ?>
                                        </div>
                                        <?php if (isset($record['browser_name']) && isset($record['operating_system'])): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                🌐 <?php echo htmlspecialchars($record['browser_name']); ?> • 
                                                💿 <?php echo htmlspecialchars($record['operating_system']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <div class="text-xs text-gray-900">
                                            <?php 
                                            $location = $record['location'];
                                            
                                            // If no location in database, get it from IP
                                            if (empty($location)) {
                                                $locationInfo = IPAddressUtils::getLocationInfo($record['ip_address']);
                                                if ($locationInfo['city'] && $locationInfo['country']) {
                                                    $location = $locationInfo['city'] . ', ' . $locationInfo['country'];
                                                } elseif ($locationInfo['country']) {
                                                    $location = $locationInfo['country'];
                                                } else {
                                                    $location = 'Unknown Location';
                                                }
                                            }
                                            
                                            $location = htmlspecialchars($location);
                                            
                                            if ($location === 'Unknown Location') {
                                                echo '🌍 ' . $location;
                                            } elseif (strpos($location, 'Local') !== false || strpos($location, 'Localhost') !== false) {
                                                echo '🏠 ' . $location;
                                            } else {
                                                echo '📍 ' . $location;
                                            }
                                            ?>
                                        </div>
                                        <?php 
                                        // Show detailed location info if available from database or API
                                        $cityFromDB = $record['location_city'] ?? null;
                                        $countryFromDB = $record['location_country'] ?? null;
                                        
                                        if (empty($cityFromDB) && empty($countryFromDB)) {
                                            $locationInfo = IPAddressUtils::getLocationInfo($record['ip_address']);
                                            $cityFromDB = $locationInfo['city'];
                                            $countryFromDB = $locationInfo['country'];
                                        }
                                        
                                        if ($cityFromDB && $countryFromDB): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                🏙️ <?php echo htmlspecialchars($cityFromDB); ?> • 
                                                🌏 <?php echo htmlspecialchars($countryFromDB); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button onclick="showAuditDetails(<?php echo $record['id']; ?>)" 
                                            class="text-primary-600 hover:text-primary-800 text-sm font-medium">
                                        👁️ View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of <span class="font-medium"><?php echo number_format($totalRecords); ?></span> entries
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Audit Details Modal -->
    <div id="auditDetailsModal" class="audit-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Audit Trail Details</h3>
                <button class="modal-close" onclick="closeAuditDetails()">&times;</button>
            </div>
            <div id="auditDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Real-time Activity Indicator -->
    <div id="activityIndicator" class="activity-indicator">
        <div class="activity-pulse"></div>
        <span class="text-sm font-medium text-gray-700">New activity detected</span>
    </div>

    <script>
        // Export data function
        function exportData(format) {
            const currentParams = new URLSearchParams(window.location.search);
            currentParams.set('export', format);
            
            // Show loading state
            const exportBtn = event.target.closest('.export-btn');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Exporting...';
            exportBtn.disabled = true;
            
            // Create temporary link for download
            const link = document.createElement('a');
            link.href = `audit-trails.php?${currentParams.toString()}`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button after delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
                showNotification(`${format.toUpperCase()} export completed`, 'success', 3000);
            }, 2000);
        }

        // Simple notification function
        function showNotification(message, type, duration) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, duration);
        }

        // Mobile Sidebar Toggle
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.bg-white.w-64');
            sidebar.classList.toggle('mobile-open');
        }
        
        // Notification System
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Audit Details Modal
        function showAuditDetails(id) {
            const modal = document.getElementById('auditDetailsModal');
            const content = document.getElementById('auditDetailsContent');
            
            // Show loading state
            content.innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                    <p class="text-gray-600 mt-2">Loading audit details...</p>
                </div>
            `;
            modal.classList.add('show');
            
            // Fetch real audit details
            fetch(`get_audit_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const record = data.record;
                        const detailsHTML = `
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Audit ID</label>
                                        <p class="font-mono text-gray-900">${record.id}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Session ID</label>
                                        <p class="font-mono text-gray-900">${record.session_id || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Request Method</label>
                                        <p class="font-mono text-gray-900">${record.request_method || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Response Code</label>
                                        <p class="font-mono text-gray-900">${record.response_code || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">Response Time</label>
                                        <p class="font-mono text-gray-900">${record.response_time_ms ? record.response_time_ms + 'ms' : 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-500">User Role</label>
                                        <p class="font-mono text-gray-900">${record.user_role}</p>
                                    </div>
                                </div>
                                ${record.request_url ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Request URL</label>
                                    <p class="font-mono text-sm bg-gray-100 p-2 rounded break-all">${record.request_url}</p>
                                </div>` : ''}
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Full Device Information</label>
                                    <p class="text-sm bg-gray-100 p-2 rounded">${record.device_info}</p>
                                </div>
                                ${record.error_message ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Error Message</label>
                                    <p class="text-sm bg-red-50 text-red-700 p-2 rounded">${record.error_message}</p>
                                </div>` : ''}
                                ${record.additional_context ? `
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Additional Context</label>
                                    <pre class="text-xs bg-gray-100 p-2 rounded overflow-x-auto">${JSON.stringify(JSON.parse(record.additional_context), null, 2)}</pre>
                                </div>` : ''}
                            </div>
                        `;
                        content.innerHTML = detailsHTML;
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-8">
                                <p class="text-red-600">❌ Failed to load audit details</p>
                                <p class="text-gray-600 text-sm mt-2">${data.message || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-600">❌ Error loading audit details</p>
                            <p class="text-gray-600 text-sm mt-2">Network error or server unavailable</p>
                        </div>
                    `;
                });
        }
        
        function closeAuditDetails() {
            const modal = document.getElementById('auditDetailsModal');
            modal.classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('auditDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAuditDetails();
            }
        });
        
        // Real-time Activity Simulation
        function showActivityIndicator() {
            const indicator = document.getElementById('activityIndicator');
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 3000);
        }
        
        // Simulate new activity every 30 seconds
        setInterval(() => {
            if (Math.random() > 0.7) { // 30% chance
                showActivityIndicator();
            }
        }, 30000);
        
        // Add fade-in animation to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.audit-table tbody tr');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.classList.add('fade-in');
                }, index * 50);
            });
        });
        
        // Auto-refresh functionality
        let autoRefreshInterval = null;
        let lastRefreshTime = Date.now();
        
        function startAutoRefresh() {
            if (autoRefreshInterval) return; // Already running
            
            autoRefreshInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    refreshAuditData();
                }
            }, 30000); // Refresh every 30 seconds
            
            console.log('Auto-refresh started');
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                console.log('Auto-refresh stopped');
            }
        }
        
        function refreshAuditData() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Add timestamp to prevent caching
            urlParams.set('_t', Date.now());
            
            // Show refresh indicator
            showActivityIndicator();
            
            // Refresh statistics cards
            fetch(`get_audit_stats.php?${urlParams.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatisticsCards(data.stats);
                    }
                })
                .catch(error => console.error('Failed to refresh statistics:', error));
                
            // Update notification count
            updateNotificationCount();
            
            lastRefreshTime = Date.now();
        }
        
        function updateStatisticsCards(stats) {
            // Update each statistic card
            const totalActions = document.querySelector('.stat-card .stat-value');
            if (totalActions) {
                totalActions.textContent = stats.total_actions.toLocaleString();
            }
            
            // You can add more specific selectors for other stats
            console.log('Statistics updated:', stats);
        }
        
        function updateNotificationCount() {
            fetch('get_recent_activities.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const countElement = document.getElementById('notificationCount');
                        if (countElement) {
                            countElement.textContent = data.count;
                        }
                    }
                })
                .catch(error => console.error('Failed to update notifications:', error));
        }
        
        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Stop auto-refresh when page is hidden
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        document.querySelector('.search-input').focus();
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.reload();
                        break;
                    case 'e':
                        e.preventDefault();
                        document.querySelector('.export-pdf').click();
                        break;
                }
            }
            
            // ESC to close modal
            if (e.key === 'Escape') {
                closeAuditDetails();
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
