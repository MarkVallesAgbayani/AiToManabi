<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'error_logs_functions.php';
require_once '../includes/admin_notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission to view error logs or is admin
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Initialize error logs monitor
$errorLogsMonitor = new ErrorLogsMonitor($pdo);

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Check export permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'error_logs_export_pdf')) {
        header('Location: ../index.php');
        exit();
    }
    
    $filters = [
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'category' => $_GET['category'] ?? '',
        'severity' => $_GET['severity'] ?? '',
        'status' => $_GET['status'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    $errorLogsMonitor->exportToPDF($filters);
    exit;
}

// Get filters from URL parameters
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'category' => $_GET['category'] ?? '',
    'severity' => $_GET['severity'] ?? '',
    'status' => $_GET['status'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get data
$errorData = $errorLogsMonitor->getErrorData($offset, $limit, $filters);
$totalRecords = $errorLogsMonitor->getTotalErrorRecords($filters);
$totalPages = ceil($totalRecords / $limit);

// Get statistics for dashboard cards
$stats = $errorLogsMonitor->getErrorStatistics();

// Get chart data
$trendChartData = $errorLogsMonitor->getErrorTrendData(7);
$categoryChartData = $errorLogsMonitor->getErrorCategoryData(7);

// Get recent activities for notifications
$recentActivities = $errorLogsMonitor->getRecentActivities(10);

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Helper functions
function getSeverityIcon($severity) {
    switch ($severity) {
        case 'critical':
            return '🔴';
        case 'warning':
            return '🟡';
        case 'info':
            return '🔵';
        default:
            return '⚪';
    }
}

function getSeverityColor($severity) {
    switch ($severity) {
        case 'critical':
            return 'red';
        case 'warning':
            return 'yellow';
        case 'info':
            return 'blue';
        default:
            return 'gray';
    }
}

function getCategoryIcon($category) {
    switch ($category) {
        case 'ai_failure':
            return '🤖';
        case 'response_time':
            return '⏱️';
        case 'backend_error':
            return '⚙️';
        default:
            return '❗';
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error Logs - Japanese Learning Platform</title>
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
    <link href="css/error-logs.css" rel="stylesheet">
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
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'])): ?>
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
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                            System Performance Logs
                            </a>
                            <?php endif; ?>

                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
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
                            System Error Logs
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
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_view_metrics')): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total AI Failures Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain-circuit-icon lucide-brain-circuit"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M9 13a4.5 4.5 0 0 0 3-4"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M12 13h4"/><path d="M12 18h6a2 2 0 0 1 2 2v1"/><path d="M12 8h8"/><path d="M16 8V5a2 2 0 0 1 2-2"/><circle cx="16" cy="13" r=".5"/><circle cx="18" cy="3" r=".5"/><circle cx="20" cy="21" r=".5"/><circle cx="20" cy="8" r=".5"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">AI Failures (24h)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo $stats['ai_failures_24h'] ?? 0; ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-red-600">
                                            <?php echo $stats['ai_failures_7d'] ?? 0; ?> this week
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Average Response Time Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-timer-reset-icon lucide-timer-reset"><path d="M10 2h4"/><path d="M12 14v-4"/><path d="M4 13a8 8 0 0 1 8-7 8 8 0 1 1-5.3 14L4 17.6"/><path d="M9 17H4v5"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Avg Response Time</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo number_format(($stats['avg_response_time_24h'] ?? 0) / 1000, 2); ?>s
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold <?php echo ($stats['avg_response_time_24h'] ?? 0) <= 3000 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo ($stats['avg_response_time_24h'] ?? 0) <= 3000 ? 'Good' : 'Slow'; ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Errors Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-siren-icon lucide-siren"><path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Critical Errors (24h)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo $stats['critical_errors_24h'] ?? 0; ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-red-600">
                                            Requires attention
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Errors Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-candlestick-icon lucide-chart-candlestick"><path d="M9 5v4"/><rect width="4" height="6" x="7" y="9" rx="1"/><path d="M9 15v2"/><path d="M17 3v2"/><rect width="4" height="8" x="15" y="5" rx="1"/><path d="M17 13v3"/><path d="M3 3v16a2 2 0 0 0 2 2h16"/></svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1 w-0">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Errors (24h)</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            <?php echo $stats['errors_24h'] ?? 0; ?>
                                        </div>
                                        <div class="ml-2 flex items-baseline text-sm font-semibold text-gray-600">
                                            <?php echo $stats['errors_30d'] ?? 0; ?> this month
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
                <!-- Error Trends Chart -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_view_trends')): ?>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       class="lucide lucide-trending-up text-red-600">
    <path d="M16 7h6v6"/>
    <path d="m22 7-8.5 8.5-5-5L2 17"/>
  </svg>
  <h3 class="text-lg font-medium text-gray-900">Error Trends (7 days)</h3>
</div>

                    <div class="p-6">
                        <canvas id="trendChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Error Categories Chart -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_view_categories')): ?>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       class="lucide lucide-bar-chart-3 text-indigo-600">
    <path d="M3 3v18h18"/>
    <path d="M7 13h3v5H7z"/>
    <path d="M12 9h3v9h-3z"/>
    <path d="M17 5h3v13h-3z"/>
  </svg>
  <h3 class="text-lg font-medium text-gray-900">Error Categories</h3>
</div>

                    <div class="p-6">
                        <canvas id="categoryChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters and Search -->
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_search_filter')): ?>
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
                <!-- Filter Header -->
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <h4 class="text-lg font-medium text-gray-900"> Filter Error Logs</h4>
                        <!-- Filter Action Buttons - Near the title -->
                        <div class="flex space-x-2">
                            <button type="submit" form="filterForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Filter Results
                            </button>
                            <a href="error-logs.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Clear Filters
                            </a>
                            <button type="button" onclick="refreshData()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                    <!-- Export Buttons - Right corner -->
                    <div class="export-buttons">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_export_pdf')): ?>
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
                            
                            <!-- Category -->
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                                <select name="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Categories</option>
                                    <option value="ai_failure" <?php echo $filters['category'] === 'ai_failure' ? 'selected' : ''; ?>>🤖 AI Failures</option>
                                    <option value="response_time" <?php echo $filters['category'] === 'response_time' ? 'selected' : ''; ?>>⏱️ Response Time</option>
                                    <option value="backend_error" <?php echo $filters['category'] === 'backend_error' ? 'selected' : ''; ?>>⚙️ Backend Errors</option>
                                </select>
                            </div>
                            
                            <!-- Severity -->
                            <div>
                                <label for="severity" class="block text-sm font-medium text-gray-700">Severity</label>
                                <select name="severity" id="severity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Severities</option>
                                    <option value="critical" <?php echo $filters['severity'] === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                                    <option value="warning" <?php echo $filters['severity'] === 'warning' ? 'selected' : ''; ?>>🟡 Warning</option>
                                    <option value="info" <?php echo $filters['severity'] === 'info' ? 'selected' : ''; ?>>🔵 Info</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Search -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                                <input type="text" name="search" id="search" placeholder="Search by error message, user query, or module..." value="<?php echo htmlspecialchars($filters['search']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Status</option>
                                    <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="retried" <?php echo $filters['status'] === 'retried' ? 'selected' : ''; ?>>Retried</option>
                                    <option value="resolved" <?php echo $filters['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="logged" <?php echo $filters['status'] === 'logged' ? 'selected' : ''; ?>>Logged</option>
                                </select>
                            </div>
                            
                            <!-- User ID -->
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-gray-700">User ID</label>
                                <input type="number" name="user_id" id="user_id" placeholder="Filter by User ID" value="<?php echo htmlspecialchars($filters['user_id']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        

                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Error Logs Table -->
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'error_logs_view')): ?>
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
  <div class="flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         class="lucide lucide-file-terminal text-rose-600">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
      <path d="M8 8l2 2-2 2"/>
      <path d="M12 16h4"/>
    </svg>
    <h3 class="text-lg font-medium text-gray-900">System Error Logs</h3>
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
                    <table class="min-w-full divide-y divide-gray-200 error-logs-table">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($errorData)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-lg">No error logs found</p>
                                            <p class="text-sm">Try adjusting your filters or check back later</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($errorData as $index => $record): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo getSeverityColor($record['severity']); ?>-100 text-<?php echo getSeverityColor($record['severity']); ?>-800">
                                                <?php echo getSeverityIcon($record['severity']); ?> <?php echo ucfirst($record['severity']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="text-lg mr-2"><?php echo getCategoryIcon($record['category']); ?></span>
                                                <span class="text-sm font-medium text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $record['category'])); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="max-w-xs">
                                                <div class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($record['error_type']); ?></div>
                                                <div class="text-sm text-gray-500 truncate" title="<?php echo htmlspecialchars($record['error_message']); ?>">
                                                    <?php echo htmlspecialchars(substr($record['error_message'], 0, 60)) . (strlen($record['error_message']) > 60 ? '...' : ''); ?>
                                                </div>
                                                <?php if ($record['user_query']): ?>
                                                    <div class="text-xs text-blue-600 truncate" title="<?php echo htmlspecialchars($record['user_query']); ?>">
                                                        Query: <?php echo htmlspecialchars(substr($record['user_query'], 0, 40)) . (strlen($record['user_query']) > 40 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('Y-m-d H:i:s', strtotime($record['occurred_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($record['user_id']): ?>
                                                <div>ID: <?php echo $record['user_id']; ?></div>
                                                <div class="text-xs"><?php echo htmlspecialchars($record['ip_address']); ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                switch($record['status']) {
                                                    case 'failed': echo 'bg-red-100 text-red-800'; break;
                                                    case 'retried': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="showErrorDetails(<?php echo $record['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                                View Details
                                            </button>
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
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Access Restricted</h3>
                <p class="text-gray-600">You don't have permission to view error logs. Please contact your administrator for access.</p>
            </div>
            <?php endif; ?>
        </main>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div id="errorDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Error Details
                        </h3>
                        <button onclick="closeErrorDetails()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="errorDetailsContent" class="space-y-4">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Chart.js configurations
        const trendChartData = <?php echo json_encode($trendChartData); ?>;
        const categoryChartData = <?php echo json_encode($categoryChartData); ?>;

        // Error Trends Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendChartData.map(item => {
                    const date = new Date(item.error_date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Total Errors',
                    data: trendChartData.map(item => item.total_errors),
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Critical Errors',
                    data: trendChartData.map(item => item.critical_errors),
                    borderColor: 'rgba(220, 38, 38, 1)',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Errors'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        // Error Categories Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['AI Failures', 'Response Time Issues', 'Backend Errors'],
                datasets: [{
                    data: [
                        categoryChartData.ai_failures || 0,
                        categoryChartData.response_issues || 0,
                        categoryChartData.backend_errors || 0
                    ],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(107, 114, 128, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Error Details Modal
        function showErrorDetails(errorId) {
            const modal = document.getElementById('errorDetailsModal');
            const content = document.getElementById('errorDetailsContent');
            
            content.innerHTML = '<div class="text-center py-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div><p class="mt-2 text-gray-600">Loading...</p></div>';
            modal.classList.remove('hidden');
            
            fetch(`get_error_details.php?id=${errorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayErrorDetails(data.error);
                    } else {
                        content.innerHTML = '<div class="text-red-600">Error loading details: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="text-red-600">Error loading details: ' + error.message + '</div>';
                });
        }

        function displayErrorDetails(error) {
            const content = document.getElementById('errorDetailsContent');
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">Basic Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                            <div><strong>Category:</strong> ${getCategoryIcon(error.category)} ${error.category.replace('_', ' ')}</div>
                            <div><strong>Severity:</strong> ${getSeverityIcon(error.severity)} ${error.severity}</div>
                            <div><strong>Error Type:</strong> ${error.error_type}</div>
                            <div><strong>Status:</strong> ${error.status}</div>
                            <div><strong>Occurred At:</strong> ${new Date(error.occurred_at).toLocaleString()}</div>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">User Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                            <div><strong>User ID:</strong> ${error.user_id || 'N/A'}</div>
                            <div><strong>IP Address:</strong> ${error.ip_address || 'N/A'}</div>
                            <div><strong>Device:</strong> ${error.device_type || 'N/A'}</div>
                            <div><strong>Browser:</strong> ${error.browser_name || 'N/A'}</div>
                            <div><strong>OS:</strong> ${error.operating_system || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Error Message</h4>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <pre class="text-sm text-red-800 whitespace-pre-wrap">${error.error_message}</pre>
                    </div>
                </div>
                ${error.user_query ? `
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">User Query</h4>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-800">${error.user_query}</p>
                    </div>
                </div>
                ` : ''}
                ${error.stack_trace ? `
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Stack Trace</h4>
                    <div class="bg-gray-50 p-4 rounded-lg max-h-40 overflow-y-auto">
                        <pre class="text-xs text-gray-700 whitespace-pre-wrap">${error.stack_trace}</pre>
                    </div>
                </div>
                ` : ''}
                ${error.response_time_ms ? `
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Performance Details</h4>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div><strong>Response Time:</strong> ${error.response_time_ms}ms</div>
                        <div><strong>Expected Time:</strong> ${error.expected_response_time_ms || 'N/A'}ms</div>
                        <div><strong>Endpoint:</strong> ${error.endpoint || 'N/A'}</div>
                    </div>
                </div>
                ` : ''}
            `;
        }

        function closeErrorDetails() {
            document.getElementById('errorDetailsModal').classList.add('hidden');
        }

        function getCategoryIcon(category) {
            switch(category) {
                case 'ai_failure': return '🤖';
                case 'response_time': return '⏱️';
                case 'backend_error': return '⚙️';
                default: return '❗';
            }
        }

        function getSeverityIcon(severity) {
            switch(severity) {
                case 'critical': return '🔴';
                case 'warning': return '🟡';
                case 'info': return '🔵';
                default: return '⚪';
            }
        }

        // Auto-refresh functionality
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                if (!document.hidden) {
                    refreshStats();
                }
            }, 60000); // Refresh every 60 seconds
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        function refreshStats() {
            fetch('get_error_stats.php')
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
            // Update statistics cards if elements exist
            const cards = {
                'ai_failures_24h': stats.ai_failures_24h,
                'avg_response_time_24h': Math.round((stats.avg_response_time_24h || 0) / 1000 * 100) / 100,
                'critical_errors_24h': stats.critical_errors_24h,
                'errors_24h': stats.errors_24h
            };
            
            Object.keys(cards).forEach(key => {
                const element = document.querySelector(`[data-stat="${key}"]`);
                if (element) {
                    element.textContent = cards[key];
                }
            });
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
            if (e.key === 'Escape') {
                closeErrorDetails();
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
