<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'performance_monitoring_functions.php';
require_once '../includes/admin_notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission to view performance logs
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'])) {
    header('Location: ../index.php');
    exit();
}

// Initialize performance monitor
$performanceMonitor = new PerformanceMonitor($pdo);

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Check export permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'performance_logs_export')) {
        header('Location: ../index.php');
        exit();
    }
    
    $filters = [
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'event_type' => $_GET['event_type'] ?? '',
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    $performanceMonitor->exportToPDF($filters);
    exit;
}

// Get filters from URL parameters
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'event_type' => $_GET['event_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'duration_min' => $_GET['duration_min'] ?? '',
    'duration_max' => $_GET['duration_max'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get data
$performanceData = $performanceMonitor->getPerformanceData($offset, $limit, $filters);
$totalRecords = $performanceMonitor->getTotalPerformanceRecords($filters);
$totalPages = ceil($totalRecords / $limit);

// Get statistics for dashboard cards
$stats = $performanceMonitor->getPerformanceStatistics();

// Get chart data
$uptimeChartData = $performanceMonitor->getUptimeChartData(7);
$pageLoadChartData = $performanceMonitor->getPageLoadChartData(7);


// Get recent activities for notifications
$recentActivities = $performanceMonitor->getRecentActivities(10);

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Format current status duration
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    } else {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $days . 'd ' . $hours . 'h';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'online':
        case 'uptime':
        case 'fast':
        case 'completed':
        case 'active':
            return '✅';
        case 'offline':
        case 'downtime':
        case 'slow':
            return '⚠️';
        case 'timeout':
        case 'error':
            return '❌';
        default:
            return '🔵';
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'online':
        case 'uptime':
        case 'fast':
        case 'completed':
        case 'active':
            return 'green';
        case 'offline':
        case 'downtime':
        case 'slow':
            return 'yellow';
        case 'timeout':
        case 'error':
            return 'red';
        default:
            return 'blue';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Performance & Error Logs - Japanese Learning Platform</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/performance-logs.css" rel="stylesheet">
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
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full text-gray-700 hover:bg-gray-100 focus:outline-none bg-primary-50 text-primary-700 font-medium" :class="open ? 'bg-primary-50 text-primary-700 font-medium' : ''">
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
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view', 'security_warnings_view', 'login_activity_view_metrics', 'login_activity_view_report', 'login_activity_export_pdf', 'broken_links_view_report', 'broken_links_export_pdf'])): ?>
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
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                                                         <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
   
                            Audit Trails
                            </a>
                            <?php endif; ?>

                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                            System Performance Logs
                            </a>
                            <?php endif; ?>

                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                        <h1 class="text-2xl font-semibold text-gray-900">
                            System Performance
                        </h1>
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
            <main class="p-4">
            <!-- Statistics Cards -->
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_view_metrics')): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Current Status Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-<?php echo getStatusColor($stats['current_status']['current_status'] ?? 'offline'); ?>-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-<?php echo ($stats['current_status']['current_status'] ?? 'offline') === 'online' ? 'check-circle' : 'times-circle'; ?> text-<?php echo getStatusColor($stats['current_status']['current_status'] ?? 'offline'); ?>-600"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">System Status</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo ucfirst($stats['current_status']['current_status'] ?? 'offline'); ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?php echo ($stats['current_status']['current_status'] ?? 'offline') === 'online' ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo formatDuration($stats['current_status']['current_duration'] ?? 0); ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uptime Percentage Card -->
                 
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-arrow-up text-green-600"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Uptime (30d)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo number_format($stats['uptime_stats']['uptime_percentage'] ?? 0, 2); ?>%
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                            <?php echo $stats['uptime_stats']['downtime_incidents'] ?? 0; ?> incidents
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Average Load Time Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-clock text-blue-600"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Avg Load Time (24h)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo number_format($stats['page_load_stats']['avg_load_time'] ?? 0, 2); ?>s
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?php echo ($stats['page_load_stats']['avg_load_time'] ?? 0) <= 3 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $stats['page_load_stats']['fast_percentage'] ?? 0; ?>% fast
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Requests Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-purple-600"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Requests (24h)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo number_format($stats['page_load_stats']['total_requests'] ?? 0); ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-gray-600">
                                            <?php echo $stats['page_load_stats']['failed_requests'] ?? 0; ?> failed
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_view_uptime_chart')): ?>
                <!-- Uptime/Downtime Timeline Chart -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
        <!-- Uptime Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" 
             stroke="currentColor" stroke-width="2" stroke-linecap="round" 
             stroke-linejoin="round" class="text-green-600">
            <path d="M7 17L17 7"/>
            <path d="M7 7h10v10"/>
        </svg>

        <!-- Downtime Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" 
             stroke="currentColor" stroke-width="2" stroke-linecap="round" 
             stroke-linejoin="round" class="text-red-600">
            <path d="M17 17L7 7"/>
            <path d="M17 7H7v10"/>
        </svg>

        <h3 class="text-lg font-medium text-gray-900">Uptime vs Downtime (7 days)</h3>
    </div>
    <div class="p-6">
        <canvas id="uptimeChart" width="400" height="200"></canvas>
    </div>
</div>
<?php endif; ?>


                <!-- Page Load Times Chart -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_view_load_times')): ?>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
    <!-- Speed Icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" 
         stroke-linejoin="round" class="text-yellow-500">
        <path d="M13 2L3 14h9l-1 8L21 10h-9l1-8z"/>
    </svg>

    <h3 class="text-lg font-medium text-gray-900">Average Page Load Times</h3>
</div>

                    <div class="p-6">
                        <canvas id="pageLoadChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
                <!-- Filter Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <h4 class="text-lg font-medium text-gray-900"> Performance Logs Report</h4>
                        <!-- Filter Action Buttons - Near the title -->
                        <div class="flex space-x-2">
                            <button type="submit" form="filterForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Filter Results
                            </button>
                            <a href="performance-logs.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Clear Filters
                            </a>
                        </div>
                    </div>
                    <!-- Export Buttons - Right corner -->
                    <div class="export-buttons">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_logs_export')): ?>
                        <button type="button" onclick="exportData('pdf')" class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                            Export PDF
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-6">
                    <form method="GET" id="filterForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Date Range -->
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                                <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                                <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            
                            <!-- Event Type -->
                            <div>
                                <label for="event_type" class="block text-sm font-medium text-gray-700">Event Type</label>
                                <select name="event_type" id="event_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Events</option>
                                    <option value="uptime" <?php echo $filters['event_type'] === 'uptime' ? 'selected' : ''; ?>>Uptime</option>
                                    <option value="downtime" <?php echo $filters['event_type'] === 'downtime' ? 'selected' : ''; ?>>Downtime</option>
                                    <option value="page_load" <?php echo $filters['event_type'] === 'page_load' ? 'selected' : ''; ?>>Page Load</option>
                                </select>
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Status</option>
                                    <option value="fast" <?php echo $filters['status'] === 'fast' ? 'selected' : ''; ?>>Fast (≤3s)</option>
                                    <option value="slow" <?php echo $filters['status'] === 'slow' ? 'selected' : ''; ?>>Slow (>3s)</option>
                                    <option value="timeout" <?php echo $filters['status'] === 'timeout' ? 'selected' : ''; ?>>Timeout</option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Search -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                <input type="text" name="search" id="search" placeholder="Search by page name, action, or error..." value="<?php echo htmlspecialchars($filters['search']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            
                            <!-- Duration Range -->
                            <div>
                                <label for="duration_min" class="block text-sm font-medium text-gray-700">Min Duration (s)</label>
                                <input type="number" name="duration_min" id="duration_min" step="0.1" placeholder="0.0" value="<?php echo htmlspecialchars($filters['duration_min']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="duration_max" class="block text-sm font-medium text-gray-700">Max Duration (s)</label>
                                <input type="number" name="duration_max" id="duration_max" step="0.1" placeholder="10.0" value="<?php echo htmlspecialchars($filters['duration_max']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        

                    </form>
                </div>
            </div>

            <!-- Performance Logs Table -->
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_logs_view')): ?>
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
  <div class="flex items-center gap-2">
    <!-- Lucide Activity Icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         class="lucide lucide-activity text-indigo-600">
      <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
    </svg>
    <h3 class="text-lg font-medium text-gray-900">Performance Logs</h3>
  </div>
</div>


                
                <!-- Pagination Controls - Top of Table -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-b border-gray-200 sm:px-6">
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
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
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
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($performanceData)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
                                        <p class="text-lg">No performance data found</p>
                                        <p class="text-sm">Try adjusting your filters or check back later</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($performanceData as $index => $record): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="text-2xl mr-3">
                                                    <?php echo getStatusIcon($record['event_type']); ?>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['title']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($record['description'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('Y-m-d H:i:s', strtotime($record['start_time'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $record['end_time'] ? date('Y-m-d H:i:s', strtotime($record['end_time'])) : 'Ongoing'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($record['duration']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php 
                                                    $duration = floatval($record['duration']);
                                                    if ($record['event_type'] === 'page_load') {
                                                        echo $duration <= 3 ? 'bg-green-100 text-green-800' : ($duration <= 10 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                    } else {
                                                        echo 'bg-blue-100 text-blue-800';
                                                    }
                                                    ?>">
                                                    <?php 
                                                    if ($record['event_type'] === 'page_load') {
                                                        echo number_format($duration, 2) . 's';
                                                    } else {
                                                        echo formatDuration($duration);
                                                    }
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                $color = getStatusColor($record['status']);
                                                echo "bg-{$color}-100 text-{$color}-800";
                                                ?>">
                                                <?php echo getStatusIcon($record['status']); ?> <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="space-y-1">
                                                <?php if ($record['user_id']): ?>
                                                    <div><strong>User:</strong> <?php echo $record['user_id']; ?></div>
                                                <?php endif; ?>
                                                <?php if ($record['ip_address']): ?>
                                                    <div><strong>IP:</strong> <?php echo htmlspecialchars($record['ip_address']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($record['device_type']): ?>
                                                    <div><strong>Device:</strong> <?php echo htmlspecialchars($record['device_type']); ?> / <?php echo htmlspecialchars($record['browser'] ?? ''); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
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
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of <span class="font-medium"><?php echo $totalRecords; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Next</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php endif; ?>


    <script>
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

        // Export function
        function exportData(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.location.href = '?' + params.toString();
        }

        // Chart.js configurations
        const uptimeChartData = <?php echo json_encode($uptimeChartData); ?>;
        const pageLoadChartData = <?php echo json_encode($pageLoadChartData); ?>;

        // Uptime/Downtime Chart
        const uptimeCtx = document.getElementById('uptimeChart').getContext('2d');
        new Chart(uptimeCtx, {
            type: 'bar',
            data: {
                labels: uptimeChartData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Uptime (hours)',
                    data: uptimeChartData.map(item => item.uptime_hours),
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 1
                }, {
                    label: 'Downtime (hours)',
                    data: uptimeChartData.map(item => item.downtime_hours),
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: false
                    }
                }
            }
        });

        // Page Load Times Chart
        const pageLoadCtx = document.getElementById('pageLoadChart').getContext('2d');
        new Chart(pageLoadCtx, {
            type: 'bar',
            data: {
                labels: pageLoadChartData.map(item => item.page_name),
                datasets: [{
                    label: 'Average Load Time (seconds)',
                    data: pageLoadChartData.map(item => parseFloat(item.avg_load_time)),
                    backgroundColor: pageLoadChartData.map(item => {
                        const time = parseFloat(item.avg_load_time);
                        if (time <= 3) return 'rgba(34, 197, 94, 0.8)';
                        if (time <= 10) return 'rgba(251, 191, 36, 0.8)';
                        return 'rgba(239, 68, 68, 0.8)';
                    }),
                    borderColor: pageLoadChartData.map(item => {
                        const time = parseFloat(item.avg_load_time);
                        if (time <= 3) return 'rgba(34, 197, 94, 1)';
                        if (time <= 10) return 'rgba(251, 191, 36, 1)';
                        return 'rgba(239, 68, 68, 1)';
                    }),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Seconds'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false
                    }
                }
            }
        });

        // Auto-refresh functionality
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                if (!document.hidden) {
                    refreshStats();
                }
            }, 30000); // Refresh every 30 seconds
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        function refreshStats() {
            fetch('get_performance_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatisticsCards(data.stats);
                        updateNotificationCount(data.recent_activities?.length || 0);
                    }
                })
                .catch(error => console.error('Error refreshing stats:', error));
        }
        
        function updateStatisticsCards(stats) {
            // Update current status
            const statusElement = document.querySelector('[data-stat="current_status"]');
            if (statusElement && stats.current_status) {
                statusElement.textContent = stats.current_status.current_status.charAt(0).toUpperCase() + stats.current_status.current_status.slice(1);
            }
            
            // Update uptime percentage
            const uptimeElement = document.querySelector('[data-stat="uptime_percentage"]');
            if (uptimeElement && stats.uptime_stats) {
                uptimeElement.textContent = parseFloat(stats.uptime_stats.uptime_percentage).toFixed(2) + '%';
            }
            
            // Update average load time
            const loadTimeElement = document.querySelector('[data-stat="avg_load_time"]');
            if (loadTimeElement && stats.page_load_stats) {
                loadTimeElement.textContent = parseFloat(stats.page_load_stats.avg_load_time).toFixed(2) + 's';
            }
            
            // Update total requests
            const requestsElement = document.querySelector('[data-stat="total_requests"]');
            if (requestsElement && stats.page_load_stats) {
                requestsElement.textContent = parseInt(stats.page_load_stats.total_requests).toLocaleString();
            }
        }
        
        function updateNotificationCount(count) {
            const countElement = document.getElementById('notificationCount');
            if (countElement) {
                countElement.textContent = count;
                countElement.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        // Page visibility handling
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
        
        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportData('pdf');
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
